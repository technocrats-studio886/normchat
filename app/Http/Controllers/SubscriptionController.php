<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    private const PLAN_PRICE = 25000;
    private const INCLUDED_TOKENS = 10_000; // 10K tokens = 10 normkredit
    private const PRICE_PER_CREDIT = 1000; // Rp1.000 per normkredit (1K token)
    private const ADD_SEAT_PRICE = 4000;
    private const JOIN_MIN_PATUNGAN = 10000; // Rp10.000 minimum patungan join

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

        $hasActive = Subscription::query()
            ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'active')
            ->exists();

        if (session('subscription_paid')) {
            return redirect()->route('groups.create');
        }

        if ($hasActive) {
            return redirect()->route('groups.index');
        }

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

        // Dummy payment: mark as paid, tokens will be allocated when group is created
        session([
            'subscription_paid' => true,
            'subscription_tokens' => self::INCLUDED_TOKENS,
        ]);

        return redirect()->route('subscription.payment.success');
    }

    public function paymentSuccess(): View
    {
        return view('subscription.payment-success');
    }

    // ── Token Purchase ───────────────────────────────────────

    public function buyTokensForm(): View
    {
        $user = Auth::user();

        // Get user's groups (owned or member)
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
            'credit_amount' => ['required_if:mode,by_credits', 'nullable', 'numeric', 'min:1'],
            'price_amount' => ['required_if:mode,by_price', 'nullable', 'integer', 'min:1000'],
        ]);

        $user = Auth::user();
        $group = Group::findOrFail($validated['group_id']);

        // Verify user has access to this group
        $isMember = $group->owner_id === $user->id
            || $group->members()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isMember, 403);

        if ($validated['mode'] === 'by_credits') {
            $credits = (float) $validated['credit_amount'];
            $tokenAmount = (int) ($credits * 1000);
            $price = (int) ceil($credits * self::PRICE_PER_CREDIT);
        } else {
            $price = (int) $validated['price_amount'];
            $credits = $price / self::PRICE_PER_CREDIT;
            $tokenAmount = (int) floor($credits * 1000);
        }

        if ($tokenAmount < 1000) {
            return back()->withErrors(['credit_amount' => 'Minimal pembelian 1 normkredit (1.000 token).']);
        }

        $paymentRef = 'TOKEN-' . strtoupper(Str::random(10));

        // Add tokens to group
        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );
        $groupToken->addTokens($tokenAmount);

        // Record contribution
        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'topup',
            'token_amount' => $tokenAmount,
            'price_paid' => $price,
            'payment_reference' => $paymentRef,
        ]);

        session([
            'token_purchase' => [
                'reference' => $paymentRef,
                'tokens' => $tokenAmount,
                'price' => $price,
                'group_name' => $group->name,
            ],
        ]);

        return redirect()->route('subscription.tokens.buy.success')
            ->with('success', 'Token berhasil ditambahkan ke grup ' . $group->name);
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
            'payment_method' => ['required', 'in:dummy_va'],
            'payment_confirmed' => ['required', 'accepted'],
        ]);

        $subscription = $group->subscription;
        abort_unless($subscription !== null, 422, 'Subscription untuk group ini belum tersedia.');

        $seatCount = (int) $validated['seat_count'];
        $totalAmount = $seatCount * self::ADD_SEAT_PRICE;
        $paymentRef = 'DUMMY-SEAT-' . strtoupper(Str::random(10));

        $subscription->included_seats = (int) $subscription->included_seats + $seatCount;
        $subscription->save();

        $payment = SubscriptionPayment::query()->create([
            'subscription_id' => $subscription->id,
            'group_id' => $group->id,
            'created_by' => (int) Auth::id(),
            'payment_type' => 'add_seat_dummy',
            'reference' => $paymentRef,
            'seat_count' => $seatCount,
            'unit_price' => self::ADD_SEAT_PRICE,
            'total_amount' => $totalAmount,
            'status' => 'paid',
            'metadata_json' => [
                'payment_method' => $validated['payment_method'],
                'note' => 'Dummy seat payment success',
            ],
        ]);

        session([
            'add_seat_payment' => [
                'reference' => $payment->reference,
                'seat_count' => $seatCount,
                'amount' => $totalAmount,
                'unit_price' => self::ADD_SEAT_PRICE,
            ],
        ]);

        return redirect()->route('subscription.add-seat.success', $group)
            ->with('success', 'Pembayaran dummy berhasil. Seat tambahan aktif.');
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
