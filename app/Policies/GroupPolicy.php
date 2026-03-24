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

    public function manageSettings(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->hasPermission($user, $group, 'manage_billing');
    }

    public function exportChat(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->hasPermission($user, $group, 'export_chat');
    }

    public function createBackup(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->hasPermission($user, $group, 'recover_history');
    }

    public function restoreBackup(User $user, Group $group): bool
    {
        return $this->createBackup($user, $group);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->hasPermission($user, $group, 'remove_member');
    }

    public function promoteMember(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group);
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

    private function hasPermission(User $user, Group $group, string $permissionKey): bool
    {
        $membership = GroupMember::query()
            ->with('role.permissions')
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership || ! $membership->role) {
            return false;
        }

        return $membership->role->permissions->contains('key', $permissionKey);
    }
}