<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Role;
use App\Models\Subscription;
use App\Services\InterdotzService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GroupController extends Controller
{
    private const INCLUDED_CREDITS = 12;
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

        return view('groups.create', [
            'duPrice' => $duPrice,
            'includedCredits' => self::INCLUDED_CREDITS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
        ]);

        $user = Auth::user();
        $duPrice = (int) config('normchat.du_group_creation', 175);

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

        return view('groups.join', [
            'group' => $group,
            'alreadyMember' => false,
            'duPatungan' => $duPatungan,
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
        ]);

        if (! Hash::check($request->input('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.'])->withInput();
        }

        $user = Auth::user();
        $duPatungan = (int) config('normchat.du_patungan', 25);

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

        $member->delete();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_removed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Member berhasil dihapus dari group.');
    }

    // ── Private helpers ─────────────────────────────────────

    public function activateGroup(Group $group, $user, int $duPaid = 0, ?string $transactionId = null): void
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
            'main_price' => $duPaid,
            'included_seats' => 2,
        ]);

        $includedTokens = self::INCLUDED_CREDITS * self::TOKENS_PER_CREDIT;
        GroupToken::create([
            'group_id' => $group->id,
            'total_tokens' => $includedTokens,
            'used_tokens' => 0,
            'remaining_tokens' => $includedTokens,
        ]);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'group_creation',
            'token_amount' => $includedTokens,
            'price_paid' => $duPaid,
            'payment_reference' => $transactionId,
        ]);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.create',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'du_paid' => $duPaid,
                'transaction_id' => $transactionId,
                'normkredits' => self::INCLUDED_CREDITS,
            ],
            'created_at' => now(),
        ]);
    }

    public function activateJoin(Group $group, $user, int $duPaid = 0, ?string $transactionId = null): void
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

        // Convert DU paid into normkredit tokens using topup rate.
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);
        $tokensFromDu = 0;
        if ($duPaid > 0 && $duPer12Nk > 0) {
            $normkredits = (int) floor(($duPaid * 12) / $duPer12Nk);
            $tokensFromDu = $normkredits * self::TOKENS_PER_CREDIT;
        }

        if ($tokensFromDu > 0) {
            $groupToken = GroupToken::firstOrCreate(
                ['group_id' => $group->id],
                ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
            );
            $groupToken->addTokens($tokensFromDu);
        }

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'patungan',
            'token_amount' => $tokensFromDu,
            'price_paid' => $duPaid,
            'payment_reference' => $transactionId,
        ]);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.member_joined',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'du_paid' => $duPaid,
                'transaction_id' => $transactionId,
                'patungan' => true,
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
}
