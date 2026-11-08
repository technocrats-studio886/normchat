<?php

namespace Tests\Feature;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\Context\ContextUnderstanding;
use App\Services\Chat\MentionNotifier;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tag Normi per fitur (laporan user 9 Nov 2026): chat biasa @normi, fitur @normi-image, @normi-canvas,
 * @normi-research, @normi-learn; status Normi sesuai pekerjaannya (bukan selalu "sedang mengetik");
 * jawaban fitur ditandai modenya agar balasan ke jawaban itu menanyakan dulu sebelum memakai fiturnya lagi.
 */
class NormiFeatureTagsTest extends TestCase
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

        $this->owner = User::factory()->create(['tour_seen_at' => now()]);
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_each_feature_has_its_own_tag(): void
    {
        $this->assertSame('@normi-image', AiMode::Image->tag());
        $this->assertSame('@normi-canvas', AiMode::Canvas->tag());
        $this->assertSame('@normi-research', AiMode::DeepResearch->tag());
        $this->assertSame('@normi-learn', AiMode::GuidedLearning->tag());

        $this->assertSame(AiMode::Image, AiMode::fromContent('@normi-image kucing oranye'));
        $this->assertSame(AiMode::DeepResearch, AiMode::fromContent('tolong @NORMI-RESEARCH tren EV 2026'));
        $this->assertNull(AiMode::fromContent('@normi halo'));
        $this->assertNull(AiMode::fromContent('@normi-imagex kucing'));
        $this->assertNull(AiMode::fromContent('email@normi-image.com'));

        // Semua tag tetap memanggil Normi.
        $this->assertTrue(Message::isAiPrompt('@normi-canvas buat landing page'));
    }

    public function test_typing_a_feature_tag_selects_that_feature(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)
            ->postJson(route('chat.store', $this->group), ['content' => '@normi-image kucing oranye'])
            ->assertCreated();

        $message = Message::where('sender_type', 'user')->firstOrFail();
        $this->assertSame(AiMode::Image, $message->ai_mode);
        $this->assertSame('image', $response->json('message.ai_mode'));
        $this->assertTrue($response->json('message.awaiting_ai'));
    }

    public function test_menu_choice_wins_over_a_plain_mention(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson(route('chat.store', $this->group), ['content' => '@normi-research tren EV', 'ai_mode' => 'deep_research'])
            ->assertCreated();

        $this->assertSame(AiMode::DeepResearch, Message::where('sender_type', 'user')->firstOrFail()->ai_mode);
    }

    public function test_image_requests_show_an_image_status_and_mark_the_answer(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['prompt' => 'a cat', 'precise' => false, 'use_reference' => false, 'caption' => 'Kucing'])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('png-bytes')]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 100],
            ]),
        ]);

        $this->runAi('@normi-image kucing oranye di jendela');

        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->isTyping && $event->activity === 'image');
        Event::assertNotDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->isTyping && $event->activity === 'typing');

        $answer = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('image', $answer->message_type);
        $this->assertSame(AiMode::Image, $answer->ai_mode);
        $this->assertSame('image', app(MessagePresenter::class)->present($answer)['ai_mode']);
    }

    public function test_text_answers_show_typing_and_carry_no_feature(): void
    {
        $this->fakeChat('Halo juga!');

        $this->runAi('@normi halo');

        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->isTyping && $event->activity === 'typing'
            && $event->broadcastWith()['activity'] === 'typing');
        $answer = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertNull($answer->ai_mode);
        $this->assertNull(app(MessagePresenter::class)->present($answer)['ai_mode']);
    }

    public function test_replying_to_a_feature_answer_without_choosing_it_again_answers_as_chat(): void
    {
        $this->fakeChat('Ringkasnya begini.');
        $request = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id,
            'content' => '@normi-research tren EV', 'ai_mode' => AiMode::DeepResearch]);
        $research = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'reply_to_message_id' => $request->id,
            'content' => 'Hasil riset...', 'ai_mode' => AiMode::DeepResearch]);

        // Pengguna memilih "Tidak" di kartu konfirmasi: balasan tanpa tag fitur.
        $this->runAi('jelaskan poin kedua', ['reply_to_message_id' => $research->id]);

        $answer = Message::where('sender_type', 'ai')->whereKeyNot($research->id)->latest('id')->firstOrFail();
        $this->assertSame('Ringkasnya begini.', $answer->content);
        $this->assertNull($answer->ai_mode);
        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->isTyping && $event->activity === 'typing');
    }

    public function test_feature_tags_are_suggested_and_stripped_from_the_question(): void
    {
        $suggestions = collect(app(MentionNotifier::class)->suggestionsFor($this->group, $this->owner));
        $this->assertSame(['@normi', '@normi-learn', '@normi-image', '@normi-canvas', '@normi-research'],
            $suggestions->where('type', 'ai')->pluck('insert')->all());

        $question = new Message(['content' => '@normi-research tren mobil listrik 2026']);
        $this->assertSame('tren mobil listrik 2026', ContextUnderstanding::question($question));
    }

    private function fakeChat(string $content): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ])]);
    }

    private function runAi(string $content, array $attributes = []): void
    {
        $message = Message::create(array_merge([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'text',
            'content' => $content,
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
