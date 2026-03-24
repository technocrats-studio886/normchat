<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner Demo',
            'email' => 'owner@normchat.local',
            'auth_provider' => 'google',
            'provider_user_id' => 'owner-demo-1',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@normchat.local',
            'auth_provider' => 'google',
            'provider_user_id' => 'admin-demo-1',
        ]);

        $roles = [
            'owner' => Role::create(['key' => 'owner', 'name' => 'Owner', 'description' => 'Pemilik grup']),
            'admin' => Role::create(['key' => 'admin', 'name' => 'Admin', 'description' => 'Operator grup']),
            'member' => Role::create(['key' => 'member', 'name' => 'Member', 'description' => 'Anggota grup']),
            'ai' => Role::create(['key' => 'ai', 'name' => 'AI', 'description' => 'AI participant']),
        ];

        $permissionKeys = [
            'add_member',
            'remove_member',
            'add_ai',
            'change_password',
            'set_approval',
            'export_chat',
            'recover_history',
            'manage_billing',
            'pin_message',
            'delete_message',
            'view_audit_log',
        ];

        $permissionMap = [];

        foreach ($permissionKeys as $key) {
            $permission = Permission::create([
                'key' => $key,
                'name' => str($key)->replace('_', ' ')->title(),
                'description' => 'Permission '.$key,
            ]);

            $permissionMap[$key] = $permission->id;
            $roles['owner']->permissions()->syncWithoutDetaching($permission->id);
        }

        foreach (['add_member', 'remove_member', 'export_chat', 'pin_message', 'delete_message', 'view_audit_log'] as $adminKey) {
            if (isset($permissionMap[$adminKey])) {
                $roles['admin']->permissions()->syncWithoutDetaching($permissionMap[$adminKey]);
            }
        }

        foreach (['export_chat'] as $memberKey) {
            if (isset($permissionMap[$memberKey])) {
                $roles['member']->permissions()->syncWithoutDetaching($permissionMap[$memberKey]);
            }
        }

        $group = Group::create([
            'name' => 'Normchat Core Team',
            'description' => 'Ruang kolaborasi produk dan AI assistant.',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('norm1234'),
            'approval_enabled' => true,
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role_id' => $roles['owner']->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role_id' => $roles['admin']->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Subscription::create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-main',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 149000,
            'included_seats' => 2,
        ]);

        Message::insert([
            [
                'group_id' => $group->id,
                'sender_type' => 'user',
                'sender_id' => $owner->id,
                'content' => 'Selamat datang di Normchat. Kita mulai kickoff hari ini.',
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'group_id' => $group->id,
                'sender_type' => 'user',
                'sender_id' => $admin->id,
                'content' => 'Siap. @chatgpt tolong rangkum objective sprint ini.',
                'created_at' => now()->subMinutes(4),
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'group_id' => $group->id,
                'sender_type' => 'ai',
                'sender_id' => null,
                'content' => 'Ringkasan: fokus sprint adalah join flow aman, chat real-time stabil, dan export DOCX/PDF.',
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinutes(3),
            ],
        ]);

        User::factory(2)->create();
    }
}
