<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Chat\MessagePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman tidak boleh tampil dari cache browser dengan sesi lama (laporan user 7 Nov 2026: setelah keluar,
 * "Halo, Alex" tampil lagi lewat tombol kembali dan izin realtime berulang kali dialihkan ke /login).
 * Halaman tetap boleh masuk Back-Forward Cache (no-cache, bukan no-store; laporan user 9 Nov 2026):
 * resources/js/session-guard.js memeriksa sesi setiap kali halaman dipulihkan dari cache.
 */
class StaleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_pages_must_be_revalidated_but_may_use_the_back_forward_cache(): void
    {
        config()->set('normchat.pricing.free_groups_enabled', false);
        $user = User::factory()->create(['tour_seen_at' => now()]);

        $assertRevalidated = function (string $cache, string $url): void {
            $this->assertStringContainsString('no-cache', $cache, $url);
            $this->assertStringContainsString('private', $cache, $url);
            // no-store membuat halaman tidak pernah masuk Back-Forward Cache.
            $this->assertStringNotContainsString('no-store', $cache, $url);
        };

        foreach (['/login', '/'] as $url) {
            $assertRevalidated((string) $this->get($url)->assertOk()->headers->get('Cache-Control'), $url);
        }
        foreach ([route('groups.index'), route('profile.show')] as $url) {
            $assertRevalidated((string) $this->actingAs($user)->get($url)->assertOk()->headers->get('Cache-Control'), $url);
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

        // URL berversi dari MessagePresenter: isinya tidak pernah berganti, jadi disimpan selamanya.
        $versioned = MessagePresenter::attachmentUrl($message);
        $response = $this->actingAs($user)->get($versioned)->assertOk();
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $etag = (string) $response->headers->get('ETag');
        $this->assertNotSame('', $etag);

        // Muat ulang dengan ETag yang sama cukup dijawab 304 tanpa isi file.
        $this->actingAs($user)->get($versioned, ['If-None-Match' => $etag])->assertStatus(304);

        // Lampiran pesan diganti (jawab ulang Normi): versinya ikut berganti.
        $message->update(['attachment_path' => 'x/catatan-2.html']);
        $this->assertNotSame($versioned, MessagePresenter::attachmentUrl($message->refresh()));
    }

    public function test_realtime_authorization_answers_401_instead_of_redirecting_when_signed_out(): void
    {
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-group.1'])->assertUnauthorized();
    }
}
