<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeatPaymentAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_purchase_additional_seats_with_dummy_payment(): void
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Team A',
            'description' => 'Test group',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        $subscription = Subscription::query()->create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 15000,
            'included_seats' => 2,
        ]);

        $response = $this->actingAs($owner)->post(route('subscription.add-seat.process', $group), [
            'seat_count' => 3,
            'payment_method' => 'dummy_va',
            'payment_confirmed' => '1',
        ]);

        $response->assertRedirect(route('subscription.add-seat.success', $group));
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'included_seats' => 5,
        ]);

        $this->actingAs($owner)
            ->get(route('subscription.add-seat.success', $group))
            ->assertOk()
            ->assertSee('Dummy Payment Reference');
    }

    public function test_add_seat_requires_dummy_payment_confirmation(): void
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Team B',
            'description' => 'Test group',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        $subscription = Subscription::query()->create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => 15000,
            'included_seats' => 2,
        ]);

        $response = $this->from(route('subscription.add-seat', $group))
            ->actingAs($owner)
            ->post(route('subscription.add-seat.process', $group), [
                'seat_count' => 2,
                'payment_method' => 'dummy_va',
            ]);

        $response->assertRedirect(route('subscription.add-seat', $group));
        $response->assertSessionHasErrors('payment_confirmed');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'included_seats' => 2,
        ]);
    }

    public function test_admin_cannot_promote_member_even_if_has_add_member_permission(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Team C',
            'description' => 'Test group',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'approval_enabled' => false,
        ]);

        $ownerRole = Role::query()->create(['key' => 'owner', 'name' => 'Owner']);
        $adminRole = Role::query()->create(['key' => 'admin', 'name' => 'Admin']);
        $memberRole = Role::query()->create(['key' => 'member', 'name' => 'Member']);

        $permission = Permission::query()->create(['key' => 'add_member', 'name' => 'Add Member']);
        $adminRole->permissions()->attach($permission->id);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
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
            ->post(route('groups.members.promote', [$group, $memberMembership]), ['role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('groups.members.promote', [$group, $memberMembership]), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'id' => $memberMembership->id,
            'role_id' => $adminRole->id,
        ]);
    }
}
