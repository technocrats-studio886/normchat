<?php

namespace Tests\Feature;

use App\Jobs\CreateBackupSnapshotJob;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Halaman riwayat & export, export PDF, dan snapshot backup. */
class GroupArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('normchat_exports');
        Storage::fake('normchat_backups');
        $this->owner = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => "📊 POLL: Kapan?\n1. Senin\n2. Selasa"]);
    }

    public function test_pdf_export_downloads_and_is_listed(): void
    {
        $this->actingAs($this->owner)
            ->post("/groups/{$this->group->id}/settings/export", ['file_type' => 'pdf'])
            ->assertOk()
            ->assertDownload('Kelas BD.pdf');

        $export = $this->group->exports()->firstOrFail();
        $this->actingAs($this->owner)->get("/groups/{$this->group->id}/settings/history-export")
            ->assertOk()
            ->assertSee(route('settings.export.download', [$this->group, $export]), false);
    }

    public function test_docx_export_contains_messages_polls_and_links(): void
    {
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'content' => 'Cek https://normchat.test/materi']);

        $this->actingAs($this->owner)
            ->post("/groups/{$this->group->id}/settings/export", ['file_type' => 'docx'])
            ->assertOk()
            ->assertDownload('Kelas BD.docx');

        $export = $this->group->exports()->firstOrFail();
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('normchat_exports')->path($export->storage_path));
        $document = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Polling: Kapan?', $document);
        $this->assertStringContainsString('https://normchat.test/materi', $document);
        $this->assertStringContainsString('] AI', $document);
    }

    public function test_backup_is_queued_and_snapshot_is_written(): void
    {
        Queue::fake();
        $this->actingAs($this->owner)->post("/groups/{$this->group->id}/settings/backup")->assertSessionHas('success');
        Queue::assertPushed(CreateBackupSnapshotJob::class);

        (new CreateBackupSnapshotJob($this->group->id, $this->owner->id))->handle(app(\App\Services\GroupBackups::class));

        $backup = GroupBackup::firstOrFail();
        $snapshot = json_decode(Storage::disk('normchat_backups')->get($backup->storage_path), true);
        $this->assertCount(1, $snapshot['messages']);
        $this->assertSame($this->owner->id, $snapshot['members'][0]['user_id']);
    }
}
