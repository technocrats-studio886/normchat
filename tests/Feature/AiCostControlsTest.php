<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Services\Ai\ChatCompletion;
use App\Services\Ai\RequestBudget;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCostControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_write_lifetimes_are_counted_once_and_reasoning_is_not_double_billed(): void
    {
        $completion = ChatCompletion::fromResponsesPayload([
            'status' => 'completed', 'usage' => ['input_tokens' => 12000, 'output_tokens' => 1000,
                'input_tokens_details' => ['cached_tokens' => 4000, 'cache_write_tokens' => 7000,
                    'cache_write_tokens_details' => ['ttl_30m_tokens' => 3000, 'ttl_12h_tokens' => 4000]],
                'output_tokens_details' => ['reasoning_tokens' => 800]],
        ], 200);
        $this->assertSame(800, $completion->usage['reasoning_tokens']);
        $this->assertSame(4000, $completion->usage['cache_write_12h_tokens']);
        $model = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();
        // 1k ordinary ×2 + 4k cached ×0.1 + 3k write ×2.5 + 4k long write ×5 + 1k output ×10.
        $this->assertEqualsWithDelta(0.0399, $model->costForUsage($completion->usage), 1e-9);
    }

    public function test_research_reservation_includes_tool_cost_without_lowering_funded_output(): void
    {
        $pricing = app(NormkreditPricing::class);
        $model = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();
        $plain = RequestBudget::plan($model, 3000, 500, $pricing);
        $research = RequestBudget::plan($model, 3000, 500, $pricing, extraUsd: 0.05);
        $this->assertSame($plain->maxOutputTokens, $research->maxOutputTokens);
        $this->assertEqualsWithDelta($plain->providerUsd + 0.05, $research->providerUsd, 1e-9);
        $this->assertGreaterThan($plain->holdCredits, $research->holdCredits);
        $this->assertLessThanOrEqual((float) $pricing->get('max_credits_per_request'), $research->holdCredits);
    }
    public function test_cache_writes_have_their_own_price_and_reasoning_is_not_charged_twice(): void
    {
        $completion = ChatCompletion::fromResponsesPayload([
            'id' => 'resp-cost-test', 'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hasil']]]],
            'usage' => ['input_tokens' => 12000, 'output_tokens' => 500,
                'input_tokens_details' => ['cached_tokens' => 4000, 'cache_write_tokens' => 7000,
                    'cache_write_tokens_details' => ['ttl_30m_tokens' => 3000, 'ttl_12h_tokens' => 4000]],
                'output_tokens_details' => ['reasoning_tokens' => 100]],
        ], 200);
        $model = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();
        $this->assertSame(100, $completion->usage['reasoning_tokens']);
        $this->assertSame(7000, $completion->usage['cache_write_tokens']);
        $this->assertSame(4000, $completion->usage['cache_write_12h_tokens']);
        $this->assertEqualsWithDelta(0.0349, $model->costForUsage($completion->usage), 1e-9);
    }

    public function test_chat_usage_keeps_reasoning_and_default_cache_write_lifetime(): void
    {
        $response = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'id' => 'chatcmpl-cost-test', 'choices' => [['message' => ['content' => 'Hasil'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100,
                'prompt_tokens_details' => ['cache_write_tokens' => 800],
                'completion_tokens_details' => ['reasoning_tokens' => 80]],
        ])));
        $completion = ChatCompletion::fromResponse($response);
        $model = AiModel::where('model_id', 'gpt-6.1-sol')->firstOrFail();
        $this->assertSame(80, $completion->usage['reasoning_tokens']);
        $this->assertEqualsWithDelta(0.0034, $model->costForUsage($completion->usage), 1e-9);
    }

    public function test_image_respects_per_request_cap_before_calling_openai(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\MessageSent::class, \App\Events\TypingStatus::class]);
        \Illuminate\Support\Facades\Http::fake();
        config()->set('services.openai.api_key', 'offline');
        app(NormkreditPricing::class)->update(['max_credits_per_request' => 0.0001]);
        $owner = \App\Models\User::factory()->create();
        $group = \App\Models\Group::create(['name' => 'Gambar', 'owner_id' => $owner->id, 'status' => 'active']);
        app(\App\Services\Credits\CreditWallet::class)->deposit($group, 100);
        $message = \App\Models\Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'content' => '@normi-image buat gambar kucing']);
        app(\App\Services\Ai\GroupAiAssistant::class)->handle($message);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame(100.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->assertDatabaseHas('messages', ['reply_to_message_id' => $message->id, 'visible_to_user_id' => $owner->id]);
    }

}
