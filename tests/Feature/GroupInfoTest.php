<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use App\Services\FreeGroups;
use App\Services\Groups\GroupAvatars;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Info grup + foto grup (docs/rancangan-info-grup.md). */
class GroupInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GroupAvatars::DISK);
        $this->owner = User::factory()->create(['name' => 'Sari Wulan']);
        $this->member = User::factory()->create(['name' => 'Budi']);
        $this->group = app(FreeGroups::class)->ensureFirstGroup($this->owner);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->member->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_owner_uploads_replaces_and_removes_the_group_photo_on_the_avatar_disk(): void
    {
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), [
            'name' => 'Grup Baru',
            'avatar' => UploadedFile::fake()->image('foto.png', 900, 600),
        ])->assertRedirect()->assertSessionHas('group_info', 'main');

        $first = $this->group->fresh()->avatar_path;
        $this->assertSame('Grup Baru', $this->group->fresh()->name);
        $this->assertMatchesRegularExpression('#^groups/'.$this->group->id.'-[A-Za-z0-9]{24}\.webp$#', $first);
        Storage::disk(GroupAvatars::DISK)->assertExists($first);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk(GroupAvatars::DISK)->get($first));
        $this->assertSame([512, 512, IMAGETYPE_WEBP], [$width, $height, $type]);

        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), [
            'name' => 'Grup Baru',
            'avatar' => UploadedFile::fake()->image('lagi.jpg', 300, 300),
        ]);
        $second = $this->group->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk(GroupAvatars::DISK)->assertMissing($first);

        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['name' => 'Grup Baru', 'remove_avatar' => '1']);
        $this->assertNull($this->group->fresh()->avatar_path);
        Storage::disk(GroupAvatars::DISK)->assertMissing($second);
        $this->assertTrue(AuditLog::where('action', 'settings.update_group_profile')->get()->contains(fn ($log) => ($log->metadata_json['avatar_removed'] ?? false) === true));
    }

    public function test_photo_upload_is_validated_and_limited_to_group_managers(): void
    {
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), [
            'name' => 'X',
            'avatar' => UploadedFile::fake()->create('dok.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), [
            'name' => 'X',
            'avatar' => UploadedFile::fake()->image('besar.png')->size(6000),
        ])->assertSessionHasErrors('avatar');

        $this->actingAs($this->member)->post(route('settings.profile.update', $this->group), [
            'name' => 'Dibajak',
            'avatar' => UploadedFile::fake()->image('foto.png'),
        ])->assertForbidden();
        $this->assertNull($this->group->fresh()->avatar_path);
        $this->assertSame([], Storage::disk(GroupAvatars::DISK)->allFiles());
    }

    public function test_photo_is_served_to_members_and_to_people_holding_the_group_code(): void
    {
        $this->actingAs($this->member)->get(route('groups.avatar', $this->group))->assertNotFound();

        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['name' => 'G', 'avatar' => UploadedFile::fake()->image('a.png', 64, 64)]);
        $group = $this->group->fresh();

        $this->actingAs($this->member)->get($group->avatarUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('groups.avatar', $group))->assertForbidden();
        $this->actingAs($stranger)->get($group->joinAvatarUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($stranger)->get(route('groups.join', $group->share_id))->assertSee($group->joinAvatarUrl(), false);
        $this->actingAs($stranger)->get(route('groups.join.avatar', 'ZZZZZZ'))->assertNotFound();

        // Foto tampil di header chat, daftar grup, dan Info grup.
        $this->actingAs($this->member)->get(route('chat.show', $group))->assertSee($group->avatarUrl(), false);
        $this->actingAs($this->owner)->get(route('groups.index'))->assertSee($group->avatarUrl(), false);
    }

    public function test_info_page_shows_balance_under_the_profile_and_settings_by_role(): void
    {
        $this->group->update(['description' => 'Belajar bareng', 'member_daily_credit_limit' => 5]);

        $owner = $this->actingAs($this->owner)->get(route('settings.show', $this->group))->assertOk();
        // Profil atas, edit per bagian (foto, nama, deskripsi masing-masing punya form sendiri)
        // → saldo + Tambah → Cari, Bagikan, Lainnya → tab Anggota | Media | Pengaturan.
        $owner->assertSeeInOrder(['Ubah foto grup', 'Pasang foto', 'name="avatar"', 'data-info-field="name"', 'name="name"', 'data-info-field="description"', 'Belajar bareng', 'name="description"', 'Saldo grup', '>Tambah<', 'Cari', 'Bagikan', 'Lainnya', '>Anggota<', '>Media<', '>Pengaturan<', 'Tambah anggota', 'Media, tautan, dan dok', 'Kata sandi grup', 'Batas harian per anggota'], false)
            ->assertDontSee('Isi Normkredit')->assertDontSee('Kelola')->assertSee('name="member_daily_credit_limit"', false)
            ->assertSee('Hapus grup')->assertSee('data-share-sheet', false)->assertSee(route('groups.join', $this->group->share_id));

        $this->actingAs($this->member)->get(route('settings.show', $this->group))->assertOk()
            ->assertDontSee('data-info-field-edit', false)->assertDontSee('name="avatar"', false)->assertDontSee('name="password"', false)
            ->assertDontSee('name="member_daily_credit_limit"', false)
            ->assertSee('Batas pemakaian 5,00 NK per anggota per hari')->assertSee('Keluar grup')->assertDontSee('Hapus grup');
    }

    public function test_each_profile_part_saves_on_its_own(): void
    {
        $this->group->update(['description' => 'Deskripsi lama']);
        $name = $this->group->name;

        // Deskripsi saja: nama tetap.
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['description' => 'Deskripsi baru'])->assertSessionHasNoErrors();
        $this->assertSame([$name, 'Deskripsi baru'], [$this->group->fresh()->name, $this->group->fresh()->description]);

        // Nama saja: deskripsi tetap.
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['name' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame(['Nama Baru', 'Deskripsi baru'], [$this->group->fresh()->name, $this->group->fresh()->description]);

        // Foto saja: nama dan deskripsi tetap.
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['avatar' => UploadedFile::fake()->image('a.png', 80, 80)])->assertSessionHasNoErrors();
        $group = $this->group->fresh();
        $this->assertSame(['Nama Baru', 'Deskripsi baru'], [$group->name, $group->description]);
        $this->assertNotNull($group->avatar_path);

        // Nama kosong ditolak dan kolom nama terbuka lagi dengan pesannya, bagian lain tidak.
        $this->actingAs($this->owner)->from(route('settings.show', $this->group))->post(route('settings.profile.update', $this->group), ['name' => '']);
        $html = $this->actingAs($this->owner)->get(route('settings.show', $this->group))->getContent();
        $this->assertMatchesRegularExpression('/data-info-field="name"\s+data-editing/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-info-field="description"\s+data-editing/', $html);
    }

    public function test_password_form_changes_only_the_password_and_returns_to_the_settings_tab(): void
    {
        $this->group->update(['description' => 'Tetap ada']);
        $this->actingAs($this->owner)->from(route('settings.show', $this->group))
            ->post(route('settings.profile.update', $this->group), ['password' => 'rahasia', 'info_view' => 'manage'])
            ->assertRedirect(route('settings.show', $this->group))->assertSessionHas('group_info', 'manage');

        $group = $this->group->fresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('rahasia', $group->password_hash));
        $this->assertSame('Tetap ada', $group->description);
        $this->assertSame($this->group->name, $group->name);
        $this->actingAs($this->owner)->get(route('settings.show', $this->group))->assertSee('data-info-tab="manage"', false);

        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['password' => 'abc', 'info_view' => 'manage'])->assertSessionHasErrors('password');
        $this->actingAs($this->owner)->post(route('settings.profile.update', $this->group), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_media_tab_lists_images_for_the_strip_and_viewer(): void
    {
        foreach (range(1, 3) as $i) {
            \App\Models\Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'image', 'content' => '', 'attachment_path' => "x/{$i}.png", 'attachment_original_name' => "{$i}.png"]);
        }
        $page = $this->actingAs($this->member)->get(route('settings.show', $this->group))->assertOk();
        $page->assertSee('data-info-media-strip', false)->assertSee('data-media-viewer', false)
            ->assertSee('data-info-media-item="2"', false)->assertSee('Buka gambar 3 dari 3');
    }

    public function test_desktop_chat_embeds_the_same_panel_and_reopens_it_after_saving(): void
    {
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertOk()
            ->assertSee('nc-info-embed', false)->assertSee('data-info-side-toggle', false)->assertSee('data-share-sheet', false)
            ->assertDontSee('data-info-reopen', false);

        $this->actingAs($this->owner)->from(route('chat.show', $this->group))
            ->post(route('groups.credits.limit', $this->group), ['member_daily_credit_limit' => 3])
            ->assertRedirect(route('chat.show', $this->group));
        $this->actingAs($this->owner)->get(route('chat.show', $this->group))
            ->assertSee('data-info-reopen', false)->assertSee('data-info-tab="manage"', false);
    }
}
