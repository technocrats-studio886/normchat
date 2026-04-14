<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
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

    public function buyTokensForm(): View
    {
        $user = Auth::user();
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);

        $groups = Group::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'active'));
            })
            ->with('groupToken')
            ->get();

        return view('subscription.buy-tokens', [
            'groups' => $groups,
            'duPer12Nk' => $duPer12Nk,
        ]);
    }

    public function buyTokens(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'package' => ['required', 'in:nk_12,nk_24,nk_48,nk_100'],
        ]);

        $user = Auth::user();
        $group = Group::findOrFail($validated['group_id']);

        $isMember = $group->owner_id === $user->id
            || $group->members()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isMember, 403);

        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);

        $packages = [
            'nk_12' => ['normkredits' => 12, 'du' => $duPer12Nk],
            'nk_24' => ['normkredits' => 24, 'du' => $duPer12Nk * 2],
            'nk_48' => ['normkredits' => 48, 'du' => $duPer12Nk * 4],
            'nk_100' => ['normkredits' => 100, 'du' => (int) ceil(($duPer12Nk / 12) * 100)],
        ];

        $pkg = $packages[$validated['package']];
        $duAmount = $pkg['du'];
        $normkredits = $pkg['normkredits'];
        $tokenAmount = $normkredits * self::TOKENS_PER_CREDIT;

        // Superadmin bypass
        if ($this->isBypassPayment()) {
            $this->addTokensToGroup($group, $user, $tokenAmount, 0, 'SA-TOPUP-' . strtoupper(Str::random(8)));

            return redirect()->route('subscription.tokens.buy.success')
                ->with('token_purchase', [
                    'normkredits' => $normkredits,
                    'tokens' => $tokenAmount,
                    'du_paid' => 0,
                    'group_name' => $group->name,
                ]);
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
                    'du_paid' => $duAmount,
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
}
