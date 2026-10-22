<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\PollVoted;
use App\Events\UserMentioned;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageVersion;
use App\Models\PollVote;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Perilaku chat grup dari sisi HTTP: kirim, edit, hapus, polling, mention, baca. */
class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([MessageSent::class, MessageUpdated::class, MessageDeleted::class, PollVoted::class, UserMentioned::class]);
        Queue::fake();
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create(['name' => 'Budi Santoso']);
        $this->member = User::factory()->create(['name' => 'Siti']);
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create([
                'group_id' => $this->group->id,
                'user_id' => $user->id,
                'role_id' => Role::forKey($role)->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
    }

    public function test_sending_text_broadcasts_and_returns_payload(): void
    {
        $response = $this->actingAs($this->member)
            ->postJson($this->url('messages'), ['content' => 'Halo semua'])
            ->assertCreated()
            ->assertJsonPath('message.content', 'Halo semua')
            ->assertJsonPath('message.sender_name', 'Siti')
            ->assertJsonPath('message.message_type', 'text');

        Event::assertDispatched(MessageSent::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('audit_logs', ['action' => 'chat.send_message', 'target_id' => $response->json('message.id')]);
    }

    public function test_empty_message_is_rejected(): void
    {
        $this->actingAs($this->member)->post($this->url('messages'), ['content' => '  '])->assertSessionHasErrors('content');
        $this->assertSame(0, Message::count());
    }

    public function test_ai_mention_and_reply_to_ai_are_queued(): void
    {
        $this->actingAs($this->member)->postJson($this->url('messages'), ['content' => '@normi tolong bantu'])->assertCreated();

        $aiReply = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'content' => 'Jawaban']);
        $this->actingAs($this->member)->postJson($this->url('messages'), [
            'content' => 'lanjutkan',
            'reply_to_message_id' => $aiReply->id,
            'reply_quote_text' => '  Jawaban   ',
        ])->assertCreated()->assertJsonPath('message.reply_to.sender_name', 'Normi AI')->assertJsonPath('message.reply_to.quote_text', 'Jawaban');

        $this->assertSame(2, ChatMessageQueue::where('status', 'queued')->count());
        Queue::assertPushed(ProcessGroupChatQueueJob::class, 2);
    }

    public function test_only_normi_mention_calls_the_ai(): void
    {
        foreach (['@ai tolong', '@openai halo', '@chatgpt halo', 'halo @normianto', 'kirim ke tim@normi.chat'] as $content) {
            $this->actingAs($this->member)->postJson($this->url('messages'), ['content' => $content])->assertCreated();
        }
        $this->assertSame(0, ChatMessageQueue::count(), 'Mention lama dan handle mirip tidak memanggil AI.');

        $this->actingAs($this->member)->postJson($this->url('messages'), ['content' => 'Halo @Normi, bantu dong'])->assertCreated();
        $this->assertSame(1, ChatMessageQueue::count());
    }

    public function test_attachments_are_stored_with_detected_type(): void
    {
        $this->actingAs($this->member)->postJson($this->url('messages'), [
            'attachment' => UploadedFile::fake()->create('foto.png', 10, 'image/png'),
        ])->assertCreated()->assertJsonPath('message.message_type', 'image');

        $this->actingAs($this->member)->postJson($this->url('messages'), [
            'attachment' => UploadedFile::fake()->create('rekaman.mp3', 10, 'audio/mpeg'),
        ])->assertCreated()->assertJsonPath('message.message_type', 'voice')->assertJsonPath('message.attachment_mime', 'audio/mpeg');

        $this->actingAs($this->member)->postJson($this->url('messages'), [
            'attachment' => UploadedFile::fake()->create('materi.pdf', 10, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('message.message_type', 'file');

        foreach (Message::all() as $message) {
            Storage::disk('normchat_attachments')->assertExists($message->attachment_path);
            $this->assertStringStartsWith("group-{$this->group->id}/", $message->attachment_path);
        }

        $image = Message::where('message_type', 'image')->first();
        $this->actingAs($this->owner)->get($this->url("messages/{$image->id}/attachment"))
            ->assertOk()->assertHeader('Content-Disposition', 'inline; filename="foto.png"');
    }

    public function test_sender_can_edit_and_previous_content_is_versioned(): void
    {
        $message = $this->messageFrom($this->member, 'versi satu');

        $this->actingAs($this->owner)->patchJson($this->url("messages/{$message->id}"), ['content' => 'diubah owner'])->assertForbidden();

        $this->actingAs($this->member)->patchJson($this->url("messages/{$message->id}"), ['content' => 'versi dua @budi_santoso'])
            ->assertOk()
            ->assertJsonPath('message.content', 'versi dua @budi_santoso')
            ->assertJsonPath('message.is_edited', true);

        $this->assertSame('versi satu', MessageVersion::where('message_id', $message->id)->value('content_snapshot'));
        Event::assertDispatched(MessageUpdated::class);
        Event::assertDispatched(UserMentioned::class, fn (UserMentioned $event) => $event->userId === $this->owner->id);
    }

    public function test_only_moderators_delete_messages(): void
    {
        $message = $this->messageFrom($this->member, 'hapus aku');

        $this->actingAs($this->member)->deleteJson($this->url("messages/{$message->id}"))->assertForbidden();
        $this->actingAs($this->owner)->deleteJson($this->url("messages/{$message->id}"))->assertOk()->assertJsonPath('message_id', $message->id);

        $this->assertSoftDeleted($message);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_polls_can_be_voted_and_counted(): void
    {
        $poll = $this->messageFrom($this->owner, "📊 POLL: Rapat kapan?\n\n1. Senin\n2. Selasa");
        $notPoll = $this->messageFrom($this->owner, 'poll: tanpa ikon');

        $this->actingAs($this->member)->postJson($this->url("polls/{$poll->id}/vote"), ['option_number' => 3])
            ->assertStatus(422)->assertJsonPath('message', 'poll_option_invalid');
        $this->actingAs($this->member)->postJson($this->url("polls/{$notPoll->id}/vote"), ['option_number' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'poll_not_found');

        $this->actingAs($this->member)->postJson($this->url("polls/{$poll->id}/vote"), ['option_number' => 2])
            ->assertOk()->assertJsonPath('poll.option_counts.2', 1)->assertJsonPath('poll.my_vote_option', 2);
        $this->actingAs($this->owner)->postJson($this->url("polls/{$poll->id}/vote"), ['option_number' => 2])->assertOk();
        $this->actingAs($this->member)->postJson($this->url("polls/{$poll->id}/vote"), ['option_number' => 1])->assertOk();

        $this->actingAs($this->owner)->getJson($this->url("polls/stats?ids={$poll->id},{$notPoll->id}"))
            ->assertOk()
            ->assertJsonPath("polls.{$poll->id}.question", 'Rapat kapan?')
            ->assertJsonPath("polls.{$poll->id}.option_counts", ['1' => 1, '2' => 1])
            ->assertJsonPath("polls.{$poll->id}.total_votes", 2)
            ->assertJsonPath("polls.{$poll->id}.my_vote_option", 2)
            ->assertJsonMissingPath("polls.{$notPoll->id}");

        $this->assertSame(2, PollVote::count());
        Event::assertDispatched(PollVoted::class);
    }

    public function test_mentions_notify_members_but_not_the_sender_or_ai(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('messages'), ['content' => '@siti @normi @budi_santoso cek ini'])->assertCreated();

        Event::assertDispatched(UserMentioned::class, 1);
        Event::assertDispatched(UserMentioned::class, fn (UserMentioned $event) => $event->userId === $this->member->id);
    }

    public function test_editing_without_new_mentions_does_not_notify_again(): void
    {
        $message = $this->messageFrom($this->member, 'hai @budi_santoso');

        $this->actingAs($this->member)->patchJson($this->url("messages/{$message->id}"), ['content' => 'halo @budi_santoso'])->assertOk();

        Event::assertNotDispatched(UserMentioned::class);
    }

    public function test_export_poll_summary_includes_counts_and_percentages(): void
    {
        $poll = $this->messageFrom($this->owner, "📊 POLL: Rapat kapan?\n1. Senin\n2. Selasa");
        foreach ([[$this->owner, 1], [$this->member, 2]] as [$user, $option]) {
            PollVote::create(['group_id' => $this->group->id, 'poll_message_id' => $poll->id, 'user_id' => $user->id, 'option_number' => $option, 'voted_at' => now()]);
        }

        $summary = app(\App\Services\Chat\PollService::class)->summariesFor([$poll])[$poll->id];

        $this->assertSame(2, $summary['total_votes']);
        $this->assertSame(
            [['number' => 1, 'label' => 'Senin', 'count' => 1, 'percent' => 50.0], ['number' => 2, 'label' => 'Selasa', 'count' => 1, 'percent' => 50.0]],
            $summary['options']
        );
    }

    public function test_mark_read_tracks_last_read_message(): void
    {
        $first = $this->messageFrom($this->owner, 'satu');
        $this->messageFrom($this->owner, 'dua');

        $this->actingAs($this->member)->postJson($this->url('messages/read'), ['last_read_message_id' => $first->id])
            ->assertOk()->assertJsonPath('last_read_message_id', $first->id)->assertJsonPath('unread_count', 1);

        $this->actingAs($this->member)->get($this->url('chat'))->assertOk()->assertSee('satu');
    }

    private function url(string $path): string
    {
        return "/groups/{$this->group->id}/{$path}";
    }

    private function messageFrom(User $user, string $content): Message
    {
        return Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'message_type' => 'text',
            'content' => $content,
        ]);
    }
}
