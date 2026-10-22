<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Sidebar desktop: grup milik user saja, pesan terakhir, jumlah belum dibaca, profil di bawah. */
class DesktopSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_lists_own_groups_with_latest_message_and_unread_count(): void
    {
        $me = User::factory()->create(['name' => 'Siti Aminah']);
        $friend = User::factory()->create(['name' => 'Budi Santoso']);
        $mine = $this->groupWith('Kelas BD', $me, [$friend]);
        $quiet = $this->groupWith('Grup Sepi', $me);
        $this->groupWith('Grup Orang Lain', $friend);

        $first = $this->message($mine, $me, 'sudah kubaca');
        GroupMember::where('group_id', $mine->id)->where('user_id', $me->id)->update(['last_read_message_id' => $first->id]);
        $this->message($mine, $friend, 'pesan baru satu');
        $this->message($mine, $friend, 'pesan baru dua');

        $this->actingAs($me)->get('/groups')
            ->assertOk()
            ->assertSee('data-sidebar="1"', false)
            ->assertSeeInOrder(['Kelas BD', 'Budi: pesan baru dua', '2', 'Grup Sepi', 'Belum ada pesan'])
            ->assertDontSee('Grup Orang Lain')
            ->assertSee('Siti Aminah')
            ->assertSee('Pilih grup untuk mulai chat');
    }

    public function test_open_group_is_marked_active_without_unread_badge(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $group = $this->groupWith('Kelas BD', $me, [$friend]);
        $this->message($group, $friend, 'halo');

        $this->actingAs($me)->get("/groups/{$group->id}/chat")
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('nc-sidebar-badge', false);
    }

    public function test_guests_do_not_get_a_sidebar(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('data-sidebar="1"', false);
    }

    /** @param list<User> $members */
    private function groupWith(string $name, User $owner, array $members = []): Group
    {
        $group = Group::create(['name' => $name, 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([[$owner, Role::OWNER], ...array_map(fn ($user) => [$user, Role::MEMBER], $members)] as [$user, $role]) {
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

    private function message(Group $group, User $sender, string $content): Message
    {
        return Message::create([
            'group_id' => $group->id,
            'sender_type' => 'user',
            'sender_id' => $sender->id,
            'message_type' => 'text',
            'content' => $content,
        ]);
    }
}
