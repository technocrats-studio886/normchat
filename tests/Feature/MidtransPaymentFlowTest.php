<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SNAP_URL = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/tok-1';

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', []);
        config()->set('services.midtrans.server_key', self::SERVER_KEY);
        config()->set('services.midtrans.is_production', false);
    }

    public function test_group_creation_starts_qris_checkout_for_250_credits_plus_fee(): void
    {
        $this->fakeSnap();

        $this->actingAs(User::factory()->create())
            ->post('/groups', ['name' => 'Kelas BD', 'password' => '1234'])
            ->assertRedirect(self::SNAP_URL);

        $pending = PendingPayment::firstOrFail();
        Http::assertSent(fn ($request) => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode(self::SERVER_KEY . ':'))
            && $request['transaction_details'] === ['order_id' => $pending->order_id, 'gross_amount' => 30210]
            && $request['enabled_payments'] === ['other_qris']
            // Biaya layanan QRIS baru muncul di Midtrans sebagai baris sendiri; jumlah item = gross_amount.
            && count($request['item_details']) === 2
            && $request['item_details'][0]['price'] === 30000
            && str_contains($request['item_details'][0]['name'], '250 Normkredit')
            && $request['item_details'][1]['price'] === 210
            && $request['item_details'][1]['name'] === 'Biaya layanan QRIS 0,7%'
            && str_contains($request['callbacks']['finish'], '/groups/payment-callback?order=' . $pending->order_id));

        $this->assertSame('pending_payment', Group::firstOrFail()->status);
        $this->assertSame('group_create_midtrans', $pending->payment_type);
        $this->assertStringStartsWith('NCGRP-', $pending->order_id);
        $this->assertSame(30210, (int) $pending->expected_amount);
        $this->assertSame(250, $pending->metadata_json['credits']);
        $this->assertSame(30000, $pending->metadata_json['base_amount']);
        $this->assertSame(210, $pending->metadata_json['service_fee']);
        $this->assertSame('midtrans', $pending->metadata_json['gateway']);
        $this->assertSame('tok-1', $pending->metadata_json['payment_id']);
    }

    public function test_rejected_snap_request_removes_pending_group(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['error_messages' => ['invalid']], 400)]);

        $this->actingAs(User::factory()->create())
            ->from('/groups/create')
            ->post('/groups', ['name' => 'Kelas BD', 'password' => '1234'])
            ->assertRedirect('/groups/create')
            ->assertSessionHasErrors(['payment' => 'Pembayaran sedang bermasalah. Coba lagi sebentar lagi.']);

        $this->assertSame(0, Group::withTrashed()->count());
        $this->assertSame(0, PendingPayment::count());
    }

    public function test_checkout_requires_server_key(): void
    {
        config()->set('services.midtrans.server_key', '');
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post('/groups', ['name' => 'Kelas BD', 'password' => '1234'])
            ->assertSessionHasErrors(['payment' => 'Pembayaran belum tersedia. Coba lagi nanti.']);

        Http::assertNothingSent();
    }

    public function test_join_and_topup_are_sold_in_125_credit_packages(): void
    {
        $this->fakeSnap();
        $owner = User::factory()->create();
        $group = $this->activeGroup($owner);

        $this->actingAs(User::factory()->create())
            ->post("/join/{$group->share_id}", ['password' => '1234', 'packages' => 1])
            ->assertRedirect(self::SNAP_URL);
        $this->assertDatabaseHas('pending_payments', ['payment_type' => 'group_join_midtrans', 'expected_amount' => 15105]);

        $this->actingAs($owner)->get("/tokens/buy?group={$group->id}")->assertOk();
        $this->actingAs($owner)
            ->post('/tokens/buy', ['group_id' => $group->id, 'packages' => 2])
            ->assertRedirect(self::SNAP_URL);
        $this->assertDatabaseHas('pending_payments', ['payment_type' => 'topup_midtrans', 'expected_amount' => 30210]);
        $this->assertSame(250, PendingPayment::where('payment_type', 'topup_midtrans')->first()->metadata_json['credits']);
    }

    public function test_callback_syncs_status_api_and_activates_group_once(): void
    {
        $user = User::factory()->create();
        [$group, $pending] = $this->pendingGroupCreation($user, 300);
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response($this->notification($pending, 'settlement'))]);

        $this->actingAs($user)
            ->get("/groups/payment-callback?order={$pending->order_id}")
            ->assertRedirect(route('chat.show', $group));

        Http::assertSent(fn ($request) => $request->url() === "https://api.sandbox.midtrans.com/v2/{$pending->order_id}/status");
        $this->assertSame('active', $group->fresh()->status);
        $this->assertSame(300.0, $group->fresh()->groupToken->credits);

        $this->actingAs($user)->get("/groups/payment-callback?order={$pending->order_id}");
        $this->assertSame(300.0, $group->fresh()->groupToken->credits);
    }

    public function test_callback_with_expired_status_deletes_pending_group(): void
    {
        $user = User::factory()->create();
        [$group, $pending] = $this->pendingGroupCreation($user, 300);
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response($this->notification($pending, 'expire'))]);

        $this->actingAs($user)
            ->get("/groups/payment-callback?order={$pending->order_id}")
            ->assertRedirect(route('groups.index'));

        $this->assertSame('failed', $pending->fresh()->status);
        $this->assertNull(Group::withTrashed()->find($group->id));
    }

    public function test_callback_ignores_orders_of_other_users(): void
    {
        [, $pending] = $this->pendingGroupCreation(User::factory()->create(), 300);

        $this->actingAs(User::factory()->create())
            ->get("/groups/payment-callback?order={$pending->order_id}")
            ->assertRedirect(route('groups.index'))
            ->assertSessionHas('info', 'Pembayaran tidak ditemukan.');
    }

    public function test_paid_topup_callback_shows_success_page(): void
    {
        $user = User::factory()->create();
        $group = $this->activeGroup($user);
        $pending = PendingPayment::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'order_id' => 'NCTOPUP-1',
            'payment_type' => 'topup_midtrans',
            'expected_amount' => 12000,
            'status' => 'paid',
            'metadata_json' => ['action' => 'topup_midtrans', 'group_name' => $group->name, 'credits' => 120],
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->get("/groups/payment-callback?order={$pending->order_id}")
            ->assertRedirect(route('subscription.tokens.buy.success'))
            ->assertSessionHas('token_purchase.normkredits', 120);
    }

    public function test_signed_notification_settles_order(): void
    {
        [$group, $pending] = $this->pendingGroupCreation(User::factory()->create(), 400);

        $this->postJson('/api/webhooks/midtrans', $this->notification($pending, 'settlement'))
            ->assertOk()->assertJsonPath('status', 'paid');

        $this->assertSame(400.0, $group->fresh()->groupToken->credits);
        $this->assertSame('paid', $pending->fresh()->status);
    }

    public function test_underpaid_notification_does_not_grant_credits(): void
    {
        [$group, $pending] = $this->pendingGroupCreation(User::factory()->create(), 250);
        $pending->update(['expected_amount' => 30210]);
        // Ditandatangani benar, tapi gross_amount hanya harga dasar tanpa biaya layanan.
        $payload = $this->notification($pending, 'settlement', null, 30000);

        $this->postJson('/api/webhooks/midtrans', $payload)->assertOk();

        $this->assertSame('failed', $pending->fresh()->status);
        $this->assertSame('amount_mismatch', $pending->fresh()->metadata_json['gateway_status']);
        $this->assertNull($group->fresh()?->groupToken);
    }

    public function test_notification_with_wrong_signature_is_rejected(): void
    {
        [$group, $pending] = $this->pendingGroupCreation(User::factory()->create(), 400);
        $payload = $this->notification($pending, 'settlement');
        $payload['signature_key'] = hash('sha512', 'forged');

        $this->postJson('/api/webhooks/midtrans', $payload)->assertForbidden();

        $this->assertSame('pending_payment', $group->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_challenged_capture_stays_pending_and_deny_fails(): void
    {
        [, $pending] = $this->pendingGroupCreation(User::factory()->create(), 300);

        $this->postJson('/api/webhooks/midtrans', $this->notification($pending, 'capture', 'challenge'))
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame('pending', $pending->fresh()->status);

        $this->postJson('/api/webhooks/midtrans', $this->notification($pending, 'deny'))
            ->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame('failed', $pending->fresh()->status);
    }

    private function fakeSnap(): void
    {
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'tok-1',
            'redirect_url' => self::SNAP_URL,
        ], 201)]);
    }

    private function notification(PendingPayment $pending, string $status, ?string $fraud = null, ?int $amount = null): array
    {
        $grossAmount = number_format($amount ?? (int) $pending->expected_amount, 2, '.', '');
        $statusCode = in_array($status, ['settlement', 'capture'], true) ? '200' : '202';

        return array_filter([
            'order_id' => $pending->order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'fraud_status' => $fraud,
            'transaction_id' => 'mt-' . $pending->id,
            'payment_type' => 'qris',
            'settlement_time' => $status === 'settlement' ? now()->toDateTimeString() : null,
            'signature_key' => hash('sha512', $pending->order_id . $statusCode . $grossAmount . self::SERVER_KEY),
        ], fn ($value) => $value !== null);
    }

    private function activeGroup(User $owner): Group
    {
        $group = Group::create([
            'name' => 'Kelas Basis Data',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        GroupToken::forGroup($group->id);

        return $group;
    }

    /** @return array{0: Group, 1: PendingPayment} */
    private function pendingGroupCreation(User $user, int $credits): array
    {
        $group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $user->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'pending_payment',
        ]);

        $pending = PendingPayment::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'order_id' => 'NCGRP-' . uniqid(),
            'payment_type' => 'group_create_midtrans',
            'expected_amount' => app(NormkreditPricing::class)->quote($credits)->total(),
            'status' => 'pending',
            'metadata_json' => ['action' => 'group_create_midtrans', 'credits' => $credits, 'payment_id' => 'tok-1'],
            'expires_at' => now()->addHour(),
        ]);

        return [$group, $pending];
    }
}
