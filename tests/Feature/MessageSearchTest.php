<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\Role;
use App\Models\User;
use App\Services\Chat\SystemMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Cari pesan di seluruh riwayat grup (docs/rancangan-cari-pesan.md). */
class MessageSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Budi Santoso']);
        $this->member = User::factory()->create(['name' => 'Siti']);
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
    }

    private function say(string $content, ?User $from = null, array $extra = []): Message
    {
        $from ??= $this->owner;

        return Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => 'user', 'sender_id' => $from->id, 'content' => $content, ...$extra]);
    }

    private function search(User $as, string $q, array $params = [])
    {
        return $this->actingAs($as)->getJson(route('chat.search', ['group' => $this->group, 'q' => $q] + $params));
    }

    public function test_finds_matches_case_insensitively_newest_first_with_total_and_sender_labels(): void
    {
        $first = $this->say('Jadwal NOMOR 1 besok');
        $this->say('Tidak ada yang cocok');
        $second = $this->say('nomor 1 sudah selesai', $this->member);
        $ai = Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => 'ai', 'content' => 'Jawaban untuk Nomor 1 adalah benar']);

        $response = $this->search($this->member, 'nomor 1')->assertOk()->assertJsonPath('total', 3)->assertJsonPath('has_more', false);

        $this->assertSame([$ai->id, $second->id, $first->id], array_column($response->json('results'), 'id'));
        $this->assertSame(['Normi AI', 'Kamu', 'Budi Santoso'], array_column($response->json('results'), 'sender_name'));
        $this->assertNull($response->json('results.0.sender_avatar_url'));
        $this->assertNotEmpty($response->json('results.1.created_at'));
    }

    public function test_requires_two_characters_and_escapes_like_wildcards(): void
    {
        $this->say('diskon 50% hari ini');
        $this->say('diskon 50 persen');
        $this->say('a_b');
        $this->say('axb');

        $this->search($this->member, 'd')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('results', []);
        $this->search($this->member, '50%')->assertJsonPath('total', 1);
        $this->search($this->member, 'a_b')->assertJsonPath('total', 1);
    }

    public function test_matches_attachment_file_names(): void
    {
        $this->say('', null, ['message_type' => 'file', 'attachment_original_name' => 'Laporan-Keuangan.pdf', 'attachment_path' => 'x/y.pdf']);

        $this->search($this->member, 'keuangan')->assertJsonPath('total', 1)->assertJsonPath('results.0.snippet', 'Laporan-Keuangan.pdf');
    }

    public function test_only_returns_messages_the_viewer_may_see(): void
    {
        $visible = $this->say('rapat kamis');
        $deleted = $this->say('rapat dihapus');
        $deleted->delete();
        $hidden = $this->say('rapat disembunyikan');
        MessageHide::create(['user_id' => $this->member->id, 'message_id' => $hidden->id]);
        $this->say('rapat pribadi owner', null, ['sender_type' => 'ai', 'sender_id' => null, 'visible_to_user_id' => $this->owner->id]);
        $own = $this->say('rapat pribadi member', null, ['sender_type' => 'ai', 'sender_id' => null, 'visible_to_user_id' => $this->member->id]);
        Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'sender_type' => Message::SENDER_SYSTEM, 'system_event' => SystemMessages::MEMBER_JOINED, 'content' => 'rapat: Siti bergabung']);

        $ids = array_column($this->search($this->member, 'rapat')->assertJsonPath('total', 2)->json('results'), 'id');

        $this->assertEqualsCanonicalizing([$visible->id, $own->id], $ids);
    }

    public function test_respects_new_member_history_limit(): void
    {
        $old = $this->say('catatan lama');
        $cutoff = $this->say('catatan batas');
        $new = $this->say('catatan baru');
        GroupMember::where(['group_id' => $this->group->id, 'user_id' => $this->member->id])->update(['history_from_message_id' => $cutoff->id]);

        $this->search($this->member, 'catatan')->assertJsonPath('total', 1)->assertJsonPath('results.0.id', $new->id);
        $this->search($this->owner, 'catatan')->assertJsonPath('total', 3);
        $this->assertNotNull($old);
    }

    public function test_pages_with_before_id(): void
    {
        $ids = [];
        foreach (range(1, 35) as $n) {
            $ids[] = $this->say("kata kunci nomor {$n}")->id;
        }

        $page1 = $this->search($this->member, 'kata kunci')->assertJsonPath('total', 35)->assertJsonPath('has_more', true);
        $this->assertCount(30, $page1->json('results'));
        $last = $page1->json('results.29.id');

        $page2 = $this->search($this->member, 'kata kunci', ['before_id' => $last])->assertJsonPath('total', 35)->assertJsonPath('has_more', false);
        $this->assertSame(array_reverse(array_slice($ids, 0, 5)), array_column($page2->json('results'), 'id'));
    }

    public function test_snippet_is_cut_around_the_first_match_with_ellipses(): void
    {
        $long = str_repeat('awal ', 30) . 'KATA-TARGET ' . str_repeat('akhir ', 30);
        $this->say($long);
        $this->say('pendek saja');

        $snippet = $this->search($this->member, 'kata-target')->json('results.0.snippet');

        $this->assertStringContainsString('KATA-TARGET', $snippet);
        $this->assertStringStartsWith('…', $snippet);
        $this->assertStringEndsWith('…', $snippet);
        $this->assertLessThanOrEqual(94, mb_strlen($snippet));
        $this->assertSame('pendek saja', $this->search($this->member, 'pendek')->json('results.0.snippet'));
    }

    public function test_outsiders_cannot_search(): void
    {
        $this->say('rahasia grup');
        $stranger = User::factory()->create();

        $this->search($stranger, 'rahasia')->assertForbidden();
    }

    public function test_around_returns_target_with_neighbours_and_flags(): void
    {
        $ids = [];
        foreach (range(1, 100) as $n) {
            $ids[] = $this->say("pesan {$n}")->id;
        }
        $target = $ids[49];

        $response = $this->actingAs($this->member)->getJson(route('chat.around', ['group' => $this->group, 'message' => $target]))->assertOk()
            ->assertJsonPath('target_id', $target)->assertJsonPath('has_older', true)->assertJsonPath('has_newer', true);

        $got = array_column($response->json('messages'), 'id');
        $this->assertCount(61, $got);
        $this->assertSame(array_slice($ids, 19, 61), $got);
        $this->assertSame('pesan 50', $response->json('messages.30.content'));

        $edge = $this->actingAs($this->member)->getJson(route('chat.around', ['group' => $this->group, 'message' => $ids[2]]))->assertOk()
            ->assertJsonPath('has_older', false);
        $this->assertSame(array_slice($ids, 0, 33), array_column($edge->json('messages'), 'id'));
    }

    public function test_around_follows_feed_visibility_rules(): void
    {
        $old = $this->say('lama');
        $cutoff = $this->say('batas');
        $new = $this->say('baru');
        $private = $this->say('pribadi owner', null, ['sender_type' => 'ai', 'sender_id' => null, 'visible_to_user_id' => $this->owner->id]);
        GroupMember::where(['group_id' => $this->group->id, 'user_id' => $this->member->id])->update(['history_from_message_id' => $cutoff->id]);

        $this->actingAs($this->member)->getJson(route('chat.around', ['group' => $this->group, 'message' => $old->id]))->assertNotFound();
        $this->actingAs($this->member)->getJson(route('chat.around', ['group' => $this->group, 'message' => $private->id]))->assertNotFound();
        $ok = $this->actingAs($this->member)->getJson(route('chat.around', ['group' => $this->group, 'message' => $new->id]))->assertOk();
        $this->assertSame([$new->id], array_column($ok->json('messages'), 'id'));

        $other = Group::create(['name' => 'Lain', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        $foreign = Message::create(['group_id' => $other->id, 'message_type' => 'text', 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => 'grup lain']);
        $this->actingAs($this->owner)->getJson(route('chat.around', ['group' => $this->group, 'message' => $foreign->id]))->assertNotFound();
    }

    public function test_recent_reloads_latest_messages_and_created_card_flag(): void
    {
        foreach (range(1, 85) as $n) {
            $this->say("pesan {$n}");
        }

        $response = $this->actingAs($this->member)->getJson(route('chat.recent', ['group' => $this->group]))->assertOk()->assertJsonPath('show_created_card', false);
        $this->assertCount(80, $response->json('messages'));
        $this->assertSame('pesan 85', $response->json('messages.79.content'));
        $this->assertSame('pesan 6', $response->json('messages.0.content'));
    }
}
