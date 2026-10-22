<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Support\HistoryRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Riwayat login dan riwayat transaksi Normkredit milik user. */
class ProfileHistoryController extends Controller
{
    public const LOGIN_ACTIONS = ['auth.connect', 'auth.logout'];

    private const PER_PAGE = 15;

    /** Filter ?source= → nilai kolom source kontribusi. */
    private const SOURCE_FILTERS = [
        'patungan' => ['patungan', 'patungan_midtrans'],
        'topup' => ['topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup'],
        'group_creation' => ['group_creation', 'group_creation_midtrans'],
    ];

    public function logins(Request $request): View
    {
        $user = $request->user();
        $range = HistoryRange::from($request->query('range'), 'week');
        $sortDir = $this->sortDirection($request);

        $query = HistoryRange::apply(AuditLog::where('actor_id', $user->id)->whereIn('action', self::LOGIN_ACTIONS), $range);
        $logins = AuditLog::where('actor_id', $user->id)->where('action', 'auth.connect');

        return view('profile.login-history', [
            'loginLogs' => (clone $query)->orderBy('created_at', $sortDir)->paginate(self::PER_PAGE)->withQueryString(),
            'loginRange' => $range,
            'sortDir' => $sortDir,
            'totalCount' => $query->count(),
            'totalAllTime' => (clone $logins)->count(),
            'lastLogin' => $logins->latest('created_at')->first(),
        ]);
    }

    public function transactions(Request $request): View
    {
        $user = $request->user();
        $range = HistoryRange::from($request->query('range'), 'all');
        $source = (string) $request->query('source', 'all');
        $sortDir = $this->sortDirection($request);

        $query = HistoryRange::apply(GroupTokenContribution::where('user_id', $user->id)->with('group:id,name'), $range)
            ->when(isset(self::SOURCE_FILTERS[$source]), fn ($q) => $q->whereIn('source', self::SOURCE_FILTERS[$source]));
        $allTransactions = GroupTokenContribution::where('user_id', $user->id);

        return view('profile.transactions', [
            'transactions' => (clone $query)->orderBy('created_at', $sortDir)->paginate(self::PER_PAGE)->withQueryString(),
            'range' => $range,
            'source' => $source,
            'sortDir' => $sortDir,
            'totalCount' => $query->count(),
            'totalAllTime' => (clone $allTransactions)->count(),
            'totalSpentIdr' => (int) (clone $allTransactions)->sum('price_paid'),
            'totalCreditsEarned' => (float) $allTransactions->sum('credit_amount'),
        ]);
    }

    private function sortDirection(Request $request): string
    {
        return $request->query('sort') === 'asc' ? 'asc' : 'desc';
    }
}
