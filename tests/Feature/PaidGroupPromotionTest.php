<?php

namespace Tests\Feature;

use App\Enums\PaymentPurpose;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\User;
use App\Services\GroupPromotion;
use App\Services\NormkreditPricing;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaidGroupPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('services.midtrans.server_key', 'offline');
        config()->set('services.midtrans.is_production', false);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'test', 'redirect_url' => 'https://app.sandbox.midtrans.com/pay'], 201)]);
    }

    private function promo(array $values = []): void
    {
        app(NormkreditPricing::class)->update($values + [
            'group_promo_enabled' => true, 'group_promo_type' => 'fixed', 'group_promo_value' => 50,
            'group_promo_starts_at' => now()->subDay()->toIso8601String(),
            'group_promo_ends_at' => now()->addDay()->toIso8601String(),
        ]);
    }

    private function checkout(User $owner): PendingPayment
    {
        $this->actingAs($owner)->post('/groups', ['name' => 'Grup dibayar', 'password' => 'abcd'])->assertRedirect('https://app.sandbox.midtrans.com/pay');

        return PendingPayment::latest('id')->firstOrFail();
    }

    public function test_login_and_group_list_never_create_free_groups_even_with_legacy_override(): void
    {
        app(NormkreditPricing::class)->update(['free_groups_enabled' => true]);
        $user = User::factory()->create();
        Auth::login($user);
        $this->get('/groups')->assertOk();
        $this->get('/groups/create')->assertSee('Saldo awal setelah pembayaran')->assertDontSee('Buat grup gratis');
        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('group_token_contributions', 0);
    }

    public function test_every_paid_group_gets_the_locked_bonus_only_after_payment_and_once(): void
    {
        $this->promo();
        $user = User::factory()->create();
        $first = $this->checkout($user);
        $group = $first->group;
        $this->assertSame('pending_payment', $group->status);
        $this->assertSame(30210, $first->expected_amount);
        $this->assertSame(50.0, (float) $first->metadata_json['bonus_credits']);
        $this->assertDatabaseCount('group_token_contributions', 0);
        // Sandi dan kode tidak cukup untuk membuka grup yang belum dibayar.
        $friend = User::factory()->create();
        $this->actingAs($friend)->get('/join/' . $group->share_id)->assertNotFound();

        $this->promo(['group_promo_enabled' => false, 'group_promo_value' => 999]);
        $fulfillment = app(PaymentFulfillment::class);
        $fulfillment->fulfill($first, 30210, 'paid-first', 'qris', now());
        $fulfillment->fulfill($first->fresh(), 30210, 'paid-first', 'qris', now());
        $this->assertSame(300.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->assertSame(2, GroupTokenContribution::where('group_id', $group->id)->count());
        $this->assertSame(2, $group->fresh()->free_member_slots);
        $this->assertFalse($group->fresh()->isFree());
        $this->assertSame(0, (int) GroupTokenContribution::where('source', GroupTokenContribution::SOURCE_BONUS)->sole()->price_paid);

        $this->promo();
        $second = $this->checkout($user);
        $fulfillment->fulfill($second, 30210, 'paid-second', 'qris', now());
        $this->assertSame(300.0, (float) $second->group->groupToken()->value('remaining_credits'));
    }

    public function test_underpayment_and_failed_checkout_grant_neither_base_credits_nor_bonus(): void
    {
        $this->promo();
        $pending = $this->checkout(User::factory()->create());
        app(PaymentFulfillment::class)->fulfill($pending, 30000, 'underpaid', 'qris', now());
        $this->assertSame('failed', $pending->fresh()->status);
        $this->assertDatabaseCount('group_token_contributions', 0);
    }

    public function test_paid_group_has_two_free_friends_and_no_bonus_for_topups(): void
    {
        $this->promo();
        $owner = User::factory()->create();
        $pending = $this->checkout($owner);
        app(PaymentFulfillment::class)->fulfill($pending, 30210, 'paid-friends', 'qris', now());
        $group = $pending->group->fresh();
        foreach (range(1, 2) as $index) {
            $friend = User::factory()->create();
            $this->actingAs($friend)->post('/join/' . $group->share_id . '/sandi', ['password' => 'abcd']);
            $this->post('/join/' . $group->share_id)->assertRedirect(route('chat.show', $group));
        }
        $this->assertSame(0, $group->fresh()->freeSlotsLeft());
        $this->post('/groups/' . $group->id . '/leave')->assertRedirect('/groups');
        $this->assertSame(0, $group->fresh()->freeSlotsLeft());
        app(\App\Services\Payments\NormkreditCheckout::class)->start($owner, $group, PaymentPurpose::Topup, app(NormkreditPricing::class)->quotePackages(1));
        $topup = PendingPayment::latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('bonus_credits', $topup->metadata_json);
        app(PaymentFulfillment::class)->fulfill($topup, 15105, 'paid-topup', 'qris', now());
        $this->assertSame(425.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->assertSame(1, GroupTokenContribution::where('source', GroupTokenContribution::SOURCE_BONUS)->count());
    }

    public function test_percent_schedule_boundaries_and_disabled_promotions(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->promo(['group_promo_type' => 'percent', 'group_promo_value' => 20,
            'group_promo_starts_at' => now()->toIso8601String(), 'group_promo_ends_at' => now()->addHour()->toIso8601String()]);
        $this->assertSame(50.0, app(GroupPromotion::class)->offer()['bonus_credits']);
        $this->travel(1)->hours();
        $this->assertSame(0.0, app(GroupPromotion::class)->offer()['bonus_credits']);
        $this->promo(['group_promo_starts_at' => now()->addDay()->toIso8601String()]);
        $this->assertFalse(app(GroupPromotion::class)->offer()['active']);
        $this->promo(['group_promo_enabled' => false]);
        $this->assertFalse(app(GroupPromotion::class)->offer()['active']);
    }

    public function test_two_friends_join_paid_group_free_then_next_member_pays_and_slots_do_not_return(): void
    {
        $owner = User::factory()->create();
        $pending = $this->checkout($owner);
        app(PaymentFulfillment::class)->fulfill($pending, 30210, 'paid', 'qris', now());
        $group = $pending->group;
        for ($i = 0; $i < 2; $i++) {
            $friend = User::factory()->create();
            $this->actingAs($friend)->post('/join/' . $group->share_id . '/sandi', ['password' => 'abcd']);
            $this->post('/join/' . $group->share_id)->assertRedirect(route('chat.show', $group));
        }
        $this->assertSame(0, $group->fresh()->freeSlotsLeft());
        $this->post('/groups/' . $group->id . '/leave')->assertRedirect(route('groups.index'));
        $this->assertSame(0, $group->fresh()->freeSlotsLeft());
        $next = User::factory()->create();
        $this->actingAs($next)->post('/join/' . $group->share_id . '/sandi', ['password' => 'abcd']);
        $this->get('/join/' . $group->share_id . '/lanjut')->assertSee('Bayar');
        $this->post('/join/' . $group->share_id, ['packages' => 1])->assertRedirect('https://app.sandbox.midtrans.com/pay');
        $this->assertSame(PaymentPurpose::GroupJoin->value, PendingPayment::latest('id')->first()->payment_type);
        $this->assertArrayNotHasKey('bonus_credits', PendingPayment::latest('id')->first()->metadata_json);
    }

    public function test_superadmin_sets_promo_in_wita_and_public_copy_matches(): void
    {
        $this->travelTo(now()->startOfMinute());
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $data = ['group_promo_enabled' => 1, 'group_promo_type' => 'fixed', 'group_promo_value' => 50,
            'group_promo_starts_at' => now()->subHour()->setTimezone(GroupPromotion::TIMEZONE)->format('Y-m-d\TH:i'),
            'group_promo_ends_at' => now()->addHour()->setTimezone(GroupPromotion::TIMEZONE)->format('Y-m-d\TH:i'), 'paid_group_member_slots' => 2];
        $this->actingAs($boss)->post('/superadmin/promo', $data)->assertSessionHasNoErrors()->assertRedirect('/superadmin/promo');
        $this->get('/superadmin/promo')->assertOk()->assertSee('WITA')->assertSee('bonus 50 NC');
        $this->post('/superadmin/promo', ['group_promo_type' => 'percent', 'group_promo_value' => 101] + $data)->assertSessionHasErrors('group_promo_value');
        $this->post('/superadmin/promo', ['group_promo_ends_at' => $data['group_promo_starts_at']] + $data)->assertSessionHasErrors('group_promo_ends_at');
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('250 NC + bonus 50 NC')->assertSee('setelah pembayaran berhasil');
        foreach (['/home', '/pricing'] as $page) {
            $this->get($page)->assertOk()->assertSee('50.00 bonus NC')->assertDontSee('Start your free group')->assertSee('2 friends');
        }
        $member = User::factory()->create();
        $this->actingAs($member)->get('/superadmin/promo')->assertForbidden();
        $this->post('/superadmin/promo', $data)->assertForbidden();
    }
}
