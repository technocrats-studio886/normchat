<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        $groups = Group::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->with(['aiConnections', 'members'])
            ->withCount('members')
            ->latest()
            ->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $user->aiConnection || $user->aiConnection->decryptedAccessToken() === null) {
            return redirect()->route('login')
                ->withErrors(['llm' => 'You must connect an LLM before creating a group']);
        }

        // 4.2 & 4.5: Must have active subscription to create group
        $hasActive = Subscription::query()
            ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
            ->where('status', 'active')
            ->exists();

        if (! $hasActive && ! session('subscription_paid')) {
            return redirect()->route('subscription.pricing')
                ->with('info', 'Aktifkan paket dulu sebelum membuat grup.');
        }

        return view('groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'approval_enabled' => ['nullable'],
        ]);

        $user = Auth::user();

        if (! $user->aiConnection || $user->aiConnection->decryptedAccessToken() === null) {
            return redirect()->route('login')
                ->withErrors(['llm' => 'You must connect an LLM before creating a group']);
        }

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $user->id,
            'password_hash' => Hash::make($validated['password']),
            'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
        ]);

        $ownerRole = Role::firstWhere('key', 'owner');

        if ($ownerRole) {
            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $user->id],
                ['role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]
            );
        }

        // Create subscription for the new group
        Subscription::create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 15000,
            'included_seats' => 2,
        ]);

        session()->forget('subscription_paid');

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $user->id,
            'action' => 'group.create',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'created_at' => now(),
        ]);

        return redirect()->route('groups.index')->with('success', 'Group "' . $group->name . '" berhasil dibuat!');
    }

    // ── Join via share ID: show form ───────────────────────

    public function showJoin(string $shareId): View|RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();

        // If already a member, redirect to chat
        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing || (int) $group->owner_id === (int) Auth::id()) {
            return view('groups.join', [
                'group' => $group,
                'alreadyMember' => true,
            ]);
        }

        return view('groups.join', [
            'group' => $group,
            'alreadyMember' => false,
        ]);
    }

    // ── Join via share ID: verify password, auto member ──────

    public function joinViaShareId(Request $request, string $shareId): RedirectResponse
    {
        $group = Group::where('share_id', $shareId)->firstOrFail();

        // Owner → straight to chat
        if ((int) $group->owner_id === (int) Auth::id()) {
            return redirect()->route('chat.show', $group);
        }

        // Already active member → straight to chat
        $existing = GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return redirect()->route('chat.show', $group)
                ->with('info', 'Anda sudah menjadi member group ini.');
        }

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->input('password'), $group->password_hash)) {
            return back()->withErrors(['password' => 'Password grup salah.']);
        }

        // Auto-join as member
        $memberRole = Role::firstWhere('key', 'member')
            ?: Role::firstWhere('key', 'admin')
            ?: Role::firstWhere('key', 'owner');

        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => Auth::id()],
            ['role_id' => $memberRole?->id, 'status' => 'active', 'joined_at' => now()]
        );

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_joined',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'created_at' => now(),
        ]);

        return redirect()->route('chat.show', $group)
            ->with('success', 'Berhasil bergabung ke group "' . $group->name . '"!');
    }

    public function promoteMember(Request $request, Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('promoteMember', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat diubah rolenya dari menu ini.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $targetRole = Role::firstWhere('key', $validated['role']);
        if (! $targetRole) {
            return back()->withErrors(['member' => 'Role tujuan tidak ditemukan. Jalankan seeder roles terbaru.']);
        }

        $member->role_id = $targetRole->id;
        $member->save();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_role_changed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'metadata_json' => ['role' => $validated['role']],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Role member berhasil diperbarui.');
    }

    public function removeMember(Group $group, GroupMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        if ((int) $member->user_id === (int) $group->owner_id) {
            return back()->withErrors(['member' => 'Owner tidak dapat dihapus dari group.']);
        }

        $member->delete();

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'group.member_removed',
            'target_type' => GroupMember::class,
            'target_id' => $member->id,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Member berhasil dihapus dari group.');
    }
}
