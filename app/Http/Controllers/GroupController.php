<?php

namespace App\Http\Controllers;

use App\Events\GroupMembershipChanged;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\Subscription;
use App\Services\InterdotzService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupController extends Controller
{
    private const TOKENS_PER_CREDIT = 2_500;
    private const DEFAULT_AI_PROVIDER = 'openai';
    private const DEFAULT_AI_MODEL = 'gpt-5';
    private const BYPASS_EMAILS = [
        'superadmin@interdotz.com',
    ];

    private function isBypassPayment(): bool
    {
        return in_array(Auth::user()?->email, self::BYPASS_EMAILS, true);
    }

    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        $groups = Group::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'active'));
            })
            ->with(['members', 'groupToken'])
            ->withCount('members')
            ->latest()
            ->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View|RedirectResponse
    {
        $duPrice = (int) config('normchat.du_group_creation', 175);
        $idrPrice = (int) config('normchat.idr_group_creation', 35000);
        $idrTestPrice = (int) config('normchat.idr_group_creation_test', 1);

        return view('groups.create', [
            'duPrice' => $duPrice,
            'idrPrice' => $idrPrice,
            'idrTestPrice' => $idrTestPrice,
            'includedCredits' => $this->groupCreationCredits(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
            'payment_method' => ['nullable', 'in:du,midtrans,midtrans_test'],
        ]);

        $user = Auth::user();
        $duPrice = (int) config('normchat.du_group_creation', 175);
        $paymentMethod = (string) ($validated['payment_method'] ?? 'du');

        // Superadmin bypass — free group creation
        if ($this->isBypassPayment()) {
            $group = Group::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'owner_id' => $user->id,
                'password_hash' => Hash::make($validated['password']),
                'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
                'ai_provider' => self::DEFAULT_AI_PROVIDER,
                'ai_model' => self::DEFAULT_AI_MODEL,
                'status' => 'active',
            ]);
            $this->activateGroup($group, $user);

            return redirect()->route('chat.show', $group)
                ->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
        }

        // Create group in pending status
        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $user->id,
            'password_hash' => Hash::make($validated['password']),
            'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
            'ai_provider' => self::DEFAULT_AI_PROVIDER,
            'ai_model' => self::DEFAULT_AI_MODEL,
            'status' => 'pending_payment',
        ]);

        if ($paymentMethod === 'midtrans') {
            return $this->initiateGroupMidtransPayment($request, $group, $user);
        }

        if ($paymentMethod === 'midtrans_test') {
            return $this->initiateGroupMidtransPayment($request, $group, $user, true);
        }

        // Charge via Interdotz DU
        $interdotz = app(InterdotzService::class);

        if (! $interdotz->isConfigured()) {
            $group->forceDelete();

            return back()->withErrors(['payment' => 'Sistem pembayaran belum dikonfigurasi.'])->withInput();
        }

        $ssoToken = $user->getAccessToken();
        if (! $ssoToken) {
            $group->forceDelete();

            return back()->withErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.'])->withInput();
        }

        $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');

        $referenceId = "group_create_{$group->id}_" . time();
        $callbackUrl = url('/api/webhooks/interdotz/charge');

        // Try direct charge first (immediate deduction)
        $chargeResult = $interdotz->charge($ssoToken, $duPrice, 'normchat_group_creation', $referenceId, $interdotzUserId);

        if ($chargeResult && isset($chargeResult['payload'])) {
            $this->activateGroup($group, $user, $duPrice, $chargeResult['payload']['transaction_id'] ?? $referenceId);

            return redirect()->route('chat.show', $group)
                ->with('success', 'Group "' . $group->name . '" berhasil dibuat! ' . $duPrice . ' DU telah dipotong.');
        }

        // Fallback to chargeRequest (redirect flow) if direct charge fails
        $result = $interdotz->chargeRequest(
            $ssoToken,
            $duPrice,
            'normchat_group_creation',
            $referenceId,
            "Pembuatan grup Normchat: {$group->name} ({$duPrice} DU)",
            $callbackUrl,
            $interdotzUserId
        );

        $redirectUrl = $result['payload']['redirect_url'] ?? $result['payload']['redirectUrl'] ?? null;
        if ($result && ! empty($redirectUrl)) {
            session(['pending_group_ref' => $referenceId, 'pending_group_id' => $group->id]);

            return redirect()->away($redirectUrl);
        }

        $group->forceDelete();

        $serviceError = $interdotz->getLastError();
        $errorMessage = 'Gagal memproses pembayaran.';
        if ($serviceError) {
            $errorMessage .= ' ' . $serviceError;
        } else {
            $errorMessage .= ' Pastikan saldo Dots Units mencukupi (' . $duPrice . ' DU).';
        }

        return back()->withErrors(['payment' => $errorMessage])->withInput();
    }

    /**
     * Callback page after user returns from Interdotz payment confirmation.
     */
    public function paymentCallback(Request $request): RedirectResponse
    {
        $orderId = (string) $request->query('order', '');
        if ($orderId !== '') {
            $pendingPayment = PendingPayment::query()
                ->where('order_id', $orderId)
                ->where('user_id', (int) Auth::id())
                ->first();

            if (! $pendingPayment) {
                return redirect()->route('groups.index')
                    ->with('info', 'Referensi pembayaran tidak ditemukan.');
            }

            if ($pendingPayment->status === 'paid') {
                $meta = (array) ($pendingPayment->metadata_json ?? []);
                $action = (string) ($meta['action'] ?? '');
                $group = $pendingPayment->group;

                if (($action === 'group_create_midtrans' || $action === 'group_join_midtrans') && $group) {
                    return redirect()->route('chat.show', $group)
                        ->with('success', 'Pembayaran berhasil dikonfirmasi.');
                }

                if ($action === 'topup_midtrans') {
                    session(['token_purchase' => [
                        'group_name' => (string) ($meta['group_name'] ?? ($group?->name ?? 'Grup')),
                        'normkredits' => (int) ($meta['normkredits'] ?? 0),
                        'tokens' => (int) ($meta['token_amount'] ?? 0),
                        'paid_amount' => (int) $pendingPayment->expected_amount,
                        'payment_method' => 'midtrans',
                        'payment_unit' => 'IDR',
                    ]]);

                    return redirect()->route('subscription.tokens.buy.success')
                        ->with('success', 'Top-up berhasil dikonfirmasi.');
                }

                return redirect()->route('groups.index')->with('success', 'Pembayaran berhasil dikonfirmasi.');
            }

            if (in_array($pendingPayment->status, ['failed', 'expired'], true)) {
                return redirect()->route('groups.index')
                    ->with('info', 'Pembayaran gagal atau kedaluwarsa. Silakan coba kembali.');
            }

            // ── Payment still pending: sync status from Midtrans via Interdotz ──
            $meta = (array) ($pendingPayment->metadata_json ?? []);
            $paymentId = (string) ($meta['payment_id'] ?? '');

            if ($paymentId !== '') {
                $interdotz = app(InterdotzService::class);
                $user = Auth::user();
                $ssoToken = $user?->getAccessToken();

                if ($ssoToken) {
                    $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');
                    $statusResponse = $interdotz->getPaymentStatus($ssoToken, $paymentId, $interdotzUserId);

                    if ($statusResponse) {
                        $statusPayload = $statusResponse['payload'] ?? $statusResponse;
                        $gatewayStatus = strtolower((string) ($statusPayload['status'] ?? ''));

                        Log::info('Payment callback: synced Midtrans status.', [
                            'order_id' => $orderId,
                            'gateway_status' => $gatewayStatus,
                        ]);

                        $isPaid = in_array($gatewayStatus, [
                            'settlement', 'capture', 'paid', 'success', 'confirmed',
                        ], true);

                        $isFailed = in_array($gatewayStatus, [
                            'expire', 'expired', 'cancel', 'cancelled', 'deny', 'denied', 'failure', 'failed',
                        ], true);

                        if ($isPaid) {
                            $paidAmount = (int) ($statusPayload['amount'] ?? $pendingPayment->expected_amount);
                            $paymentRef = (string) ($statusPayload['gateway_transaction_id'] ?? $paymentId);
                            $paymentMethod = (string) ($statusPayload['payment_method'] ?? 'midtrans');
                            $paidAt = $statusPayload['paid_at'] ?? now();

                            // Fulfill the pending payment directly
                            app(\App\Http\Controllers\Api\WebhookController::class)
                                ->fulfillPendingPaymentFromCallback(
                                    $pendingPayment, $paidAmount, $paymentRef, $paymentMethod, $paidAt
                                );

                            // Re-read to confirm it was fulfilled
                            $pendingPayment->refresh();

                            if ($pendingPayment->status === 'paid') {
                                $action = (string) ($meta['action'] ?? '');
                                $group = $pendingPayment->group;

                                if (($action === 'group_create_midtrans' || $action === 'group_join_midtrans') && $group) {
                                    return redirect()->route('chat.show', $group)
                                        ->with('success', 'Pembayaran berhasil! Selamat menggunakan grup.');
                                }

                                if ($action === 'topup_midtrans') {
                                    session(['token_purchase' => [
                                        'group_name' => (string) ($meta['group_name'] ?? ($group?->name ?? 'Grup')),
                                        'normkredits' => (int) ($meta['normkredits'] ?? 0),
                                        'tokens' => (int) ($meta['token_amount'] ?? 0),
                                        'paid_amount' => $paidAmount,
                                        'payment_method' => 'midtrans',
                                        'payment_unit' => 'IDR',
                                    ]]);

                                    return redirect()->route('subscription.tokens.buy.success')
                                        ->with('success', 'Top-up berhasil dikonfirmasi.');
                                }

                                return redirect()->route('groups.index')
                                    ->with('success', 'Pembayaran berhasil dikonfirmasi.');
                            }
                        }

                        if ($isFailed) {
                            $pendingPayment->update(['status' => 'failed']);

                            // Delete pending group if it was a creation
                            $action = (string) ($meta['action'] ?? '');
                            if ($action === 'group_create_midtrans') {
                                $group = $pendingPayment->group;
                                if ($group && ($group->status ?? '') === 'pending_payment') {
                                    $group->forceDelete();
                                }
                            }

                            return redirect()->route('groups.index')
                                ->with('info', 'Pembayaran gagal atau kedaluwarsa. Silakan coba kembali.');
                        }
                    }
                }
            }

            return redirect()->route('groups.index')
                ->with('info', 'Pembayaran sedang diproses. Mohon tunggu beberapa saat.');
        }

        $groupId = session('pending_group_id');
        if (! $groupId) {
            return redirect()->route('groups.index')->with('info', 'Menunggu konfirmasi pembayaran...');
        }

        $group = Group::find($groupId);
        if (! $group) {
            return redirect()->route('groups.index');
        }

        if (($group->status ?? '') !== 'pending_payment') {
            session()->forget(['pending_group_ref', 'pending_group_id']);

            return redirect()->route('chat.show', $group)
                ->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
        }

        return redirect()->route('groups.index')
            ->with('info', 'Pembayaran sedang diproses. Group akan aktif setelah pembayaran dikonfirmasi.');
    }

    // ── Join via share ID: show patungan form ───────────────

    public function showJoin(string $shareId): View|RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->with('groupToken')->firstOrFail();

        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing || (int) $group->owner_id === (int) Auth::id()) {
            return view('groups.join', [
                'group' => $group,
                'alreadyMember' => true,
            ]);
        }

        $duPatungan = (int) config('normchat.du_patungan', 25);
        $idrPatungan = (int) config('normchat.idr_patungan_min', 5000);

        return view('groups.join', [
            'group' => $group,
            'alreadyMember' => false,
            'duPatungan' => $duPatungan,
            'idrPatungan' => $idrPatungan,
        ]);
    }

    // ── Join via share ID: charge DU patungan then join ───────

    public function joinViaShareId(Request $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();

        if ((int) $group->owner_id === (int) Auth::id()) {
            return redirect()->route('chat.show', $group);
        }

        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return redirect()->route('chat.show', $group)
                ->with('info', 'Anda sudah menjadi member group ini.');
        }

        $request->validate([
            'password' => 'required|string',
            'payment_method' => 'nullable|in:du,midtrans',
        ]);

        $paymentMethod = (string) $request->input('payment_method', 'du');

        if (! Hash::check($request->input('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.'])->withInput();
        }

        $user = Auth::user();
        $duPatungan = (int) config('normchat.du_patungan', 25);

        if ($paymentMethod === 'midtrans') {
            return $this->initiateJoinMidtransPayment($request, $group, $user, $shareId);
        }

        // Superadmin bypass
        if ($this->isBypassPayment()) {
            $this->activateJoin($group, $user);

            return redirect()->route('chat.show', $group)
                ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"!');
        }

        $interdotz = app(InterdotzService::class);

        if (! $interdotz->isConfigured()) {
            return back()->withErrors(['payment' => 'Sistem pembayaran belum dikonfigurasi.']);
        }

        $ssoToken = $user->getAccessToken();
        if (! $ssoToken) {
            return back()->withErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.']);
        }

        $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');

        $referenceId = "patungan_{$group->id}_{$user->id}_" . time();
        $callbackUrl = url('/api/webhooks/interdotz/charge');

        // Try direct charge first (immediate deduction)
        $chargeResult = $interdotz->charge($ssoToken, $duPatungan, 'normchat_patungan', $referenceId, $interdotzUserId);

        if ($chargeResult && isset($chargeResult['payload'])) {
            $this->activateJoin($group, $user, $duPatungan, $chargeResult['payload']['transaction_id'] ?? $referenceId);

            return redirect()->route('chat.show', $group)
                ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"! ' . $duPatungan . ' DU telah dipotong.');
        }

        // Fallback to chargeRequest (redirect flow)
        $result = $interdotz->chargeRequest(
            $ssoToken,
            $duPatungan,
            'normchat_patungan',
            $referenceId,
            "Patungan bergabung ke grup: {$group->name} ({$duPatungan} DU)",
            $callbackUrl,
            $interdotzUserId
        );

        $redirectUrl = $result['payload']['redirect_url'] ?? $result['payload']['redirectUrl'] ?? null;
        if ($result && ! empty($redirectUrl)) {
            session([
                'pending_join_ref' => $referenceId,
                'pending_join_group' => $group->id,
                'pending_join_password_verified' => true,
            ]);

            return redirect()->away($redirectUrl);
        }

        $serviceError = $interdotz->getLastError();
        $errorMessage = 'Gagal memproses pembayaran patungan.';
        if ($serviceError) {
            $errorMessage .= ' ' . $serviceError;
        } else {
            $errorMessage .= ' Pastikan saldo Dots Units mencukupi (' . $duPatungan . ' DU).';
        }

        return back()->withErrors(['payment' => $errorMessage]);
    }

    public function promoteMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('promoteMember', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat diubah rolenya dari menu ini.']);
        }

        if ((int) $member->user_id === (int) Auth::id()) {
            return back()->withErrors(['member' => 'Kamu tidak dapat mengubah role akun sendiri dari menu ini.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $targetRole = $validated['role'] === 'admin'
            ? Role::find($this->ensureRoleId('admin', 'Admin', 'Group administrator'))
            : Role::find($this->ensureRoleId('member', 'Member', 'Group member'));
        if (! $targetRole) {
            return back()->withErrors(['member' => 'Role tujuan tidak ditemukan.']);
        }

        $member->role_id = $targetRole->id;
        $member->save();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_role_changed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'metadata_json' => ['role' => $validated['role']],
            'created_at' => now(),
        ]);

        event(new GroupMembershipChanged(
            (int) $group->id,
            (int) $member->user_id,
            'role_changed',
            (string) ($targetRole->key ?? $validated['role'])
        ));

        return back()->with('success', 'Role member berhasil diperbarui.');
    }

    public function removeMember(Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat dihapus dari group.']);
        }

        if ((int) $member->user_id === (int) Auth::id()) {
            return back()->withErrors(['member' => 'Kamu tidak dapat menghapus akun sendiri dari group.']);
        }

        $removedUserId = (int) $member->user_id;

        $member->delete();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_removed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'created_at' => now(),
        ]);

        event(new GroupMembershipChanged(
            (int) $group->id,
            $removedUserId,
            'removed',
            null
        ));

        return back()->with('success', 'Member berhasil dihapus dari group.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $userId = (int) Auth::id();
        abort_unless($userId > 0 && (int) $group->owner_id === $userId, 403);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $userId,
            'action' => 'group.deleted',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'name' => (string) $group->name,
            ],
            'created_at' => now(),
        ]);

        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grup berhasil dihapus.');
    }

    // ── Private helpers ─────────────────────────────────────

    public function activateGroup(
        Group $group,
        $user,
        int $paidAmount = 0,
        ?string $transactionId = null,
        string $paymentMethod = 'du'
    ): void
    {
        $group->update(['status' => 'active']);

        $ownerRoleId = $this->ensureRoleId('owner', 'Owner', 'Group owner');
        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => $ownerRoleId, 'status' => 'active', 'joined_at' => now()]
        );

        Subscription::create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => $paidAmount,
            'included_seats' => 2,
        ]);

        $includedCredits = $this->groupCreationCredits();
        $includedTokens = $includedCredits * self::TOKENS_PER_CREDIT;
        GroupToken::create([
            'group_id' => $group->id,
            'total_tokens' => $includedTokens,
            'used_tokens' => 0,
            'remaining_tokens' => $includedTokens,
        ]);

        $source = $paymentMethod === 'midtrans' ? 'group_creation_midtrans' : 'group_creation';

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => $source,
            'token_amount' => $includedTokens,
            'price_paid' => $paidAmount,
            'payment_reference' => $transactionId,
        ]);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.create',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'normkredits' => $includedCredits,
            ],
            'created_at' => now(),
        ]);
    }

    public function activateJoin(
        Group $group,
        $user,
        int $paidAmount = 0,
        ?string $transactionId = null,
        string $paymentMethod = 'du'
    ): void
    {
        $memberRoleId = $this->ensureRoleId('member', 'Member', 'Group member');

        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => $memberRoleId, 'status' => 'active', 'joined_at' => now()]
        );

        $subscription = $group->subscription;
        if ($subscription) {
            $subscription->included_seats = (int) $subscription->included_seats + 1;
            $subscription->save();
        }

        $joinCredits = $this->joinCredits();
        $tokensFromJoin = $joinCredits * self::TOKENS_PER_CREDIT;

        if ($tokensFromJoin > 0) {
            $groupToken = GroupToken::firstOrCreate(
                ['group_id' => $group->id],
                ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
            );
            $groupToken->addTokens($tokensFromJoin);
        }

        $source = $paymentMethod === 'midtrans' ? 'patungan_midtrans' : 'patungan';

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => $source,
            'token_amount' => $tokensFromJoin,
            'price_paid' => $paidAmount,
            'payment_reference' => $transactionId,
        ]);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.member_joined',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'patungan' => true,
                'normkredits' => $joinCredits,
            ],
            'created_at' => now(),
        ]);
    }

    private function ensureRoleId(string $key, string $name, string $description): int
    {
        return (int) Role::firstOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => $description]
        )->id;
    }

    private function groupCreationCredits(): int
    {
        return max(0, (int) config('normchat.group_creation_credits', 10));
    }

    private function joinCredits(): int
    {
        return max(0, (int) config('normchat.join_credits', 15));
    }

    private function createUniqueOrderId(string $prefix): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $orderId = strtoupper($prefix) . '-' . now()->format('His') . '-' . strtoupper(Str::random(6));
            if (! PendingPayment::where('order_id', $orderId)->exists()) {
                return $orderId;
            }
        }

        return strtoupper($prefix) . '-' . now()->format('His') . '-' . strtoupper(Str::random(8));
    }

    private function initiateGroupMidtransPayment(Request $request, Group $group, $user, bool $isTest = false): RedirectResponse
    {
        $interdotz = app(InterdotzService::class);
        $idrPrice = $isTest
            ? (int) config('normchat.idr_group_creation_test', 1)
            : (int) config('normchat.idr_group_creation', 35000);

        if (! $interdotz->isConfigured()) {
            $group->forceDelete();

            return back()->withErrors(['payment' => 'Sistem pembayaran belum dikonfigurasi.'])->withInput();
        }

        $ssoToken = $user->getAccessToken();
        if (! $ssoToken) {
            $group->forceDelete();

            return back()->withErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.'])->withInput();
        }

        $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');
        $orderId = $this->createUniqueOrderId('NCGRP');
        $callbackUrl = route('groups.payment.callback', ['order' => $orderId]);

        $payment = $interdotz->createPayment(
            $ssoToken,
            $orderId,
            $idrPrice,
            'IDR',
            $callbackUrl,
            [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => '',
            ],
            [[
                'id' => ($isTest ? 'group-test-' : 'group-') . $group->id,
                'name' => ($isTest ? 'Test pembayaran grup ' : 'Pembuatan grup ') . $group->name,
                'price' => $idrPrice,
                'quantity' => 1,
            ]],
            $interdotzUserId
        );

        $payload = is_array($payment['payload'] ?? null) ? $payment['payload'] : (is_array($payment) ? $payment : []);
        $redirectUrl = (string) ($payload['redirect_url'] ?? $payload['redirectUrl'] ?? $payload['checkout_url'] ?? $payload['checkoutUrl'] ?? '');
        $paymentId = (string) ($payload['id'] ?? $payload['payment_id'] ?? '');

        if ($redirectUrl === '') {
            $group->forceDelete();

            $errorMessage = $interdotz->getLastError() ?? 'Gagal membuat pembayaran Midtrans.';

            return back()->withErrors(['payment' => $errorMessage])->withInput();
        }

        PendingPayment::create([
            'user_id' => (int) $user->id,
            'group_id' => (int) $group->id,
            'order_id' => $orderId,
            'payment_type' => 'group_create_midtrans',
            'expected_amount' => $idrPrice,
            'status' => 'pending',
            'metadata_json' => [
                'action' => 'group_create_midtrans',
                'group_name' => $group->name,
                'payment_method' => $isTest ? 'midtrans_test' : 'midtrans',
                'is_test' => $isTest,
                'payment_id' => $paymentId,
            ],
            'expires_at' => now()->addHour(),
        ]);

        return redirect()->away($redirectUrl);
    }

    private function initiateJoinMidtransPayment(Request $request, Group $group, $user, string $shareId): RedirectResponse
    {
        $interdotz = app(InterdotzService::class);
        $idrPatungan = (int) config('normchat.idr_patungan_min', 5000);

        if (! $interdotz->isConfigured()) {
            return back()->withErrors(['payment' => 'Sistem pembayaran belum dikonfigurasi.']);
        }

        $ssoToken = $user->getAccessToken();
        if (! $ssoToken) {
            return back()->withErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.']);
        }

        $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');
        $orderId = $this->createUniqueOrderId('NCJOIN');
        $callbackUrl = route('groups.payment.callback', ['order' => $orderId]);

        $payment = $interdotz->createPayment(
            $ssoToken,
            $orderId,
            $idrPatungan,
            'IDR',
            $callbackUrl,
            [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => '',
            ],
            [[
                'id' => 'join-' . $group->id,
                'name' => 'Donasi bergabung grup ' . $group->name,
                'price' => $idrPatungan,
                'quantity' => 1,
            ]],
            $interdotzUserId
        );

        $payload = is_array($payment['payload'] ?? null) ? $payment['payload'] : (is_array($payment) ? $payment : []);
        $redirectUrl = (string) ($payload['redirect_url'] ?? $payload['redirectUrl'] ?? $payload['checkout_url'] ?? $payload['checkoutUrl'] ?? '');
        $paymentId = (string) ($payload['id'] ?? $payload['payment_id'] ?? '');

        if ($redirectUrl === '') {
            $errorMessage = $interdotz->getLastError() ?? 'Gagal membuat pembayaran Midtrans.';

            return back()->withErrors(['payment' => $errorMessage]);
        }

        PendingPayment::create([
            'user_id' => (int) $user->id,
            'group_id' => (int) $group->id,
            'order_id' => $orderId,
            'payment_type' => 'group_join_midtrans',
            'expected_amount' => $idrPatungan,
            'status' => 'pending',
            'metadata_json' => [
                'action' => 'group_join_midtrans',
                'group_name' => $group->name,
                'share_id' => $shareId,
                'payment_method' => 'midtrans',
                'payment_id' => $paymentId,
            ],
            'expires_at' => now()->addHour(),
        ]);

        return redirect()->away($redirectUrl);
    }
}
