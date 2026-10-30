<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\EmbedGroupMessagesJob;
use App\Jobs\SummarizeGroupContextJob;
use App\Models\AiUsageLog;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Context\MemoryMaintenance;
use App\Services\Ai\SpendGuard;
use App\Services\Chat\MessageActions;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * llm-rules bagian 5.4 dan 5.6: Summary Worker (ringkasan grup + topik), embedding pesan, dan
 * bangun ulang ingatan saat pesan yang sudah diringkas dihapus untuk semua atau diedit. Semua biaya
 * pemeliharaan dari saldo OpenAI; Normkredit grup tidak tersentuh.
 */
class LlmMemoryTest extends TestCase
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
        Event::fake([MessageSent::class, MessageDeleted::class, TypingStatus::class]);

        $this->noel = User::factory()->create(['name' => 'Noel']);
        $this->sari = User::factory()->create(['name' => 'Sari']);
        $this->group = $this->makeGroup('Kelas BD');
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_summary_is_needed_only_at_the_message_threshold_or_window_budget(): void
    {
        $state = GroupContextSummary::forGroup($this->group->id);
        foreach (range(1, 99) as $n) {
            $this->say($this->sari, "pesan {$n}");
        }
        $this->assertFalse(app(MemoryMaintenance::class)->needsSummary($state), 'Grup 99 pesan pendek cukup dikirim apa adanya.');

        $this->say($this->sari, 'pesan 100');
        $this->assertTrue(app(MemoryMaintenance::class)->needsSummary($state));

        $other = $this->makeGroup('Grup panjang');
        $otherState = GroupContextSummary::forGroup($other->id);
        config()->set('normchat.context.window_tokens', 50);
        foreach (range(1, 5) as $n) {
            $this->say($this->sari, str_repeat("isi panjang {$n} ", 20), $other);
        }
        $this->assertTrue(app(MemoryMaintenance::class)->needsSummary($otherState), 'Pesan sedikit tapi melebihi anggaran jendela.');
    }

    public function test_summary_job_writes_group_summary_and_topics_and_moves_the_anchor_without_charging_nc(): void
    {
        config()->set('normchat.context.window_overlap_messages', 30);
        $messages = [];
        foreach (range(1, 40) as $n) {
            $messages[] = $this->say($n % 2 ? $this->noel : $this->sari, "diskusi database nomor {$n}");
        }
        $this->say($this->noel, 'Pemberitahuan pribadi untuk Noel', null, ['sender_type' => 'ai', 'sender_id' => null, 'visible_to_user_id' => $this->noel->id]);
        $this->say($this->sari, 'Isi grup lain tidak boleh ikut', $this->makeGroup('Grup lain'));
        $balance = GroupToken::forGroup($this->group->id)->remaining_credits;
        $ledger = CreditLedgerEntry::count();

        $this->fakeOpenAi([json_encode([
            'ringkasan_grup' => 'Grup sepakat memakai PostgreSQL.',
            'topik' => [[
                'id' => null, 'judul' => 'Pilihan database', 'ringkasan' => 'Sepakat PostgreSQL karena transaksi aman.',
                'pesan_awal' => $messages[0]->id, 'pesan_akhir' => $messages[5]->id, 'pesan_penting' => [$messages[3]->id, 999999],
            ]],
        ])]);

        $this->runSummaryJob();

        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $this->assertSame('Grup sepakat memakai PostgreSQL.', $state->summary);
        $this->assertSame($messages[39]->id, $state->covered_until_message_id);
        $this->assertSame($messages[10]->id, $state->window_start_message_id, 'Jangkar = 30 pesan terakhir yang sudah diringkas.');
        $this->assertSame(40, $state->source_message_count);
        $this->assertNull($state->stale_at);

        $topic = GroupContextTopic::where('group_id', $this->group->id)->firstOrFail();
        $this->assertSame('Pilihan database', $topic->title);
        $this->assertSame([$messages[3]->id], $topic->key_message_ids, 'Id pesan di luar cicilan dibuang.');
        $this->assertNotNull($topic->embedding);

        $prompt = json_encode($this->chatRequests()[0]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Pemberitahuan pribadi', $prompt);
        $this->assertStringNotContainsString('grup lain', $prompt);

        // Dibayar dari saldo OpenAI: Normkredit dan ledger NC tidak berubah.
        $this->assertSame(0.0, (float) AiUsageLog::where('kind', 'context_summary')->value('charged_credits'));
        $this->assertSame($balance, GroupToken::forGroup($this->group->id)->remaining_credits);
        $this->assertSame($ledger, CreditLedgerEntry::count());
    }

    public function test_existing_topic_is_updated_instead_of_duplicated(): void
    {
        $first = $this->say($this->noel, 'Kita pakai AdamW.');
        $topic = GroupContextTopic::create([
            'group_id' => $this->group->id, 'title' => 'Optimizer', 'summary' => 'Sepakat AdamW.',
            'first_message_id' => $first->id, 'last_message_id' => $first->id, 'key_message_ids' => [$first->id],
            'last_activity_at' => now(),
        ]);
        GroupContextSummary::forGroup($this->group->id)->update(['summary' => 'Lama', 'covered_until_message_id' => $first->id]);
        $next = $this->say($this->sari, 'Learning rate diturunkan ke 0,0001.');

        $this->fakeOpenAi([json_encode([
            'ringkasan_grup' => 'AdamW dengan learning rate 0,0001.',
            'topik' => [['id' => $topic->id, 'judul' => 'Optimizer', 'ringkasan' => 'AdamW, lr 0,0001.', 'pesan_awal' => $next->id, 'pesan_akhir' => $next->id, 'pesan_penting' => [$next->id]]],
        ])]);

        $this->runSummaryJob();

        $this->assertSame(1, GroupContextTopic::where('group_id', $this->group->id)->count());
        $topic->refresh();
        $this->assertSame('AdamW, lr 0,0001.', $topic->summary);
        $this->assertSame([$first->id, $next->id], [$topic->first_message_id, $topic->last_message_id]);
        $this->assertSame([$first->id, $next->id], $topic->key_message_ids);
    }

    public function test_broken_json_from_the_model_keeps_the_old_memory(): void
    {
        $covered = $this->say($this->noel, 'Pesan lama');
        GroupContextSummary::forGroup($this->group->id)->update(['summary' => 'Ringkasan lama', 'covered_until_message_id' => $covered->id]);
        $this->say($this->sari, 'Pesan baru');
        $this->fakeOpenAi(['ini bukan json']);

        $this->runSummaryJob();

        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $this->assertSame('Ringkasan lama', $state->summary);
        $this->assertSame($covered->id, $state->covered_until_message_id);
    }

    public function test_deleting_a_summarized_message_stops_using_the_memory_and_rebuilds_it_without_that_message(): void
    {
        $secret = $this->say($this->noel, 'Password wifi kelas: rahasia123');
        $keep = $this->say($this->sari, 'Deadline laporan tanggal 5 November.');
        $this->summarizedState('Password wifi rahasia123, deadline 5 November.', $keep);
        $topic = $this->topicOver($secret, $keep, 'Wifi rahasia123');
        MessageEmbedding::create(['message_id' => $secret->id, 'group_id' => $this->group->id, 'embedding' => json_encode($this->vector(0)), 'model' => 'text-embedding-3-small', 'content_hash' => 'x']);

        Bus::fake([SummarizeGroupContextJob::class]);
        app(MessageActions::class)->deleteForAll($this->noel, $this->group, $secret);

        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $this->assertNotNull($state->stale_at);
        $this->assertNotNull($state->rebuild_requested_at);
        $this->assertNotNull($topic->fresh()->stale_at);
        $this->assertSame(0, MessageEmbedding::where('message_id', $secret->id)->count());
        Bus::assertDispatched(SummarizeGroupContextJob::class, fn (SummarizeGroupContextJob $job) => $job->groupId === $this->group->id);

        // Sebelum bangun ulang selesai, ingatan lama tidak dipakai.
        $context = app(ContextManager::class)->build($this->group, $this->say($this->sari, '@normi deadline kapan?'));
        $this->assertNull($context->summary);
        $this->assertSame([], $context->topics);

        $this->fakeOpenAi([json_encode(['ringkasan_grup' => 'Deadline laporan 5 November.', 'topik' => []])]);
        $this->runSummaryJob();

        $state->refresh();
        $this->assertSame('Deadline laporan 5 November.', $state->summary);
        $this->assertNull($state->stale_at);
        $this->assertNull($state->rebuild_requested_at);
        $this->assertSame(0, GroupContextTopic::where('group_id', $this->group->id)->count(), 'Topik lama yang memuat isi terhapus dihapus.');
        $this->assertStringNotContainsString('rahasia123', json_encode($this->chatRequests()[0]['messages']));
    }

    public function test_editing_a_summarized_message_rebuilds_memory_and_embeds_it_again(): void
    {
        $edited = $this->say($this->noel, 'Rapat hari Senin.');
        $last = $this->say($this->sari, 'Oke.');
        $this->summarizedState('Rapat hari Senin.', $last);
        GroupContextSummary::where('group_id', $this->group->id)->update(['embedded_until_message_id' => $last->id]);
        MessageEmbedding::create(['message_id' => $edited->id, 'group_id' => $this->group->id, 'embedding' => json_encode($this->vector(0)), 'model' => 'text-embedding-3-small', 'content_hash' => 'x']);

        Bus::fake([SummarizeGroupContextJob::class, EmbedGroupMessagesJob::class]);
        app(MessageActions::class)->edit($this->noel, $this->group, $edited, 'Rapat hari Selasa.', regenerate: false);

        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $this->assertNotNull($state->rebuild_requested_at);
        $this->assertSame($edited->id - 1, $state->embedded_until_message_id, 'Isi baru di-embed ulang mulai pesan itu.');
        $this->assertSame(0, MessageEmbedding::where('message_id', $edited->id)->count());
        Bus::assertDispatched(SummarizeGroupContextJob::class);
    }

    public function test_embedding_job_embeds_new_and_changed_messages_only(): void
    {
        $messages = [
            $this->say($this->noel, 'Kita pakai AdamW.'),
            $this->say($this->sari, 'Setuju.'),
            $this->say($this->noel, 'Learning rate 0,0001.'),
        ];
        $this->say($this->noel, 'Pemberitahuan pribadi', null, ['sender_type' => 'ai', 'sender_id' => null, 'visible_to_user_id' => $this->noel->id]);
        $this->say($this->sari, '');
        $this->say($this->sari, 'Grup lain', $this->makeGroup('Grup lain'));
        $this->fakeEmbeddings();

        $this->runEmbedJob();

        $this->assertEqualsCanonicalizing(array_map(fn (Message $m) => $m->id, $messages), MessageEmbedding::pluck('message_id')->all());
        $this->assertSame(1, $this->embeddingRequests());
        $this->assertSame(0.0, (float) AiUsageLog::where('kind', 'embedding')->value('charged_credits'));

        // Tidak ada pesan baru: tidak ada panggilan lagi.
        $this->runEmbedJob();
        $this->assertSame(1, $this->embeddingRequests());

        // Pesan baru saja yang di-embed.
        $new = $this->say($this->sari, 'Batch size 32.');
        $this->runEmbedJob();
        $this->assertSame(2, $this->embeddingRequests());
        $this->assertSame(4, MessageEmbedding::count());
        $this->assertSame(['Sari: Batch size 32.'], Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/embeddings'))->last()[0]->data()['input']);
        $this->assertSame($new->id, GroupContextSummary::where('group_id', $this->group->id)->value('embedded_until_message_id'));
    }

    public function test_embedding_waits_when_the_openai_balance_is_at_its_reserve(): void
    {
        $this->say($this->noel, 'Kita pakai AdamW.');
        app(SpendGuard::class)->setBalance(5, 5, $this->noel->id);
        $this->fakeEmbeddings();

        $this->runEmbedJob();

        $this->assertSame(0, MessageEmbedding::count());
        $this->assertSame(0, $this->embeddingRequests());
        $this->assertNull(GroupContextSummary::where('group_id', $this->group->id)->value('embedded_until_message_id'));
    }

    public function test_embed_command_only_queues_groups_that_use_normi(): void
    {
        $quiet = $this->makeGroup('Grup diam');
        foreach ([$this->group, $quiet] as $group) {
            GroupContextSummary::forGroup($group->id);
            $this->say($this->sari, 'Pesan baru', $group);
        }
        AiUsageLog::create([
            'group_id' => $this->group->id, 'kind' => 'chat', 'model' => 'gpt-6-luna', 'provider_cost_usd' => 0.001,
            'usd_to_idr' => 17950, 'provider_cost_idr' => 17.95, 'multiplier' => 1.5, 'charged_credits' => 0.2, 'balance_after' => 299.8,
        ]);
        Bus::fake([EmbedGroupMessagesJob::class]);

        $this->artisan('normchat:embed-new-messages')->assertSuccessful();

        Bus::assertDispatchedTimes(EmbedGroupMessagesJob::class, 1);
        Bus::assertDispatched(EmbedGroupMessagesJob::class, fn (EmbedGroupMessagesJob $job) => $job->groupId === $this->group->id);
    }

    public function test_deleting_a_group_removes_its_memory(): void
    {
        $message = $this->say($this->noel, 'Kita pakai AdamW.');
        $this->summarizedState('AdamW.', $message);
        $this->topicOver($message, $message, 'Optimizer');
        MessageEmbedding::create(['message_id' => $message->id, 'group_id' => $this->group->id, 'embedding' => json_encode($this->vector(0)), 'model' => 'text-embedding-3-small', 'content_hash' => 'x']);

        $this->group->delete();

        $this->assertSame(0, GroupContextSummary::where('group_id', $this->group->id)->count());
        $this->assertSame(0, GroupContextTopic::where('group_id', $this->group->id)->count());
        $this->assertSame(0, MessageEmbedding::where('group_id', $this->group->id)->count());
    }

    public function test_restored_messages_rebuild_memory_and_are_embedded_again(): void
    {
        $message = $this->say($this->noel, 'Kita pakai AdamW.');
        $this->summarizedState('AdamW.', $message);
        GroupContextSummary::where('group_id', $this->group->id)->update(['embedded_until_message_id' => $message->id]);
        Bus::fake([SummarizeGroupContextJob::class]);

        app(MemoryMaintenance::class)->messagesRestored($this->group->id);

        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $this->assertNull($state->embedded_until_message_id);
        $this->assertNotNull($state->rebuild_requested_at);
        Bus::assertDispatched(SummarizeGroupContextJob::class);
    }

    private function makeGroup(string $name): Group
    {
        return Group::create(['name' => $name, 'owner_id' => $this->noel->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
    }

    private function say(User $user, string $content, ?Group $group = null, array $extra = []): Message
    {
        return Message::create([
            'group_id' => ($group ?? $this->group)->id, 'sender_type' => 'user', 'sender_id' => $user->id,
            'message_type' => 'text', 'content' => $content, ...$extra,
        ]);
    }

    private function summarizedState(string $summary, Message $coveredUntil): void
    {
        GroupContextSummary::forGroup($this->group->id)->update([
            'summary' => $summary,
            'covered_until_message_id' => $coveredUntil->id,
            'window_start_message_id' => $coveredUntil->id,
        ]);
    }

    private function topicOver(Message $first, Message $last, string $title): GroupContextTopic
    {
        return GroupContextTopic::create([
            'group_id' => $this->group->id, 'title' => $title, 'summary' => $title,
            'first_message_id' => $first->id, 'last_message_id' => $last->id, 'key_message_ids' => [$first->id],
            'last_activity_at' => now(),
        ]);
    }

    private function runSummaryJob(): void
    {
        (new SummarizeGroupContextJob($this->group->id))->handle(app(MaintenanceAi::class));
    }

    private function runEmbedJob(): void
    {
        (new EmbedGroupMessagesJob($this->group->id))->handle(app(MaintenanceAi::class));
    }

    /** @param list<string> $replies jawaban chat completions berurutan; embedding selalu berhasil */
    private function fakeOpenAi(array $replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['id' => 'chatcmpl-uji', 'choices' => [['message' => ['content' => $reply]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 150]]);
        }
        Http::fake(['api.openai.com/v1/chat/completions' => $sequence, 'api.openai.com/v1/embeddings' => $this->embeddingResponder()]);
    }

    private function fakeEmbeddings(): void
    {
        Http::fake(['api.openai.com/v1/embeddings' => $this->embeddingResponder()]);
    }

    private function embeddingResponder(): \Closure
    {
        return function (Request $request) {
            $inputs = (array) $request->data()['input'];

            return Http::response([
                'data' => array_map(fn (int $index) => ['index' => $index, 'embedding' => $this->vector($index % 1536)], array_keys($inputs)),
                'usage' => ['prompt_tokens' => 10 * count($inputs)],
            ]);
        };
    }

    /** @return list<array> payload request chat completions, urut kirim */
    private function chatRequests(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    private function embeddingRequests(): int
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/embeddings'))->count();
    }

    private function vector(int $hot): array
    {
        $vector = array_fill(0, 1536, 0.0);
        $vector[$hot] = 1.0;

        return $vector;
    }
}
