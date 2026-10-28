<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\TypingStatus;
use App\Events\UserMentioned;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\Role;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Aksi pada pesan terkirim: edit & jawab ulang, versi, hapus untuk semua/saya, teruskan, pemberitahuan Normi. */
class MessageActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $other;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, MessageUpdated::class, MessageDeleted::class, TypingStatus::class, UserMentioned::class]);
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create(['name' => 'Budi']);
        $this->member = User::factory()->create(['name' => 'Siti']);
        $this->other = User::factory()->create(['name' => 'Dimas']);
        $this->group = $this->groupWith('Kelas BD', [[$this->owner, Role::OWNER], [$this->member, Role::MEMBER], [$this->other, Role::MEMBER]]);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    // ── Hapus ───────────────────────────────────────────────

    public function test_sender_deletes_own_message_and_leaves_a_trace(): void
    {
        $message = $this->messageFrom($this->member, 'rahasia kecil');

        $this->actingAs($this->other)->deleteJson($this->url("messages/{$message->id}"))->assertForbidden();
        $this->actingAs($this->member)->deleteJson($this->url("messages/{$message->id}"))
            ->assertOk()
            ->assertJsonPath('message.is_deleted', true)
            ->assertJsonPath('message.content', null)
            ->assertJsonPath('message.deleted_by_moderator', false);

        $this->assertSoftDeleted($message);
        $this->assertSame('rahasia kecil', Message::withTrashed()->find($message->id)->content, 'isi tetap tersimpan untuk ekspor');
        Event::assertDispatched(MessageDeleted::class, fn (MessageDeleted $event) => $event->message['id'] === $message->id && $event->message['content'] === null);

        $page = $this->actingAs($this->other)->get(route('chat.show', $this->group))->assertOk()->getContent();
        $this->assertStringNotContainsString('rahasia kecil', $page);
        $this->assertStringContainsString('"is_deleted":true', $page);
    }

    public function test_moderator_deletes_anyones_message_marked_as_moderator(): void
    {
        $message = $this->messageFrom($this->member, 'spam');

        $this->actingAs($this->owner)->deleteJson($this->url("messages/{$message->id}"))
            ->assertOk()
            ->assertJsonPath('message.deleted_by_moderator', true);
    }

    public function test_any_member_deletes_a_normi_answer_but_not_a_notice(): void
    {
        $question = $this->messageFrom($this->member, '@normi apa itu 1NF?');
        $answer = $this->aiMessage(['reply_to_message_id' => $question->id, 'content' => '1NF adalah…']);
        $notice = $this->aiMessage(['reply_to_message_id' => $question->id, 'visible_to_user_id' => $this->member->id, 'content' => 'Normkredit grup habis.']);

        $this->actingAs($this->other)->deleteJson($this->url("messages/{$answer->id}"))->assertOk();
        $this->actingAs($this->member)->deleteJson($this->url("messages/{$notice->id}"))->assertForbidden();
    }

    public function test_quote_of_a_deleted_message_shows_it_was_deleted(): void
    {
        $original = $this->messageFrom($this->member, 'pesan asli');
        $reply = $this->messageFrom($this->other, 'balasan', ['reply_to_message_id' => $original->id]);
        $original->delete();

        $this->actingAs($this->owner)->get(route('chat.show', $this->group))
            ->assertOk()
            ->assertSee('"reply_to":{"id":' . $original->id, false)
            ->assertDontSee('pesan asli');
        $this->assertNotNull($reply->fresh());
    }

    public function test_hide_for_me_is_per_account_and_survives_reload(): void
    {
        $message = $this->messageFrom($this->member, 'hanya aku yang tidak mau lihat');

        $this->actingAs($this->other)->postJson($this->url('messages/hide'), ['message_ids' => [$message->id, 999999]])
            ->assertOk()->assertJsonPath('hidden', 1);
        $this->actingAs($this->other)->postJson($this->url('messages/hide'), ['message_ids' => [$message->id]])->assertOk();

        $this->assertSame(1, MessageHide::count());
        $this->actingAs($this->other)->get(route('chat.show', $this->group))->assertDontSee('hanya aku yang tidak mau lihat');
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertSee('hanya aku yang tidak mau lihat');
    }

    public function test_cannot_hide_messages_of_another_group(): void
    {
        $elsewhere = $this->groupWith('Grup lain', [[$this->other, Role::OWNER]]);
        $foreign = Message::create(['group_id' => $elsewhere->id, 'sender_type' => 'user', 'sender_id' => $this->other->id, 'content' => 'x']);

        $this->actingAs($this->member)->postJson($this->url('messages/hide'), ['message_ids' => [$foreign->id]])
            ->assertOk()->assertJsonPath('hidden', 0);
    }

    public function test_sidebar_preview_is_plain_text_and_respects_hide_and_delete(): void
    {
        $this->aiMessage(['content' => "**Rencana** belajar dengan `SELECT`\n\n- satu"]);
        $this->actingAs($this->member)->get(route('chat.show', $this->group))
            ->assertSee('Normi AI: Rencana belajar dengan SELECT satu', false);

        $last = $this->messageFrom($this->other, 'terakhir');
        $this->actingAs($this->member)->postJson($this->url('messages/hide'), ['message_ids' => [$last->id]]);
        $this->actingAs($this->member)->get(route('chat.show', $this->group))->assertSee('Normi AI: Rencana', false);

        $last->delete();
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertSee('Dimas: Pesan ini dihapus', false);
    }

    // ── Edit & versi ────────────────────────────────────────

    public function test_edit_without_regenerate_does_not_call_normi(): void
    {
        Queue::fake();
        $question = $this->messageFrom($this->member, '@normi jelaskan 2NF');
        $this->aiMessage(['reply_to_message_id' => $question->id, 'content' => 'Jawaban 2NF']);

        $this->actingAs($this->member)->patchJson($this->url("messages/{$question->id}"), ['content' => '@normi jelaskan 2NF ya'])
            ->assertOk()->assertJsonPath('message.is_edited', true);

        $this->assertSame(0, ChatMessageQueue::count());
        Queue::assertNothingPushed();
    }

    public function test_regenerate_replaces_the_old_answer_in_place_and_keeps_versions(): void
    {
        $question = $this->messageFrom($this->member, '@normi jelaskan 2NF');
        $answer = $this->aiMessage(['reply_to_message_id' => $question->id, 'content' => 'Jawaban 2NF']);
        $this->fakeOpenAi('Jawaban 3NF');

        $this->actingAs($this->member)->patchJson($this->url("messages/{$question->id}"), ['content' => '@normi jelaskan 3NF', 'regenerate' => true])
            ->assertOk();

        $this->assertSame(1, Message::where('sender_type', 'ai')->count(), 'tidak ada jawaban kedua');
        $this->assertSame('Jawaban 3NF', $answer->fresh()->content);
        $this->assertTrue((bool) Message::where('sender_type', 'ai')->value('id'));
        Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $event) => $event->message['id'] === $answer->id && $event->message['is_edited'] === true);

        $this->actingAs($this->other)->getJson($this->url("messages/{$question->id}/versions"))
            ->assertOk()
            ->assertJsonCount(2, 'versions')
            ->assertJsonPath('versions.0.number', 1)
            ->assertJsonPath('versions.0.content', '@normi jelaskan 2NF')
            ->assertJsonPath('versions.0.answer.content', 'Jawaban 2NF')
            ->assertJsonPath('versions.1.content', '@normi jelaskan 3NF')
            ->assertJsonPath('versions.1.answer.content', 'Jawaban 3NF');
    }

    public function test_regenerate_adds_a_new_answer_when_the_old_one_was_replied_to(): void
    {
        $question = $this->messageFrom($this->member, '@normi jelaskan 2NF');
        $answer = $this->aiMessage(['reply_to_message_id' => $question->id, 'content' => 'Jawaban 2NF']);
        $this->messageFrom($this->other, 'makasih', ['reply_to_message_id' => $answer->id]);
        $this->fakeOpenAi('Jawaban 3NF');

        $this->actingAs($this->member)->patchJson($this->url("messages/{$question->id}"), ['content' => '@normi jelaskan 3NF', 'regenerate' => true])
            ->assertOk();

        $this->assertSame('Jawaban 2NF', $answer->fresh()->content);
        $this->assertSame(['Jawaban 2NF', 'Jawaban 3NF'], Message::where('sender_type', 'ai')->orderBy('id')->pluck('content')->all());
    }

    public function test_edit_is_blocked_while_normi_is_still_answering(): void
    {
        $question = $this->messageFrom($this->member, '@normi jelaskan 2NF');
        ChatMessageQueue::enqueue($question);

        $this->actingAs($this->member)->patchJson($this->url("messages/{$question->id}"), ['content' => 'ganti'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Tunggu Normi selesai menjawab.');
    }

    public function test_only_own_text_messages_can_be_edited(): void
    {
        $theirs = $this->messageFrom($this->other, 'punya Dimas');
        $poll = $this->messageFrom($this->member, "📊 POLL: Kapan?\n1. Senin\n2. Selasa");
        $forwarded = $this->messageFrom($this->member, 'terusan', ['forwarded_from_name' => 'Dimas']);
        $voice = $this->messageFrom($this->member, null, ['message_type' => 'voice']);

        foreach ([$theirs, $poll, $forwarded, $voice] as $message) {
            $this->actingAs($this->member)->patchJson($this->url("messages/{$message->id}"), ['content' => 'x'])->assertForbidden();
        }
    }

    public function test_editing_has_no_time_limit(): void
    {
        $old = $this->messageFrom($this->member, 'lama sekali');
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $this->actingAs($this->member)->patchJson($this->url("messages/{$old->id}"), ['content' => 'diperbaiki'])->assertOk();
    }

    // ── Pemberitahuan Normi ─────────────────────────────────

    public function test_normi_notice_is_only_for_the_requester(): void
    {
        $empty = $this->groupWith('Grup tanpa saldo', [[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]]);
        Http::fake();
        $question = Message::create(['group_id' => $empty->id, 'sender_type' => 'user', 'sender_id' => $this->member->id, 'content' => '@normi halo']);
        ChatMessageQueue::enqueue($question);
        (new ProcessGroupChatQueueJob($empty->id))->handle();

        $notice = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame($this->member->id, $notice->visible_to_user_id);
        $this->assertSame($question->id, $notice->reply_to_message_id);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->onlyUserId === $this->member->id
            && $event->broadcastOn()[0]->name === 'private-App.Models.User.' . $this->member->id);

        $this->actingAs($this->member)->get(route('chat.show', $empty))->assertSee('"is_notice":true', false);
        $this->actingAs($this->owner)->get(route('chat.show', $empty))->assertDontSee('"is_notice":true', false);
    }

    // ── Teruskan ────────────────────────────────────────────

    public function test_forward_copies_to_chosen_groups_with_label_and_without_calling_normi(): void
    {
        Queue::fake();
        $target = $this->groupWith('Grup tujuan', [[$this->member, Role::OWNER]]);
        $notMine = $this->groupWith('Bukan grupku', [[$this->other, Role::OWNER]]);
        Storage::disk('normchat_attachments')->put('group-1/foto.png', 'png');
        $source = $this->messageFrom($this->other, '@normi lihat ini', [
            'message_type' => 'image',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/foto.png',
            'attachment_mime' => 'image/png',
            'attachment_original_name' => 'foto.png',
        ]);

        $this->actingAs($this->member)->postJson($this->url("messages/{$source->id}/forward"), ['group_ids' => [$target->id, $notMine->id]])
            ->assertOk()->assertJsonPath('forwarded', 1);

        $copy = Message::where('group_id', $target->id)->firstOrFail();
        $this->assertSame([$this->member->id, 'Dimas', '@normi lihat ini'], [$copy->sender_id, $copy->forwarded_from_name, $copy->content]);
        $this->assertNotSame($source->attachment_path, $copy->attachment_path);
        Storage::disk('normchat_attachments')->assertExists($copy->attachment_path);
        $this->assertSame(0, Message::where('group_id', $notMine->id)->count());
        $this->assertSame(0, ChatMessageQueue::count());
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->groupId === $target->id && $event->message['forwarded_from'] === 'Dimas');
    }

    public function test_chat_page_lists_forward_targets(): void
    {
        $this->groupWith('Grup tujuan', [[$this->member, Role::OWNER]]);

        $this->actingAs($this->member)->get(route('chat.show', $this->group))
            ->assertSee('Grup tujuan');
    }

    // ── Bantuan ─────────────────────────────────────────────

    private function groupWith(string $name, array $members): Group
    {
        $group = Group::create([
            'name' => $name,
            'owner_id' => $members[0][0]->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        foreach ($members as [$user, $role]) {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role_id' => Role::forKey($role)->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return $group;
    }

    private function messageFrom(User $user, ?string $content, array $attributes = []): Message
    {
        return Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'content' => $content,
            ...$attributes,
        ]);
    }

    private function aiMessage(array $attributes): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'sender_id' => null, ...$attributes]);
    }

    private function fakeOpenAi(string $answer): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $answer]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100],
            ]),
        ]);
    }

    private function url(string $path): string
    {
        return "/groups/{$this->group->id}/{$path}";
    }
}
