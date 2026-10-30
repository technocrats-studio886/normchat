<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Exceptions\SpendLimitReachedException;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Jobs\SendAdminAlertMail;
use App\Models\AdminAlert;
use App\Models\AiRequestRefusal;
use App\Models\AiSpendHold;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\CreditHold;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\PricingVersion;
use App\Models\User;
use App\Services\Ai\CostReconciliation;
use App\Services\Ai\SpendGuard;
use App\Services\Credits\CreditWallet;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * llm-rules bagian 3–4: dua saldo yang berbeda. Normkredit grup (ledger, presisi, kurs harian, versi harga)
 * dan saldo akun OpenAI (SpendGuard: anggaran bulanan + saldo dengan cadangan, peringatan, rekonsiliasi).
 */
class LlmBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        Event::fake([MessageSent::class, TypingStatus::class]);

        $this->owner = User::factory()->create(['name' => 'Noel']);
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    // ── Ledger NC ──────────────────────────────────────────

    public function test_every_balance_change_is_in_the_ledger_and_amounts_add_up_to_the_balance(): void
    {
        $wallet = app(CreditWallet::class);
        $hold = $wallet->hold($this->group, $this->owner, 10);
        $wallet->settle($hold, 3.25);
        $wallet->release($wallet->hold($this->group, $this->owner, 5));
        $wallet->adjust($this->group, -20, ['user_id' => $this->owner->id]);

        $entries = CreditLedgerEntry::query()->where('group_id', $this->group->id)->orderBy('id')->get();
        $this->assertSame(['deposit', 'hold', 'settle', 'hold', 'release', 'admin_adjust'], $entries->pluck('type')->all());

        // Saldo sesudah satu baris = saldo sebelum baris berikutnya.
        foreach ($entries->slice(1)->values() as $index => $entry) {
            $this->assertSame($entries[$index]->balance_after, $entry->balance_before);
            $this->assertSame($entries[$index]->reserved_after, $entry->reserved_before);
        }

        // amount = perubahan total dana grup (tersedia + ditahan).
        $token = GroupToken::forGroup((int) $this->group->id);
        $this->assertEqualsWithDelta($token->remaining_credits + $token->reserved_credits, $entries->sum('amount'), 0.00001);
        $this->assertSame(-3.25, $entries[2]->amount);
        $this->assertSame(276.75, $token->remaining_credits);
    }

    public function test_ai_answer_links_hold_settle_usage_log_pricing_version_and_rate(): void
    {
        $this->fakeChat();
        app(NormkreditPricing::class)->setExchangeRate(18000, $this->owner->id);

        $this->runAi('@normi apa itu normalisasi?');

        $log = AiUsageLog::query()->where('kind', 'chat')->firstOrFail();
        $settle = CreditLedgerEntry::query()->where('type', 'settle')->firstOrFail();
        $hold = CreditLedgerEntry::query()->where('type', 'hold')->firstOrFail();

        $this->assertSame($log->id, $settle->ai_usage_log_id);
        $this->assertSame($hold->credit_hold_id, $settle->credit_hold_id);
        $this->assertSame(300.0, $log->balance_before);
        $this->assertSame('chatcmpl-uji', $log->provider_request_id);
        $this->assertSame(app(PricingVersions::class)->currentId(), $log->pricing_version_id);
        $this->assertSame($log->pricing_version_id, $settle->pricing_version_id);
        $this->assertNotNull($log->exchange_rate_id);
        $this->assertSame($log->exchange_rate_id, $settle->exchange_rate_id);
        // 18.000 + selisih kartu 2% = 18.360
        $this->assertSame(18360.0, (float) $log->usd_to_idr);
        $this->assertSame(25.0, $log->cache_hit_percent);
    }

    // ── Kurs harian ────────────────────────────────────────

    public function test_daily_rate_adds_card_margin_and_env_is_only_a_fallback(): void
    {
        $pricing = app(NormkreditPricing::class);
        $this->assertSame(17950.0, $pricing->usdToIdr());
        $this->assertNull($pricing->exchangeRateId());

        $rate = $pricing->setExchangeRate(18158, $this->owner->id);
        $this->assertSame(18521.16, $rate->applied_rate);
        $this->assertSame(18521.16, $pricing->usdToIdr());
    }

    public function test_locked_pricing_keeps_its_rate_when_superadmin_changes_it(): void
    {
        $pricing = app(NormkreditPricing::class);
        $pricing->setExchangeRate(18000, $this->owner->id);
        $locked = $pricing->locked();

        $pricing->setExchangeRate(19000, $this->owner->id);

        $this->assertSame(18360.0, $locked->usdToIdr());
        $this->assertSame(19380.0, $pricing->usdToIdr());
    }

    public function test_stale_rate_warns_superadmin_once(): void
    {
        Bus::fake([SendAdminAlertMail::class]);
        $this->artisan('normchat:check-exchange-rate')->assertSuccessful();
        $this->assertTrue(AdminAlert::where('key', 'rate:never')->exists());

        app(NormkreditPricing::class)->setExchangeRate(18000, $this->owner->id);
        $this->travel(25)->hours();
        $this->artisan('normchat:check-exchange-rate')->assertSuccessful();
        $this->artisan('normchat:check-exchange-rate')->assertSuccessful();

        $this->assertSame(1, AdminAlert::where('key', 'like', 'rate:stale:%')->count());
        Bus::assertDispatchedTimes(SendAdminAlertMail::class, 2);
    }

    // ── Versi harga ────────────────────────────────────────

    public function test_saving_prices_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $versions = app(PricingVersions::class);
        $first = $versions->currentId();
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);

        $this->actingAs($boss)->post(route('superadmin.settings.update'), [
            ...app(NormkreditPricing::class)->settings(),
            'rupiah_per_credit' => 150,
        ])->assertRedirect();

        $this->assertNotSame($first, $versions->currentId());
        $this->assertSame(120, (int) PricingVersion::find($first)->settings['rupiah_per_credit']);
        $this->assertSame(150, (int) PricingVersion::find($versions->currentId())->settings['rupiah_per_credit']);
        $this->assertContains(['rupiah_per_credit', 120, 150], $versions->changes(PricingVersion::find($versions->currentId())));
    }

    // ── SpendGuard: saldo akun OpenAI ──────────────────────

    public function test_monthly_budget_stops_new_requests_and_warns_at_each_threshold(): void
    {
        Bus::fake([SendAdminAlertMail::class]);
        $spend = app(SpendGuard::class);
        $spend->setMonthlyBudget(10, $this->owner->id);

        $spend->settle($spend->reserve('chat', 5.5), 5.5);
        $this->assertTrue(AdminAlert::where('key', 'like', 'budget:%:50')->exists());
        $this->assertFalse(AdminAlert::where('key', 'like', 'budget:%:70')->exists());

        $spend->settle($spend->reserve('chat', 3.2), 3.2);
        $this->assertTrue(AdminAlert::where('key', 'like', 'budget:%:85')->exists());

        try {
            $spend->reserve('chat', 2);
            $this->fail('Anggaran seharusnya menolak.');
        } catch (SpendLimitReachedException $e) {
            $this->assertSame('budget', $e->limit);
        }
        $this->assertTrue(AdminAlert::where('key', 'like', 'budget:%:100')->exists());
        // 50, 70, 85, 100: masing-masing sekali.
        Bus::assertDispatchedTimes(SendAdminAlertMail::class, 4);
    }

    public function test_openai_balance_keeps_the_reserve_untouched(): void
    {
        $spend = app(SpendGuard::class);
        $spend->setBalance(20, 5, $this->owner->id);

        $spend->settle($spend->reserve('chat', 14), 14);
        $this->expectException(SpendLimitReachedException::class);
        $spend->reserve('embedding', 1.5);
    }

    public function test_tag_is_refused_without_charging_nc_when_openai_balance_is_exhausted(): void
    {
        Http::fake();
        app(SpendGuard::class)->setBalance(10, 10, $this->owner->id);

        $this->runAi('@normi halo');

        Http::assertNothingSent();
        $notice = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('Normi AI sedang tidak bisa dipakai. Coba lagi nanti.', $notice->content);
        $this->assertSame($this->owner->id, $notice->visible_to_user_id);
        $this->assertSame('spend_guard', AiRequestRefusal::firstOrFail()->reason);
        $this->assertSame(300.0, GroupToken::forGroup((int) $this->group->id)->remaining_credits);
    }

    public function test_answer_settles_real_cost_in_spend_guard(): void
    {
        $this->fakeChat();
        $this->runAi('@normi apa itu normalisasi?');

        $hold = AiSpendHold::query()->where('kind', 'chat')->firstOrFail();
        $this->assertSame(AiSpendHold::SETTLED, $hold->status);
        $this->assertNotNull($hold->credit_hold_id);
        // (1.000 × 0,10 + 250 cached × 0,01 ... ) dihitung dari usage: sama dengan biaya di log.
        $this->assertEqualsWithDelta(AiUsageLog::where('kind', 'chat')->value('provider_cost_usd'), $hold->actual_usd, 1e-9);
        $this->assertSame(0.0, app(SpendGuard::class)->status()['reserved']);
    }

    public function test_stale_holds_are_released(): void
    {
        $wallet = app(CreditWallet::class);
        $creditHold = $wallet->hold($this->group, $this->owner, 12);
        $spendHold = app(SpendGuard::class)->reserve('chat', 0.5, (int) $this->group->id);

        $this->travel(20)->minutes();
        $this->artisan('normchat:release-stale-holds')->assertSuccessful();

        $this->assertSame(CreditHold::RELEASED, $creditHold->fresh()->status);
        $this->assertSame(AiSpendHold::RELEASED, $spendHold->fresh()->status);
        $this->assertSame(300.0, GroupToken::forGroup((int) $this->group->id)->remaining_credits);
    }

    // ── Rekonsiliasi harian ────────────────────────────────

    public function test_reconciliation_corrects_spend_with_real_openai_cost_and_applies_only_the_change(): void
    {
        config()->set('services.openai.admin_key', 'sk-admin-uji');
        config()->set('services.openai.project_id', 'proj_uji');
        Bus::fake([SendAdminAlertMail::class]);
        $day = CarbonImmutable::parse('2026-10-27', 'UTC');
        $this->travelTo($day->setTime(10, 0));
        $spend = app(SpendGuard::class);
        $spend->setBalance(100, 10, $this->owner->id);
        $this->travelTo($day->setTime(12, 0));
        $this->logCost(1.0);
        $spend->settle($spend->reserve('chat', 1.0), 1.0);

        Http::fake(['api.openai.com/v1/organization/costs*' => Http::sequence()
            ->push($this->costs(1.03))
            ->push($this->costs(1.10)),
        ]);
        $this->travelTo($day->addDay()->setTime(1, 0));

        $first = app(CostReconciliation::class)->reconcile($day);
        $this->assertEqualsWithDelta(0.03, $first->diff_usd, 1e-9);
        $this->assertEqualsWithDelta(1.03, $spend->status()['spent_since_set'], 1e-9);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'project_ids[]=proj_uji')
            && $request->hasHeader('Authorization', 'Bearer sk-admin-uji'));

        // Data OpenAI menyusul: selisih kedua (0,10) hanya menambah 0,07.
        $this->travelTo($day->addDay()->setTime(20, 0));
        app(CostReconciliation::class)->reconcile($day);
        $this->assertEqualsWithDelta(1.10, $spend->status()['spent_since_set'], 1e-9);
        // Selisih 10% > 5% setelah data dianggap lengkap → peringatan.
        $this->assertTrue(AdminAlert::where('key', 'reconcile:2026-10-27')->exists());
    }

    public function test_reconciliation_is_skipped_without_admin_key(): void
    {
        Http::fake();
        $this->artisan('normchat:reconcile-openai-costs')->expectsOutputToContain('Rekonsiliasi nonaktif')->assertSuccessful();
        Http::assertNothingSent();
    }

    private function costs(float $usd): array
    {
        return ['data' => [['object' => 'bucket', 'results' => [['object' => 'organization.costs.result', 'amount' => ['value' => $usd, 'currency' => 'usd']]]]], 'next_page' => null];
    }

    private function logCost(float $usd): void
    {
        AiUsageLog::create([
            'group_id' => $this->group->id, 'kind' => 'chat', 'model' => 'gpt-6-luna', 'provider_cost_usd' => $usd,
            'usd_to_idr' => 17950, 'provider_cost_idr' => $usd * 17950, 'multiplier' => 1.5, 'charged_credits' => 1, 'balance_after' => 0,
        ]);
    }

    private function fakeChat(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-uji',
                'choices' => [['message' => ['content' => 'Normalisasi adalah ...']]],
                'usage' => ['prompt_tokens' => 1000, 'prompt_tokens_details' => ['cached_tokens' => 250], 'completion_tokens' => 400],
            ]),
            'api.openai.com/v1/embeddings' => Http::response([], 500),
        ]);
    }

    private function runAi(string $content): void
    {
        $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => $content]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
