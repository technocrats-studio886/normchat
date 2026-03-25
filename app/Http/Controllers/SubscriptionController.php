<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
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
    private const PLAN_PRICE = 25000;
    private const INCLUDED_TOKENS = 10_000; // 10K tokens = 10 normkredit
    private const PRICE_PER_CREDIT = 1000; // Rp1.000 per normkredit (1K token)
    private const ADD_SEAT_PRICE = 4000;
    private const JOIN_MIN_PATUNGAN = 10000; // Rp10.000 minimum patungan join
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

        $hasActive = Subscription::query()
            ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'active')
            ->exists();

        // Check if there's already a paid pending payment (webhook fulfilled)
        $paidPending = PendingPayment::where('user_id', $user->id)
            ->where('payment_type', 'subscription')
            ->where('status', 'paid')
            ->first();

        if ($paidPending || session('subscription_paid')) {
            return redirect()->route('groups.create');
        }

        if ($hasActive) {
            return redirect()->route('groups.index');
        }

        // Check for existing pending payment (not expired)
        $existingPending = PendingPayment::where('user_id', $user->id)
            ->where('payment_type', 'subscription')
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        return view('subscription.payment-detail', [
            'user' => $user,
            'planPrice' => self::PLAN_PRICE,
            'includedTokens' => self::INCLUDED_TOKENS,
            'pendingPayment' => $existingPending,
            'trakteerUrl' => config('services.trakteer.page_url'),
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => ['required', 'in:normchat-pro'],
        ]);

        $user = Auth::user();

        // Expire any old pending subscription payments
        PendingPayment::where('user_id', $user->id)
            ->where('payment_type', 'subscription')
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $orderId = 'NC-SUB-' . strtoupper(Str::random(8));

        PendingPayment::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'payment_type' => 'subscription',
            'expected_amount' => self::PLAN_PRICE,
            'status' => 'pending',
            'metadata_json' => [
                'plan' => 'normchat-pro',
                'included_tokens' => self::INCLUDED_TOKENS,
            ],
            'expires_at' => now()->addHours(self::PAYMENT_EXPIRY_HOURS),
        ]);

        return redirect()->route('subscription.payment.waiting', ['order_id' => $orderId]);
    }

    public function paymentWaiting(Request $request): View|RedirectResponse
    {
        $orderId = $request->query('order_id');
        $user = Auth::user();

        $pending = PendingPayment::where('order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $pending) {
            return redirect()->route('subscription.payment.detail')
                ->with('error', 'Order tidak ditemukan.');
        }

        if ($pending->isPaid()) {
            $this->setSessionFromPaidPending($pending);
            return redirect($this->getSuccessRedirect($pending));
        }

        return view('subscription.payment-waiting', [
            'pending' => $pending,
            'trakteerUrl' => config('services.trakteer.page_url'),
        ]);
    }

    /**
     * AJAX polling endpoint: check if pending payment has been fulfilled by webhook.
     */
    public function paymentStatus(Request $request): JsonResponse
    {
        $orderId = $request->query('order_id');
        $user = Auth::user();

        $pending = PendingPayment::where('order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $pending) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($pending->isExpired()) {
            $pending->update(['status' => 'expired']);
            return response()->json(['status' => 'expired']);
        }

        if ($pending->isPaid()) {
            $this->setSessionFromPaidPending($pending);

            return response()->json([
                'status' => 'paid',
                'redirect' => $this->getSuccessRedirect($pending),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    public function paymentSuccess(): View
    {
        return view('subscription.payment-success');
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
            'trakteerUrl' => config('services.trakteer.page_url'),
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

        $orderId = 'NC-TOKEN-' . strtoupper(Str::random(8));

        PendingPayment::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'order_id' => $orderId,
            'payment_type' => 'topup',
            'expected_amount' => $price,
            'status' => 'pending',
            'metadata_json' => [
                'token_amount' => $tokenAmount,
                'credits' => $credits,
                'group_name' => $group->name,
            ],
            'expires_at' => now()->addHours(self::PAYMENT_EXPIRY_HOURS),
        ]);

        return redirect()->route('subscription.payment.waiting', ['order_id' => $orderId]);
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
            'trakteerUrl' => config('services.trakteer.page_url'),
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
        abort_unless($subscription !== null, 422, 'Subscription untuk group ini belum tersedia.');

        $seatCount = (int) $validated['seat_count'];
        $totalAmount = $seatCount * self::ADD_SEAT_PRICE;

        $orderId = 'NC-SEAT-' . strtoupper(Str::random(8));

        PendingPayment::create([
            'user_id' => Auth::id(),
            'group_id' => $group->id,
            'order_id' => $orderId,
            'payment_type' => 'add_seat',
            'expected_amount' => $totalAmount,
            'status' => 'pending',
            'metadata_json' => [
                'seat_count' => $seatCount,
                'unit_price' => self::ADD_SEAT_PRICE,
                'subscription_id' => $subscription->id,
            ],
            'expires_at' => now()->addHours(self::PAYMENT_EXPIRY_HOURS),
        ]);

        return redirect()->route('subscription.payment.waiting', ['order_id' => $orderId]);
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

    // ── Helpers ──────────────────────────────────────────────

    private function setSessionFromPaidPending(PendingPayment $pending): void
    {
        match ($pending->payment_type) {
            'subscription' => session([
                'subscription_paid' => true,
                'subscription_tokens' => self::INCLUDED_TOKENS,
            ]),
            'topup' => session([
                'token_purchase' => [
                    'reference' => $pending->order_id,
                    'tokens' => $pending->metadata_json['token_amount'] ?? 0,
                    'price' => $pending->expected_amount,
                    'group_name' => $pending->metadata_json['group_name'] ?? '',
                ],
            ]),
            'add_seat' => session([
                'add_seat_payment' => [
                    'reference' => $pending->order_id,
                    'seat_count' => $pending->metadata_json['seat_count'] ?? 0,
                    'amount' => $pending->expected_amount,
                    'unit_price' => $pending->metadata_json['unit_price'] ?? self::ADD_SEAT_PRICE,
                ],
            ]),
            default => null,
        };
    }

    private function getSuccessRedirect(PendingPayment $pending): string
    {
        return match ($pending->payment_type) {
            'subscription' => route('subscription.payment.success'),
            'topup' => route('subscription.tokens.buy.success'),
            'add_seat' => $pending->group_id
                ? route('subscription.add-seat.success', $pending->group_id)
                : route('groups.index'),
            default => route('groups.index'),
        };
    }
}
