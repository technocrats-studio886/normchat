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

    public function test_profile_pages_show_login_history_and_user_payment_history(): void
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
            'credit_amount' => 300,
            'price_paid' => 30000,
            'payment_reference' => 'topup_ref_abc',
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Riwayat Masuk')
            ->assertSee('Riwayat Pembayaran');

        $this->actingAs($user)->get('/profile/login-history')
            ->assertOk()
            // Detail teknis (IP, user agent) tidak ditampilkan; cukup perangkat dan status.
            ->assertSee('Masuk')->assertDontSee('10.10.10.10')->assertDontSee('Mozilla/5.0');

        $this->actingAs($user)->get('/profile/transactions')
            ->assertOk()
            ->assertSee('Isi Normkredit')
            ->assertSee('Rp30.000');
    }

    public function test_profile_activity_route_redirects_to_login_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/activity')
            ->assertRedirect(route('profile.login-history'));
    }
}
