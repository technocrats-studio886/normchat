<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterdotzApiCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.interdotz.enabled', true);
        config()->set('services.interdotz.client_id', 'test-client-id');
        config()->set('services.interdotz.client_secret', 'test-client-secret');
        config()->set('normchat.allow_interdotz_bearer_only', false);
        config()->set('app.url', 'https://normchat.twenti.studio');
        // Kosongkan agar URL logo diturunkan dari app.url, tidak dari .env lokal.
        config()->set('normchat.product_logo_url', null);
        config()->set('normchat.product_icon_url', null);
    }

    public function test_product_data_requires_interdotz_client_credentials(): void
    {
        $response = $this->getJson('/api/product/data?userId=u-1');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'unauthorized interdotz request');
    }

    public function test_product_data_returns_compatible_payload_and_logo_metadata(): void
    {
        User::factory()->create([
            'name' => 'Interdotz Tester',
            'email' => 'interdotz-tester@example.com',
            'auth_provider' => 'interdotz',
            'provider_user_id' => 'u-1',
            'interdotz_id' => 'id-1',
        ]);

        $response = $this
            ->withHeaders($this->clientHeaders())
            ->getJson('/api/product/data?userId=u-1');

        $response->assertOk()
            ->assertJsonPath('client_id', 'test-client-id')
            ->assertJsonPath('app.name', 'Normchat')
            ->assertJsonPath('app.logo_url', 'https://normchat.twenti.studio/normchat-logo.png')
            ->assertJsonPath('app.icon_url', 'https://normchat.twenti.studio/icons/icon-192.png')
            ->assertJsonPath('payload.client_id', 'test-client-id')
            ->assertJsonPath('payload.app.logo_url', 'https://normchat.twenti.studio/normchat-logo.png');
    }

    public function test_transactions_support_limit_alias_and_response_wrapper(): void
    {
        $user = User::factory()->create([
            'auth_provider' => 'interdotz',
            'provider_user_id' => 'u-2',
            'interdotz_id' => 'id-2',
        ]);

        $group = Group::create([
            'name' => 'Team Interdotz',
            'owner_id' => $user->id,
            'status' => 'active',
        ]);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'topup',
            'credit_amount' => 300,
            'price_paid' => 30000,
            'payment_reference' => 'topup_ref_1',
        ]);

        $response = $this
            ->withHeaders($this->clientHeaders())
            ->getJson('/api/transactions?userId=u-2&limit=5');

        $response->assertOk()
            ->assertJsonPath('current_page', 0)
            ->assertJsonPath('total_items', 1)
            ->assertJsonPath('transactions.0.reference_type', 'topup')
            ->assertJsonPath('transactions.0.referenceType', 'topup')
            ->assertJsonPath('transactions.0.amount', 30000)
            ->assertJsonPath('transactions.0.normkredits', 300)
            ->assertJsonPath('payload.totalItems', 1)
            ->assertJsonPath('payload.transactions.0.referenceId', 'topup_ref_1');
    }

    public function test_webhook_rejects_invalid_request_when_secret_is_configured(): void
    {
        config()->set('normchat.webhook_secret', 'secret-webhook');

        $response = $this->postJson('/api/webhooks/interdotz/payment', [
            'status' => 'settlement',
            'reference_id' => 'NCGRP-000000-TEST',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'invalid webhook signature');
    }

    public function test_webhook_accepts_secret_header_when_configured(): void
    {
        config()->set('normchat.webhook_secret', 'secret-webhook');

        $response = $this
            ->withHeaders([
                'X-Webhook-Secret' => 'secret-webhook',
            ])
            ->postJson('/api/webhooks/interdotz/payment', [
                'status' => 'settlement',
                'reference_id' => 'NCGRP-000000-TEST',
            ]);

        // Lolos verifikasi secret; order tidak ada sehingga 404, bukan 401.
        $response->assertNotFound()
            ->assertJsonPath('message', 'payment not found');
    }

    private function clientHeaders(): array
    {
        return [
            'X-Client-Id' => 'test-client-id',
            'X-Client-Secret' => 'test-client-secret',
        ];
    }
}
