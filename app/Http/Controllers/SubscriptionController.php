<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    private const ADD_SEAT_PRICE = 4000;

    public function landing(): View
    {
        return view('marketing.landing');
    }

    public function pricing(): View|RedirectResponse
    {
        return view('subscription.pricing');
    }

    public function paymentDetail(): View|RedirectResponse
    {
        $user = Auth::user();

        // Already has active subscription → skip to home
        $hasActive = Subscription::query()
            ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'active')
            ->exists();

        if ($hasActive || session('subscription_paid')) {
            return redirect()->route('groups.index');
        }

        return view('subscription.payment-detail', [
            'user' => $user,
            'provider' => $user->auth_provider,
            'email' => $user->email,
            'planPrice' => 15000,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => ['required', 'in:normchat-pro'],
        ]);

        // Dummy payment. Mark as paid for first group creation.
        session(['subscription_paid' => true]);

        return redirect()->route('subscription.payment.success');
    }

    public function paymentSuccess(): View
    {
        return view('subscription.payment-success');
    }

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
        $paymentRef = 'DUMMY-SEAT-'.strtoupper(Str::random(10));

        // Dummy payment marked as successful: increase seat capacity directly.
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

        return redirect()->route('subscription.add-seat.success', $group)->with('success', 'Pembayaran dummy berhasil. Seat tambahan aktif.');
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
