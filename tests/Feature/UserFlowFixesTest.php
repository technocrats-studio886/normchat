<?php

namespace Tests\Feature;

use App\Events\GroupMembershipChanged;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\EmbedGroupMessagesJob;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug dari uji alur pengguna di staging (30 Okt 2026), satu test per temuan B2–B10.
 */
class UserFlowFixesTest extends TestCase
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
        Event::fake([MessageSent::class, TypingStatus::class, GroupMembershipChanged::class]);

        $this->noel = User::factory()->create(['name' => 'Noel']);
        $this->sari = User::factory()->create(['name' => 'Sari']);
        $this->group = Group::create(['name' => 'Panitia', 'owner_id' => $this->noel->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
        foreach ([[$this->noel, Role::OWNER], [$this->sari, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    // B2
    public function test_user_without_groups_sees_the_group_code_field_right_away(): void
    {
        $newcomer = User::factory()->create();

        $html = $this->actingAs($newcomer)->get(route('groups.index'))->assertOk()->getContent();

        $emptyState = substr($html, strpos($html, 'Belum ada grup'), 1500);
        $this->assertStringContainsString('data-join-by-id-form', $emptyState);
        $this->assertStringContainsString('name="share_id"', $emptyState);
    }

    // B3
    public function test_document_sent_with_a_caption_keeps_its_content_in_the_history(): void
    {
        $doc = $this->say($this->sari, 'Draft anggaran konsumsi', [
            'message_type' => 'file', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'uji/anggaran.pdf',
            'attachment_mime' => 'application/pdf', 'attachment_original_name' => 'anggaran.pdf',
        ]);
        Cache::put('msg-doc-text:' . $doc->id, 'Nasi kotak 150 porsi x Rp25.000 = Rp3.750.000', now()->addHour());
        $this->fakeOpenAi(['Totalnya Rp3.750.000.']);

        $this->ask('@normi berapa total anggaran nasi kotak?');

        $prompt = json_encode($this->chatRequests()[0]['messages'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('[Dokumen \"anggaran.pdf\"]', $prompt);
        $this->assertStringContainsString('Rp3.750.000', $prompt);
        $this->assertStringContainsString('Keterangan: Draft anggaran konsumsi', $prompt);
    }

    // B4
    public function test_conversational_words_do_not_count_as_topic_keywords_so_ambiguous_follow_ups_reach_the_model(): void
    {
        config()->set('normchat.context.window_tokens', 60);
        $this->say($this->sari, 'Mobil tamu parkir di basement gedung C.');
        foreach (range(1, 10) as $n) {
            $this->say($this->noel, "obrolan pengisi nomor {$n} soal jadwal piket");
        }
        $this->fakeOpenAi([
            json_encode(['maksud' => 'menanyakan keputusan parkir mobil tamu', 'perlu_konteks_lama' => true, 'kata_pencarian' => ['parkir mobil tamu'], 'topik' => [], 'kejelasan' => 'jelas']),
            'Mobil tamu parkir di basement gedung C.',
        ]);

        $this->ask('@normi yang itu tadi jadinya gimana?');

        $this->assertSame(1, AiUsageLog::where('kind', 'context_understanding')->count(), 'Rujukan ambigu dipahami lewat model.');
        $this->assertStringContainsString('basement gedung C', json_encode($this->chatRequests()[1]['messages'], JSON_UNESCAPED_UNICODE));
    }

    // B5
    public function test_follow_up_question_gets_the_old_messages_behind_normis_previous_answer(): void
    {
        config()->set('normchat.context.window_tokens', 120);
        $source = $this->say($this->sari, 'Mobil tamu parkir di basement gedung C.');
        foreach (range(1, 12) as $n) {
            $this->say($this->noel, "obrolan pengisi nomor {$n} soal jadwal piket");
        }
        $question = $this->say($this->noel, '@normi mobil tamu parkir di mana?');
        $answer = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'reply_to_message_id' => $question->id, 'content' => 'Di basement gedung C.']);
        AiUsageLog::create([
            'group_id' => $this->group->id, 'request_message_id' => $question->id, 'response_message_id' => $answer->id, 'kind' => 'chat',
            'model' => 'gpt-6-luna', 'usd_to_idr' => 17950, 'multiplier' => 1.5, 'charged_credits' => 0.1, 'balance_after' => 299.9,
            'breakdown_json' => ['context' => ['sources' => [$source->id]]],
        ]);
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

        $context = app(ContextManager::class)->build($this->group, $this->say($this->noel, '@normi oke, itu sudah pasti?'));

        $this->assertContains($source->id, array_map(fn (Message $m) => $m->id, $context->retrieved));
        $this->assertSame([$source->id], array_values(array_intersect([$source->id], $context->stats()['sources'])));
    }

    // B6
    public function test_forwarded_message_is_summarized_and_embedded_under_its_original_author(): void
    {
        $forwarded = $this->say($this->noel, 'Aku bawa proyektor.', ['forwarded_from_name' => 'Sari']);
        $forwarded->load('sender');

        $this->assertSame('Sari (diteruskan oleh Noel): Aku bawa proyektor.', EmbedGroupMessagesJob::text($forwarded));

        $line = (new \ReflectionMethod(\App\Jobs\SummarizeGroupContextJob::class, 'line'))
            ->invoke(new \App\Jobs\SummarizeGroupContextJob($this->group->id), $forwarded);
        $this->assertStringContainsString('Sari (diteruskan oleh Noel): Aku bawa proyektor.', $line);
    }

    // B8
    public function test_join_approval_switch_is_gone_from_create_and_settings(): void
    {
        $this->actingAs($this->noel)->get(route('groups.create'))->assertOk()->assertDontSee('approval_enabled', false);
        $this->actingAs($this->noel)->get(route('settings.show', $this->group))->assertOk()->assertDontSee('approval_enabled', false);
    }

    // B9
    public function test_member_can_leave_and_the_owner_cannot(): void
    {
        $this->actingAs($this->sari)->get(route('settings.show', $this->group))->assertSee('Keluar grup');
        $this->actingAs($this->noel)->get(route('settings.show', $this->group))->assertDontSee('Keluar grup');

        $this->actingAs($this->sari)->post(route('groups.leave', $this->group))->assertRedirect(route('groups.index'));

        $this->assertFalse(GroupMember::where('group_id', $this->group->id)->where('user_id', $this->sari->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'group.member_left')->exists());
        Event::assertDispatched(GroupMembershipChanged::class, fn ($e) => $e->action === 'left');
        $this->actingAs($this->sari)->get(route('chat.show', $this->group))->assertForbidden();
        $this->assertSame(300.0, (float) $this->group->groupToken()->value('remaining_credits'), 'Patungan tetap di saldo grup.');

        $this->actingAs($this->noel)->post(route('groups.leave', $this->group))->assertSessionHasErrors('member');
        $this->assertTrue(GroupMember::where('group_id', $this->group->id)->where('user_id', $this->noel->id)->exists());
    }

    // B10
    public function test_daily_member_limit_refusal_names_the_limit_not_the_group_balance(): void
    {
        $this->group->update(['member_daily_credit_limit' => 0.001]);
        $this->fakeOpenAi(['tidak dipakai']);

        $this->ask('@normi tuliskan rencana lomba lengkap', $this->sari);

        $notice = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame($this->sari->id, (int) $notice->visible_to_user_id);
        $this->assertStringContainsString('Batas pemakaian harianmu', $notice->content);
        $this->assertStringNotContainsString('Isi Normkredit', $notice->content);
    }

    private function say(User $user, string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }

    private function ask(string $content, ?User $user = null): void
    {
        $message = $this->say($user ?? $this->noel, $content);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }

    /** @param list<string> $replies jawaban chat completions berurutan */
    private function fakeOpenAi(array $replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['id' => 'chatcmpl-uji', 'choices' => [['message' => ['content' => $reply]]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 120]]);
        }
        Http::fake(['api.openai.com/v1/chat/completions' => $sequence, 'api.openai.com/v1/embeddings' => Http::response([], 500)]);
    }

    /** @return list<array> */
    private function chatRequests(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }
}
