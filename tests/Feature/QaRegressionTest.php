<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QaRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_blackbox_public_pages_are_accessible(): void
    {
        $this->get('/')->assertOk();
        $this->get('/pricing')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_blackbox_guest_is_redirected_from_protected_page(): void
    {
        $this->get('/groups')->assertRedirectContains('/login');
    }

    public function test_blackbox_legacy_checkout_routes_redirect_to_new_payment_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/checkout')
            ->assertRedirect('/payment/detail');

        $this->actingAs($user)
            ->get('/checkout/success')
            ->assertRedirect('/payment/success');
    }

    public function test_whitebox_login_uses_provider_logo_assets_in_expected_order(): void
    {
        $this->get('/login')->assertSeeInOrder([
            'Connect ChatGPT',
            'Connect Claude',
            'Connect Gemini',
        ], false);
    }

}