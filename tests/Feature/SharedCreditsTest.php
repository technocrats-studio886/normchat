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
use App\Models\GroupMemberCredit;
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
 * Aturan pembagian kredit: saldo grup terlihat satu angka, tetapi kredit terlindungi
 * hanya bisa dipakai pemiliknya; anggota lain hanya memakai kredit bersama.
 */
class SharedCreditsTest extends TestCase
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

    public function test_deposit_splits_into_shared_and_buyers_protected_credits(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);

        $owner = $this->wallet->balanceFor($this->group, $this->owner);
        $member = $this->wallet->balanceFor($this->group, $this->member);

        $this->assertSame([250.0, 125.0, 125.0], [$owner->groupTotal, $owner->shared, $owner->protected]);
        $this->assertSame(125.0, $member->usable(), 'Anggota hanya bisa memakai kredit bersama.');
    }

    public function test_member_can_never_spend_someone_elses_protected_credits(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);

        try {
            $this->wallet->hold($this->group, $this->member, 130);
            $this->fail('Hold melebihi kredit bersama seharusnya ditolak.');
        } catch (InsufficientBudgetException $e) {
            $this->assertStringContainsString('tersedia 125,00 NK', $e->getMessage());
        }

        $this->assertSame(125.0, $this->wallet->balanceFor($this->group, $this->owner)->protected);
    }

    public function test_own_protected_credits_are_spent_first_by_default_and_order_can_be_flipped(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);

        $hold = $this->wallet->hold($this->group, $this->owner, 10);
        $this->assertSame([0.0, 10.0], [$hold->shared_amount, $hold->protected_amount]);
        $this->wallet->release($hold);

        $this->wallet->setSpendSharedFirst($this->group, $this->owner, true);
        $hold = $this->wallet->hold($this->group, $this->owner, 10);
        $this->assertSame([10.0, 0.0], [$hold->shared_amount, $hold->protected_amount]);
    }

    public function test_settle_charges_actual_usage_and_refunds_the_rest_to_its_pool(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 100, 50);
        $this->wallet->setSpendSharedFirst($this->group, $this->owner, true);

        // Tahan 60: 50 bersama + 10 terlindungi; aktual 30 → semua dari bersama, 20 bersama + 10 terlindungi kembali.
        $hold = $this->wallet->hold($this->group, $this->owner, 60);
        $this->assertSame(40.0, $this->wallet->balanceFor($this->group, $this->owner)->groupTotal);

        $this->assertSame(30.0, $this->wallet->settle($hold, 30));

        $balance = $this->wallet->balanceFor($this->group, $this->owner);
        $this->assertSame([70.0, 20.0, 50.0], [$balance->groupTotal, $balance->shared, $balance->protected]);
        $this->assertSame([30.0, 0.0], [$hold->fresh()->shared_amount, $hold->fresh()->protected_amount]);
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
        $this->assertSame(30.0, (float) $this->group->groupToken->fresh()->used_credits);
    }

    public function test_settle_above_hold_takes_extra_only_from_usable_credits(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 100, 50);

        $hold = $this->wallet->hold($this->group, $this->member, 40);
        // Aktual 70 > tahanan 40, tetapi kredit bersama hanya tersisa 10 → total terpotong 50.
        $this->assertSame(50.0, $this->wallet->settle($hold, 70));

        $this->assertSame(0.0, $this->wallet->balanceFor($this->group, $this->member)->shared);
        $this->assertSame(50.0, $this->wallet->balanceFor($this->group, $this->owner)->protected);
    }

    public function test_owner_daily_limit_caps_members_shared_usage_but_not_owner(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);
        $this->group->update(['member_daily_shared_limit' => 5]);

        $this->wallet->settle($this->wallet->hold($this->group, $this->member, 4), 4);
        $this->assertSame(1.0, $this->wallet->balanceFor($this->group, $this->member)->usable());

        $this->expectException(InsufficientBudgetException::class);
        try {
            $this->wallet->hold($this->group, $this->member, 2);
        } finally {
            $this->assertSame(121.0, $this->wallet->balanceFor($this->group, $this->owner)->usableShared());
            $this->wallet->hold($this->group, $this->owner, 50);
        }
    }

    public function test_protected_credits_can_only_be_moved_to_shared(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);

        $this->actingAs($this->owner)
            ->post(route('groups.credits.share', $this->group), ['amount' => 25])
            ->assertSessionHasNoErrors();
        $balance = $this->wallet->balanceFor($this->group, $this->owner);
        $this->assertSame([150.0, 100.0], [$balance->shared, $balance->protected]);

        $this->actingAs($this->owner)
            ->post(route('groups.credits.share', $this->group), ['amount' => 101])
            ->assertSessionHasErrors('amount');
        $this->assertSame(100.0, $this->wallet->balanceFor($this->group, $this->owner)->protected);
    }

    public function test_only_owner_sets_member_daily_limit(): void
    {
        $this->actingAs($this->member)
            ->post(route('groups.credits.limit', $this->group), ['member_daily_shared_limit' => 10])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('groups.credits.limit', $this->group), ['member_daily_shared_limit' => 10])
            ->assertSessionHasNoErrors();
        $this->assertSame(10.0, $this->group->fresh()->member_daily_shared_limit);
    }

    public function test_purchase_share_percent_defaults_to_half_and_follows_buyer_choice(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);

        $this->actingAs($boss)->post('/groups', ['name' => 'A', 'password' => '1234', 'share_percent' => 20]);
        $this->assertSame(50.0, GroupTokenContribution::latest('id')->first()->shared_credit_amount);

        $this->actingAs($boss)->post('/groups', ['name' => 'B', 'password' => '1234']);
        $this->assertSame(125.0, GroupTokenContribution::latest('id')->first()->shared_credit_amount);
    }

    public function test_paid_patungan_uses_share_percent_from_checkout(): void
    {
        $pending = PendingPayment::create([
            'user_id' => $this->member->id,
            'group_id' => $this->group->id,
            'order_id' => 'NCJOIN-1',
            'payment_type' => 'group_join_midtrans',
            'expected_amount' => 15105,
            'status' => 'pending',
            'metadata_json' => ['action' => 'group_join_midtrans', 'credits' => 125, 'share_percent' => 0],
            'expires_at' => now()->addHour(),
        ]);

        app(PaymentFulfillment::class)->fulfill($pending, 15105, 'mt-1', 'qris', now());

        $member = $this->wallet->balanceFor($this->group, $this->member);
        $this->assertSame([0.0, 125.0], [$member->shared, $member->protected]);
    }

    public function test_member_ai_request_is_paid_from_shared_credits_only(): void
    {
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban']]],
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 500],
        ])]);
        $this->group->update(['ai_model' => 'gpt-6.1-sol']);
        $this->wallet->deposit($this->group, $this->owner, 250, 50);

        $message = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->member->id,
            'message_type' => 'text',
            'content' => '@ai jelaskan normalisasi',
        ]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        $this->assertSame(4.12, AiUsageLog::firstOrFail()->charged_credits);
        $this->assertSame(120.88, $this->wallet->balanceFor($this->group, $this->member)->shared);
        $this->assertSame(125.0, GroupMemberCredit::where('user_id', $this->owner->id)->value('protected_credits'));
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
    }

    public function test_pages_explain_credit_rules(): void
    {
        $this->wallet->deposit($this->group, $this->owner, 250, 50);
        $this->group->update(['member_daily_shared_limit' => 10]);

        $this->actingAs($this->owner)->get('/groups/create')
            ->assertOk()->assertSee('Bagikan ke kredit bersama')->assertSee('name="share_percent" value="50"', false);

        $this->actingAs($this->owner)->get("/groups/{$this->group->id}/settings")
            ->assertOk()->assertSee('Kredit Grup')->assertSee('125,00 NK')->assertSee('Batas harian kredit bersama per anggota');

        $this->actingAs($this->member)->get("/groups/{$this->group->id}/settings")
            ->assertOk()->assertSee('10,00 NK per anggota per hari')->assertDontSee('Batas harian kredit bersama per anggota (NK)');

        $this->actingAs(User::factory()->create())->get("/join/{$this->group->share_id}")
            ->assertOk()->assertSee('Kredit bersama grup saat ini')->assertSee('Bagikan ke kredit bersama');
    }
}
