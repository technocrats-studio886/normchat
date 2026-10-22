<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    }

    public function test_login_page_offers_google(): void
    {
        $this->get('/login')->assertOk()->assertSee('Login dengan Google')->assertSee(route('auth.google.redirect'), false);
    }

    public function test_redirect_goes_to_google_consent_screen(): void
    {
        $this->get('/auth/google/redirect')->assertRedirectContains('accounts.google.com');
    }

    public function test_missing_configuration_is_reported(): void
    {
        config()->set('services.google.client_id', '');

        $this->get('/auth/google/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['auth' => 'Login Google belum dikonfigurasi.']);
    }

    public function test_callback_creates_user_and_logs_in(): void
    {
        $this->fakeGoogleUser('g-123', 'noel@student.itk.ac.id', true);

        $this->get('/auth/google/callback')->assertRedirect(route('groups.index'));

        $user = User::where('provider_user_id', 'g-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->auth_provider);
        $this->assertSame('Noel', $user->name);
        $this->assertSame('noel', $user->username);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.connect', 'actor_id' => $user->id]);
    }

    public function test_callback_links_existing_account_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'noel@student.itk.ac.id']);
        $this->fakeGoogleUser('g-123', 'noel@student.itk.ac.id', true);

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::count());
        $this->assertSame('g-123', $existing->fresh()->provider_user_id);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'victim@example.com']);
        $this->fakeGoogleUser('g-999', 'victim@example.com', false);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['auth' => 'Email akun Google belum terverifikasi.']);

        $this->assertGuest();
    }

    public function test_cancelled_consent_returns_to_login(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andThrow(new \Laravel\Socialite\Two\InvalidStateException());
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('auth');
        $this->assertGuest();
    }

    private function fakeGoogleUser(string $id, string $email, bool $verified): void
    {
        $googleUser = (new SocialiteUser())
            ->setRaw(['email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Noel', 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/a/x']);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
