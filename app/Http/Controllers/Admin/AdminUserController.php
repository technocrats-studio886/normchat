<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProfileHistoryController;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Superadmin › User: daftar & detail, blokir (semua sesi berakhir lewat EndBlockedSession),
 * aktifkan kembali, hapus akun (soft delete), dan pulihkan. Akun superadmin tidak bisa diblokir
 * atau dihapus dari sini.
 */
class AdminUserController extends Controller
{
    /** Status keanggotaan selama akunnya dihapus. */
    private const SUSPENDED = 'account_deleted';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::withTrashed()
            ->withCount(['ownedGroups', 'memberships' => fn ($query) => $query->where('status', 'active')])
            ->addSelect(['last_login_at' => AuditLog::select('created_at')->whereColumn('actor_id', 'users.id')->where('action', 'auth.connect')->latest('id')->limit(1)])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('superadmin.users.index', ['users' => $users, 'search' => $search]);
    }

    public function show(User $user): View
    {
        return view('superadmin.users.show', [
            'user' => $user,
            'lastLogin' => AuditLog::where('actor_id', $user->id)->whereIn('action', ProfileHistoryController::LOGIN_ACTIONS)->latest('id')->first(),
            'ownedGroups' => Group::where('owner_id', $user->id)->get(['id', 'name', 'status']),
            'memberships' => GroupMember::where('user_id', $user->id)->with(['group:id,name,status,owner_id', 'role'])->get()
                ->filter(fn (GroupMember $member) => $member->group && (int) $member->group->owner_id !== (int) $user->id),
            'audit' => AuditLog::where('target_type', User::class)->where('target_id', $user->id)->where('action', 'like', 'admin.%')->with('actor:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperadmin(), 403, 'Akun superadmin tidak bisa diblokir.');

        $block = $user->blocked_at === null;
        // Tanpa remember token, sesi "ingat saya" di perangkat lain juga tidak bisa dipulihkan.
        $user->forceFill(['blocked_at' => $block ? now() : null, 'remember_token' => null])->save();
        AuditLog::record($block ? 'admin.block_user' : 'admin.unblock_user', $request->user()->id, $user, ['email' => $user->email]);

        return back()->with('success', $block ? 'User diblokir. Semua sesinya berakhir.' : 'User diaktifkan kembali.');
    }

    /**
     * Hapus akun: dinonaktifkan (soft delete) dan hilang dari aplikasi, tetapi datanya tetap di
     * database dan bisa dipulihkan. Grup miliknya ikut dinonaktifkan, keanggotaannya di grup lain
     * ditangguhkan; keduanya kembali saat akun dipulihkan.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperadmin() || $user->is($request->user()), 403, 'Akun superadmin tidak bisa dihapus.');
        if (strtolower(trim((string) $request->input('confirm_email'))) !== strtolower((string) $user->email)) {
            return back()->withErrors(['confirm_email' => 'Ketik email user persis untuk menghapus.']);
        }

        DB::transaction(function () use ($request, $user) {
            $at = now();
            Group::where('owner_id', $user->id)->get()->each(fn (Group $group) => $group->forceFill(['deleted_at' => $at])->save());
            GroupMember::where('user_id', $user->id)->where('status', 'active')->update(['status' => self::SUSPENDED]);
            $user->forceFill(['remember_token' => null, 'deleted_at' => $at])->save();
            AuditLog::record('admin.delete_user', $request->user()->id, $user, [
                'email' => $user->email,
                'owned_groups' => Group::withTrashed()->where('owner_id', $user->id)->where('deleted_at', $at)->pluck('name')->all(),
            ]);
        });

        return redirect()->route('superadmin.users.index')->with('success', "Akun {$user->email} dihapus. Bisa dipulihkan dari daftar user.");
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->trashed(), 404);

        DB::transaction(function () use ($request, $user) {
            Group::onlyTrashed()->where('owner_id', $user->id)->where('deleted_at', $user->deleted_at)->restore();
            GroupMember::where('user_id', $user->id)->where('status', self::SUSPENDED)->update(['status' => 'active']);
            $user->restore();
            AuditLog::record('admin.restore_user', $request->user()->id, $user, ['email' => $user->email]);
        });

        return back()->with('success', "Akun {$user->email} dipulihkan.");
    }
}
