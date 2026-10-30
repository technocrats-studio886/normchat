<?php

namespace Tests\Feature;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Exceptions\RequestTooLargeException;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiRequestRefusal;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\CreditHold;
use App\Models\Group;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Ai\Context\TimeRange;
use App\Services\Ai\Tokens;
use App\Services\Credits\CreditWallet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * llm-rules bagian 5.3, 5.6, dan 7 (K18–K21): pesan sumber topik, rentang waktu di pertanyaan,
 * penanda "Normi gagal mengingat", dan batas keras token input per permintaan.
 */
class LlmRecallTest extends TestCase
{
    use RefreshDatabase;

    private User $noel;

    private User $sari;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        // Rabu, 28 Oktober 2026 pukul 14.00 WIB.
        $this->travelTo(CarbonImmutable::parse('2026-10-28 14:00', 'Asia/Jakarta'));

        $this->noel = User::factory()->create(['name' => 'Noel']);
        $this->sari = User::factory()->create(['name' => 'Sari']);
        $this->group = Group::create(['name' => 'Panitia', 'owner_id' => $this->noel->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    // ── K19: kata waktu ────────────────────────────────────

    public function test_time_phrases_become_ranges_in_the_askers_zone(): void
    {
        $now = CarbonImmutable::parse('2026-10-28 14:00', 'Asia/Jakarta');
        $wib = fn (?TimeRange $range) => $range === null ? null : [
            $range->from->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            $range->to->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
        ];

        $this->assertSame(['2026-10-27 00:00', '2026-10-28 00:00'], $wib(TimeRange::in('@normi kemarin bahas apa?', $now)));
        $this->assertSame(['2026-10-26 00:00', '2026-10-27 00:00'], $wib(TimeRange::in('yang kemarin lusa itu', $now)));
        $this->assertSame(['2026-10-27 18:00', '2026-10-28 05:00'], $wib(TimeRange::in('tadi malam ngomongin apa', $now)));
        $this->assertSame(['2026-10-28 04:00', '2026-10-28 11:00'], $wib(TimeRange::in('tadi pagi', $now)));
        $this->assertSame(['2026-10-19 00:00', '2026-10-26 00:00'], $wib(TimeRange::in('keputusan minggu lalu', $now)));
        $this->assertSame(['2026-10-19 00:00', '2026-10-26 00:00'], $wib(TimeRange::in('@normi minggu kemarin kita sepakat apa?', $now)));
        $this->assertSame(['2026-09-01 00:00', '2026-10-01 00:00'], $wib(TimeRange::in('bulan kemarin', $now)));
        $this->assertSame(['2026-09-01 00:00', '2026-10-01 00:00'], $wib(TimeRange::in('bulan lalu', $now)));
        $this->assertSame(['2026-10-25 00:00', '2026-10-26 00:00'], $wib(TimeRange::in('3 hari yang lalu', $now)));
        $this->assertSame(['2026-10-05 00:00', '2026-10-06 00:00'], $wib(TimeRange::in('rapat tanggal 5', $now)));
        $this->assertSame(['2026-09-30 00:00', '2026-10-01 00:00'], $wib(TimeRange::in('tanggal 30', $now)));
        $this->assertSame(['2025-11-05 00:00', '2025-11-06 00:00'], $wib(TimeRange::in('tanggal 5 november', $now)));
        $this->assertSame(['2026-10-26 00:00', '2026-10-27 00:00'], $wib(TimeRange::in('rapat Senin', $now)));
        $this->assertSame(['2026-10-21 00:00', '2026-10-22 00:00'], $wib(TimeRange::in('hari Rabu', $now)), 'Hari yang sama dengan hari ini = pekan lalu.');
        $this->assertSame(['2026-10-25 00:00', '2026-10-26 00:00'], $wib(TimeRange::in('hari minggu', $now)));

        foreach (['apa arti optimizer?', 'rencana minggu depan', 'dua minggu sekali', 'tanggal 45', 'itu tadi sudah benar?'] as $question) {
            $this->assertNull(TimeRange::in($question, $now), $question);
        }
    }

    public function test_question_naming_only_a_day_brings_messages_from_that_day(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        $this->sayAt('2026-10-20 10:00', $this->noel, 'Pembukaan acara jam 8 pagi.');
        $yesterday = $this->sayAt('2026-10-27 15:00', $this->sari, 'Konsumsi dipesan dari katering Bu Ani.');
        foreach (range(1, 12) as $n) {
            $this->say($this->sari, "obrolan pengisi nomor {$n}");
        }
        $this->fakeOpenAi([json_encode(['maksud' => 'menanyakan isi pembahasan kemarin', 'perlu_konteks_lama' => true, 'kata_pencarian' => [], 'topik' => [], 'kejelasan' => 'jelas'])]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->noel, '@normi kemarin kita bahas apa?'));

        $this->assertContains($yesterday->id, $this->ids($context->retrieved));
        $this->assertTrue($context->stats()['searched']);
        $this->assertFalse($context->stats()['recall_failed']);
    }

    public function test_messages_in_the_named_range_rank_above_other_matches(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        config()->set('normchat.context.retrieval_messages', 1);
        $inRange = $this->sayAt('2026-10-27 09:00', $this->sari, 'Anggaran konsumsi disetujui 5 juta.');
        foreach (range(1, 6) as $n) {
            $this->sayAt("2026-10-0{$n} 09:00", $this->noel, "Anggaran konsumsi usulan versi {$n}.");
        }
        foreach (range(1, 12) as $n) {
            $this->say($this->sari, "obrolan pengisi nomor {$n}");
        }
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $withDay = app(ContextManager::class)->build($this->group, $this->say($this->noel, '@normi anggaran konsumsi kemarin berapa?'));
        $withoutDay = app(ContextManager::class)->build($this->group, $this->say($this->noel, '@normi anggaran konsumsi berapa?'));

        $this->assertContains($inRange->id, $this->ids($withDay->retrieved), 'Rentang "kemarin" menaikkan pesan tanggal 27.');
        $this->assertNotContains($inRange->id, $this->ids($withoutDay->retrieved), 'Tanpa kata waktu, pesan lain yang menang.');
    }

    // ── K18: pesan sumber topik ────────────────────────────

    public function test_selected_topic_brings_its_source_messages_even_without_search(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        $source = $this->say($this->sari, 'Final: optimizer AdamW, learning rate 0,0003, batch 32.');
        $topic = GroupContextTopic::create([
            'group_id' => $this->group->id, 'title' => 'Optimizer', 'summary' => 'Sepakat memakai AdamW.',
            'first_message_id' => $source->id, 'last_message_id' => $source->id, 'key_message_ids' => [$source->id],
            'last_activity_at' => now(),
        ]);
        foreach (range(1, 12) as $n) {
            $this->say($this->sari, "obrolan pengisi nomor {$n} soal optimizer");
        }
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->noel, '@normi soal optimizer gimana?'));

