<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integrasi Interdotz dinonaktifkan tapi kodenya dipertahankan: pastikan route-nya
 * mati selama flag false.
 */
class InterdotzIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_interdotz_routes_are_disabled_by_default(): void
    {
        config()->set('services.interdotz.enabled', false);
        $this->get('/login')->assertOk()->assertDontSee('Masuk dengan Interdotz');

        $user = User::factory()->create();

        $this->get('/auth/interdotz/login')->assertNotFound();
        $this->get('/sso/interdotz/callback?access_token=x.y.z')->assertNotFound();
        $this->actingAs($user)->get('/mailbox')->assertNotFound();
        $this->postJson('/api/webhooks/interdotz/payment', ['reference_id' => 'x'])->assertNotFound();
        $this->getJson('/api/product/data?userId=1')->assertNotFound();
        $this->getJson('/api/transactions?userId=1')->assertNotFound();
    }
}
