<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_account_requires_authentication(): void
    {
        $this->get('/profile/account')->assertRedirectContains('/login');
    }

    public function test_user_can_update_display_name_only(): void
    {
        $user = User::factory()->create([
            'name' => 'Noel Lama',
            'email' => 'noel@example.com',
            'username' => 'username-lama',
        ]);

        $this->actingAs($user)
            ->post('/profile/account', [
                'name' => 'Noel Baru',
            ])
            ->assertRedirect(route('profile.account'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Noel Baru',
            'username' => 'username-lama',
        ]);
    }
}
