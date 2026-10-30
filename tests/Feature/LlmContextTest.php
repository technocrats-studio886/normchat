<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * llm-rules bagian 5: Context Manager + Context Understanding Manager. Anggota mengobrol tanpa Normi;
 * saat Normi di-tag, konteksnya disusun dari ringkasan, jendela riwayat, topik, dan pesan lama yang relevan —
 * hanya dari grup itu, tanpa pesan terhapus dan pemberitahuan pribadi.
 */
class LlmContextTest extends TestCase
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

        $this->noel = User::factory()->create(['name' => 'Noel']);
        $this->sari = User::factory()->create(['name' => 'Sari']);
        $this->group = $this->makeGroup('Tugas CNN');
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_small_group_sends_whole_history_with_names_and_nothing_from_elsewhere(): void
    {
        $other = $this->makeGroup('Bisnis');
        $this->say($other, $this->noel, 'harga produk RAHASIA-B');
        $this->say($this->group, $this->noel, 'Kita coba pakai AdamW untuk eksperimen CNN berikutnya.');
        $this->say($this->group, $this->sari, 'Setuju, tapi learning rate-nya mungkin perlu diturunkan.');
        $this->say($this->group, $this->sari, "📊 POLL: Learning rate berapa?\n1. 0,001\n2. 0,0001");
        $this->say($this->group, $this->sari, 'Catatan dari kelas lain', ['forwarded_from_name' => 'Budi']);
        $this->say($this->group, $this->sari, 'TERHAPUS-123')->delete();
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'visible_to_user_id' => $this->sari->id, 'content' => 'NOTICE-PRIBADI']);
        $this->fakeOpenAi(['Menurutku AdamW dengan learning rate 0,0001 layak diuji.']);

        $this->ask('@normi menurutmu bagaimana?');

        $chat = $this->chatRequests();
        $this->assertCount(1, $chat, 'Tanpa riwayat di luar jendela, tidak ada panggilan pemahaman.');
        $prompt = json_encode($chat[0]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Noel: Kita coba pakai AdamW', $prompt);
        $this->assertStringContainsString('Sari: Setuju, tapi learning rate', $prompt);
        $this->assertStringContainsString('Polling: Learning rate berapa? — pilihan: 1. 0,001, 2. 0,0001', $prompt);
        $this->assertStringContainsString('(diteruskan dari Budi) Catatan dari kelas lain', $prompt);
        $this->assertStringContainsString('Noel bertanya ke Normi', $prompt);
        $this->assertStringNotContainsString('RAHASIA-B', $prompt);
        $this->assertStringNotContainsString('TERHAPUS-123', $prompt);
        $this->assertStringNotContainsString('NOTICE-PRIBADI', $prompt);
        $this->assertSame('normchat-group-' . $this->group->id, $chat[0]['prompt_cache_key']);
    }

    public function test_summary_comes_before_the_window_which_starts_at_the_anchor(): void
    {
        $messages = collect(range(1, 10))->map(fn (int $n) => $this->say($this->group, $this->sari, "pesan-ke-{$n}"));
        GroupContextSummary::create([
            'group_id' => $this->group->id,
            'summary' => 'RINGKASAN-UJI: grup memilih AdamW.',
            'covered_until_message_id' => $messages[7]->id,
            'window_start_message_id' => $messages[5]->id,
        ]);
        $this->fakeOpenAi(['Jawaban']);

        $this->ask('@normi lanjut');

        $sent = $this->chatRequests()[0]['messages'];
        $this->assertSame('system', $sent[1]['role']);
        $this->assertStringContainsString('RINGKASAN-UJI', $sent[1]['content']);
        $history = json_encode(array_slice($sent, 2, -1), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('pesan-ke-6', $history);
        $this->assertStringNotContainsString('pesan-ke-5"', $history);
        $this->assertStringContainsString('pesan-ke-10', $history);
    }

    public function test_stale_summary_is_not_used(): void
    {
        $first = $this->say($this->group, $this->sari, 'pesan-awal');
        $this->say($this->group, $this->sari, 'pesan-kedua');
        GroupContextSummary::create([
            'group_id' => $this->group->id,
            'summary' => 'RINGKASAN-BASI',
            'covered_until_message_id' => $first->id,
            'window_start_message_id' => $first->id,
            'stale_at' => now(),
        ]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->group, $this->noel, '@normi apa kabar'));

        $this->assertNull($context->summary);
        $this->assertSame(['pesan-awal', 'pesan-kedua'], array_map(fn (Message $m) => $m->content, $context->window));
    }

    public function test_context_is_built_as_of_the_question_for_regenerated_answers(): void
    {
        $this->say($this->group, $this->sari, 'sebelum pertanyaan');
        $question = $this->say($this->group, $this->noel, '@normi rangkum diskusi');
        $this->say($this->group, $this->sari, 'SESUDAH-PERTANYAAN');

        $context = app(ContextManager::class)->build($this->group, $question);

        $this->assertSame(['sebelum pertanyaan'], array_map(fn (Message $m) => $m->content, $context->window));
    }

    public function test_keyword_search_finds_old_messages_outside_the_window_in_this_group_only(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        $other = $this->makeGroup('Lain');
        $this->say($other, $this->noel, 'AdamW dipakai di grup lain');
        $before = $this->say($this->group, $this->sari, 'Kita perlu optimizer yang stabil.');
        $old = $this->say($this->group, $this->noel, 'Hasil validasi AdamW paling stabil, pakai learning rate 0,0001.');
        $after = $this->say($this->group, $this->sari, 'Oke, setuju.');
        $this->say($this->group, $this->sari, 'AdamW terhapus')->delete();
        foreach (range(1, 12) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} tentang jadwal piket");
        }
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->group, $this->noel, '@normi AdamW kemarin hasilnya gimana?'));

        $ids = array_map(fn (Message $m) => $m->id, $context->retrieved);
        $this->assertSame([$before->id, $old->id, $after->id], $ids, 'Pesan relevan beserta tetangganya, urut waktu.');
        $this->assertNotContains('AdamW dipakai di grup lain', array_map(fn (Message $m) => $m->content, $context->retrieved));
    }

    public function test_semantic_search_finds_old_messages_with_different_words(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        $old = $this->say($this->group, $this->noel, 'Menurutku kita pakai AdamW saja karena hasil validasinya lebih stabil.');
        $this->embed($old, 0);
        foreach (range(1, 12) as $n) {
            $this->embed($this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} tentang jadwal piket"), 1);
        }
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => $this->vector(0)]], 'usage' => ['prompt_tokens' => 12]])]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->group, $this->noel, '@normi jadi optimizer yang dipakai apa?'));

        $this->assertContains($old->id, array_map(fn (Message $m) => $m->id, $context->retrieved));
        $this->assertSame(1, AiUsageLog::where('kind', 'embedding')->where('charged_credits', 0)->count());
    }

    public function test_ambiguous_reference_asks_luna_once_and_answers_with_what_it_finds(): void
    {
        config()->set('normchat.context.window_tokens', 80);
        $this->say($this->group, $this->noel, 'Pilihan database: MongoDB atau PostgreSQL.');
        $reason = $this->say($this->group, $this->sari, 'Kita pilih PostgreSQL karena transaksinya lebih aman.');
        foreach (range(1, 10) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} soal pembagian tugas");
        }
        $this->say($this->group, $this->noel, 'Teknologi sudah beres, lanjut bahas jadwal.');
        $this->fakeOpenAi([
            json_encode(['maksud' => 'menanyakan alasan grup memilih PostgreSQL dibanding MongoDB', 'perlu_konteks_lama' => true,
                'kata_pencarian' => ['alasan pilih PostgreSQL'], 'topik' => [], 'kejelasan' => 'jelas']),
            'Karena transaksi PostgreSQL lebih aman, seperti kata Sari.',
        ]);

        $this->ask('@normi kenapa kita akhirnya memilih teknologi yang kedua?');

        $chat = $this->chatRequests();
        $this->assertCount(2, $chat);
        $this->assertSame(['type' => 'json_object'], $chat[0]['response_format']);
        $main = json_encode($chat[1]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Kita pilih PostgreSQL karena transaksinya lebih aman.', $main);
        $this->assertStringContainsString('Maksud pertanyaan menurut konteks: menanyakan alasan grup memilih PostgreSQL', $main);

        // Pemahaman dibayar dari saldo OpenAI; NC hanya untuk jawaban.
        $this->assertSame(0.0, AiUsageLog::where('kind', 'context_understanding')->value('charged_credits'));
        $this->assertSame(1, CreditLedgerEntry::where('type', 'settle')->count());
        $this->assertTrue(AiUsageLog::where('kind', 'chat')->firstOrFail()->breakdown_json['context']['understanding']);
        $this->assertGreaterThan(0, AiUsageLog::where('kind', 'chat')->firstOrFail()->breakdown_json['context']['retrieved']);
        $this->assertNotNull($reason);
    }

    public function test_understanding_failure_falls_back_to_the_rules(): void
    {
        config()->set('normchat.context.window_tokens', 80);
        $this->say($this->group, $this->sari, 'Kita pilih PostgreSQL karena transaksinya lebih aman.');
        foreach (range(1, 10) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} soal pembagian tugas");
        }
        $this->say($this->group, $this->noel, 'Teknologi sudah beres.');
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()->push(['error' => 'down'], 500)->push($this->completion('Jawaban')),
            'api.openai.com/v1/embeddings' => Http::response([], 500),
        ]);

        $this->ask('@normi kenapa kita akhirnya memilih teknologi yang kedua?');

        $this->assertSame('Jawaban', Message::where('sender_type', 'ai')->latest('id')->value('content'));
        $this->assertSame(0, AiUsageLog::where('kind', 'context_understanding')->count());
    }

    public function test_unclear_reference_with_nothing_found_asks_for_clarification(): void
    {
        config()->set('normchat.context.window_tokens', 40);
        foreach (range(1, 8) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} soal pembagian tugas");
        }
        $this->fakeOpenAi([
            json_encode(['maksud' => 'tidak jelas', 'perlu_konteks_lama' => true, 'kata_pencarian' => ['zzzz'], 'topik' => [], 'kejelasan' => 'ambigu']),
            'Maksudnya yang mana ya?',
        ]);

        $this->ask('@normi itu tadi sudah benar?');

        $this->assertStringContainsString('Minta klarifikasi singkat', json_encode($this->chatRequests()[1]['messages'], JSON_UNESCAPED_UNICODE));
    }

    public function test_reply_to_a_message_outside_the_window_is_always_included(): void
    {
        config()->set('normchat.context.window_tokens', 40);
        $old = $this->say($this->group, $this->sari, 'Deadline laporan tanggal 5 November.');
        foreach (range(1, 8) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n} soal pembagian tugas");
        }
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->group, $this->noel, '@normi cek ini', ['reply_to_message_id' => $old->id]));

        $this->assertContains($old->id, array_map(fn (Message $m) => $m->id, $context->retrieved));
    }

    public function test_topics_named_in_the_question_are_used_and_future_or_stale_topics_are_not(): void
    {
        config()->set('normchat.context.window_tokens', 40);
        $first = $this->say($this->group, $this->sari, 'Arsitektur CNN pakai 3 lapis konvolusi.');
        foreach (range(1, 6) as $n) {
            $this->say($this->group, $this->sari, "obrolan pengisi nomor {$n}");
        }
        $topic = GroupContextTopic::create(['group_id' => $this->group->id, 'title' => 'Arsitektur CNN', 'summary' => 'Disepakati 3 lapis konvolusi.', 'first_message_id' => $first->id, 'last_message_id' => $first->id]);
        GroupContextTopic::create(['group_id' => $this->group->id, 'title' => 'Arsitektur CNN lama', 'summary' => 'BASI', 'first_message_id' => $first->id, 'last_message_id' => $first->id, 'stale_at' => now()]);
        $question = $this->say($this->group, $this->noel, '@normi arsitektur CNN kita berapa lapis?');
        GroupContextTopic::create(['group_id' => $this->group->id, 'title' => 'Arsitektur CNN baru', 'summary' => 'MASA-DEPAN', 'first_message_id' => $question->id + 1, 'last_message_id' => $question->id + 1]);
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $context = app(ContextManager::class)->build($this->group, $question);

        $this->assertSame([$topic->id], array_map(fn (GroupContextTopic $t) => $t->id, $context->topics));
    }

    private function makeGroup(string $name): Group
    {
        return Group::create(['name' => $name, 'owner_id' => $this->noel->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
    }

    private function say(Group $group, User $user, string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }

    private function ask(string $content): void
    {
        $message = $this->say($this->group, $this->noel, $content);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }

    /** @param list<string> $replies jawaban chat completions berurutan */
    private function fakeOpenAi(array $replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push($this->completion($reply));
        }
        Http::fake(['api.openai.com/v1/chat/completions' => $sequence, 'api.openai.com/v1/embeddings' => Http::response([], 500)]);
    }

    private function completion(string $text): array
    {
        return ['id' => 'chatcmpl-uji', 'choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 120]];
    }

    /** @return list<array> payload request chat completions, urut kirim */
    private function chatRequests(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    private function vector(int $hot): array
    {
        $vector = array_fill(0, 1536, 0.0);
        $vector[$hot] = 1.0;

        return $vector;
    }

    private function embed(Message $message, int $hot): void
    {
        MessageEmbedding::create(['message_id' => $message->id, 'group_id' => $message->group_id, 'embedding' => json_encode($this->vector($hot)), 'model' => 'text-embedding-3-small', 'content_hash' => 'x']);
    }
}
