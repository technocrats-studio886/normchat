<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Services\Credits\CreditWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Superadmin › Grup: daftar & detail (tanpa isi pesan), penyesuaian Normkredit beralasan,
 * bekukan/aktifkan, dan hapus. Setiap tindakan dicatat di log audit admin.
 */
class AdminGroupController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $groups = Group::query()
            ->with(['owner:id,name,email', 'groupToken'])
            ->withCount(['members' => fn ($query) => $query->where('status', 'active')])
            ->addSelect(['last_activity' => Message::select('created_at')->whereColumn('group_id', 'groups.id')->latest('id')->limit(1)])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))))
            ->when(in_array($status, ['active', Group::STATUS_FROZEN], true), fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('superadmin.groups.index', ['groups' => $groups, 'search' => $search, 'status' => $status]);
    }

    public function show(Group $group): View
    {
        $group->load(['owner:id,name,email', 'members.user:id,name,email', 'members.role']);
        $since = now()->subDays(30);

        return view('superadmin.groups.show', [
            'group' => $group,
            'token' => GroupToken::forGroup((int) $group->id),
            'messageCount' => Message::where('group_id', $group->id)->count(),
            'lastActivity' => Message::where('group_id', $group->id)->max('created_at'),
            'usage' => AiUsageLog::where('group_id', $group->id)->where('created_at', '>=', $since)
                ->selectRaw('count(*) filter (where kind not in (' . AdminReportController::placeholders(AiUsageLog::NOT_ANSWERS) . ')) as requests,'
                    . ' coalesce(sum(charged_credits), 0) as charged, coalesce(sum(provider_cost_idr), 0) as cost_idr', AiUsageLog::NOT_ANSWERS)->first(),
            'transactions' => GroupTokenContribution::where('group_id', $group->id)->with('user:id,name')->latest('id')->limit(30)->get(),
            'audit' => AuditLog::where('group_id', $group->id)->where('action', 'like', 'admin.%')->with('actor:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    public function adjustCredits(Request $request, Group $group, CreditWallet $wallet): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $amount = round((float) $data['amount'], 2);

        try {
            DB::transaction(function () use ($request, $group, $wallet, $amount, $data) {
                $contribution = GroupTokenContribution::create([
                    'group_id' => $group->id,
                    'user_id' => $request->user()->id,
                    'source' => 'admin_adjustment',
                    'credit_amount' => $amount,
                    'price_paid' => 0,
                    'payment_reference' => 'ADM-' . now()->format('YmdHis'),
                    'note' => $data['reason'],
                ]);
                $wallet->adjust($group, $amount, ['user_id' => $request->user()->id, 'contribution_id' => $contribution->id]);
                AuditLog::record('admin.adjust_credits', $request->user()->id, $group, ['amount' => $amount, 'reason' => $data['reason'], 'group' => $group->name]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('success', ($amount > 0 ? 'Ditambahkan ' : 'Dikurangi ') . number_format(abs($amount), 2, ',', '.') . ' NK.');
    }

    public function freeze(Request $request, Group $group): RedirectResponse
    {
        $freeze = ! $group->isFrozen();
        $group->update(['status' => $freeze ? Group::STATUS_FROZEN : 'active']);
        AuditLog::record($freeze ? 'admin.freeze_group' : 'admin.unfreeze_group', $request->user()->id, $group, ['name' => $group->name]);

        return back()->with('success', $freeze ? 'Grup dibekukan.' : 'Grup diaktifkan kembali.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        if (trim((string) $request->input('confirm_name')) !== $group->name) {
            return back()->withErrors(['confirm_name' => 'Ketik nama grup persis untuk menghapus.']);
        }

        AuditLog::record('admin.delete_group', $request->user()->id, $group, ['name' => $group->name, 'owner_id' => $group->owner_id]);
        $group->delete();

        return redirect()->route('superadmin.groups.index')->with('success', "Grup {$group->name} dihapus.");
    }
}
