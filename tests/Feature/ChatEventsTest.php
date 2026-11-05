<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupDraft;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Chat\SystemMessages;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\ExportOptions;
use App\Services\GroupMembership;
use App\Services\Groups\GroupAvatars;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** docs/rancangan-alur-chat-wa.md: pemberitahuan, riwayat anggota baru, draf, urutan grup, ekspor. */
class ChatEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $friend;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GroupAvatars::DISK);
        Storage::fake('normchat_attachments');
        config()->set('normchat.pricing.free_groups_enabled', false);
        $this->owner = User::factory()->create(['name' => 'Sari Wulan']);
        $this->friend = User::factory()->create(['name' => 'Budi Santoso']);
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'status' => 'active', 'free_member_slots' => 5]);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    private function say(User $user, string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }

    private function initialMessages(User $user): array
    {
        $html = $this->actingAs($user)->get(route('chat.show', $this->group))->assertOk()->getContent();
        preg_match('/<script type="application\/json" data-initial-messages[^>]*>(.*?)<\/script>/s', $html, $match);

        return json_decode($match[1] ?? '[]', true);
    }

    public function test_new_member_sees_only_messages_after_joining_until_the_owner_shares_history(): void
    {
        $this->say($this->owner, 'RAHASIA-LAMA');
        $oldImage = $this->say($this->owner, '', ['message_type' => 'image', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'x/lama.png', 'attachment_mime' => 'image/png', 'attachment_original_name' => 'lama.png']);
        Storage::disk('normchat_attachments')->put('x/lama.png', 'png');

        app(GroupMembership::class)->joinFree($this->group, $this->friend);
        $this->say($this->owner, 'Selamat datang Budi');

        $seen = collect($this->initialMessages($this->friend));
        $this->assertSame(['member_joined', null], $seen->map(fn ($m) => $m['system']['event'] ?? null)->all());
        $this->assertSame(['Budi Santoso bergabung lewat tautan undangan.', 'Selamat datang Budi'], $seen->pluck('content')->all());
        $this->actingAs($this->friend)->get(route('chat.attachment', ['group' => $this->group, 'message' => $oldImage]))->assertNotFound();
        $this->actingAs($this->friend)->get(route('settings.show', $this->group))->assertDontSee('x/lama.png')->assertSee('Belum ada gambar di grup ini.');
        // Pesan yang tertutup tidak dihitung belum dibaca.
        $this->actingAs($this->friend)->get(route('groups.index'))->assertSee('data-unread="1"', false);

        // Pemilik melihat tautan "Kirim riwayat pesan" pada pemberitahuan bergabung; anggota itu tidak.
        $join = collect($this->initialMessages($this->owner))->firstWhere('system.event', 'member_joined');
        $this->assertTrue($join['system']['history_pending']);
        $this->assertSame('Budi Santoso bergabung lewat tautan undangan.', $join['system']['text']);

        // Hanya pemilik.
        $admin = User::factory()->create();
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $admin->id, 'role_id' => Role::forKey(Role::ADMIN)->id, 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($admin)->postJson(route('groups.members.history', ['group' => $this->group, 'user' => $this->friend->id]))->assertForbidden();

        $this->actingAs($this->owner)->postJson(route('groups.members.history', ['group' => $this->group, 'user' => $this->friend->id]))->assertOk()->assertJson(['shared' => true]);
        $this->actingAs($this->owner)->postJson(route('groups.members.history', ['group' => $this->group, 'user' => $this->friend->id]))->assertOk()->assertJson(['shared' => false]);
        $this->assertSame(1, Message::where('system_event', SystemMessages::HISTORY_SHARED)->count());
        $this->assertTrue(AuditLog::where('action', 'group.history_shared')->exists());

        $seen = collect($this->initialMessages($this->friend));
        $this->assertContains('RAHASIA-LAMA', $seen->pluck('content')->all());
        $this->assertSame('Sari Wulan mengirim riwayat pesan ke Budi Santoso.', $seen->last()['system']['text']);
        $this->actingAs($this->friend)->get(route('chat.attachment', ['group' => $this->group, 'message' => $oldImage]))->assertOk();
        $this->assertFalse(collect($this->initialMessages($this->owner))->firstWhere('system.event', 'member_joined')['system']['history_pending']);
    }

    public function test_rejoining_closes_the_history_again_and_leave_and_remove_are_recorded(): void
    {
        app(GroupMembership::class)->joinFree($this->group, $this->friend);
        $this->actingAs($this->friend)->post(route('groups.leave', $this->group))->assertRedirect(route('groups.index'));
        $this->say($this->owner, 'Sesudah Budi keluar');

        app(GroupMembership::class)->joinFree($this->group, $this->friend);
        $member = GroupMember::where('group_id', $this->group->id)->where('user_id', $this->friend->id)->first();
        $this->assertSame((int) Message::where('system_event', SystemMessages::MEMBER_JOINED)->max('id') - 1, (int) $member->history_from_message_id);
        $this->assertNotContains('Sesudah Budi keluar', collect($this->initialMessages($this->friend))->pluck('content')->all());

        $this->actingAs($this->owner)->post(route('groups.members.remove', ['group' => $this->group, 'member' => $member]));
        $this->assertSame(
            ['member_joined', 'member_left', null, 'member_joined', 'member_removed'],
            Message::where('group_id', $this->group->id)->orderBy('id')->pluck('system_event')->all(),
        );
        $this->assertSame('Sari Wulan mengeluarkan Budi Santoso.', Message::where('system_event', 'member_removed')->value('content'));
    }

    public function test_profile_changes_are_announced_with_before_and_after_and_limits_apply(): void
    {
        $this->group->update(['description' => 'Deskripsi lama']);
        $update = fn (array $data) => $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), $data);

        $update(['name' => 'Kelas BD']); // tidak berubah: tidak ada pemberitahuan
        $update(['name' => 'Kelas Basis Data']);
        $update(['description' => 'Deskripsi baru']);
        $update(['description' => '']);
        $update(['avatar' => UploadedFile::fake()->image('a.png', 64, 64)]);
        $update(['remove_avatar' => '1']);

        $events = Message::where('group_id', $this->group->id)->orderBy('id')->get();
        $this->assertSame(['name_changed', 'description_changed', 'description_changed', 'photo_changed', 'photo_removed'], $events->pluck('system_event')->all());
        $this->assertSame('Sari Wulan mengganti nama grup dari «Kelas BD» menjadi «Kelas Basis Data».', $events[0]->content);
        $this->assertSame(['before' => 'Deskripsi lama', 'after' => 'Deskripsi baru'], array_intersect_key($events[1]->system_meta, ['before' => 1, 'after' => 1]));
        $this->assertSame('Sari Wulan menghapus deskripsi grup.', $events[2]->content);

        $update(['name' => str_repeat('a', Group::NAME_MAX + 1)])->assertSessionHasErrors('name');
        $update(['description' => str_repeat('a', Group::DESCRIPTION_MAX + 1)])->assertSessionHasErrors('description');
        $this->actingAs($this->owner)->get(route('settings.show', $this->group))->assertSee('maxlength="60"', false)->assertSee('maxlength="300"', false)->assertSee('data-char-count', false);
        // Pemberitahuan tidak bisa diedit, dihapus, atau diteruskan.
        $this->actingAs($this->owner)->deleteJson(route('chat.destroy', ['group' => $this->group, 'message' => $events[0]]))->assertForbidden();
    }

    public function test_drafts_sync_last_24_hours_and_put_the_group_on_top(): void
    {
        $other = Group::create(['name' => 'Grup Lain', 'owner_id' => $this->owner->id, 'status' => 'active']);
        GroupMember::create(['group_id' => $other->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        $this->say($this->owner, 'pesan lama Kelas BD');
        $this->travel(1)->minutes();
        Message::create(['group_id' => $other->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => 'pesan baru Grup Lain']);

        // Tanpa draf: urut pesan terbaru (HP dan sidebar desktop).
        $this->actingAs($this->owner)->get(route('groups.index'))->assertSeeInOrder(['Grup Lain', 'Kelas BD']);

        $this->actingAs($this->owner)->putJson(route('groups.draft.update', $this->group), ['content' => 'belum dikirim'])->assertOk();
        $this->actingAs($this->owner)->get(route('groups.index'))->assertSeeInOrder(['Kelas BD', 'Draf', 'belum dikirim', 'Grup Lain']);
        $this->actingAs($this->owner)->get(route('chat.show', $other))->assertSeeInOrder(['data-sidebar', 'Kelas BD', 'belum dikirim', 'Grup Lain'], false);
        // Kolom ketik terisi lagi saat grup dibuka (perangkat lain juga, karena dari server).
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertSee('data-draft="{&quot;content&quot;:&quot;belum dikirim&quot;', false);
        // Tanda kutip di draf tidak merusak atribut HTML.
        $this->actingAs($this->owner)->putJson(route('groups.draft.update', $this->group), ['content' => "it's \"<b>x</b>"])->assertOk();
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertSee('it&#039;s', false)->assertDontSee('<b>x</b>', false);
        $this->actingAs($this->owner)->putJson(route('groups.draft.update', $this->group), ['content' => 'belum dikirim'])->assertOk();

        // Terkirim: draf selesai.
        $this->actingAs($this->owner)->postJson(route('chat.store', $this->group), ['content' => 'belum dikirim'])->assertCreated();
        $this->assertSame(0, GroupDraft::count());

        // Lebih dari 24 jam: tidak ditampilkan lagi, lalu dibersihkan terjadwal.
        $this->actingAs($this->owner)->postJson(route('groups.draft.beacon', $other), ['content' => 'draf basi'])->assertOk();
        $this->travel(25)->hours();
        $this->actingAs($this->owner)->get(route('groups.index'))->assertDontSee('draf basi');
        $this->artisan('normchat:prune-drafts')->assertSuccessful();
        $this->assertSame(0, GroupDraft::count());

        // Isi kosong menghapus draf; orang lain tidak bisa menulis draf di grup yang bukan miliknya.
        $this->actingAs($this->owner)->putJson(route('groups.draft.update', $this->group), ['content' => 'x'])->assertOk();
        $this->actingAs($this->owner)->putJson(route('groups.draft.update', $this->group), ['content' => '  '])->assertOk();
        $this->assertSame(0, GroupDraft::count());
        $this->actingAs(User::factory()->create())->putJson(route('groups.draft.update', $this->group), ['content' => 'x'])->assertForbidden();
    }

    public function test_chat_page_has_created_card_day_marker_and_unread_position_data(): void
    {
        app(GroupMembership::class)->joinFree($this->group, $this->friend);
        $html = $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertOk()->getContent();
        $this->assertStringContainsString('Kamu yang membuat grup ini', $html);
        $this->assertStringContainsString('Undang via tautan atau kode QR', $html);
        $this->assertStringContainsString('data-day-float', $html);
        $this->assertStringContainsString('data-viewer-is-owner="1"', $html);
        $this->actingAs($this->friend)->get(route('chat.show', $this->group))->assertSee('Sari Wulan membuat grup ini');
    }

    public function test_export_includes_group_created_and_member_notices_and_respects_closed_history(): void
    {
        $this->say($this->owner, 'RAHASIA-LAMA');
        app(GroupMembership::class)->joinFree($this->group, $this->friend);
        $this->say($this->friend, 'Halo semua');
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['description' => 'Belajar bareng']);

        $owner = app(ChatExportBuilder::class)->build($this->group, new ExportOptions('pdf', 'all', null, null, [], true, true, 'Asia/Jakarta'), $this->owner);
        $notices = collect($owner->entries)->pluck('notice.text')->filter()->values()->all();
        $this->assertSame(['Sari Wulan membuat grup ini.', 'Budi Santoso bergabung lewat tautan undangan.', 'Sari Wulan mengubah deskripsi grup.'], $notices);
        $this->assertSame('Belajar bareng', collect($owner->entries)->last()->notice['after']);
        $this->assertSame(2, $owner->count(), 'Pemberitahuan tidak dihitung sebagai pesan.');

        $friend = app(ChatExportBuilder::class)->build($this->group, new ExportOptions('pdf', 'all', null, null, [], true, true, 'Asia/Jakarta'), $this->friend);
        $texts = collect($friend->entries)->map(fn ($entry) => $entry->notice['text'] ?? $entry->content)->all();
        $this->assertNotContains('RAHASIA-LAMA', $texts);
        $this->assertNotContains('Sari Wulan membuat grup ini.', $texts);
        $this->assertContains('Halo semua', $texts);

        // PDF dan DOCX memuat baris pemberitahuan.
        $pdf = app(\App\Services\Exports\PdfChatExporter::class)->render($owner);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
