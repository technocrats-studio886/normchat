<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Support\MessageRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Batas 3.000 karakter sama di kolom ketik dan server; baris baru CRLF dari browser dihitung satu karakter. */
class MessageLengthLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->group = Group::create(['name' => 'Grup', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_exactly_the_limit_with_browser_newlines_is_accepted(): void
    {
        $text = implode("\r\n", array_fill(0, 75, str_repeat('a', 39))); // 75×39 + 74 baris baru = 2.999
        $text .= 'b';
        $this->assertSame(3074, mb_strlen($text));
        $this->assertSame(MessageRules::MAX_CONTENT, mb_strlen(str_replace("\r\n", "\n", $text)));

        $this->actingAs($this->owner)->postJson(route('chat.store', $this->group), ['content' => $text])->assertCreated();
        $this->assertSame(MessageRules::MAX_CONTENT, mb_strlen(Message::sole()->content));
    }

    public function test_one_over_the_limit_is_refused_with_an_indonesian_message_and_nothing_is_saved(): void
    {
        $this->actingAs($this->owner)->postJson(route('chat.store', $this->group), ['content' => str_repeat('a', MessageRules::MAX_CONTENT + 1)])
            ->assertUnprocessable()->assertJsonPath('errors.content.0', MessageRules::tooLongMessage());
        $this->assertSame(0, Message::count());
    }

    public function test_edit_and_scheduled_messages_share_the_same_limit(): void
    {
        $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => 'awal']);
        $this->actingAs($this->owner)->patchJson(route('chat.update', [$this->group, $message]), ['content' => str_repeat('a', 3001)])
            ->assertUnprocessable()->assertJsonPath('errors.content.0', MessageRules::tooLongMessage());
        $this->actingAs($this->owner)->patchJson(route('chat.update', [$this->group, $message]), ['content' => implode("\r\n", array_fill(0, 1500, 'a'))])->assertOk();

        $this->actingAs($this->owner)->postJson(route('groups.tools.scheduled.store', $this->group), ['content' => str_repeat('a', 3001), 'send_at' => now()->addHour()->toIso8601String()])
            ->assertUnprocessable()->assertJsonPath('errors.content.0', MessageRules::tooLongMessage());
    }

    public function test_composer_reads_the_limit_from_the_server(): void
    {
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertOk()
            ->assertSee('data-max-length="'.MessageRules::MAX_CONTENT.'"', false)
            ->assertSee('data-length-note', false);
    }
}
