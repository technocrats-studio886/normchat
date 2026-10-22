<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupToken;
use App\Services\Credits\CreditWallet;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mengunci perilaku job AI per jalur (chat, gambar, dokumen, transkripsi, fallback)
 * dari sisi luar: request ke OpenAI, pesan yang dibuat, dan Normkredit yang ditagih.
 */
class AiChatJobTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        app(CreditWallet::class)->deposit($this->group, $this->owner, 300, 100);
    }

    public function test_message_without_mention_is_ignored(): void
    {
        Http::fake();

        $this->runAi(['content' => 'halo semua']);

        Http::assertNothingSent();
        $this->assertSame(0, Message::where('sender_type', 'ai')->count());
    }

    public function test_document_request_creates_file_and_charges_chat_usage(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => "# Ringkasan\n\nIsi dokumen."]]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
            ]),
        ]);

        $this->runAi(['content' => '@ai buatkan file markdown ringkasan diskusi']);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertNotNull($reply->attachment_path);
        $this->assertStringEndsWith('.md', $reply->attachment_original_name);

        // Cepat: (1.000 × 0,10 + 1.000 × 0,50) / 1M = $0,0006 × 2 × 17.950 ÷ 120 = 0,1795 → 0,18 NK
        $log = AiUsageLog::firstOrFail();
        $this->assertSame('document', $log->kind);
        $this->assertSame(0.18, $log->charged_credits);
    }

    public function test_voice_note_transcription_is_billed_with_the_reply(): void
    {
        Storage::disk('normchat_attachments')->put('group-1/voice.webm', 'audio-bytes');
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'tolong jelaskan normalisasi',
                'usage' => ['type' => 'tokens', 'input_tokens' => 1000, 'output_tokens' => 200],
            ]),
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Normalisasi adalah ...']]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
            ]),
        ]);

        $this->runAi([
            'content' => '@ai',
            'message_type' => 'voice',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/voice.webm',
            'attachment_mime' => 'audio/webm',
            'attachment_original_name' => 'voice.webm',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'audio/transcriptions')
            && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'model'
                && ($part['contents'] ?? null) === 'gpt-4o-mini-transcribe'));

        // Chat $0,0006 × 2 (Cepat) + transkripsi (1.000 × 1,25 + 200 × 5) / 1M = $0,00225 × 2 (media)
        // = $0,0057 × 17.950 ÷ 120 = 0,8526 → 0,86 NK
        $log = AiUsageLog::firstOrFail();
        $transcription = collect($log->breakdown_json['lines'])->firstWhere('component', 'transcription');
        $this->assertEqualsWithDelta(0.00225, $transcription['cost_usd'], 1e-9);
        $this->assertSame(2.0, (float) $transcription['multiplier']);
        $this->assertSame(0.86, $log->charged_credits);
    }

    public function test_failed_model_falls_back_to_default_catalog_model_and_its_price(): void
    {
        $this->group->update(['ai_model' => 'gpt-6-astra']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'The model gpt-6-astra does not exist']], 404)
                ->push([
                    'choices' => [['message' => ['content' => 'Jawaban']]],
                    'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
                ]),
        ]);

        $this->runAi(['content' => '@ai halo']);

        $default = AiModel::defaultModel();
        $log = AiUsageLog::firstOrFail();
        $this->assertSame($default->model_id, $log->model);
        $this->assertSame((float) $default->multiplier, $log->multiplier);
    }

    public function test_failed_response_is_not_charged(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => 'boom'], 500)]);

        $this->runAi(['content' => '@ai halo']);

        $this->assertSame(0, AiUsageLog::count());
        $this->assertSame(300.0, $this->group->groupToken->fresh()->credits);
        $this->assertStringContainsString('AI sedang sibuk', (string) Message::where('sender_type', 'ai')->value('content'));
    }

    private function runAi(array $attributes): void
    {
        $message = Message::create(array_merge([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'text',
        ], $attributes));

        ChatMessageQueue::create([
            'group_id' => $this->group->id,
            'message_id' => $message->id,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
