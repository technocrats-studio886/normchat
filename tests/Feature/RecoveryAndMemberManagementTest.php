<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecoveryAndMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_restore_forbidden_for_member_without_recover_permission(): void
    {
        Storage::fake('normchat_backups');

        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Recovery Group',
            'description' => 'Test',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        Subscription::query()->create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 15000,
            'included_seats' => 2,
        ]);

        $adminRole = Role::query()->create(['key' => 'admin', 'name' => 'Admin']);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Message::query()->create([
            'group_id' => $group->id,
            'sender_type' => 'user',
            'sender_id' => $owner->id,
            'content' => 'current message',
        ]);

        $snapshot = [
            'group' => ['id' => $group->id, 'name' => $group->name],
            'members' => [],
            'messages' => [
                ['sender_type' => 'ai', 'sender_id' => null, 'content' => 'restored message'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $path = 'restore-test.json';
        Storage::disk('normchat_backups')->put($path, json_encode($snapshot));

        $backup = GroupBackup::query()->create([
            'group_id' => $group->id,
            'backup_type' => 'snapshot',
            'storage_path' => $path,
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('settings.backup.restore', [$group, $backup]), ['reason' => 'test'])
            ->assertForbidden();
    }

    public function test_owner_can_restore_backup_and_recovery_log_is_created(): void
    {
        Storage::fake('normchat_backups');

        $owner = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Recovery Group',
            'description' => 'Test',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        Subscription::query()->create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 15000,
            'included_seats' => 2,
        ]);

        Message::query()->create([
            'group_id' => $group->id,
            'sender_type' => 'user',
            'sender_id' => $owner->id,
            'content' => 'current message',
        ]);

        $snapshot = [
            'group' => ['id' => $group->id, 'name' => $group->name],
            'members' => [],
            'messages' => [
                ['sender_type' => 'ai', 'sender_id' => null, 'content' => 'restored message'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $path = 'restore-owner.json';
        Storage::disk('normchat_backups')->put($path, json_encode($snapshot));

        $backup = GroupBackup::query()->create([
            'group_id' => $group->id,
            'backup_type' => 'snapshot',
            'storage_path' => $path,
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('settings.backup.restore', [$group, $backup]), ['reason' => 'rollback'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'group_id' => $group->id,
            'content' => 'restored message',
            'sender_type' => 'ai',
        ]);

        $this->assertDatabaseHas('recovery_logs', [
            'group_id' => $group->id,
            'backup_id' => $backup->id,
            'restored_by' => $owner->id,
            'reason' => 'rollback',
        ]);
    }

    public function test_member_remove_requires_remove_member_permission(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Member Group',
            'description' => 'Test',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        $ownerRole = Role::query()->create(['key' => 'owner', 'name' => 'Owner']);
        $adminRole = Role::query()->create(['key' => 'admin', 'name' => 'Admin']);
        $memberRole = Role::query()->create(['key' => 'member', 'name' => 'Member']);

        $adminMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $memberMembership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('groups.members.remove', [$group, $memberMembership]))
            ->assertForbidden();

        $removePermission = Permission::query()->create(['key' => 'remove_member', 'name' => 'Remove Member']);
        $adminRole->permissions()->attach($removePermission->id);

        $this->actingAs($admin)
            ->post(route('groups.members.remove', [$group, $memberMembership]))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', [
            'id' => $memberMembership->id,
        ]);

        $this->assertDatabaseHas('group_members', [
            'id' => $adminMembership->id,
        ]);
    }
}
