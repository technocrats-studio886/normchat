<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Jobs\SendAdminAlertMail;
use App\Jobs\SummarizeGroupContextJob;
use App\Models\AdminAlert;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\ExchangeRate;
use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Context\MemoryMaintenance;
use App\Services\Ai\CostReconciliation;
use App\Services\Ai\SpendGuard;
use App\Services\Chat\MessageActions;
use App\Services\Credits\CreditWallet;
use App\Services\NormkreditPricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug yang ditemukan tinjauan kode llm-rules (30 Okt 2026): biaya OpenAI yang tidak ditagih harus tetap
 * tercatat sekali, rekonsiliasi tidak boleh membuka anggaran dari data parsial, peringatan anggaran bisa
 * muncul lagi setelah anggaran diubah, bangun ulang ingatan melanjutkan kemajuan, selisih kartu langsung
 * berlaku, laporan per grup hanya menghitung jawaban, dan transkripsi voice note riwayat dibatasi.
 */
class LlmReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        Event::fake([MessageSent::class, MessageDeleted::class, TypingStatus::class]);
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create(['name' => 'Noel']);
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_unbilled_reply_cost_is_logged_once_so_reconciliation_does_not_count_it_twice(): void
    {
        config()->set('services.openai.admin_key', 'sk-admin-uji');
        config()->set('services.openai.project_id', 'proj_uji');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 05:00', 'UTC'));
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'tolong jelaskan normalisasi', 'usage' => ['input_tokens' => 1000, 'output_tokens' => 200]]),
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'down'], 500),
        ]);

        $this->ask(['content' => '@normi', ...$this->voice('group-1/tanya.webm')]);

        // Transkripsi (1.000 × 1,25 + 200 × 5) / 1M = $0,00225; jawaban gagal → tidak ditagih.
        $log = AiUsageLog::sole();
        $this->assertSame(0.0, $log->charged_credits);
        $this->assertEqualsWithDelta(0.00225, $log->provider_cost_usd, 1e-9);
        $spend = app(SpendGuard::class);
        $this->assertEqualsWithDelta(0.00225, $spend->status()['spent'], 1e-9);

        Http::fake(['api.openai.com/v1/organization/costs*' => Http::response($this->costs(0.00225))]);
        $this->travelTo(now()->addDay()->setTime(20, 0));
        $row = app(CostReconciliation::class)->reconcile(CarbonImmutable::now('UTC')->subDay());

        $this->assertEqualsWithDelta(0.0, $row->diff_usd, 1e-9, 'Biaya yang tidak ditagih sudah ada di log; tidak dihitung dua kali.');
        $this->assertEqualsWithDelta(0.00225, $spend->status()['spent'], 1e-9);
    }

    public function test_cost_spent_before_a_refusal_is_recorded_as_refused(): void
    {
        config()->set('normchat.pricing.max_input_tokens', 2000);
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => str_repeat('transkrip yang sangat panjang ', 400), 'usage' => ['input_tokens' => 1000, 'output_tokens' => 200]]),
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'tidak boleh dipanggil'], 500),
        ]);

        $this->ask(['content' => '@normi', ...$this->voice('group-1/panjang.webm')]);

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
        $log = AiUsageLog::sole();
        $this->assertSame(AiUsageLog::REFUSED, $log->kind);
        $this->assertSame(0.0, $log->charged_credits);
        $this->assertEqualsWithDelta(0.00225, app(SpendGuard::class)->status()['spent'], 1e-9);
        $this->assertSame(300.0, $this->group->groupToken()->firstOrFail()->remaining_credits);
    }

    public function test_reconciliation_waits_for_cost_data_and_never_lowers_spend_before_the_day_is_complete(): void
    {
        config()->set('services.openai.admin_key', 'sk-admin-uji');
        config()->set('services.openai.project_id', 'proj_uji');
        $day = CarbonImmutable::parse('2026-10-27', 'UTC');
        $this->travelTo($day->setTime(12, 0));
        $spend = app(SpendGuard::class);
        $this->logCost(1.0);
        $spend->settle($spend->reserve('chat', 1.0), 1.0);
        Http::fake(['api.openai.com/v1/organization/costs*' => Http::sequence()
            ->push(['data' => [['object' => 'bucket', 'results' => []]], 'next_page' => null])
            ->push($this->costs(0.4))
            ->push($this->costs(0.95)),
        ]);

        // 01.00 UTC: bucket ada tapi biayanya belum masuk → belum siap.
        $this->travelTo($day->addDay()->setTime(1, 0));
        $this->assertNull(app(CostReconciliation::class)->reconcile($day));
        $this->assertEqualsWithDelta(1.0, $spend->status()['spent'], 1e-9);

        // 03.00 UTC: data parsial $0,40 tidak boleh menurunkan pemakaian.
        $this->travelTo($day->addDay()->setTime(3, 0));
        $partial = app(CostReconciliation::class)->reconcile($day);
        $this->assertEqualsWithDelta(-0.6, $partial->diff_usd, 1e-9);
        $this->assertEqualsWithDelta(0.0, $partial->applied_usd, 1e-9);
        $this->assertEqualsWithDelta(1.0, $spend->status()['spent'], 1e-9);

        // Setelah data lengkap, koreksi turun berlaku.
        $this->travelTo($day->addDay()->setTime(20, 0));
        app(CostReconciliation::class)->reconcile($day);
        $this->assertEqualsWithDelta(0.95, $spend->status()['spent'], 1e-9);
    }

    public function test_budget_alerts_fire_again_after_the_budget_is_changed(): void
    {
        Bus::fake([SendAdminAlertMail::class]);
        $spend = app(SpendGuard::class);
        $spend->setMonthlyBudget(10, $this->owner->id);
        $spend->settle($spend->reserve('chat', 9.5), 9.5);
        try {
            $spend->reserve('chat', 1);
        } catch (\App\Exceptions\SpendLimitReachedException) {
        }
        $this->assertSame(1, AdminAlert::where('key', 'like', 'budget:%:100')->count());

        $this->travel(1)->minutes();
        $spend->setMonthlyBudget(15, $this->owner->id);
        $this->assertSame(0, AdminAlert::open()->where('key', 'like', 'budget:%:100')->count());
        $spend->settle($spend->reserve('chat', 5), 5);
        try {
            $spend->reserve('chat', 1);
        } catch (\App\Exceptions\SpendLimitReachedException) {
        }

        $this->assertSame(1, AdminAlert::open()->where('key', 'like', 'budget:%:100')->count(), 'Anggaran baru habis lagi: diperingatkan lagi.');
        Bus::assertDispatched(SendAdminAlertMail::class, fn (SendAdminAlertMail $mail) => str_contains($mail->subject, 'anggaran OpenAI bulan ini habis'));
        $this->assertSame(2, collect(Bus::dispatched(SendAdminAlertMail::class))->filter(fn (SendAdminAlertMail $mail) => str_contains($mail->subject, 'habis'))->count());
    }

    public function test_interrupted_rebuild_continues_where_it_stopped(): void
    {
        config()->set('normchat.context.chunk_tokens', 60);
        $messages = collect(range(1, 6))->map(fn (int $n) => $this->say("pesan-ke-{$n} " . str_repeat('isi ', 20)));
        GroupContextSummary::forGroup($this->group->id)->update(['summary' => 'lama', 'covered_until_message_id' => $messages->last()->id]);
        Bus::fake([SummarizeGroupContextJob::class]);
        app(MessageActions::class)->deleteForAll($this->owner, $this->group, $messages[0]);

        $ok = fn (string $summary) => ['id' => 'c', 'choices' => [['message' => ['content' => json_encode(['ringkasan_grup' => $summary, 'topik' => []])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]];
        Http::fake(['api.openai.com/v1/chat/completions' => Http::sequence()
            ->push($ok('bagian 1'))
            ->push(['error' => 'down'], 500)
            ->push($ok('bagian 2'))
            ->whenEmpty(Http::response($ok('lanjutan'))),
            'api.openai.com/v1/embeddings' => Http::response([], 500),
        ]);

        $this->runSummaryJob();
        $state = GroupContextSummary::where('group_id', $this->group->id)->firstOrFail();
        $stoppedAt = $state->covered_until_message_id;
        $this->assertNotNull($stoppedAt);
        $this->assertLessThan($messages->last()->id, $stoppedAt);
        $this->assertNull($state->rebuild_requested_at, 'Ingatan lama sudah dihapus; tidak perlu dihapus lagi.');
        $this->assertNotNull($state->stale_at, 'Belum selesai: ringkasan parsial belum dipakai.');

        // Tag berikutnya melanjutkan, tidak mengulang dari pesan pertama.
        app(MemoryMaintenance::class)->afterAnswer($this->group);
        Bus::assertDispatched(SummarizeGroupContextJob::class);
        $this->runSummaryJob();

        $state->refresh();
        $this->assertSame($messages->last()->id, $state->covered_until_message_id);
        $this->assertNull($state->stale_at);
        $prompts = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => json_encode($pair[0]->data()['messages']))->values();
        $this->assertStringNotContainsString('pesan-ke-2 ', $prompts->last(), 'Pesan yang sudah diringkas tidak dikirim ulang.');
    }

    public function test_changing_the_card_margin_reprices_the_rate_immediately(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $pricing = app(NormkreditPricing::class);
        $pricing->setExchangeRate(18000, $boss->id);
        $this->assertSame(18360.0, $pricing->usdToIdr());

        $this->actingAs($boss)->post(route('superadmin.settings.update'), [...$pricing->settings(), 'card_margin_percent' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(18900.0, app(NormkreditPricing::class)->usdToIdr());
        $this->assertSame('Selisih kartu diubah ke 5%', ExchangeRate::latest('id')->value('note'));
    }

    public function test_usage_by_group_counts_answers_only(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        foreach (['chat', 'embedding', 'context_summary', 'context_understanding', AiUsageLog::REFUSED] as $kind) {
            $this->logCost(0.001, $kind);
        }

        $row = $this->actingAs($boss)->get(route('superadmin.finance'))->assertOk()->viewData('usageByGroup')->first();

        $this->assertSame(1, (int) $row->requests);
        $this->assertSame(1, (int) $this->actingAs($boss)->get(route('superadmin.groups.show', $this->group))->viewData('usage')->requests);
    }

    public function test_history_transcribes_at_most_the_five_newest_voice_notes(): void
    {
        foreach (range(1, 8) as $n) {
            $this->say('', $this->voice("group-1/vn-{$n}.webm"));
        }
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'isi voice note', 'usage' => ['input_tokens' => 100, 'output_tokens' => 20]]),
            'api.openai.com/v1/chat/completions' => Http::response(['id' => 'c', 'choices' => [['message' => ['content' => 'Jawaban']]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 100]]),
        ]);

        $this->ask(['content' => '@normi rangkum voice note tadi']);

        $sent = Http::recorded(fn (Request $request) => str_contains($request->url(), 'audio/transcriptions'))
            ->map(fn (array $pair) => collect($pair[0]->data())->firstWhere('name', 'file')['filename'] ?? null)
            ->sort()->values()->all();
        $this->assertSame(['vn-4.webm', 'vn-5.webm', 'vn-6.webm', 'vn-7.webm', 'vn-8.webm'], $sent);
        $prompt = json_encode(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))->first()[0]->data()['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertSame(3, substr_count($prompt, '[Pesan sebelumnya berisi audio]'));
        $this->assertSame(5, substr_count($prompt, '[Transkrip audio] isi voice note'));
    }

    public function test_reconciliation_compares_against_what_spend_guard_recorded(): void
    {
        config()->set('services.openai.admin_key', 'sk-admin-uji');
        config()->set('services.openai.project_id', 'proj_uji');
        $day = CarbonImmutable::parse('2026-10-20', 'UTC');
        $this->travelTo($day->setTime(9, 0));
        $spend = app(SpendGuard::class);
        // Biaya yang tercatat di SpendGuard tanpa log pemakaian (mis. jawaban error setelah transkripsi).
        $spend->charge('chat', 0.5, $this->group->id);
        // Perintah mencocokkan lusa kemarin (20 Okt) lalu kemarin (21 Okt, tanpa biaya).
        Http::fake(['api.openai.com/v1/organization/costs*' => Http::sequence()
            ->push($this->costs(0.5))
            ->push(['data' => [['object' => 'bucket', 'results' => []]], 'next_page' => null]),
        ]);

        $this->travelTo($day->addDays(2)->setTime(1, 0));
        $this->artisan('normchat:reconcile-openai-costs')->assertSuccessful();

        $this->assertEqualsWithDelta(0.0, \App\Models\AiCostReconciliation::where('date', '2026-10-20')->value('diff_usd'), 1e-9);
        $this->assertEqualsWithDelta(0.5, $spend->status()['spent'], 1e-9, 'Tidak dihitung dua kali.');
        $this->assertSame(['2026-10-20', '2026-10-21'], \App\Models\AiCostReconciliation::orderBy('date')->get()->map(fn ($row) => $row->date->toDateString())->all());
    }

    public function test_semantic_search_finds_this_groups_message_among_many_groups(): void
    {
        $vector = fn (int $hot, float $other = 0.0) => array_map(fn (int $i) => $i === $hot ? 1.0 : ($i === 1 ? $other : 0.0), range(0, 1535));
        $others = collect(range(1, 3))->map(fn (int $n) => Group::create(['name' => "Lain {$n}", 'owner_id' => $this->owner->id, 'password_hash' => 'x', 'status' => 'active']));
        foreach ($others as $other) {
            foreach (range(1, 40) as $n) {
                $message = Message::create(['group_id' => $other->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => "lain {$n}"]);
                \App\Models\MessageEmbedding::create(['message_id' => $message->id, 'group_id' => $other->id, 'embedding' => json_encode($vector(0)), 'model' => 'm', 'content_hash' => 'x']);
            }
        }
        $mine = $this->say('Menurutku kita pakai AdamW saja karena validasinya stabil.');
        \App\Models\MessageEmbedding::create(['message_id' => $mine->id, 'group_id' => $this->group->id, 'embedding' => json_encode($vector(0, 0.3)), 'model' => 'm', 'content_hash' => 'x']);
        $before = $this->say('pembatas jendela')->id;

        $found = app(\App\Services\Ai\Context\HistorySearch::class)->find($this->group, $before, [], $vector(0));

        $this->assertSame([$mine->id], array_map(fn (Message $m) => $m->id, $found));
    }

    public function test_regenerated_answer_refreshes_normi_memory(): void
    {
        $question = $this->say('@normi jelaskan 2NF');
        $answer = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'content' => 'Jawaban lama', 'reply_to_message_id' => $question->id]);
        GroupContextSummary::forGroup($this->group->id)->update(['summary' => 'berisi jawaban lama', 'covered_until_message_id' => $answer->id]);
        \App\Models\MessageEmbedding::create(['message_id' => $answer->id, 'group_id' => $this->group->id, 'embedding' => json_encode(array_fill(0, 1536, 0.1)), 'model' => 'm', 'content_hash' => 'x']);
        Bus::fake([SummarizeGroupContextJob::class]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['id' => 'c', 'choices' => [['message' => ['content' => 'Jawaban baru']]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 50]])]);

        app(\App\Services\Ai\GroupAiAssistant::class)->handle($question, $answer);

        $this->assertSame('Jawaban baru', $answer->fresh()->content);
        $this->assertNotNull(GroupContextSummary::where('group_id', $this->group->id)->value('rebuild_requested_at'));
        $this->assertSame(0, \App\Models\MessageEmbedding::where('message_id', $answer->id)->count());
    }

    private function voice(string $path): array
    {
        Storage::disk('normchat_attachments')->put($path, 'audio-bytes');

        return ['message_type' => 'voice', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => $path, 'attachment_mime' => 'audio/webm', 'attachment_original_name' => basename($path)];
    }

    private function say(string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }

    private function ask(array $attributes): void
    {
        $message = $this->say((string) ($attributes['content'] ?? ''), $attributes);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }

    private function runSummaryJob(): void
    {
        (new SummarizeGroupContextJob($this->group->id))->handle(app(MaintenanceAi::class));
    }

    private function costs(float $usd): array
    {
        return ['data' => [['object' => 'bucket', 'results' => [['object' => 'organization.costs.result', 'amount' => ['value' => $usd, 'currency' => 'usd']]]]], 'next_page' => null];
    }

    private function logCost(float $usd, string $kind = 'chat'): void
    {
        AiUsageLog::create([
            'group_id' => $this->group->id, 'kind' => $kind, 'model' => 'gpt-6-luna', 'provider_cost_usd' => $usd,
            'usd_to_idr' => 17950, 'provider_cost_idr' => $usd * 17950, 'multiplier' => 1.5, 'charged_credits' => $kind === 'chat' ? 1 : 0, 'balance_after' => 0,
        ]);
    }
}
