<?php

namespace App\Http\Controllers;

use App\Enums\PaymentPurpose;
use App\Exceptions\CheckoutFailedException;
use App\Http\Requests\TopupCreditsRequest;
use App\Models\Group;
use App\Services\NormkreditPricing;
use App\Services\Payments\NormkreditCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Top-up Normkredit grup dengan nominal bebas. Selalu dimulai dari halaman grup:
 * grup tujuan dikunci di session agar form tidak bisa diarahkan ke grup lain.
 */
class TopupController extends Controller
{
    private const SESSION_GROUP_KEY = 'normchat_topup_group_id';

    public function __construct(private readonly NormkreditPricing $pricing)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        $group = Group::query()
            ->visibleTo($request->user())
            ->with('groupToken')
            ->find((int) $request->query('group'));

        if (! $group) {
            return redirect()->route('groups.index')
                ->with('info', 'Isi Normkredit dari halaman grup.');
        }

        session([self::SESSION_GROUP_KEY => (int) $group->id]);

        return view('subscription.buy-tokens', ['contextGroup' => $group]);
    }

    public function store(TopupCreditsRequest $request, NormkreditCheckout $checkout): RedirectResponse
    {
        $lockedGroupId = (int) session(self::SESSION_GROUP_KEY, 0);
        if ($lockedGroupId <= 0) {
            return redirect()->route('groups.index')
                ->withErrors(['payment' => 'Isi Normkredit dari halaman grup.']);
        }

        if ($lockedGroupId !== (int) $request->validated('group_id')) {
            return redirect()->route('groups.index')
                ->withErrors(['payment' => 'Isi Normkredit dari halaman grup.']);
        }

        $user = $request->user();
        $group = Group::findOrFail($lockedGroupId);
        abort_unless($group->isOwnedBy($user) || $group->hasActiveMember($user), 403);

        $quote = $this->pricing->quotePackages($request->packages());

        try {
            return redirect()->away($checkout->start($user, $group, PaymentPurpose::Topup, $quote));
        } catch (CheckoutFailedException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    public function success(): View
    {
        $purchase = session('token_purchase');

        return view('subscription.buy-tokens-success', [
            'purchase' => is_array($purchase) ? $purchase : null,
        ]);
    }
}
