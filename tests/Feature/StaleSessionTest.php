<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman tidak boleh tampil dari cache browser dengan sesi lama (laporan user 7 Nov 2026: setelah keluar,
 * "Halo, Alex" tampil lagi lewat tombol kembali dan izin realtime berulang kali dialihkan ke /login).
 */
class StaleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_pages_are_not_stored_by_the_browser(): void
    {
        config()->set('normchat.pricing.free_groups_enabled', false);
        $user = User::factory()->create(['tour_seen_at' => now()]);

        foreach (['/login', '/'] as $url) {
            $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        foreach ([route('groups.index'), route('profile.show')] as $url) {
            $cache = (string) $this->actingAs($user)->get($url)->assertOk()->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cache, $url);
        }
    }

    public function test_attachments_keep_their_own_cache_lifetime(): void
    {
        Storage::fake('normchat_attachments');
        Storage::disk('normchat_attachments')->put('x/catatan.html', '<p>hai</p>');
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Kelas', 'owner_id' => $user->id, 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        $message = Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'file', 'content' => '',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'x/catatan.html', 'attachment_mime' => 'text/html', 'attachment_original_name' => 'catatan.html', 'attachment_size' => 11]);

        $cache = (string) $this->actingAs($user)->get(route('chat.attachment', [$group, $message]))->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=3600', $cache);
        $this->assertStringNotContainsString('no-store', $cache);
    }

    public function test_realtime_authorization_answers_401_instead_of_redirecting_when_signed_out(): void
    {
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-group.1'])->assertUnauthorized();
    }
}
