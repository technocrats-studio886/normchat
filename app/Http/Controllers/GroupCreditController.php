<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Services\Credits\CreditWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Pengaturan pembagian Normkredit grup oleh setiap anggota dan owner. */
class GroupCreditController extends Controller
{
    public function __construct(private readonly CreditWallet $wallet)
    {
    }

    /** Pindahkan kredit terlindungi milik sendiri ke kredit bersama. */
    public function share(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('view', $group);
        $amount = (float) $request->validate(['amount' => ['required', 'numeric', 'min:0.01']])['amount'];

        try {
            $this->wallet->shareProtected($group, $request->user(), $amount);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        AuditLog::record('credits.shared', $request->user()->id, $group, ['amount' => $amount]);

        return back()->with('success', number_format($amount, 2, ',', '.') . ' NK dipindahkan ke kredit bersama.');
    }

    /** Urutan pemakaian kredit saat bertanya ke AI. */
    public function order(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('view', $group);
        $sharedFirst = $request->validate(['spend_shared_first' => ['required', 'boolean']])['spend_shared_first'];

        $this->wallet->setSpendSharedFirst($group, $request->user(), (bool) $sharedFirst);

        return back()->with('success', 'Urutan pemakaian kredit disimpan.');
    }

    /** Batas harian pemakaian kredit bersama per anggota (owner saja). 0 = tanpa batas. */
    public function limit(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);
        $limit = (float) ($request->validate(['member_daily_shared_limit' => ['nullable', 'numeric', 'min:0']])['member_daily_shared_limit'] ?? 0);

        $group->update(['member_daily_shared_limit' => $limit > 0 ? $limit : null]);
        AuditLog::record('credits.member_daily_limit', $request->user()->id, $group, ['limit' => $limit]);

        return back()->with('success', $limit > 0
            ? 'Batas harian kredit bersama per anggota: ' . number_format($limit, 2, ',', '.') . ' NK.'
            : 'Batas harian kredit bersama dihapus.');
    }
}
