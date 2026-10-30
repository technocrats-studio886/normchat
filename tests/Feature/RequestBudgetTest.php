<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Exceptions\InsufficientBudgetException;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\RequestBudget;
use App\Services\Credits\CreditWallet;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Panjang jawaban mengikuti kredit penanya: model mahal tetap bisa dipakai dengan
 * kredit kecil, dan tahanan tidak pernah melebihi kredit yang boleh dipakai.
 */
class RequestBudgetTest extends TestCase
{
    use RefreshDatabase;

    private NormkreditPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        $this->pricing = app(NormkreditPricing::class);
    }

    public function test_plenty_of_credits_keeps_configured_output_limit(): void
    {
        $budget = RequestBudget::plan($this->model('gpt-6.1-sol'), 3000, 500, $this->pricing);

        $this->assertSame(4000, $budget->maxOutputTokens);
        $this->assertFalse($budget->isCapped);
        // (3.000 × $2 + 4.000 × $10) / 1M × 2,5 × 17.950 ÷ 120 = 17,20208 → 17,2021 NK
        $this->assertSame(17.2021, $budget->holdCredits);
    }

    public function test_small_credits_shorten_the_answer_instead_of_refusing(): void
    {
        // Advanced: 1 token output = $50/1M × 3 × 17.950 ÷ 120 = 0,0224 NK
        $budget = RequestBudget::plan($this->model('gpt-6-astra'), 3000, 50, $this->pricing);

        $this->assertTrue($budget->isCapped);
        $this->assertGreaterThan(RequestBudget::MIN_OUTPUT_TOKENS, $budget->maxOutputTokens);
        $this->assertLessThan(4000, $budget->maxOutputTokens);
        $this->assertLessThanOrEqual(50.0, $budget->holdCredits);
    }

    public function test_too_little_credit_for_a_minimal_answer_is_refused(): void
    {
        $this->expectException(InsufficientBudgetException::class);
        $this->expectExceptionMessage('Normkredit tidak cukup untuk model Advanced');

        RequestBudget::plan($this->model('gpt-6-astra'), 3000, 5, $this->pricing);
    }

    public function test_per_request_cap_still_applies_to_rich_groups(): void
    {
        // Minimal Advanced (3.000 input + 300 output) = 20,19 NK; batas 40 NK memotong output.
        $this->pricing->update(['max_credits_per_request' => 40]);

        $budget = RequestBudget::plan($this->model('gpt-6-astra'), 3000, 1000, $this->pricing);

        $this->assertTrue($budget->isCapped);
        $this->assertLessThanOrEqual(40.0, $budget->holdCredits);
    }

    public function test_advanced_answer_is_shortened_to_credit_above_the_group_capital(): void
    {
        Event::fake([MessageSent::class, TypingStatus::class]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban panjang ...'], 'finish_reason' => 'length']],
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 500],
        ])]);

        // Advanced terbuka di 500 NK lalu turun ke 330: hanya 80 NK di atas modal 250 yang boleh dipakai.
        [$group, $member] = $this->groupWithCredits(500, 'gpt-6-astra');
        $wallet = app(CreditWallet::class);
        $wallet->settle($wallet->hold($group, $member, 170), 170);
        $this->ask($group, $member, '@normi jelaskan arsitektur microservice');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-astra'
            && $request['max_completion_tokens'] < 4000
            && $request['max_completion_tokens'] > RequestBudget::MIN_OUTPUT_TOKENS);

        // Biaya aktual 3.000/500 token Advanced = 24,6813 NK, dipotong dari kredit grup.
        $this->assertSame(24.6813, AiUsageLog::firstOrFail()->charged_credits);
        $this->assertSame(305.3187, app(CreditWallet::class)->balanceFor($group, $member)->groupTotal);
        $this->assertStringContainsString('Jawaban dipersingkat', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    private function model(string $modelId): AiModel
    {
        return AiModel::where('model_id', $modelId)->firstOrFail();
    }

    /** @return array{0: Group, 1: User} grup dengan $credits NK kredit grup dan satu member */
    private function groupWithCredits(float $credits, string $modelId): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => $modelId,
        ]);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role_id' => Role::forKey(Role::MEMBER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        app(CreditWallet::class)->deposit($group, $credits);

        return [$group, $member];
    }

    private function ask(Group $group, User $sender, string $content): void
    {
        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'user',
            'sender_id' => $sender->id,
            'message_type' => 'text',
            'content' => $content,
        ]);
        ChatMessageQueue::create(['group_id' => $group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);

        (new ProcessGroupChatQueueJob($group->id))->handle();
    }
}
