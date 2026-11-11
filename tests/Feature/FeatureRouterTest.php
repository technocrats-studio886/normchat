<?php

namespace Tests\Feature;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Jobs\SendMessagePushJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\FeatureRouter;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "@normi biasa" langsung memakai fiturnya di balik layar (keputusan user 12 Nov 2026). */
class FeatureRouterTest extends TestCase
{
    use RefreshDatabase;

    private User $alex;

    private User $ariell;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        Queue::fake([SendMessagePushJob::class]);
        Storage::fake('normchat_attachments');

        $this->alex = User::factory()->create(['name' => 'Alex']);
        $this->ariell = User::factory()->create(['name' => 'Ariell']);
        $this->group = Group::create(['name' => 'Gugur Ganjal', 'owner_id' => $this->alex->id, 'password_hash' => Hash::make('1'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
        foreach ([[$this->alex, Role::OWNER], [$this->ariell, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_clear_requests_go_straight_to_their_feature_without_any_extra_ai_call(): void
    {
        Http::fake();
        $cases = [
            '@normi buatkan saya website portofolio extreme coding' => AiMode::Canvas,
            '@normi bikinin game ular tangga buat adikku' => AiMode::Canvas,
            'tolong @normi buat kalkulator BMI' => AiMode::Canvas,
            '@normi riset dampak AI ke pendidikan di Indonesia' => AiMode::DeepResearch,
            '@normi carikan jurnal terbaru soal stunting' => AiMode::DeepResearch,
            '@normi ajari aku integral dari nol' => AiMode::GuidedLearning,
            '@normi aku mau belajar bahasa Jepang dari dasar' => AiMode::GuidedLearning,
            '@normi buatkan gambar kucing oranye' => AiMode::Image,
            '@normi buatkan pdf notulen rapat tadi' => AiMode::File,
        ];
        foreach ($cases as $text => $mode) {
            $this->assertSame($mode, $this->route($this->say($this->alex, $text))['mode'] ?? null, $text);
        }
        $this->assertNull($this->route($this->say($this->alex, '@normi besok kita makan di mana enaknya?')));
        Http::assertNothingSent();
    }

    public function test_doubtful_requests_are_decided_by_the_cheapest_model_for_free(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"feature":"chat"}']]], 'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 5]])
            ->push(['choices' => [['message' => ['content' => '{"feature":"image"}']]], 'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 5]]),
        ]);

        $this->assertNull($this->route($this->say($this->alex, '@normi gimana cara bikin website yang bagus?')));
        $this->assertSame(AiMode::Image, $this->route($this->say($this->alex, '@normi logo warung kopi minimalis warna hijau'))['mode']);

        Http::assertSent(fn (Request $request) => str_contains((string) $request['messages'][0]['content'], 'Tentukan fitur Normi Chat'));
        $logs = AiUsageLog::where('kind', FeatureRouter::KIND)->get();
        $this->assertCount(2, $logs);
        $this->assertSame(0.0, (float) $logs->sum('charged_credits'), 'Router dibayar platform, bukan NK grup.');
    }

    public function test_short_reply_reads_the_request_it_answers(): void
    {
        // Kasus nyata 11 Nov 2026: "jawabkn dl ini @normi" membalas permintaan website dari Ariell.
        Http::fake();
        $request = $this->say($this->ariell, '@normi buatkan saya website portofolio meliputi extreme coding');
        $pointer = $this->say($this->alex, 'jawabkn dl ini @normi', $request);

        $this->assertSame(AiMode::Canvas, $this->route($pointer)['mode']);
    }

    public function test_photo_edit_with_plain_tag_edits_the_photo_directly(): void
    {
        $this->fakeImage();
        $photo = $this->photo($this->alex, 'Bikin dasi nya menjadi warna merah @normi');

        $this->runAi($photo);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits'));
        $reply = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('image', $reply->message_type);
        $this->assertStringNotContainsStringIgnoringCase('canva', (string) $reply->content);
        $this->assertNull($reply->system_meta['suggest_mode'] ?? null, 'Tidak lagi diarahkan lewat tombol.');
    }

    public function test_photo_sent_just_before_is_edited_without_replying_to_it(): void
    {
        $this->fakeImage();
        $this->photo($this->alex, '');
        $ask = $this->say($this->alex, '@normi ganti latarnya jadi putih');

        $this->runAi($ask);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits'));
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/images/generations'));
    }

    public function test_choosing_a_feature_yourself_still_works_as_designed(): void
    {
        Http::fake();
        $message = $this->say($this->alex, '@normi-canvas jawabkn dl ini');
        $message->update(['ai_mode' => AiMode::Canvas]);
        // Mode dari menu/tag tidak lewat router sama sekali.
        $this->assertSame(AiMode::Canvas, $message->fresh()->ai_mode);
        Http::assertNothingSent();
    }

    private function route(Message $message): ?array
    {
        return app(FeatureRouter::class)->route($this->group, $message->fresh(['replyToMessage']));
    }

    private function say(User $user, string $content, ?Message $replyTo = null): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text',
            'content' => $content, 'reply_to_message_id' => $replyTo?->id]);
    }

    private function photo(User $user, string $caption): Message
    {
        $path = 'group-' . $this->group->id . '/foto-' . uniqid() . '.jpg';
        Storage::disk('normchat_attachments')->put($path, 'jpeg-bytes');

        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'image',
            'content' => $caption !== '' ? $caption : null, 'attachment_disk' => 'normchat_attachments', 'attachment_path' => $path, 'attachment_mime' => 'image/jpeg']);
    }

    private function fakeImage(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['prompt' => 'make the tie red', 'precise' => true, 'use_reference' => true, 'caption' => 'Sudah kuedit.'])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
            'api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('png-bytes')]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 100]]),
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('png-bytes')]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 100]]),
            'api.openai.com/v1/embeddings' => Http::response([], 500),
        ]);
    }

    private function runAi(Message $message): void
    {
        ChatMessageQueue::enqueue($message);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
