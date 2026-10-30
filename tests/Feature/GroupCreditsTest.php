<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Exceptions\InsufficientBudgetException;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Normkredit grup satu kantong: setiap pembelian masuk penuh ke kredit grup dan semua
 * anggota memakainya penuh, kecuali owner memberi batas harian per anggota.
 */
class GroupCreditsTest extends TestCase
{
    use RefreshDatabase;

    private CreditWallet $wallet;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        $this->wallet = app(CreditWallet::class);
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create([
                'group_id' => $this->group->id,
                'user_id' => $user->id,
                'role_id' => Role::forKey($role)->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
    }

    public function test_every_member_can_use_the_whole_group_balance(): void
    {
        $this->wallet->deposit($this->group, 250);

        $this->assertSame(250.0, $this->wallet->balanceFor($this->group, $this->owner)->usable());
        $this->assertSame(250.0, $this->wallet->balanceFor($this->group, $this->member)->usable());

        $this->wallet->hold($this->group, $this->member, 240);
        $this->assertSame(10.0, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);
    }

    public function test_hold_beyond_group_balance_is_refused(): void
    {
        $this->wallet->deposit($this->group, 100);

        try {
            $this->wallet->hold($this->group, $this->member, 130);
            $this->fail('Hold melebihi saldo grup seharusnya ditolak.');
        } catch (InsufficientBudgetException $e) {
            $this->assertStringContainsString('sisa 100,00', $e->getMessage());
        }

        $this->assertSame(100.0, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);
    }

    public function test_settle_charges_actual_usage_and_refunds_the_rest(): void
    {
        $this->wallet->deposit($this->group, 100);

        $hold = $this->wallet->hold($this->group, $this->owner, 60);
        $this->assertSame(40.0, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);

        $this->assertSame(30.0, $this->wallet->settle($hold, 30));

        $this->assertSame(70.0, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);
        $this->assertSame(30.0, $hold->fresh()->amount);
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
        $this->assertSame(30.0, (float) $this->group->groupToken->fresh()->used_credits);
    }

    public function test_settle_above_hold_takes_extra_only_from_what_is_left(): void
    {
        $this->wallet->deposit($this->group, 50);

        $hold = $this->wallet->hold($this->group, $this->member, 40);
        // Aktual 70 > tahanan 40, tetapi saldo hanya tersisa 10 → total terpotong 50.
        $this->assertSame(50.0, $this->wallet->settle($hold, 70));
        $this->assertSame(0.0, $this->wallet->balanceFor($this->group, $this->member)->groupTotal);
    }

    public function test_release_returns_the_whole_hold(): void
    {
        $this->wallet->deposit($this->group, 50);

        $this->wallet->release($this->wallet->hold($this->group, $this->member, 20));

        $this->assertSame(50.0, $this->wallet->balanceFor($this->group, $this->member)->groupTotal);
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
    }

    public function test_owner_daily_limit_caps_members_but_not_owner(): void
    {
        $this->wallet->deposit($this->group, 250);
        $this->group->update(['member_daily_credit_limit' => 5]);

        $this->wallet->settle($this->wallet->hold($this->group, $this->member, 4), 4);
        $this->assertSame(1.0, $this->wallet->balanceFor($this->group, $this->member)->usable());

        $this->expectException(InsufficientBudgetException::class);
        try {
            $this->wallet->hold($this->group, $this->member, 2);
        } finally {
            $this->assertSame(246.0, $this->wallet->balanceFor($this->group, $this->owner)->usable());
            $this->wallet->hold($this->group, $this->owner, 50);
        }
    }

    public function test_only_owner_sets_member_daily_limit(): void
    {
        $this->actingAs($this->member)
            ->post(route('groups.credits.limit', $this->group), ['member_daily_credit_limit' => 10])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('groups.credits.limit', $this->group), ['member_daily_credit_limit' => 10])
            ->assertSessionHasNoErrors();
        $this->assertSame(10.0, $this->group->fresh()->member_daily_credit_limit);
    }

    public function test_purchases_go_fully_into_group_credits(): void
    {
        $group = Group::create(['name' => 'A', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'pending_payment']);
        app(\App\Services\GroupMembership::class)->activateGroup($group, $this->owner, new \App\Services\Credits\Funding(250, 125000, 'INV-A'));

        $contribution = GroupTokenContribution::latest('id')->firstOrFail();
        $this->assertSame((float) $group->groupToken->remaining_credits, (float) $contribution->credit_amount);
    }

    public function test_paid_patungan_lands_in_group_credits(): void
    {
        $pending = PendingPayment::create([
            'user_id' => $this->member->id,
            'group_id' => $this->group->id,
            'order_id' => 'NCJOIN-1',
            'payment_type' => 'group_join_midtrans',
            'expected_amount' => 15105,
            'status' => 'pending',
            'metadata_json' => ['action' => 'group_join_midtrans', 'credits' => 125],
            'expires_at' => now()->addHour(),
        ]);

        app(PaymentFulfillment::class)->fulfill($pending, 15105, 'mt-1', 'qris', now());

        $this->assertSame(125.0, $this->wallet->balanceFor($this->group, $this->owner)->usable());
        $this->assertSame(125.0, $this->wallet->balanceFor($this->group, $this->member)->usable());
    }

    public function test_member_ai_request_is_paid_from_group_credits(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban']]],
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 500],
        ])]);
        $this->group->update(['ai_model' => 'gpt-6.1-sol']);
        $this->wallet->deposit($this->group, 250);

        $message = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->member->id,
            'message_type' => 'text',
            'content' => '@normi jelaskan normalisasi',
        ]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        $this->assertSame(4.1136, AiUsageLog::firstOrFail()->charged_credits);
        $this->assertSame(245.8864, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
    }

    public function test_pages_explain_credit_rules(): void
    {
        $this->wallet->deposit($this->group, 250);
        $this->group->update(['member_daily_credit_limit' => 10]);

        $this->actingAs($this->owner)->get('/groups/create')
            ->assertOk()->assertSee('Dipakai semua anggota untuk Normi AI.')->assertDontSee('share_percent');

        $this->actingAs($this->owner)->get("/groups/{$this->group->id}/settings")
            ->assertOk()->assertSee('Normkredit')->assertSee('250,00 NK')->assertSee('Batas pemakaian harian per anggota');

        $this->actingAs($this->member)->get("/groups/{$this->group->id}/settings")
            ->assertOk()->assertSee('Batas pemakaian 10,00 NK per anggota per hari')->assertDontSee('name="member_daily_credit_limit"', false);

        $this->actingAs(User::factory()->create())->get("/join/{$this->group->share_id}")
            ->assertOk()->assertSee('Saldo grup')->assertDontSee('share_percent');
    }
}
