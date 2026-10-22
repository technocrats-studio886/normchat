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
use App\Services\Credits\Funding;
use App\Services\GroupMembership;
use App\Services\NormkreditPricing;
use App\Services\Payments\NormkreditCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $isSuperadmin = $user->isSuperadmin();

        $group = Group::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'owner_id' => $user->id,
            'password_hash' => Hash::make($request->validated('password')),
            'approval_enabled' => $request->boolean('approval_enabled'),
            'ai_provider' => 'openai',
            'status' => $isSuperadmin ? 'active' : 'pending_payment',
        ]);

        if ($isSuperadmin) {
            $this->membership->activateGroup($group, $user, Funding::bypass($quote->credits, $request->sharePercent(), $this->bypassReference('GROUP')));

            return redirect()->route('chat.show', $group)
                ->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
        }

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupCreate, $quote, [
                'share_percent' => $request->sharePercent(),
            ]));
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
            return redirect()->route('chat.show', $group)->with('info', 'Anda sudah menjadi member group ini.');
        }

        if (! Hash::check($request->validated('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.'])->withInput();
        }

        $quote = $this->pricing->quotePackages($request->packages());

        if ($user->isSuperadmin()) {
            $this->membership->activateJoin($group, $user, Funding::bypass($quote->credits, $request->sharePercent(), $this->bypassReference('JOIN')));

            return redirect()->route('chat.show', $group)
                ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"!');
        }

        try {
            return redirect()->away($this->checkout->start($user, $group, PaymentPurpose::GroupJoin, $quote, [
                'share_id' => $shareId,
                'share_percent' => $request->sharePercent(),
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
            return back()->withErrors(['member' => 'Owner tidak dapat diubah rolenya dari menu ini.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak dapat mengubah role akun sendiri dari menu ini.']);
        }

        $roleKey = $request->validate(['role' => ['required', 'in:' . Role::ADMIN . ',' . Role::MEMBER]])['role'];
        $member->update(['role_id' => Role::forKey($roleKey)->id]);

        AuditLog::record('group.member_role_changed', $request->user()->id, $member, ['role' => $roleKey], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'role_changed', $roleKey));

        return back()->with('success', 'Role member berhasil diperbarui.');
    }

    public function removeMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);
        abort_unless((int) $member->group_id === (int) $group->id, 404);

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat dihapus dari group.']);
        }

        if ((int) $member->user_id === (int) $request->user()->id) {
            return back()->withErrors(['member' => 'Kamu tidak dapat menghapus akun sendiri dari group.']);
        }

        $member->delete();

        AuditLog::record('group.member_removed', $request->user()->id, $member, [], $group->id);
        event(new GroupMembershipChanged((int) $group->id, (int) $member->user_id, 'removed', null));

        return back()->with('success', 'Member berhasil dihapus dari group.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->isOwnedBy($request->user()), 403);

        AuditLog::record('group.deleted', $request->user()->id, $group, ['name' => (string) $group->name]);
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grup berhasil dihapus.');
    }

    private function bypassReference(string $kind): string
    {
        return 'SA-' . $kind . '-' . strtoupper(Str::random(8));
    }
}
