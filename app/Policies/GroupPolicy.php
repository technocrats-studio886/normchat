<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->isActiveMember($user, $group);
    }

    public function chat(User $user, Group $group): bool
    {
        return $this->view($user, $group);
    }

    /**
     * Membuka halaman settings (read-only untuk semua active member, edit terbatas per role).
     */
    public function viewSettings(User $user, Group $group): bool
    {
        return $this->view($user, $group);
    }

    /**
     * Edit profil grup, password, approval.
     */
    public function editGroupProfile(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'manage_billing')
            || $this->hasPermission($user, $group, 'change_password');
    }

    public function manageBilling(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'manage_billing');
    }

    public function exportChat(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'export_chat');
    }

    public function createBackup(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'recover_history');
    }

    public function restoreBackup(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'recover_history');
    }

    /**
     * AI persona — owner OR admin.
     */
    public function manageAiPersona(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group)
            || $this->hasRoleKey($user, $group, 'admin')
            || $this->hasPermission($user, $group, 'add_ai');
    }

    /**
     * Invite & accept member — owner OR admin.
     */
    public function manageMembers(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    /**
     * Promote/demote member role — owner only.
     */
    public function promoteMember(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
    }

    /**
     * Backwards-compat alias dipakai SettingsController lama.
     */
    public function manageSettings(User $user, Group $group): bool
    {
        return $this->editGroupProfile($user, $group);
    }

    private function isOwner(User $user, Group $group): bool
    {
        return (int) $group->owner_id === (int) $user->id;
    }

    private function isActiveMember(User $user, Group $group): bool
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function hasRoleKey(User $user, Group $group, string $roleKey): bool
    {
        return GroupMember::query()
            ->with('role')
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->contains(fn ($m) => $m->role && $m->role->key === $roleKey);
    }

    private function hasPermission(User $user, Group $group, string $permissionKey): bool
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($query) => $query->where('key', $permissionKey))
            ->exists();
    }
}
