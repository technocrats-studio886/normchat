<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pengaturan pemakaian Normkredit grup oleh owner. */
class GroupCreditController extends Controller
{
    /** Batas harian pemakaian kredit grup per anggota (owner saja). 0 = tanpa batas. */
    public function limit(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);
        $limit = (float) ($request->validate(['member_daily_credit_limit' => ['nullable', 'numeric', 'min:0']])['member_daily_credit_limit'] ?? 0);

        $group->update(['member_daily_credit_limit' => $limit > 0 ? $limit : null]);
        AuditLog::record('credits.member_daily_limit', $request->user()->id, $group, ['limit' => $limit]);

        return back()->with('success', $limit > 0
            ? 'Batas harian disimpan: ' . number_format($limit, 2, ',', '.') . ' Normkredit per anggota.'
            : 'Batas harian dihapus.');
    }
}
