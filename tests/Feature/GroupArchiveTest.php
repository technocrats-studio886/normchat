<?php

namespace Tests\Feature;

use App\Jobs\CreateBackupSnapshotJob;
use App\Jobs\GenerateExportJob;
use App\Models\Export;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\ExportOptions;
use App\Services\GroupBackups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Halaman Riwayat & Cadangan: unduh riwayat (filter, antrean, PDF/DOCX) dan cadangan (titik pulih). */
class GroupArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('normchat_exports');
        Storage::fake('normchat_backups');
        Storage::fake('normchat_attachments');
        $this->owner = User::factory()->create(['name' => 'Budi Santoso', 'timezone' => 'Asia/Makassar']);
        $this->member = User::factory()->create(['name' => 'Sari']);
        $this->group = Group::create(['name' => 'Grup COba', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
    }

    // ── Unduh riwayat ──────────────────────────────────────

    public function test_count_follows_range_and_sender_filters(): void
    {
        $this->message($this->owner, 'lama', now()->subDays(40));
        $this->message($this->owner, 'minggu ini', now()->subDays(2));
        $this->message($this->member, 'dari Sari', now()->subDay());
        $this->message(null, 'jawaban Normi', now()->subDay());
        $this->message(null, 'Normkredit habis', now(), ['visible_to_user_id' => $this->owner->id]);

        $count = fn (array $query) => $this->actingAs($this->owner)->getJson(route('settings.export.count', $this->group) . '?' . http_build_query(['file_type' => 'pdf', ...$query]))->assertOk()->json('count');

        $this->assertSame(4, $count(['range' => 'all']), 'pemberitahuan pribadi Normi tidak ikut');
        $this->assertSame(3, $count(['range' => '7d']));
        $this->assertSame(1, $count(['range' => '7d', 'senders' => ['ai']]));
        $this->assertSame(2, $count(['range' => '7d', 'senders' => ['ai', (string) $this->member->id]]));
        $this->assertSame(1, $count(['range' => 'custom', 'from' => now()->subDays(41)->format('Y-m-d'), 'to' => now()->subDays(39)->format('Y-m-d')]));
        $this->actingAs($this->owner)->getJson(route('settings.export.count', $this->group) . '?file_type=pdf&range=custom')->assertUnprocessable();
    }

    public function test_export_is_queued_with_filters_and_timestamped_file_name(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTimezone('Asia/Makassar')->setDate(2026, 10, 27)->setTime(19, 40)->utc());

        $this->actingAs($this->owner)->postJson(route('settings.export', $this->group), ['file_type' => 'pdf', 'range' => '30d', 'senders' => ['ai'], 'images' => false])
            ->assertCreated()
            ->assertJsonPath('export.file_name', '194010272026-Grup-COba.pdf')
            ->assertJsonPath('export.status', 'queued');

        $export = Export::firstOrFail();
        $this->assertSame(['ai'], $export->filters['senders']);
        $this->assertFalse($export->filters['images']);
        Queue::assertPushed(GenerateExportJob::class);
    }

    public function test_pdf_export_is_generated_and_only_downloadable_by_its_creator(): void
    {
        $this->message($this->owner, 'halo semua 😊');
        $this->exportAs($this->owner, 'pdf');

        $export = Export::firstOrFail();
        $this->assertSame('done', $export->status);
        $this->assertSame(1, $export->message_count);
        $this->assertStringStartsWith('%PDF', Storage::disk('normchat_exports')->get($export->storage_path));

        $this->actingAs($this->owner)->get(route('settings.export.download', [$this->group, $export]))->assertOk()->assertDownload($export->file_name);
        // Admin boleh mengunduh riwayat, tetapi tidak berkas milik orang lain.
        GroupMember::where('user_id', $this->member->id)->update(['role_id' => Role::forKey(Role::ADMIN)->id]);
        $this->actingAs($this->member)->get(route('settings.export.download', [$this->group, $export]))->assertNotFound();
        $this->actingAs($this->member)->getJson(route('settings.exports', $this->group))->assertJsonCount(0, 'exports');
    }

    public function test_docx_contains_layout_texts_markers_and_rendered_markdown(): void
    {
        $edited = $this->message($this->member, 'versi baru');
        MessageVersion::create(['message_id' => $edited->id, 'version_number' => 1, 'content_snapshot' => 'versi lama', 'edited_by' => $this->member->id, 'edited_at' => now()]);
        $gone = $this->message($this->member, 'isi yang dihapus');
        $gone->forceFill(['deleted_by' => $this->owner->id])->save();
        $gone->delete();
        $this->message(null, '**Rencana** belajar', null, ['reply_to_message_id' => $edited->id]);
        $this->message($this->owner, "📊 POLL: Kapan?\n1. Senin\n2. Selasa");

        $this->exportAs($this->owner, 'docx');
        $document = $this->docxText(Export::firstOrFail());

        $this->assertStringContainsString('Grup COba', $document);
        $this->assertStringContainsString('Diekspor ', $document);
        $this->assertStringContainsString('Diekspor oleh Budi Santoso', Storage::disk('normchat_exports')->get(Export::firstOrFail()->storage_path) ? $this->docxText(Export::firstOrFail(), 'word/footer1.xml') : '');
        $this->assertStringContainsString('isi yang dihapus', $document, 'isi pesan terhapus tetap ada di ekspor');
        $this->assertStringContainsString('Dihapus oleh Budi Santoso', $document);
        $this->assertStringContainsString('Sebelumnya:', $document);
        $this->assertStringContainsString('versi lama', $document);
        $this->assertStringContainsString('Normi AI', $document);
        $this->assertStringContainsString('Polling: Kapan?', $document);
        $this->assertStringNotContainsString('**Rencana**', $document, 'markdown dirender, bukan ditulis mentah');
        $this->assertStringContainsString('Rencana', $document);
    }

    public function test_export_document_puts_exporters_messages_on_the_right_and_marks_runs(): void
    {
        $this->message($this->member, 'satu', now()->subMinutes(5));
        $this->message($this->member, 'dua', now()->subMinutes(4));
        $this->message($this->owner, 'tiga', now()->subMinutes(3));

        $document = app(ChatExportBuilder::class)->build($this->group, ExportOptions::fromArray(['format' => 'pdf', 'timezone' => 'Asia/Makassar']), $this->owner);

        $this->assertSame([false, false, true], array_map(fn ($entry) => $entry->isMine, $document->entries));
        $this->assertSame([true, false, true], array_map(fn ($entry) => $entry->runStart, $document->entries));
        $this->assertSame([false, true, true], array_map(fn ($entry) => $entry->runEnd, $document->entries));
        $this->assertSame('Asia/Makassar', $document->exportedAt->getTimezone()->getName());
    }

    public function test_old_exports_are_pruned_after_seven_days(): void
    {
        Storage::disk('normchat_exports')->put('lama.pdf', 'x');
        Export::create(['group_id' => $this->group->id, 'file_name' => 'a.pdf', 'storage_path' => 'lama.pdf', 'file_type' => 'pdf', 'status' => 'done', 'created_by' => $this->owner->id, 'created_at' => now()->subDays(8)]);
        Export::create(['group_id' => $this->group->id, 'file_name' => 'b.pdf', 'storage_path' => '', 'file_type' => 'pdf', 'status' => 'done', 'created_by' => $this->owner->id, 'created_at' => now()->subDays(2)]);

        $this->artisan('normchat:prune-exports')->assertSuccessful();

        $this->assertSame(['b.pdf'], Export::pluck('file_name')->all());
        Storage::disk('normchat_exports')->assertMissing('lama.pdf');
    }

    // ── Cadangan ───────────────────────────────────────────

    public function test_only_moderators_see_and_use_backups(): void
    {
        $this->actingAs($this->member)->get(route('settings.history', $this->group))->assertOk()->assertDontSee('data-backup-list', false);
        $this->actingAs($this->member)->postJson(route('settings.backup', $this->group))->assertForbidden();
        $this->actingAs($this->owner)->get(route('settings.history', $this->group))->assertSee('data-backup-list', false);
    }

    public function test_backup_is_pending_then_ready_with_summary(): void
    {
        Queue::fake();
        $this->message($this->owner, 'satu');
        $this->message($this->member, 'dua', null, ['attachment_disk' => 'normchat_attachments', 'attachment_path' => 'x.png', 'message_type' => 'image']);

        $this->actingAs($this->owner)->postJson(route('settings.backup', $this->group))->assertCreated()->assertJsonPath('backup.status', 'pending');
        Queue::assertPushed(CreateBackupSnapshotJob::class);

        $backup = GroupBackup::firstOrFail();
        (new CreateBackupSnapshotJob($backup->id))->handle(app(GroupBackups::class));

        $this->actingAs($this->owner)->getJson(route('settings.backups', $this->group))
            ->assertJsonPath('backups.0.status', 'ready')
            ->assertJsonPath('backups.0.message_count', 2)
            ->assertJsonPath('backups.0.attachment_count', 1);
    }

    public function test_restore_brings_back_only_deleted_messages_and_keeps_newer_ones(): void
    {
        $keep = $this->message($this->owner, 'tetap');
        $deleted = $this->message($this->member, 'nanti dihapus', now()->subHour());
        $backups = app(GroupBackups::class);
        $backup = $backups->start($this->group, $this->owner->id);
        $backups->fill($backup);

        $this->travel(5)->minutes();
        $deleted->forceFill(['deleted_by' => $this->member->id])->save();
        $deleted->delete();
        $newer = $this->message($this->owner, 'pesan baru setelah cadangan');
        $newerDeleted = $this->message($this->owner, 'baru lalu dihapus');
        $newerDeleted->delete();

        $this->actingAs($this->owner)->getJson(route('settings.backup.preview', [$this->group, $backup]))->assertJsonPath('restorable', 1);
        $this->actingAs($this->owner)->postJson(route('settings.backup.restore', [$this->group, $backup]))->assertOk()->assertJsonPath('restored', 1);

        $restored = Message::find($deleted->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_by);
        $this->assertTrue($restored->created_at->equalTo($deleted->created_at), 'jam aslinya tetap');
        $this->assertNotNull(Message::find($keep->id));
        $this->assertNotNull(Message::find($newer->id));
        $this->assertSoftDeleted($newerDeleted);
    }

    public function test_nightly_backup_only_for_groups_with_new_messages_and_prunes_old_ones(): void
    {
        $quiet = Group::create(['name' => 'Sepi', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        $this->message($this->owner, 'ada pesan');
        GroupBackup::create(['group_id' => $quiet->id, 'backup_type' => 'auto', 'storage_path' => '', 'created_at' => now()->subDays(31), 'status' => 'ready']);

        $this->artisan('normchat:backup-groups')->assertSuccessful();
        $this->artisan('normchat:backup-groups')->assertSuccessful();

        $this->assertSame(1, GroupBackup::where('group_id', $this->group->id)->count(), 'malam kedua tanpa pesan baru tidak membuat cadangan lagi');
        $this->assertSame(0, GroupBackup::where('group_id', $quiet->id)->count(), 'cadangan lebih dari 30 hari dihapus');
        $this->assertNull(GroupBackup::where('group_id', $this->group->id)->value('created_by'));
    }

    // ── Bantuan ────────────────────────────────────────────

    private function message(?User $sender, string $content, $at = null, array $attributes = []): Message
    {
        $message = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => $sender ? 'user' : 'ai',
            'sender_id' => $sender?->id,
            'content' => $content,
            ...$attributes,
        ]);
        if ($at !== null) {
            $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }

        return $message;
    }

    private function exportAs(User $user, string $format): void
    {
        Queue::fake();
        $this->actingAs($user)->postJson(route('settings.export', $this->group), ['file_type' => $format, 'range' => 'all'])->assertCreated();
        (new GenerateExportJob(Export::latest('id')->value('id')))->handle(app(ChatExportBuilder::class));
    }

    private function docxText(Export $export, string $part = 'word/document.xml'): string
    {
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('normchat_exports')->path($export->storage_path));
        $xml = (string) $zip->getFromName($part);
        $zip->close();

        return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)));
    }
}
