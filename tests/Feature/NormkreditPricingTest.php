<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\Credits\CreditWallet;
use App\Models\Message;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\NormkreditPricing;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NormkreditPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('services.openai.api_key', 'sk-test');
    }

    public function test_catalog_has_three_categories_with_requested_multipliers(): void
    {
        $catalog = AiModel::active()->get()->mapWithKeys(fn (AiModel $m) => [$m->label => [$m->model_id, $m->multiplier]]);

        $this->assertEquals([
            'Cepat' => ['gpt-6-luna', 1.5],
            'Smart' => ['gpt-6.1-sol', 2.5],
            'Advanced' => ['gpt-6-astra', 3.0],
        ], $catalog->all());
    }

    public function test_credits_follow_real_cost_times_model_multiplier(): void
    {
        $pricing = app(NormkreditPricing::class);
        $sol = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();

        // 3.000 input + 500 output di $2/$10 = $0,011
        $cost = $sol->costUsd(3000, 0, 500);
        $this->assertEqualsWithDelta(0.011, $cost, 1e-9);

        // $0,011 × 2,5 × 17.950 = Rp493,625 ÷ Rp120 = 4,11354 NK → dibulatkan ke atas di digit keempat 4,1136
        $this->assertSame(4.1136, $pricing->creditsForCost($cost, $sol->multiplier));
        $this->assertSame(0.0, $pricing->creditsForCost(0, 2));
        // Biaya sangat kecil tetap tercatat, minimal 0,0001 NC.
        $this->assertSame(0.0001, $pricing->creditsForCost(0.0000001, 2));
    }

    public function test_packages_cost_rp120_per_credit_plus_qris_fee(): void
    {
        $pricing = app(NormkreditPricing::class);

        $group = $pricing->quoteGroupCreation();
        $this->assertSame([250, 30000, 210, 30210], [$group->credits, $group->baseAmount, $group->serviceFee, $group->total()]);

        $one = $pricing->quotePackages(1);
        $this->assertSame([125, 15000, 105, 15105], [$one->credits, $one->baseAmount, $one->serviceFee, $one->total()]);

        $three = $pricing->quotePackages(3);
        $this->assertSame([375, 45000, 315, 45315], [$three->credits, $three->baseAmount, $three->serviceFee, $three->total()]);
    }

    public function test_cached_input_uses_cached_rate(): void
    {
        $luna = AiModel::where('model_id', 'gpt-6-luna')->firstOrFail();

        // 1.000 input (400 cached) + 0 output: 600 × 0,10 + 400 × 0,01 = 64 / 1M
        $this->assertEqualsWithDelta(0.000064, $luna->costUsd(1000, 400, 0), 1e-12);
    }

    public function test_superadmin_overrides_take_effect(): void
    {
        $pricing = app(NormkreditPricing::class);
        $this->assertSame(120, $pricing->rupiahPerCredit());

        $pricing->update(['rupiah_per_credit' => 200, 'media_multiplier' => 3, 'qris_fee_percent' => 1]);

        $this->assertSame(200, $pricing->rupiahPerCredit());
        $this->assertSame(3.0, $pricing->mediaMultiplier());
        $this->assertSame(50500, $pricing->quoteGroupCreation()->total());
    }

    public function test_patungan_requires_whole_packages(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 250);

        $this->actingAs(User::factory()->create())
            ->post("/join/{$group->share_id}", ['password' => '1234', 'packages' => 0])
            ->assertSessionHasErrors('packages');
    }

    public function test_midtrans_topup_adds_ordered_credits(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 300);

        $pending = PendingPayment::create([
            'user_id' => $owner->id,
            'group_id' => $group->id,
            'order_id' => 'NCTOPUP-TEST-1',
            'payment_type' => 'topup_midtrans',
            'expected_amount' => 15105,
            'status' => 'pending',
            'metadata_json' => ['action' => 'topup_midtrans', 'credits' => 125],
            'expires_at' => now()->addHour(),
        ]);

        app(PaymentFulfillment::class)->fulfill($pending, 15105, 'gw-1', 'qris', now());
        // Idempoten: webhook kedua tidak menambah saldo lagi.
        app(PaymentFulfillment::class)->fulfill($pending->fresh(), 15105, 'gw-1', 'qris', now());

        $this->assertSame(425.0, $group->groupToken->fresh()->credits);
        $this->assertSame('paid', $pending->fresh()->status);
    }

    public function test_ai_reply_is_charged_from_actual_usage(): void
    {
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Normalisasi adalah ...']]],
                'usage' => [
                    'prompt_tokens' => 3000,
                    'completion_tokens' => 500,
                    'prompt_tokens_details' => ['cached_tokens' => 0],
                ],
            ]),
        ]);

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 300);
        $group->update(['ai_model' => 'gpt-6.1-sol']);

        $this->runAi($group, $owner, '@normi jelaskan normalisasi database');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6.1-sol'
            && $request['max_completion_tokens'] === 4000
            && ! isset($request['temperature']));

        $log = AiUsageLog::firstOrFail();
        $this->assertSame(4.1136, $log->charged_credits);
        $this->assertSame(295.8864, $log->balance_after);
        $this->assertSame(2.5, $log->multiplier);
        $this->assertEqualsWithDelta(0.011 * 17950, $log->provider_cost_idr, 0.001);
        $this->assertSame(295.88, $group->groupToken->fresh()->credits);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame($reply->id, $log->response_message_id);
    }

    public function test_request_is_refused_before_calling_openai_when_estimate_exceeds_balance(): void
    {
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake();

        $owner = User::factory()->create();
        // Smart: jawaban minimal 300 token ≈ 1,1 NK > saldo 0,5 NK
        $group = $this->makeGroup($owner, 0.5);
        $group->update(['ai_model' => 'gpt-6.1-sol']);

        $this->runAi($group, $owner, '@normi halo');

        Http::assertNothingSent();
        $this->assertSame(0, AiUsageLog::count());
        $this->assertSame(0.5, $group->groupToken->fresh()->credits);
        $this->assertStringContainsString('tidak cukup', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    public function test_daily_group_limit_blocks_further_requests(): void
    {
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake();
        app(NormkreditPricing::class)->update(['daily_group_credit_limit' => 1]);

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 300);
        AiUsageLog::create([
            'group_id' => $group->id,
            'kind' => 'chat',
            'model' => 'gpt-6-luna',
            'usd_to_idr' => 17950,
            'multiplier' => 2,
            'charged_credits' => 1,
        ]);

        $this->runAi($group, $owner, '@normi halo');

        Http::assertNothingSent();
        $this->assertStringContainsString('Batas pemakaian', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    public function test_only_superadmin_can_open_panel_and_manage_models(): void
    {
        $this->actingAs(User::factory()->create())->get('/superadmin')->assertForbidden();

        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $this->actingAs($boss)->get('/superadmin/pengaturan')->assertOk()->assertSee('gpt-6-luna');

        $this->actingAs($boss)->post('/superadmin/models', [
            'model_id' => 'gpt-5.4-nano',
            'label' => 'Hemat',
            'input_usd_per_mtok' => 0.2,
            'output_usd_per_mtok' => 1.25,
            'multiplier' => 2,
            'image_model' => 'gpt-image-2.5-flare',
            'is_active' => 1,
            'is_default' => 1,
        ])->assertRedirect('/superadmin/pengaturan');

        $this->assertSame('gpt-5.4-nano', AiModel::defaultModel()->model_id);
        $this->actingAs($boss)->get('/superadmin/models/' . AiModel::defaultModel()->id . '/edit')
            ->assertOk()->assertSee('Mesin gambar presisi');
        $this->assertSame(1, AiModel::where('is_default', true)->count());

        $this->actingAs($boss)->post('/superadmin/settings', [
            'rupiah_per_credit' => 120,
            'group_creation_credits' => 250,
            'package_credits' => 125,
            'max_packages_per_payment' => 20,
            'qris_fee_percent' => 0.7,
            'media_multiplier' => 0.5,
            'max_credits_per_request' => 150,
            'max_output_tokens' => 4000,
            'daily_group_credit_limit' => 0,
        ])->assertSessionHasErrors('media_multiplier');
    }

    public function test_owner_picks_active_model_only(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 300);
        AiModel::where('model_id', 'gpt-6.1-sol')->update(['is_active' => false]);

        $this->actingAs($owner)
            ->post("/groups/{$group->id}/settings/ai-model", ['ai_model' => 'gpt-6.1-sol'])
            ->assertSessionHasErrors('ai_model');

        $this->actingAs($owner)
            ->post("/groups/{$group->id}/settings/ai-model", ['ai_model' => 'gpt-6-luna'])
            ->assertSessionHasNoErrors();

        $this->assertSame('gpt-6-luna', $group->fresh()->resolvedAiModel()->model_id);
    }

    public function test_payment_and_ai_pages_render_nk_pricing(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, 300);

        $this->actingAs($owner)->get('/groups/create')
            ->assertOk()->assertSee('250 Normkredit')->assertSee('Buat Grup · Rp30.000')->assertDontSee('Rp30.210')
            ->assertDontSee('Biaya layanan QRIS')->assertDontSee('Dots Units');

        $this->actingAs($owner)->get("/tokens/buy?group={$group->id}")
            ->assertOk()->assertSee('125 Normkredit')->assertSee('Rp15.000')->assertDontSee('Rp15.105')->assertSee('300,00 Normkredit');

        $this->actingAs(User::factory()->create())->get("/join/{$group->share_id}")
            ->assertOk()->assertSee('125 Normkredit')->assertSee('Rp15.000')->assertDontSee('Rp15.105')->assertDontSee('Dots Units');

        $this->actingAs($owner)->get("/groups/{$group->id}/settings/ai-persona")
            ->assertOk()->assertSee('Cepat')->assertSee('Smart')->assertSee('Advanced');

        $reply = Message::create(['group_id' => $group->id, 'sender_type' => 'ai', 'message_type' => 'text', 'content' => 'Halo']);
        AiUsageLog::create([
            'group_id' => $group->id,
            'response_message_id' => $reply->id,
            'kind' => 'chat',
            'model' => 'gpt-6-luna',
            'usd_to_idr' => 17950,
            'multiplier' => 2,
            'charged_credits' => 14.7,
            'balance_after' => 285.3,
        ]);

        $this->actingAs($owner)->get("/groups/{$group->id}/chat")
            // Biaya per jawaban tidak ditampilkan di chat; saldo dilihat di pengaturan grup.
            ->assertOk()->assertSee('Normi AI')->assertDontSee('14,70')->assertDontSee('285,30');
    }

    private function makeGroup(User $owner, float $credits): Group
    {
        $group = Group::create([
            'name' => 'Kelas Basis Data',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);

        $ownerRole = Role::firstOrCreate(['key' => 'owner'], ['name' => 'Owner', 'description' => 'Group owner']);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Seluruhnya kredit bersama agar tes pricing tidak dipengaruhi aturan pembagian.
        app(CreditWallet::class)->deposit($group, $credits);

        return $group->fresh('groupToken');
    }

    private function runAi(Group $group, User $sender, string $content): void
    {
        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'user',
            'sender_id' => $sender->id,
            'message_type' => 'text',
            'content' => $content,
        ]);

        ChatMessageQueue::create([
            'group_id' => $group->id,
            'message_id' => $message->id,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        (new ProcessGroupChatQueueJob($group->id))->handle();
    }
}
