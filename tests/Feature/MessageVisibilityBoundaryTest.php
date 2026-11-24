<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupDraft;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Riwayat sebelum anggota baru bergabung (history_from_message_id) dan pemberitahuan privat anggota lain
 * tidak boleh terbaca lewat endpoint mana pun yang menerima id pesan (audit 23 Nov 2026).
 */
class MessageVisibilityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $late;

    private Group $group;

    private Message $closed;

    private Message $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->late = User::factory()->create();
        $this->group = $this->groupOf($this->owner, 'Grup Lama');
        $this->closed = $this->say('RAHASIA-SEBELUM-GABUNG');
        $this->private = Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => 'ai', 'content' => 'NOTICE-PRIVAT-PEMILIK', 'visible_to_user_id' => $this->owner->id]);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->late->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now(), 'history_from_message_id' => $this->private->id]);
    }

    private function groupOf(User $owner, string $name): Group
    {
        $group = Group::create(['name' => $name, 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);

        return $group;
    }

    private function say(string $content): Message
    {
        return Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => $content]);
    }

    public function test_versions_of_closed_or_private_messages_are_not_found(): void
    {
        foreach ([$this->closed, $this->private] as $message) {
            $this->actingAs($this->late)->getJson(route('chat.versions', [$this->group, $message]))->assertNotFound();
        }
        $this->actingAs($this->owner)->getJson(route('chat.versions', [$this->group, $this->closed]))->assertOk()
            ->assertJsonPath('versions.0.content', 'RAHASIA-SEBELUM-GABUNG');
    }

    public function test_closed_messages_cannot_be_forwarded_out(): void
    {
        $mine = $this->groupOf($this->late, 'Grup Saya');
        $this->actingAs($this->late)->postJson(route('chat.forward', [$this->group, $this->closed]), ['group_ids' => [$mine->id]])->assertNotFound();
        $this->assertFalse(Message::where('group_id', $mine->id)->exists());

        $visible = $this->say('boleh diteruskan');
        $this->actingAs($this->late)->postJson(route('chat.forward', [$this->group, $visible]), ['group_ids' => [$mine->id]])->assertOk()->assertJsonPath('forwarded', 1);
    }

    public function test_replying_to_a_closed_message_drops_the_reply_target(): void
    {
        $response = $this->actingAs($this->late)->postJson(route('chat.store', $this->group), ['content' => 'halo', 'reply_to_message_id' => $this->closed->id])->assertCreated();
        $this->assertStringNotContainsString('RAHASIA', $response->getContent());
        $this->assertNull(Message::find($response->json('message.id'))->reply_to_message_id);

        $visible = $this->say('pertanyaan baru');
        $reply = $this->actingAs($this->late)->postJson(route('chat.store', $this->group), ['content' => 'jawab', 'reply_to_message_id' => $visible->id])->assertCreated();
        $this->assertSame($visible->id, Message::find($reply->json('message.id'))->reply_to_message_id);
    }

    public function test_closed_canvas_poll_draft_and_delete_are_refused(): void
    {
        Storage::disk('normchat_attachments')->put('g/lama.html', '<p>canvas lama</p>');
        $canvas = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'canvas', 'content' => 'Canvas',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'g/lama.html', 'attachment_mime' => 'text/html']);
        $poll = $this->say("📊 Poll: Rapat kapan?\n1. Senin\n2. Selasa");
        GroupMember::where('user_id', $this->late->id)->update(['history_from_message_id' => $poll->id]);

        $this->actingAs($this->late)->get(route('chat.canvas', [$this->group, $canvas]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('chat.canvas', [$this->group, $canvas]))->assertOk();

        $this->actingAs($this->late)->postJson(route('chat.polls.vote', [$this->group, $poll]), ['option_number' => 1])->assertNotFound();
        $this->actingAs($this->late)->getJson(route('chat.polls.stats', [$this->group, 'ids' => $poll->id]))->assertOk()->assertJsonPath('polls', []);

        $this->actingAs($this->late)->deleteJson(route('chat.destroy', [$this->group, $canvas]))->assertNotFound();
        $this->assertNotSoftDeleted($canvas);

        $this->actingAs($this->late)->putJson(route('groups.draft.update', $this->group), ['content' => 'draf', 'reply_to_message_id' => $this->closed->id])->assertOk();
        $this->assertNull(GroupDraft::where('user_id', $this->late->id)->value('reply_to_message_id'));
    }

    public function test_own_messages_behind_a_rejoin_cannot_be_edited(): void
    {
        $old = Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => 'user', 'sender_id' => $this->late->id, 'content' => 'pesan lama']);
        GroupMember::where('user_id', $this->late->id)->update(['history_from_message_id' => $old->id]);

        $this->actingAs($this->late)->patchJson(route('chat.update', [$this->group, $old]), ['content' => 'ubah'])->assertNotFound();
        $this->assertSame('pesan lama', $old->fresh()->content);
    }
}
