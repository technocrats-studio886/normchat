<?php

namespace Tests\Feature;

use App\Services\Ai\OpenAiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Error sesaat OpenAI dicoba ulang, error permintaan tidak (eval 23 Nov 2026). */
class OpenAiRetryTest extends TestCase
{
    public function test_server_errors_and_rate_limits_are_retried(): void
    {
        config()->set('services.openai.retry_ms', [1, 1]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::sequence()
            ->push(['error' => ['message' => 'server_error']], 500)
            ->push(['error' => ['message' => 'rate']], 429)
            ->push(['choices' => [['message' => ['content' => 'halo'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);

        $completion = (new OpenAiClient('sk-test'))->chat(['model' => 'gpt-6-luna', 'messages' => []]);

        $this->assertTrue($completion->successful);
        $this->assertSame('halo', $completion->text);
        Http::assertSentCount(3);
    }

    public function test_bad_requests_are_not_retried(): void
    {
        config()->set('services.openai.retry_ms', [1, 1]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'bad']], 400)]);

        $completion = (new OpenAiClient('sk-test'))->chat(['model' => 'gpt-6-luna', 'messages' => []]);

        $this->assertFalse($completion->successful);
        Http::assertSentCount(1);
    }
}
