<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gambar dibuat dalam kategori yang dipilih: model kategori menyusun instruksi,
 * mesin gambar internal kategori itu membuat gambarnya, dan keduanya ditagih nyata.
 */
class ImageRoutingTest extends TestCase
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
            'name' => 'Kelas Desain',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        app(CreditWallet::class)->deposit($this->group, 500);
    }

    public function test_fast_category_plans_with_luna_and_draws_with_flare(): void
    {
        $this->fakeOpenAi(['prompt' => 'An orange cat, watercolor', 'precise' => false, 'caption' => 'Kucing oranye cat air.']);

        $this->ask('@normi buatkan gambar kucing oranye');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'gpt-6-luna'
            && $request['response_format'] === ['type' => 'json_object']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/generations')
            && $request['model'] === 'gpt-image-2.5-flare'
            && $request['prompt'] === 'An orange cat, watercolor'
            && $request['quality'] === 'medium');

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('image', $reply->message_type);
        $this->assertSame('🎨 Kucing oranye cat air.', $reply->content);
        Storage::disk('normchat_attachments')->assertExists($reply->attachment_path);

        // Penyusun (Luna): (500 × $0,10 + 100 × $0,50) / 1M = $0,0001
        // Flare: (20 × $5 + 1.056 × $30) / 1M = $0,03178
        // ($0,0001 + $0,03178) × 2 × 17.950 ÷ 120 = 9,537 → 9,54 NK
        $log = AiUsageLog::firstOrFail();
        $this->assertSame(['image', 'gpt-6-luna', 9.54, 2.0], [$log->kind, $log->model, $log->charged_credits, $log->multiplier]);
        $this->assertSame(['image_planner', 'image'], array_column($log->breakdown_json['lines'], 'component'));
        $this->assertSame('gpt-image-2.5-flare', $log->breakdown_json['lines'][1]['model']);
    }

    public function test_smart_category_uses_sunburst_only_when_precision_is_needed(): void
    {
        $this->group->update(['ai_model' => 'gpt-6.1-sol']);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push($this->plannerBody(['prompt' => 'Poster with the text NORMCHAT', 'precise' => true]))
                ->push($this->plannerBody(['prompt' => 'A sunset over the sea', 'precise' => false])),
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
        ]);

        $this->ask('@normi buatkan gambar poster bertuliskan NORMCHAT');
        $this->ask('@normi buatkan gambar senja di laut');

        $engines = collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/images/generations'))
            ->map(fn ($pair) => [$pair[0]['prompt'], $pair[0]['model']])
            ->values()
            ->all();
        $this->assertSame([
            ['Poster with the text NORMCHAT', 'gpt-image-2.5-sunburst'],
            ['A sunset over the sea', 'gpt-image-2.5-flare'],
        ], $engines);
    }

    public function test_advanced_category_draws_with_sunburst_and_is_billed_at_x3(): void
    {
        $this->group->update(['ai_model' => 'gpt-6-astra']);
        $this->fakeOpenAi(['prompt' => 'Game character concept art', 'precise' => false]);

        $this->ask('@normi buatkan gambar karakter game');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/chat/completions') && $request['model'] === 'gpt-6-astra');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/generations') && $request['model'] === 'gpt-image-2.5-sunburst');
        $this->assertSame(3.0, AiUsageLog::firstOrFail()->multiplier);
    }

    public function test_replying_to_an_image_edits_it_with_the_reference(): void
    {
        Storage::disk('normchat_attachments')->put('group-1/foto.png', 'original-png');
        $photo = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'image',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/foto.png',
            'attachment_mime' => 'image/png',
            'attachment_original_name' => 'foto.png',
        ]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => $this->plannerResponse(['prompt' => 'Make the background blue', 'use_reference' => true]),
            'api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('edited-png')]]]),
        ]);

        $this->ask('@normi gambarkan ulang foto ini dengan latar biru', $photo->id);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/edits')
            && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'image[]' && ($part['contents'] ?? null) === 'original-png'));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/images/generations'));

        $image = collect(AiUsageLog::firstOrFail()->breakdown_json['lines'])->firstWhere('component', 'image');
        $this->assertSame(1000, $image['tokens']['image_input_tokens']);
    }

    public function test_planner_failure_falls_back_to_the_request_text(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'down'], 500),
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
        ]);

        $this->ask('@normi buatkan gambar rumah adat Toraja');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/generations') && $request['prompt'] === 'rumah adat Toraja');
        $this->assertSame(['image'], array_column(AiUsageLog::firstOrFail()->breakdown_json['lines'], 'component'));
    }

    public function test_missing_image_usage_is_estimated_and_marked(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => $this->plannerResponse(['prompt' => 'A red bicycle']),
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
        ]);

        $this->ask('@normi buatkan gambar sepeda merah');

        $image = collect(AiUsageLog::firstOrFail()->breakdown_json['lines'])->firstWhere('component', 'image');
        $this->assertSame(1056, $image['tokens']['output_tokens']);
        $this->assertTrue($image['tokens']['estimated']);
    }

    public function test_failed_image_is_not_charged(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => $this->plannerResponse(['prompt' => 'A red bicycle']),
            'api.openai.com/v1/images/generations' => Http::response(['error' => ['code' => 'server_error']], 500),
        ]);

        $this->ask('@normi buatkan gambar sepeda merah');

        $this->assertSame(0, AiUsageLog::count());
        $this->assertSame(500.0, $this->group->groupToken->fresh()->credits);
        $this->assertSame(0.0, (float) $this->group->groupToken->fresh()->reserved_credits);
        $this->assertSame('Gagal membuat gambar. Coba lagi nanti.', Message::where('sender_type', 'ai')->value('content'));
    }

    private function fakeOpenAi(array $plan): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => $this->plannerResponse($plan),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('png-bytes')]],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 1056],
            ]),
        ]);
    }

    private function plannerResponse(array $plan): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response($this->plannerBody($plan));
    }

    private function plannerBody(array $plan): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode($plan)], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 100],
        ];
    }

    private function ask(string $content, ?int $replyTo = null): void
    {
        $message = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'text',
            'content' => $content,
            'reply_to_message_id' => $replyTo,
        ]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
