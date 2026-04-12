<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    private const PLAN_PRICE = 30_000;
    private const INCLUDED_TOKENS = 30_000; // 30K tokens = 12 normkredit
    private const PRICE_PER_CREDIT = 2_500; // Rp2.500 per normkredit (2.5K token)
    private const ADD_SEAT_PRICE = 4000;
    private const PAYMENT_EXPIRY_HOURS = 24;

    public function landing(): View
    {
        return view('marketing.landing');
    }

    public function pricing(): View|RedirectResponse
    {
        return view('subscription.pricing');
    }

    // ── Payment Detail (Subscription) ────────────────────────

    public function paymentDetail(): View|RedirectResponse
    {
        $user = Auth::user();

        return view('subscription.payment-detail', [
            'user' => $user,
            'planPrice' => self::PLAN_PRICE,
            'includedTokens' => self::INCLUDED_TOKENS,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => ['required', 'in:normchat-pro'],
        ]);

        // Temporary UI-only flow: skip payment backbone and continue to app.
        session([
            'subscription_paid' => true,
            'subscription_tokens' => self::INCLUDED_TOKENS,
        ]);

        return redirect()->route('groups.index');
    }

    public function paymentWaiting(Request $request): View|RedirectResponse
    {
        return redirect()->route('groups.index');
    }

    /**
     * AJAX polling endpoint: check if pending payment has been fulfilled by webhook.
     */
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

    // ── Token Purchase ───────────────────────────────────────

    public function buyTokensForm(): View
    {
        $user = Auth::user();

        $groups = Group::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->with('groupToken')
            ->get();

        return view('subscription.buy-tokens', [
            'groups' => $groups,
            'pricePerCredit' => self::PRICE_PER_CREDIT,
        ]);
    }

    public function buyTokens(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'mode' => ['required', 'in:by_credits,by_price'],
            'credit_amount' => ['required_if:mode,by_credits', 'nullable', 'numeric', 'min:12'],
            'price_amount' => ['required_if:mode,by_price', 'nullable', 'integer', 'min:30000'],
        ]);

        $user = Auth::user();
        $group = Group::findOrFail($validated['group_id']);

        $isMember = $group->owner_id === $user->id
            || $group->members()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isMember, 403);

        if ($validated['mode'] === 'by_credits') {
            $credits = (float) $validated['credit_amount'];
            $tokenAmount = (int) ($credits * 2500);
            $price = (int) ceil($credits * self::PRICE_PER_CREDIT);
        } else {
            $price = (int) $validated['price_amount'];
            $credits = $price / self::PRICE_PER_CREDIT;
            $tokenAmount = (int) floor($credits * 2500);
        }

        if ($credits < 12) {
            return back()->withErrors(['credit_amount' => 'Minimal pembelian 12 normkredit (30.000 token = Rp30.000).']);
        }

        $reference = 'SIM-TOKEN-' . strtoupper(Str::random(8));

        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );
        $groupToken->addTokens($tokenAmount);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'topup',
            'token_amount' => $tokenAmount,
            'price_paid' => 0,
            'payment_reference' => $reference,
        ]);

        session([
            'token_purchase' => [
                'reference' => $reference,
                'tokens' => $tokenAmount,
                'price' => $price,
                'group_name' => $group->name,
            ],
        ]);

        return redirect()->route('subscription.tokens.buy.success');
    }

    public function buyTokensSuccess(): View
    {
        $purchase = session('token_purchase');

        return view('subscription.buy-tokens-success', [
            'purchase' => is_array($purchase) ? $purchase : null,
        ]);
    }

    // ── Seat Management ──────────────────────────────────────

    public function addSeatForm(Group $group): View
    {
        $group->load('subscription');

        abort_unless((int) $group->owner_id === (int) Auth::id(), 403);

        return view('subscription.add-seat', [
            'group' => $group,
            'seatPrice' => self::ADD_SEAT_PRICE,
        ]);
    }

    public function processAddSeat(Request $request, Group $group): RedirectResponse
    {
        $group->load('subscription');

        abort_unless((int) $group->owner_id === (int) Auth::id(), 403);

        $validated = $request->validate([
            'seat_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $subscription = $group->subscription;
        if (! $subscription) {
            $subscription = Subscription::create([
                'group_id' => $group->id,
                'plan_name' => 'normchat-pro',
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'main_price' => self::PLAN_PRICE,
                'included_seats' => 2,
            ]);
        }

        $seatCount = (int) $validated['seat_count'];
        $totalAmount = $seatCount * self::ADD_SEAT_PRICE;
        $reference = 'SIM-SEAT-' . strtoupper(Str::random(8));

        $subscription->included_seats = (int) $subscription->included_seats + $seatCount;
        $subscription->save();

        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'group_id' => $group->id,
            'created_by' => Auth::id(),
            'payment_type' => 'add_seat',
            'reference' => $reference,
            'seat_count' => $seatCount,
            'unit_price' => self::ADD_SEAT_PRICE,
            'total_amount' => 0,
            'status' => 'paid',
            'metadata_json' => [
                'simulated' => true,
                'simulated_amount' => $totalAmount,
            ],
        ]);

        session([
            'add_seat_payment' => [
                'reference' => $reference,
                'seat_count' => $seatCount,
                'amount' => 0,
                'unit_price' => self::ADD_SEAT_PRICE,
            ],
        ]);

        return redirect()->route('subscription.add-seat.success', $group);
    }

    public function addSeatSuccess(Group $group): View
    {
        $group->load('subscription.seats');

        abort_unless((int) $group->owner_id === (int) Auth::id(), 403);

        $activeExtraSeats = max(((int) ($group->subscription?->included_seats ?? 2)) - 2, 0);
        $paymentSummary = session('add_seat_payment');

        return view('subscription.add-seat-success', [
            'group' => $group,
            'activeExtraSeats' => $activeExtraSeats,
            'paymentSummary' => is_array($paymentSummary) ? $paymentSummary : null,
        ]);
    }

    public function addSeatPaymentHistory(Group $group): View
    {
        $group->load(['subscription.payments' => fn ($query) => $query->latest()]);

        abort_unless((int) $group->owner_id === (int) Auth::id(), 403);

        return view('subscription.add-seat-payments', [
            'group' => $group,
            'payments' => $group->subscription?->payments ?? collect(),
        ]);
    }

}