        $this->assertSame([$topic->id], array_map(fn (GroupContextTopic $t) => $t->id, $context->topics));
        $this->assertContains($source->id, $this->ids($context->retrieved), 'Angka persis dari pesan asli, bukan hanya ringkasan.');
        $this->assertFalse($context->stats()['searched']);
    }

    // ── K20: gagal mengingat ───────────────────────────────

    public function test_answer_logs_recall_failure_when_the_search_finds_nothing(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        foreach (range(1, 12) as $n) {
            $this->say($this->sari, "obrolan pengisi nomor {$n}");
        }
        // Pemeliharaan ingatan di antara dua pertanyaan tidak ikut memakai jawaban tiruan.
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SummarizeGroupContextJob::class, \App\Jobs\EmbedGroupMessagesJob::class]);
        $this->fakeOpenAi(['Aku tidak menemukan pembahasan itu.', 'Proyektor biasanya disewa per hari.']);

        $this->ask('@normi kemarin kita sepakat harga sewa proyektor berapa?');
        $this->ask('@normi berapa harga sewa proyektor biasanya?');

        [$referring, $general] = AiUsageLog::where('kind', 'chat')->orderBy('id')->get()->map(fn (AiUsageLog $log) => $log->breakdown_json['context'])->all();
        $this->assertTrue($referring['searched']);
        $this->assertTrue($referring['recall_failed'], 'Merujuk pembicaraan sebelumnya tapi tidak ketemu.');
        $this->assertTrue($general['searched'], 'Kata kunci di luar jendela tetap dicari.');
        $this->assertFalse($general['recall_failed'], 'Pertanyaan umum yang tidak ketemu bukan gagal mengingat.');
    }

    public function test_answer_without_search_is_not_a_recall_failure(): void
    {
        $this->say($this->sari, 'Halo semua.');
        $this->fakeOpenAi(['Halo!']);

        $this->ask('@normi halo');

        $context = AiUsageLog::where('kind', 'chat')->firstOrFail()->breakdown_json['context'];
        $this->assertFalse($context['searched']);
        $this->assertFalse($context['recall_failed']);
        $this->assertFalse($context['trimmed']);
    }

    // ── K21: batas input ───────────────────────────────────

    public function test_context_is_trimmed_to_the_input_limit_keeping_the_newest_messages(): void
    {
        config()->set('normchat.pricing.max_input_tokens', 2000);
        foreach (range(1, 40) as $n) {
            $this->say($this->sari, "pesan nomor {$n}: " . str_repeat('isi diskusi panitia ', 10));
        }
        $this->fakeOpenAi(['Jawaban']);

        $this->ask('@normi rangkum');

        $sent = $this->chatRequests()[0]['messages'];
        $this->assertLessThanOrEqual(2000, Tokens::messages($sent));
        $prompt = json_encode($sent, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('pesan nomor 40:', $prompt);
        $this->assertStringNotContainsString('pesan nomor 1:', $prompt);
        $this->assertTrue(AiUsageLog::where('kind', 'chat')->firstOrFail()->breakdown_json['context']['trimmed']);
    }

    public function test_question_longer_than_the_limit_is_refused_without_holding_anything(): void
    {
        config()->set('normchat.pricing.max_input_tokens', 2000);
        Http::fake();

        $this->ask('@normi ' . str_repeat('tolong cek paragraf ini ', 600));

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
        $notice = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame(RequestTooLargeException::USER_MESSAGE, $notice->content);
        $this->assertSame($this->noel->id, $notice->visible_to_user_id);
        $this->assertSame(0, CreditHold::count());
        $this->assertSame('too_long', AiRequestRefusal::value('reason'));
    }

    public function test_previous_canvas_under_revision_does_not_count_toward_the_limit(): void
    {
        config()->set('normchat.pricing.max_input_tokens', 2000);
        Storage::fake('normchat_attachments');
        Storage::disk('normchat_attachments')->put('canvas/lama.md', str_repeat("Bab panjang tentang rencana acara.\n", 400));
        $previous = Message::create([
            'group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'canvas', 'content' => 'Rencana acara.',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'canvas/lama.md',
            'attachment_mime' => 'text/markdown', 'attachment_original_name' => 'rencana.md',
        ]);
        $this->fakeOpenAi([json_encode(['kind' => 'document', 'title' => 'Rencana', 'summary' => 'Sudah direvisi.', 'content' => '# Rencana baru'])]);

        $this->ask('@normi tambahkan jadwal', ['reply_to_message_id' => $previous->id, 'ai_mode' => AiMode::Canvas]);

        $prompt = json_encode($this->chatRequests()[0]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Canvas sebelumnya', $prompt);
        $this->assertStringNotContainsString('Isi dokumen yang direply', $prompt, 'Isi Canvas lama hanya dikirim sekali.');
        $this->assertSame(0, AiRequestRefusal::count());
        $this->assertSame('Sudah direvisi.', Message::where('sender_type', 'ai')->latest('id')->value('content'));
    }

    private function say(User $user, string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }

    private function sayAt(string $wib, User $user, string $content): Message
    {
        $message = $this->say($user, $content);
        $message->forceFill(['created_at' => CarbonImmutable::parse($wib, 'Asia/Jakarta')])->saveQuietly();

        return $message->refresh();
    }

    private function ask(string $content, array $extra = []): void
    {
        $message = $this->say($this->noel, $content, $extra);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }

    /** @param list<string> $replies jawaban chat completions berurutan; embedding selalu gagal */
    private function fakeOpenAi(array $replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['id' => 'chatcmpl-uji', 'choices' => [['message' => ['content' => $reply]]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 120]]);
        }
        Http::fake(['api.openai.com/v1/chat/completions' => $sequence, 'api.openai.com/v1/embeddings' => Http::response([], 500)]);
    }

    /** @return list<array> payload request chat completions, urut kirim */
    private function chatRequests(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    /** @param list<Message> $messages @return list<int> */
    private function ids(array $messages): array
    {
        return array_map(fn (Message $message) => (int) $message->id, $messages);
    }
}
