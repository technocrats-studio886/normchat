<?php

namespace App\Http\Controllers;

use App\Enums\PaymentPurpose;
use App\Events\GroupMembershipChanged;
use App\Exceptions\CheckoutFailedException;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Services\GroupMembership;
use App\Services\NormkreditPricing;
use App\Services\Payments\NormkreditCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly GroupMembership $membership,
        private readonly NormkreditCheckout $checkout,
    ) {
    }

    public function index(Request $request): View
    {
        $groups = Group::query()
            ->visibleTo($request->user())
            ->with(['members', 'groupToken'])
            ->withCount('members')
            ->latest()
            ->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('groups.create', ['quote' => $this->pricing->quoteGroupCreation()]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $user = $request->user();
        $quote = $this->pricing->quoteGroupCreation();

        $group = Group::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'owner_id' => $user->id,
            'password_hash' => Hash::make($request->validated('password')),
            'ai_provider' => 'openai',
            'status' => 'pending_payment',
        ]);

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupCreate, $quote));
        } catch (CheckoutFailedException $e) {
            $group->forceDelete();

            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }
    }

    // ── Join via share ID (patungan) ─────────────────────────

    public function showJoin(Request $request, string $shareId): View
    {
        $group = Group::where('share_id', $shareId)->with('groupToken')->firstOrFail();
        $user = $request->user();

        if ($group->isOwnedBy($user) || $group->hasActiveMember($user)) {
            return view('groups.join', ['group' => $group, 'alreadyMember' => true]);
        }

        return view('groups.join', [
            'group' => $group,
            'alreadyMember' => false,
        ]);
    }

    public function joinViaShareId(JoinGroupRequest $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();
        $user = $request->user();

        if ($group->isOwnedBy($user)) {
            return redirect()->route('chat.show', $group);
        }

        if ($group->hasActiveMember($user)) {
            return redirect()->route('chat.show', $group)->with('info', 'Kamu sudah bergabung di grup ini.');
        }

        if (! Hash::check($request->validated('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Kata sandi salah.'])->withInput();
        }

        $quote = $this->pricing->quotePackages($request->packages());

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupJoin, $quote, [
                'share_id' => $shareId,
            ]));
        } catch (CheckoutFailedException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    // ── Member management ────────────────────────────────────

    public function promoteMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('promoteMember', $group);
        abort_unless((int) $member->group_id === (int) $group->id, 404);

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Peran pemilik grup tidak bisa diubah.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak bisa mengubah peranmu sendiri.']);
        }

        $roleKey = $request->validate(['role' => ['required', 'in:' . Role::ADMIN . ',' . Role::MEMBER]])['role'];
        $member->update(['role_id' => Role::forKey($roleKey)->id]);

        AuditLog::record('group.member_role_changed', $request->user()->id, $member, ['role' => $roleKey], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'role_changed', $roleKey));

        return back()->with('success', 'Peran anggota diperbarui.');
    }

    public function removeMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);
        abort_unless((int) $member->group_id === (int) $group->id, 404);

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Pemilik grup tidak bisa dikeluarkan.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak bisa mengeluarkan dirimu sendiri.']);
        }

        $member->delete();

        AuditLog::record('group.member_removed', $request->user()->id, $member, [], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'removed', null));

        return back()->with('success', 'Anggota dikeluarkan dari grup.');
    }

    /** Anggota/admin keluar sendiri. Patungannya tetap jadi saldo grup; pesannya tetap ada. */
    public function leave(Request $request, Group $group): RedirectResponse
    {
        $user = $request->user();
        if ($group->isOwnedBy($user)) {
            return back()->withErrors(['member' => 'Pemilik grup tidak bisa keluar. Hapus grup bila sudah tidak dipakai.']);
        }

        $member = GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->first();
        abort_unless($member !== null, 404);

        $member->delete();

        AuditLog::record('group.member_left', $user->id, $member, [], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $user->id, 'left', null));

        return redirect()->route('groups.index')->with('success', 'Kamu keluar dari grup ' . $group->name . '.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);

        AuditLog::record('group.deleted', $request->user()->id, $group, ['name' => (string) $group->name]);
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grup berhasil dihapus.');
    }
}
