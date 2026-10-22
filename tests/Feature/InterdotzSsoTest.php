<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterdotzSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.interdotz.enabled', true);
        config()->set('services.interdotz.client_id', 'normchat-client');
        config()->set('services.interdotz.api_base', 'https://api-interdotz.test');
        config()->set('services.interdotz.sso_base', 'https://sso-interdotz.test');
    }

    public function test_login_redirects_to_interdotz_sso_with_client_id(): void
    {
        $this->get('/auth/interdotz/login')
            ->assertRedirectContains('https://sso-interdotz.test/login?client_id=normchat-client');
    }

    public function test_login_reports_missing_configuration(): void
    {
        config()->set('services.interdotz.client_id', '');

        $this->get('/auth/interdotz/login')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('auth');
    }

    public function test_callback_logs_in_user_when_interdotz_accepts_token(): void
    {
        Http::fake([
            'api-interdotz.test/api/profile' => Http::response([
                'payload' => ['id' => '01JINTERDOTZULID', 'name' => 'Noel', 'avatar_url' => null],
            ]),
        ]);

        $this->get('/sso/interdotz/callback?access_token=' . $this->jwt([
            'sub' => 'u-42',
            'email' => 'noel@example.com',
            'exp' => now()->addHour()->timestamp,
        ]))->assertRedirect(route('groups.index'));

        $user = User::where('provider_user_id', 'u-42')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Noel', $user->name);
        $this->assertSame('01JINTERDOTZULID', $user->interdotz_id);
    }

    public function test_forged_token_cannot_log_in_as_existing_user(): void
    {
        $superadmin = User::factory()->create(['email' => 'superadmin@interdotz.com']);
        Http::fake(['api-interdotz.test/api/profile' => Http::response(['message' => 'invalid token'], 401)]);

        $this->get('/sso/interdotz/callback?access_token=' . $this->jwt([
            'sub' => 'attacker',
            'email' => $superadmin->email,
            'exp' => now()->addHour()->timestamp,
        ]))->assertRedirect(route('login'))->assertSessionHasErrors('auth');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_expired_token_is_rejected_without_calling_interdotz(): void
    {
        Http::fake();

        $this->get('/sso/interdotz/callback?access_token=' . $this->jwt([
            'sub' => 'u-42',
            'exp' => now()->subMinute()->timestamp,
        ]))->assertRedirect(route('login'))->assertSessionHasErrors('auth');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    private function jwt(array $claims): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode(['alg' => 'HS256', 'typ' => 'JWT']) . '.' . $encode($claims) . '.signature';
    }
}
