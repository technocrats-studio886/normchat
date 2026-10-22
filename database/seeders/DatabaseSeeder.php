<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
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

        $this->call(RolePermissionSeeder::class);

        $roles = [
            'owner' => Role::forKey(Role::OWNER),
            'admin' => Role::forKey(Role::ADMIN),
        ];

        $group = Group::create([
            'name' => 'Normi Chat Core Team',
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
                'content' => 'Selamat datang di Normi Chat. Kita mulai kickoff hari ini.',
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
