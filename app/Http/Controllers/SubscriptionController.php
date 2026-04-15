<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\InterdotzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    private const TOKENS_PER_CREDIT = 2_500;
    private const BYPASS_EMAILS = [
        'superadmin@interdotz.com',
    ];

    private function isBypassPayment(): bool
    {
        return in_array(Auth::user()?->email, self::BYPASS_EMAILS, true);
    }

    public function landing(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('groups.index');
        }

        return view('marketing.landing');
    }

    public function pricing(): RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    public function paymentDetail(): RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    public function pay(Request $request): RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    public function paymentWaiting(Request $request): View|RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'paid',
            'redirect' => route('groups.index'),
        ]);
    }

    public function paymentSuccess(): RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    // ── Token Purchase (via DU) ─────────────────────────────

    public function buyTokensForm(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $packageOptions = $this->topupPackages();

        $groups = Group::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'active'));
            })
            ->with('groupToken')
            ->get();

        $contextGroup = null;
        if ($request->filled('group')) {
            $contextGroup = $groups->firstWhere('id', (int) $request->input('group'));
        }

        if (! $contextGroup) {
            return redirect()->route('groups.index')
                ->with('info', 'Top-up Normkredit hanya bisa dilakukan dari dalam grup.');
        }

        session(['normchat_topup_group_id' => (int) $contextGroup->id]);

        return view('subscription.buy-tokens', [
            'groups' => $groups,
            'packageOptions' => $packageOptions,
            'contextGroup' => $contextGroup,
        ]);
    }

    public function buyTokens(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'package' => ['required', 'in:nk_12,nk_24,nk_48,nk_100'],
            'payment_method' => ['nullable', 'in:du,midtrans'],
        ]);

        $lockedGroupId = (int) session('normchat_topup_group_id', 0);
        if ($lockedGroupId <= 0) {
            return redirect()->route('groups.index')
                ->withErrors(['payment' => 'Top-up Normkredit harus dimulai dari halaman grup.']);
        }

        if ($lockedGroupId > 0 && $lockedGroupId !== (int) $validated['group_id']) {
            return redirect()->route('groups.index')
                ->withErrors(['payment' => 'Top-up harus dilakukan dari grup aktif yang sama.']);
        }

        $user = Auth::user();
        $group = Group::findOrFail($validated['group_id']);
        $paymentMethod = (string) ($validated['payment_method'] ?? 'du');

        $isMember = $group->owner_id === $user->id
            || $group->members()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isMember, 403);

        $packages = $this->topupPackages();
        $pkg = $packages[$validated['package']];
        $duAmount = (int) $pkg['du'];
        $idrAmount = (int) $pkg['idr'];
        $normkredits = $pkg['normkredits'];
        $tokenAmount = $normkredits * self::TOKENS_PER_CREDIT;

        // Superadmin bypass
        if ($this->isBypassPayment()) {
            $this->addTokensToGroup($group, $user, $tokenAmount, 0, 'SA-TOPUP-' . strtoupper(Str::random(8)));

            return redirect()->route('subscription.tokens.buy.success')
                ->with('token_purchase', [
                    'normkredits' => $normkredits,
                    'tokens' => $tokenAmount,
                    'paid_amount' => 0,
                    'payment_method' => 'bypass',
                    'payment_unit' => 'DU',
                    'group_name' => $group->name,
                ]);
        }

        if ($paymentMethod === 'midtrans') {
            return $this->initiateTopupMidtransPayment(
                $request,
                $group,
                $user,
                $validated['package'],
                $idrAmount,
                $normkredits,
                $tokenAmount
            );
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

        $referenceId = "topup_{$group->id}_{$user->id}_" . time();
        $callbackUrl = url('/api/webhooks/interdotz/charge');

        // Try direct charge first (immediate deduction)
        $chargeResult = $interdotz->charge($ssoToken, $duAmount, 'normchat_topup', $referenceId, $interdotzUserId);

        if ($chargeResult && isset($chargeResult['payload'])) {
            $txId = $chargeResult['payload']['transaction_id'] ?? $referenceId;
            $this->addTokensToGroup($group, $user, $tokenAmount, $duAmount, $txId);

            return redirect()->route('subscription.tokens.buy.success')
                ->with('token_purchase', [
                    'normkredits' => $normkredits,
                    'tokens' => $tokenAmount,
                    'paid_amount' => $duAmount,
                    'payment_method' => 'du',
                    'payment_unit' => 'DU',
                    'group_name' => $group->name,
                ]);
        }

        // Fallback to chargeRequest (redirect flow)
        $result = $interdotz->chargeRequest(
            $ssoToken,
            $duAmount,
            'normchat_topup',
            $referenceId,
            "Top-up {$normkredits} normkredit untuk grup {$group->name} ({$duAmount} DU)",
            $callbackUrl,
            $interdotzUserId
        );

        $redirectUrl = $result['payload']['redirect_url'] ?? $result['payload']['redirectUrl'] ?? null;
        if ($result && ! empty($redirectUrl)) {
            session([
                'pending_topup_ref' => $referenceId,
                'pending_topup_group' => $group->id,
            ]);

            return redirect()->away($redirectUrl);
        }

        $serviceError = $interdotz->getLastError();
        $errorMessage = 'Gagal memproses pembayaran.';
        if ($serviceError) {
            $errorMessage .= ' ' . $serviceError;
        } else {
            $errorMessage .= " Pastikan saldo DU mencukupi ({$duAmount} DU).";
        }

        return back()->withErrors(['payment' => $errorMessage]);
    }

    public function buyTokensSuccess(): View
    {
        $purchase = session('token_purchase');

        return view('subscription.buy-tokens-success', [
            'purchase' => is_array($purchase) ? $purchase : null,
        ]);
    }

    // ── Private ─────────────────────────────────────────────────

    private function addTokensToGroup(Group $group, $user, int $tokens, int $duPaid, string $reference): void
    {
        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );
        $groupToken->addTokens($tokens);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'topup',
            'token_amount' => $tokens,
            'price_paid' => $duPaid,
            'payment_reference' => $reference,
        ]);
    }

    private function topupPackages(): array
    {
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);

        return [
            'nk_12' => [
                'normkredits' => 12,
                'du' => $duPer12Nk,
                'idr' => (int) config('normchat.idr_topup_12nk', 35000),
            ],
            'nk_24' => [
                'normkredits' => 24,
                'du' => $duPer12Nk * 2,
                'idr' => (int) config('normchat.idr_topup_24nk', 70000),
            ],
            'nk_48' => [
                'normkredits' => 48,
                'du' => $duPer12Nk * 4,
                'idr' => (int) config('normchat.idr_topup_48nk', 140000),
            ],
            'nk_100' => [
                'normkredits' => 100,
                'du' => (int) ceil(($duPer12Nk / 12) * 100),
                'idr' => (int) config('normchat.idr_topup_100nk', 280000),
            ],
        ];
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

    private function initiateTopupMidtransPayment(
        Request $request,
        Group $group,
        $user,
        string $packageId,
        int $idrAmount,
        int $normkredits,
        int $tokenAmount
    ): RedirectResponse {
        $interdotz = app(InterdotzService::class);

        if (! $interdotz->isConfigured()) {
            return back()->withErrors(['payment' => 'Sistem pembayaran belum dikonfigurasi.']);
        }

        $ssoToken = $user->getAccessToken();
        if (! $ssoToken) {
            return back()->withErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.']);
        }

        $interdotzUserId = (string) ($user->interdotz_id ?: $user->provider_user_id ?: '');
        $orderId = $this->createUniqueOrderId('NCTOPUP');
        $callbackUrl = route('groups.payment.callback', ['order' => $orderId]);

        $payment = $interdotz->createPayment(
            $ssoToken,
            $orderId,
            $idrAmount,
            'IDR',
            $callbackUrl,
            [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => '',
            ],
            [[
                'id' => 'topup-' . $packageId,
                'name' => 'Top-up ' . $normkredits . ' Normkredit',
                'price' => $idrAmount,
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
            'payment_type' => 'topup_midtrans',
            'expected_amount' => $idrAmount,
            'status' => 'pending',
            'metadata_json' => [
                'action' => 'topup_midtrans',
                'group_name' => $group->name,
                'package_id' => $packageId,
                'normkredits' => $normkredits,
                'token_amount' => $tokenAmount,
                'payment_method' => 'midtrans',
                'payment_id' => $paymentId,
            ],
            'expires_at' => now()->addHour(),
        ]);

        return redirect()->away($redirectUrl);
    }
}
