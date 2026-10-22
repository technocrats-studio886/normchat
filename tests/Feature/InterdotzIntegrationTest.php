<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\PendingPayment;
use App\Models\User;
use App\Services\InterdotzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Integrasi Interdotz dinonaktifkan tapi kodenya dipertahankan: pastikan route-nya
 * mati selama flag false, dan gateway Interdotz tetap berfungsi bila dinyalakan.
 */
class InterdotzIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_interdotz_routes_are_disabled_by_default(): void
    {
        config()->set('services.interdotz.enabled', false);
        $this->get('/login')->assertOk()->assertDontSee('Login dengan Interdotz');

        $user = User::factory()->create();

        $this->get('/auth/interdotz/login')->assertNotFound();
        $this->get('/sso/interdotz/callback?access_token=x.y.z')->assertNotFound();
        $this->actingAs($user)->get('/mailbox')->assertNotFound();
        $this->postJson('/api/webhooks/interdotz/payment', ['reference_id' => 'x'])->assertNotFound();
        $this->getJson('/api/product/data?userId=1')->assertNotFound();
        $this->getJson('/api/transactions?userId=1')->assertNotFound();
    }

    public function test_interdotz_gateway_still_works_when_enabled(): void
    {
        config()->set('services.interdotz.enabled', true);
        config()->set('normchat.payment_gateway', 'interdotz');
        config()->set('normchat.superadmin_emails', []);

        $this->mock(InterdotzService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createPayment')
                ->once()
                ->withArgs(fn ($token, $orderId, $amount) => $token === 'sso-token' && $amount === 30210)
                ->andReturn(['payload' => ['id' => 'pay-1', 'redirect_url' => 'https://interdotz.test/pay']]);
            $mock->shouldReceive('getPaymentStatus')->andReturn(['payload' => ['status' => 'settlement', 'amount' => 30210]]);
        });

        $user = User::factory()->create(['interdotz_id' => 'idz-1']);
        $user->storeOAuthTokens('sso-token', null, now()->addHour());

        $this->actingAs($user)
            ->post('/groups', ['name' => 'Kelas BD', 'password' => '1234'])
            ->assertRedirect('https://interdotz.test/pay');

        $pending = PendingPayment::firstOrFail();
        $this->assertSame('interdotz', $pending->metadata_json['gateway']);

        $this->actingAs($user)->get("/groups/payment-callback?order={$pending->order_id}");
        $this->assertSame('active', Group::firstOrFail()->status);
    }

    public function test_interdotz_gateway_requires_interdotz_session(): void
    {
        config()->set('services.interdotz.enabled', true);
        config()->set('normchat.payment_gateway', 'interdotz');
        $this->mock(InterdotzService::class, fn (MockInterface $mock) => $mock->shouldReceive('isConfigured')->andReturn(true));

        $this->actingAs(User::factory()->create())
            ->post('/groups', ['name' => 'Kelas BD', 'password' => '1234'])
            ->assertSessionHasErrors(['payment' => 'Sesi login berakhir. Silakan logout dan login kembali.']);
    }
}
