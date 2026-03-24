<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionSeatPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_add_seat_payment_history(): void
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Payment History Group',
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

        $this->actingAs($owner)
            ->post(route('subscription.add-seat.process', $group), [
                'seat_count' => 2,
                'payment_method' => 'dummy_va',
                'payment_confirmed' => '1',
            ])->assertRedirect();

        $this->actingAs($owner)
            ->get(route('subscription.add-seat.payments', $group))
            ->assertOk()
            ->assertSee('Riwayat Payment Seat')
            ->assertSee('ADD_SEAT_DUMMY');

        $this->assertDatabaseHas('subscription_payments', [
            'group_id' => $group->id,
            'seat_count' => 2,
            'unit_price' => 4000,
            'total_amount' => 8000,
            'status' => 'paid',
        ]);
    }

    public function test_non_owner_cannot_view_add_seat_payment_history(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $group = Group::query()->create([
            'name' => 'Payment History Group',
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

        $this->actingAs($otherUser)
            ->get(route('subscription.add-seat.payments', $group))
            ->assertForbidden();
    }
}
