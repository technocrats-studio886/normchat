<?php

namespace Tests\Feature;

use App\Events\GroupDataChanged;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Jobs\SendMessagePushJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\DocumentChunk;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupPin;
use App\Models\GroupTask;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Ai\Documents\DocumentIndex;
use App\Services\Ai\Documents\DocumentSearch;
use App\Services\Ai\Responders\ReminderResponder;
use App\Services\Ai\Responders\TaskResponder;
use App\Services\Chat\Reminders;
use App\Services\Chat\ScheduledMessages;
use App\Services\Chat\WeeklyRecap;
use App\Services\Credits\CreditWallet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fitur produktivitas grup (docs/rancangan-fitur-produktivitas-nov.md). */
class GroupProductivityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $budi;

    private User $sari;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class, GroupDataChanged::class, \App\Events\MessageUpdated::class, \App\Events\MessageDeleted::class]);
        Queue::fake([SendMessagePushJob::class]);
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create(['name' => 'Lesmana Sanor', 'timezone' => 'Asia/Jakarta']);
        $this->budi = User::factory()->create(['name' => 'Budi Santoso', 'timezone' => 'Asia/Jakarta']);
        $this->sari = User::factory()->create(['name' => 'Sari Wulandari', 'timezone' => 'Asia/Jakarta']);
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        $this->join($this->owner, Role::OWNER);
        $this->join($this->budi, Role::MEMBER);
        $this->join($this->sari, Role::MEMBER);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    // ── 1. Ringkasan pesan belum dibaca ─────────────────────────

    public function test_catch_up_posts_a_group_summary_of_unread_messages(): void
    {
        $lastRead = $this->say($this->budi, 'pesan yang sudah dibaca')->id;
        foreach (range(1, 6) as $i) {
            $this->say($i % 2 ? $this->sari : $this->budi, "Rapat dipindah ke Jumat jam {$i}");
        }
        $this->fakeChat('- Untuk Lesmana: rapat dipindah ke **Jumat**.');

        $response = $this->actingAs($this->owner)->postJson(route('groups.tools.catch-up', $this->group), ['from_id' => $lastRead])->assertCreated();
        $request = Message::findOrFail($response->json('message.id'));
        $this->assertNull($request->visible_to_user_id, 'Keputusan user: ringkasan terlihat semua anggota.');
        $this->assertSame(['from' => $lastRead, 'to' => $request->id - 1], $request->system_meta['catch_up']);
        $this->assertStringStartsWith('@normi ringkas 6 pesan', $request->content);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        $reply = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertNull($reply->visible_to_user_id);
        $this->assertStringStartsWith('**Ringkasan 6 pesan untuk Lesmana Sanor**', $reply->content);
        $this->assertSame('catch_up', AiUsageLog::firstOrFail()->kind);
        $this->assertGreaterThan(0, (float) AiUsageLog::firstOrFail()->charged_credits);
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/chat/completions') || ! str_contains((string) ($request['messages'][0]['content'] ?? ''), 'MODE RINGKAS')) {
                return false;
            }
            $prompt = (string) ($request['messages'][1]['content'] ?? '');

            return str_contains((string) $request['messages'][0]['content'], 'MODE RINGKAS PESAN YANG TERLEWAT')
                && str_contains($prompt, 'Rapat dipindah ke Jumat jam 6') && ! str_contains($prompt, 'pesan yang sudah dibaca');
        });
    }

    public function test_catch_up_needs_enough_unread_messages(): void
    {
        $lastRead = $this->say($this->budi, 'halo')->id;
        $this->say($this->sari, 'satu');
        $this->actingAs($this->owner)->postJson(route('groups.tools.catch-up', $this->group), ['from_id' => $lastRead])->assertStatus(422);
    }

    // ── 2. Pengingat ────────────────────────────────────────────

    public function test_reminder_requests_are_recognised(): void
    {
        foreach (['@normi ingatkan grup Jumat jam 7 bawa laptop', '@normi ingetin aku besok jam 6 pagi', 'tolong pasang pengingat rapat senin'] as $text) {
            $this->assertTrue(ReminderResponder::wants($text), $text);
        }
        foreach (['@normi apa itu reminder?', '@normi jelaskan sistem pengingatan di otak'] as $text) {
            $this->assertFalse(ReminderResponder::wants($text), $text);
        }
    }

    public function test_normi_creates_a_reminder_from_chat_and_it_fires_on_time(): void
    {
        $this->travelToWib('2026-11-11 10:00');
        $this->fakeChat(json_encode(['text' => 'Bawa laptop', 'remind_at' => '2026-11-13T19:00', 'repeat' => 'none', 'audience' => 'group']));

        $this->runAi('@normi ingatkan grup Jumat jam 7 malam bawa laptop', $this->budi);

        $reminder = Reminder::firstOrFail();
        $this->assertSame('Bawa laptop', $reminder->text);
        $this->assertTrue($reminder->remind_at->equalTo(CarbonImmutable::parse('2026-11-13 19:00', 'Asia/Jakarta')));
        $answer = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertStringContainsString('mengingatkan grup Jumat, 13 Nov pukul 19.00', $answer->content);
        $presented = app(\App\Services\Chat\MessagePresenter::class)->present($answer);
        $this->assertSame(['id' => $reminder->id, 'status' => 'pending'], array_intersect_key($presented['reminder'], ['id' => 1, 'status' => 1]));

        $this->assertSame(0, app(Reminders::class)->sendDue());
        $this->travelToWib('2026-11-13 19:00');
        $this->assertSame(1, app(Reminders::class)->sendDue());
        $this->assertSame(0, app(Reminders::class)->sendDue(), 'Tidak terkirim dua kali.');

        $fired = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Bawa laptop', $fired->content);
        $this->assertStringContainsString('Pengingat dari Budi Santoso', $fired->content);
        $this->assertNull($fired->visible_to_user_id);
        $this->assertSame(Reminder::SENT, $reminder->refresh()->status);
    }

    public function test_weekly_personal_reminder_repeats_and_stays_private(): void
    {
        $this->travelToWib('2026-11-11 10:00');
        $this->actingAs($this->sari)->postJson(route('groups.tools.reminders.store', $this->group), [
            'text' => 'Kumpulkan laporan', 'remind_at' => '2026-11-12T08:00:00+07:00', 'repeat' => 'weekly', 'audience' => 'me',
        ])->assertCreated();
        $this->assertSame(0, Message::where('system_event', 'reminder_created')->count(), 'Pengingat pribadi tidak diumumkan ke grup.');
        $this->actingAs($this->budi)->getJson(route('groups.tools.reminders', $this->group))->assertJsonCount(0, 'reminders');

        $this->travelToWib('2026-11-12 08:01');
        app(Reminders::class)->sendDue();
        $fired = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame($this->sari->id, (int) $fired->visible_to_user_id);
        $reminder = Reminder::firstOrFail();
        $this->assertSame(Reminder::PENDING, $reminder->status);
        $this->assertTrue($reminder->remind_at->equalTo(CarbonImmutable::parse('2026-11-19 08:00', 'Asia/Jakarta')));
    }

    public function test_only_creator_or_moderator_can_cancel_a_group_reminder(): void
    {
        $this->actingAs($this->budi)->postJson(route('groups.tools.reminders.store', $this->group), [
            'text' => 'Bayar kas', 'remind_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $reminder = Reminder::firstOrFail();
        $this->assertSame(1, Message::where('system_event', 'reminder_created')->count());

        $this->actingAs($this->sari)->deleteJson(route('groups.tools.reminders.cancel', [$this->group, $reminder]))->assertForbidden();
        $this->actingAs($this->owner)->deleteJson(route('groups.tools.reminders.cancel', [$this->group, $reminder]))->assertOk();
        $this->assertSame(Reminder::CANCELLED, $reminder->refresh()->status);
        $this->actingAs($this->budi)->postJson(route('groups.tools.reminders.store', $this->group), [
            'text' => 'Kemarin', 'remind_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422);
    }

    // ── 3. Tugas ────────────────────────────────────────────────

    public function test_members_share_a_task_list(): void
    {
        $source = $this->say($this->sari, 'Budi tolong rekap absensi ya');
        $this->actingAs($this->sari)->postJson(route('groups.tools.tasks.store', $this->group), [
            'title' => 'Rekap absensi', 'assignee_id' => $this->budi->id, 'due_date' => now()->addDay()->toDateString(), 'source_message_id' => $source->id,
        ])->assertCreated()->assertJsonPath('open.0.assignee_name', 'Budi Santoso')->assertJsonPath('open.0.due_label', 'Besok');
        $task = GroupTask::firstOrFail();
        Event::assertDispatched(GroupDataChanged::class, fn (GroupDataChanged $event) => $event->kind === 'tasks');

        $this->actingAs($this->budi)->patchJson(route('groups.tools.tasks.update', [$this->group, $task]), ['done' => true])
            ->assertOk()->assertJsonPath('done.0.done_by_name', 'Budi Santoso')->assertJsonCount(0, 'open');
        $this->actingAs($this->budi)->deleteJson(route('groups.tools.tasks.destroy', [$this->group, $task]))->assertForbidden();
        $this->actingAs($this->owner)->deleteJson(route('groups.tools.tasks.destroy', [$this->group, $task]))->assertOk();
        $this->assertSame(0, GroupTask::count());

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson(route('groups.tools.tasks', $this->group))->assertForbidden();
    }

    public function test_normi_turns_a_discussion_into_tasks(): void
    {
        $this->assertTrue(TaskResponder::wants('@normi buat checklist dari pembahasan tadi'));
        $this->assertTrue(TaskResponder::wants('@normi tambahkan ke tugas: beli spanduk'));
        $this->assertFalse(TaskResponder::wants('@normi tugas kita apa aja sih?'));
        $this->fakeChat(json_encode(['reply' => 'Ini tugas dari rapat tadi.', 'tasks' => [
            ['title' => 'Pesan konsumsi', 'assignee' => 'Sari', 'due' => '2026-11-14'],
            ['title' => 'Siapkan proyektor', 'assignee' => 'Orang Asing', 'due' => null],
        ]]));

        $this->runAi('@normi buat checklist dari pembahasan tadi', $this->owner);

        $tasks = GroupTask::orderBy('id')->get();
        $this->assertCount(2, $tasks);
        $this->assertSame($this->sari->id, (int) $tasks[0]->assignee_id);
        $this->assertNull($tasks[1]->assignee_id, 'Nama yang bukan anggota tidak dipasangkan.');
        $answer = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertStringContainsString('☐ Pesan konsumsi — Sari Wulandari', $answer->content);
        $this->assertSame(2, app(\App\Services\Chat\MessagePresenter::class)->present($answer)['tasks_added']);
    }

    // ── 4. Pesan terjadwal ──────────────────────────────────────

    public function test_scheduled_message_is_sent_as_the_member_at_its_time(): void
    {
        $this->travelToWib('2026-11-11 10:00');
        $this->actingAs($this->budi)->postJson(route('groups.tools.scheduled.store', $this->group), [
            'content' => 'Selamat pagi, jangan lupa kuis @normi rangkum materi minggu ini', 'send_at' => '2026-11-12T07:00:00+07:00',
        ])->assertCreated()->assertJsonPath('scheduled.0.when', 'Besok pukul 07.00');
        $this->actingAs($this->sari)->getJson(route('groups.tools.scheduled', $this->group))->assertJsonCount(0, 'scheduled');

        $this->assertSame(0, app(ScheduledMessages::class)->sendDue());
        $this->travelToWib('2026-11-12 07:00');
        Queue::fake([SendMessagePushJob::class, ProcessGroupChatQueueJob::class]);
        $this->assertSame(1, app(ScheduledMessages::class)->sendDue());

        $sent = Message::where('sender_type', 'user')->latest('id')->firstOrFail();
        $this->assertSame($this->budi->id, (int) $sent->sender_id);
        $this->assertStringStartsWith('Selamat pagi', $sent->content);
        $this->assertTrue(ChatMessageQueue::where('message_id', $sent->id)->exists(), 'Pesan terjadwal ber-@normi tetap dijawab Normi.');
        Queue::assertPushed(ProcessGroupChatQueueJob::class);
        $this->assertSame(ScheduledMessage::SENT, ScheduledMessage::firstOrFail()->status);
    }

    public function test_scheduled_message_fails_when_sender_left_and_can_be_cancelled_or_sent_now(): void
    {
        $scheduled = app(ScheduledMessages::class);
        $first = $scheduled->create($this->group, $this->sari, ['content' => 'pesan A', 'send_at' => now()->addHour()]);
        $second = $scheduled->create($this->group, $this->sari, ['content' => 'pesan B', 'send_at' => now()->addHours(2)]);

        $this->actingAs($this->budi)->postJson(route('groups.tools.scheduled.send', [$this->group, $first]))->assertNotFound();
        $this->actingAs($this->sari)->postJson(route('groups.tools.scheduled.send', [$this->group, $first]))->assertOk()->assertJsonCount(1, 'scheduled');
        $this->assertSame('pesan A', Message::latest('id')->firstOrFail()->content);

        GroupMember::where('user_id', $this->sari->id)->update(['status' => 'left']);
        $this->travel(3)->hours();
        $scheduled->sendDue();
        $this->assertSame(ScheduledMessage::FAILED, $second->refresh()->status);
        $this->actingAs($this->budi)->postJson(route('groups.tools.scheduled.store', $this->group), ['content' => 'x', 'send_at' => now()->toIso8601String()])->assertStatus(422);
    }

    // ── 5. Sematan ──────────────────────────────────────────────

    public function test_any_member_can_pin_up_to_five_messages(): void
    {
        $messages = collect(range(1, 6))->map(fn ($i) => $this->say($this->owner, "pengumuman {$i}"));
        foreach ($messages->take(5) as $message) {
            $this->actingAs($this->budi)->postJson(route('groups.tools.pin', [$this->group, $message]))->assertOk();
        }
        $this->assertSame(5, GroupPin::count());
        $this->assertSame(5, Message::where('system_event', 'message_pinned')->count());

        $this->actingAs($this->sari)->postJson(route('groups.tools.pin', [$this->group, $messages[5]]))
            ->assertOk()->assertJsonCount(5, 'pins')->assertJsonPath('pins.0.message_id', $messages[5]->id);
        $this->assertFalse(GroupPin::where('message_id', $messages[0]->id)->exists(), 'Sematan paling lama tergeser.');

        $this->actingAs($this->sari)->deleteJson(route('groups.tools.unpin', [$this->group, $messages[5]->id]))->assertOk()->assertJsonCount(4, 'pins');

        // Pesan yang dihapus untuk semua ikut lepas.
        $this->actingAs($this->owner)->deleteJson(route('chat.destroy', [$this->group, $messages[4]]))->assertOk();
        $this->assertFalse(GroupPin::where('message_id', $messages[4]->id)->exists());
    }

    public function test_pins_respect_history_visibility(): void
    {
        $old = $this->say($this->owner, 'rahasia lama');
        $this->actingAs($this->owner)->postJson(route('groups.tools.pin', [$this->group, $old]))->assertOk();
        $newcomer = User::factory()->create();
        $this->join($newcomer, Role::MEMBER, historyFrom: $old->id);

        $this->actingAs($newcomer)->getJson(route('groups.tools.pins', $this->group))->assertOk()->assertJsonCount(0, 'pins');
        $this->assertArrayNotHasKey('preview', Message::where('system_event', 'message_pinned')->firstOrFail()->system_meta);
    }

    // ── 6. Terjemahan ───────────────────────────────────────────

    public function test_translation_is_free_and_cached(): void
    {
        $message = $this->say($this->sari, 'See you tomorrow at the library');
        $this->fakeChat(json_encode(['source' => 'Inggris', 'target' => 'id', 'text' => 'Sampai jumpa besok di perpustakaan']));

        $this->actingAs($this->budi)->postJson(route('groups.tools.translate', [$this->group, $message]))
            ->assertOk()->assertJsonPath('translation.text', 'Sampai jumpa besok di perpustakaan')->assertJsonPath('translation.source', 'Inggris');
        $this->actingAs($this->owner)->postJson(route('groups.tools.translate', [$this->group, $message]))->assertOk();

        Http::assertSentCount(1);
        $log = AiUsageLog::firstOrFail();
        $this->assertSame('translation', $log->kind);
        $this->assertSame(0.0, (float) $log->charged_credits, 'Keputusan user: terjemahan gratis.');
    }

    // ── 7. Dokumen grup ─────────────────────────────────────────

    public function test_documents_are_chunked_indexed_and_used_when_asked(): void
    {
        $this->assertCount(1, DocumentIndex::chunk('Paragraf pendek.'));
        $this->assertGreaterThan(2, count(DocumentIndex::chunk(str_repeat('Kalimat tentang regresi linear. ', 150))));

        $doc = $this->document($this->sari, 'modul-statistik.txt', "Bab 1 Pengantar.\n\nBab 3 Regresi Linear: koefisien determinasi R kuadrat mengukur seberapa baik model menjelaskan variasi data.\n\nBab 4 Uji hipotesis memakai nilai p.");
        $other = Group::create(['name' => 'Lain', 'owner_id' => $this->budi->id, 'password_hash' => Hash::make('1'), 'status' => 'active']);
        $foreign = Message::create(['group_id' => $other->id, 'sender_type' => 'user', 'sender_id' => $this->budi->id, 'message_type' => 'file',
            'content' => null, 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'x/rahasia.txt', 'attachment_mime' => 'text/plain', 'attachment_original_name' => 'rahasia.txt']);
        Storage::disk('normchat_attachments')->put('x/rahasia.txt', 'Regresi linear grup lain koefisien determinasi rahasia');
        $this->fakeEmbeddings();

        $this->assertGreaterThan(0, app(DocumentIndex::class)->index($doc));
        app(DocumentIndex::class)->index($foreign);
        $this->assertNotNull($doc->refresh()->doc_indexed_at);
        $this->assertNotNull(DocumentChunk::where('message_id', $doc->id)->value('embedding'));

        $this->assertTrue(DocumentSearch::refersToDocuments('menurut modul, apa itu koefisien determinasi?'));
        $this->assertFalse(DocumentSearch::refersToDocuments('besok makan di mana?'));
        $question = $this->say($this->owner, '@normi menurut modul, koefisien determinasi itu apa?');
        $context = app(ContextManager::class)->build($this->group, $question);
        $this->assertNotEmpty($context->documents);
        $this->assertSame('modul-statistik.txt', $context->documents[0]['name']);
        $this->assertStringContainsString('koefisien determinasi', implode(' ', array_column($context->documents, 'content')));
        $this->assertNotContains('rahasia.txt', array_column($context->documents, 'name'), 'Dokumen grup lain tidak ikut.');

        // Dokumen yang dihapus untuk semua tidak dipakai lagi.
        $this->actingAs($this->sari)->deleteJson(route('chat.destroy', [$this->group, $doc]))->assertOk();
        $this->assertSame(0, DocumentChunk::where('message_id', $doc->id)->count());
    }

    public function test_document_search_respects_the_askers_history_floor(): void
    {
        $doc = $this->document($this->owner, 'notulen-rapat.txt', 'Notulen rapat: anggaran acara disepakati 5 juta rupiah.');
        $this->fakeEmbeddings();
        app(DocumentIndex::class)->index($doc);
        $newcomer = User::factory()->create(['name' => 'Anggota Baru']);
        $this->join($newcomer, Role::MEMBER, historyFrom: $doc->id);

        $question = $this->say($newcomer, '@normi di notulen rapat anggarannya berapa?');
        $this->assertSame([], app(ContextManager::class)->build($this->group, $question)->documents);
    }

    // ── 8. Rekap mingguan ───────────────────────────────────────

    public function test_weekly_recap_settings_are_for_moderators(): void
    {
        $payload = ['enabled' => true, 'day' => 1, 'hour' => 7];
        $this->actingAs($this->budi)->putJson(route('groups.tools.weekly-recap', $this->group), $payload)->assertForbidden();
        $this->actingAs($this->owner)->putJson(route('groups.tools.weekly-recap', $this->group), $payload)->assertOk();
        $this->assertTrue($this->group->refresh()->weekly_recap_enabled);
        $this->assertFalse(Group::create(['name' => 'Baru', 'owner_id' => $this->owner->id, 'password_hash' => 'x', 'status' => 'active'])->refresh()->weekly_recap_enabled, 'Bawaan mati.');
    }

    public function test_weekly_recap_is_posted_on_schedule_and_billed(): void
    {
        $this->group->update(['weekly_recap_enabled' => true, 'weekly_recap_day' => 1, 'weekly_recap_hour' => 7]);
        $this->travelToWib('2026-11-16 06:30');
        foreach (range(1, 12) as $i) {
            $this->say($i % 2 ? $this->budi : $this->sari, "Pembahasan proyek bagian {$i}");
        }
        GroupTask::create(['group_id' => $this->group->id, 'title' => 'Kirim proposal', 'created_by' => $this->owner->id]);
        $recap = app(WeeklyRecap::class);
        $this->assertFalse($recap->isDue($this->group->load('owner')));

        $this->travelToWib('2026-11-16 07:05');
        $this->assertTrue($recap->isDue($this->group->refresh()->load('owner')));
        $this->fakeChat("**Topik utama**\n- Proyek dibahas.");
        $message = $recap->generate($this->group);

        $this->assertNotNull($message);
        $this->assertStringStartsWith('**Rekap mingguan** · 9 Nov–16 Nov 2026', $message->content);
        $log = AiUsageLog::where('kind', 'weekly_recap')->firstOrFail();
        $this->assertGreaterThan(0, (float) $log->charged_credits);
        $this->assertFalse($recap->isDue($this->group->refresh()->load('owner')), 'Tidak dua kali di minggu yang sama.');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions') && str_contains((string) $request['messages'][1]['content'], 'Kirim proposal'));
    }

    public function test_quiet_groups_get_no_weekly_recap(): void
    {
        $this->group->update(['weekly_recap_enabled' => true]);
        $this->say($this->budi, 'sepi');
        Http::fake();
        $this->assertNull(app(WeeklyRecap::class)->generate($this->group->load('owner')));
        Http::assertNothingSent();
    }

    // ── Bantuan ────────────────────────────────────────────────

    /** Waktu uji dalam WIB, disetel sebagai UTC: Carbon memakai zona waktu "sekarang" palsu untuk tanggal dari database. */
    private function travelToWib(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Asia/Jakarta')->utc());
    }

    private function join(User $user, string $role, ?int $historyFrom = null): void
    {
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $user->id,
            'role_id' => Role::forKey($role)->id,
            'status' => 'active',
            'joined_at' => now(),
            'history_from_message_id' => $historyFrom,
        ]);
    }

    private function say(User $user, string $content): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content]);
    }

    private function document(User $user, string $name, string $text): Message
    {
        $path = 'group-' . $this->group->id . '/' . $name;
        Storage::disk('normchat_attachments')->put($path, $text);

        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => $path, 'attachment_mime' => 'text/plain', 'attachment_original_name' => $name]);
    }

    private function fakeChat(string $content): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 300],
            ]),
            'api.openai.com/v1/embeddings' => Http::response([], 500),
        ]);
    }

    /** Embedding palsu: vektor sama untuk semua teks, jadi pencarian makna menemukan semua potongan. */
    private function fakeEmbeddings(): void
    {
        Http::fake(['api.openai.com/v1/embeddings' => function (Request $request) {
            $vector = array_fill(0, 1536, 0.0);
            $vector[0] = 1.0;

            return Http::response([
                'data' => array_map(fn ($i) => ['index' => $i, 'embedding' => $vector], array_keys((array) $request['input'])),
                'usage' => ['prompt_tokens' => 50],
            ]);
        }, 'api.openai.com/v1/chat/completions' => Http::response([], 500)]);
    }

    private function runAi(string $content, User $sender): void
    {
        $message = $this->say($sender, $content);
        ChatMessageQueue::enqueue($message);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
