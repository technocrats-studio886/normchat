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
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Advanced dikunci saldo grup: terbuka saat saldo ≥ 2× modal (500 NK), terkunci lagi di bawah
 * 1× modal (250 NK), dan tidak boleh memakai 250 NK terakhir.
 */
class ModelAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    private CreditWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);

        $this->wallet = app(CreditWallet::class);
        $this->owner = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        // Grup baru: modal pembuatan grup.
        $this->wallet->deposit($this->group, 250);
    }

    public function test_advanced_is_gated_at_two_times_and_one_time_the_group_creation_credits(): void
    {
        $astra = AiModel::where('model_id', 'gpt-6-astra')->firstOrFail();
        $this->assertSame([2.0, 1.0], [$astra->unlock_multiple, $astra->lock_multiple]);
        $this->assertFalse(AiModel::where('model_id', 'gpt-6-luna')->firstOrFail()->isGated());
        $this->assertFalse(AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail()->isGated());
    }

    public function test_new_group_cannot_use_advanced_and_is_not_charged(): void
    {
        Http::fake();

        $this->ask('gpt-6-astra');

        Http::assertNothingSent();
        $this->assertSame(0, AiUsageLog::count());
        $this->assertSame(250.0, $this->balance());
        $notice = (string) Message::where('sender_type', 'ai')->value('content');
        $this->assertStringContainsString('Advanced terkunci', $notice);
        $this->assertStringContainsString('500 Normkredit (sekarang 250)', $notice);
    }

    public function test_other_categories_stay_usable_in_a_new_group(): void
    {
        $this->fakeReply();

        $this->ask('gpt-6.1-sol');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6.1-sol');
        $this->assertSame(1, AiUsageLog::count());
    }

    public function test_advanced_opens_at_500_and_stays_open_until_the_group_creation_credits(): void
    {
        $this->wallet->deposit($this->group, 250);
        $this->assertSame(['gpt-6-astra'], $this->group->groupToken->fresh()->unlocked_models);

        // Turun ke 400 lewat kategori lain: Advanced tetap terbuka.
        $this->spend(100);
        $this->fakeReply();
        $this->ask('gpt-6-astra');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-astra');
        $this->assertSame('gpt-6-astra', AiUsageLog::firstOrFail()->model);
    }

    public function test_advanced_cannot_spend_the_group_creation_credits(): void
    {
        $this->wallet->deposit($this->group, 250);
        // Tersisa 255 NK: hanya 5 NK di atas modal, kurang untuk jawaban Advanced minimal (≈6,7 NK).
        $this->spend(245);
        Http::fake();

        $this->ask('gpt-6-astra');

        Http::assertNothingSent();
        $this->assertSame(255.0, $this->balance());
        $this->assertStringContainsString('hanya boleh memakai saldo di atas 250 Normkredit (modal grup)', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    public function test_advanced_answer_is_limited_to_credits_above_the_group_creation_credits(): void
    {
        $this->wallet->deposit($this->group, 250);
        $this->spend(230);
        $this->fakeReply();

        $this->ask('gpt-6-astra');

        // 20 NK di atas modal: jawaban dipersingkat agar saldo tidak turun di bawah 250.
        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-astra' && $request['max_completion_tokens'] < 4000);
        $this->assertGreaterThanOrEqual(250.0, $this->balance());
    }

    public function test_advanced_locks_again_below_the_group_creation_credits(): void
    {
        $this->wallet->deposit($this->group, 250);
        $this->spend(251);
        $this->assertSame([], $this->group->groupToken->fresh()->unlocked_models);
        Http::fake();

        $this->ask('gpt-6-astra');

        Http::assertNothingSent();
        $this->assertStringContainsString('Advanced terkunci', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    public function test_chat_page_shows_advanced_locked_with_top_up_link_until_unlocked(): void
    {
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))
            ->assertOk()
            ->assertSee('data-ai-model-option="gpt-6-astra" data-ai-model-name="Advanced" role="menuitemradio" aria-checked="false"', false)
            ->assertSee('data-ai-model-locked="1" data-ai-model-open-at="500" data-ai-model-floor="250" aria-disabled="true"', false)
            ->assertSee('Terbuka saat saldo grup ≥ 500 NK')
            ->assertSee(route('subscription.tokens.buy', ['group' => $this->group->id]), false);

        $this->wallet->deposit($this->group, 250);

        $this->actingAs($this->owner)->get(route('chat.show', $this->group))
            ->assertSee('data-ai-model-locked="0"', false)
            ->assertSee('composer-menu-note hidden', false);
    }

    public function test_superadmin_sets_and_validates_unlock_thresholds(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $sol = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();
        $form = [
            'model_id' => $sol->model_id,
            'label' => $sol->label,
            'input_usd_per_mtok' => $sol->input_usd_per_mtok,
            'output_usd_per_mtok' => $sol->output_usd_per_mtok,
            'multiplier' => $sol->multiplier,
            'image_model' => $sol->image_model,
            'is_active' => 1,
            'is_default' => 1,
        ];

        $this->actingAs($boss)->put("/superadmin/models/{$sol->id}", $form + ['unlock_multiple' => 1, 'lock_multiple' => 2])
            ->assertSessionHasErrors('lock_multiple');

        $this->actingAs($boss)->put("/superadmin/models/{$sol->id}", $form + ['unlock_multiple' => 1.5, 'lock_multiple' => 0.5])
            ->assertRedirect('/superadmin');
        $this->assertSame([1.5, 0.5], [$sol->fresh()->unlock_multiple, $sol->fresh()->lock_multiple]);

        $this->actingAs($boss)->put("/superadmin/models/{$sol->id}", $form + ['unlock_multiple' => '', 'lock_multiple' => 1]);
        $this->assertFalse($sol->fresh()->isGated());
        $this->assertNull($sol->fresh()->lock_multiple);
    }

    private function ask(string $modelId): void
    {
        $message = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'text',
            'content' => '@normi jelaskan normalisasi',
            'ai_model' => $modelId,
        ]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }

    /** Pemakaian kategori lain yang menurunkan saldo grup. */
    private function spend(float $credits): void
    {
        $this->wallet->settle($this->wallet->hold($this->group, $this->owner, $credits), $credits);
    }

    private function fakeReply(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Normalisasi adalah ...']]],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 100],
            ]),
        ]);
    }

    private function balance(): float
    {
        return $this->group->groupToken->fresh()->credits;
    }
}
