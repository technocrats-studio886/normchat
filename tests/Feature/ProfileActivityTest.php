<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_activity_requires_authentication(): void
    {
        $this->get('/profile/activity')->assertRedirectContains('/login');
    }

    public function test_profile_activity_shows_login_history_and_user_payment_history(): void
    {
        $user = User::factory()->create([
            'name' => 'Noel',
            'email' => 'noel@example.com',
        ]);

        $group = Group::create([
            'name' => 'Team Alpha',
            'owner_id' => $user->id,
            'status' => 'active',
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'auth.connect',
            'target_type' => User::class,
            'target_id' => $user->id,
            'metadata_json' => [
                'ip' => '10.10.10.10',
                'user_agent' => 'Mozilla/5.0 (Test Device)',
            ],
            'created_at' => now(),
        ]);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'topup',
            'token_amount' => 30000,
            'price_paid' => 150,
            'payment_reference' => 'topup_ref_abc',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk()
            ->assertSee('Setting')
            ->assertSee('Login History')
            ->assertSee('IP 10.10.10.10')
            ->assertSee('Riwayat transaksi')
            ->assertSee('Top-up Normkredit')
            ->assertSee('150 DU');
    }

    public function test_profile_activity_route_redirects_to_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/activity')
            ->assertRedirect(route('profile.show') . '#riwayat-login');
    }
}
