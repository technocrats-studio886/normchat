<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\AttachmentTextExtractor;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Jalur S3 sungguhan (driver aws-s3) terhadap server S3 tiruan (MinIO). Dilewati bila
 * NORMCHAT_TEST_S3_ENDPOINT tidak diset.
 */
class S3StorageTest extends TestCase
{
    use RefreshDatabase;

    private const DISKS = ['normchat_attachments' => 'attachments', 'normchat_exports' => 'exports', 'normchat_backups' => 'backups', 'normchat_avatars' => 'avatars'];

    private string $bucket;

    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = (string) env('NORMCHAT_TEST_S3_ENDPOINT', '');
        if ($endpoint === '') {
            $this->markTestSkipped('NORMCHAT_TEST_S3_ENDPOINT tidak diset.');
        }

        $this->bucket = 'normchat-test-' . bin2hex(random_bytes(4));
        $client = new S3Client(['version' => 'latest', 'region' => 'us-east-1', 'endpoint' => $endpoint, 'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'minioadmin', 'secret' => 'minioadmin']]);
        $client->createBucket(['Bucket' => $this->bucket]);

        foreach (self::DISKS as $disk => $folder) {
            config()->set("filesystems.disks.{$disk}", [
                'driver' => 's3', 'key' => 'minioadmin', 'secret' => 'minioadmin', 'region' => 'us-east-1', 'bucket' => $this->bucket,
                'endpoint' => $endpoint, 'use_path_style_endpoint' => true, 'root' => $folder, 'visibility' => 'private', 'throw' => true, 'report' => true,
            ]);
            Storage::forgetDisk($disk);
        }

        $this->localRoot = sys_get_temp_dir() . '/nc-s3-' . bin2hex(random_bytes(4));
        config()->set('filesystems.disks.normchat_root.root', $this->localRoot);
        Storage::fake('public');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (isset($this->localRoot)) {
            File::deleteDirectory($this->localRoot);
        }
        parent::tearDown();
    }

    public function test_uploaded_attachment_lands_in_s3_and_is_served_from_cache_afterwards(): void
    {
        [$owner, $group] = $this->group();
        $this->actingAs($owner)->postJson(route('chat.store', $group), ['attachment' => UploadedFile::fake()->createWithContent('catatan.txt', 'isi catatan rapat')])
            ->assertCreated();
        $message = Message::firstOrFail();

        $this->assertSame('isi catatan rapat', Storage::disk('normchat_attachments')->get($message->attachment_path));
        $this->assertSame('isi catatan rapat', $this->actingAs($owner)->get(route('chat.attachment', [$group, $message]))->assertOk()->streamedContent());

        // Objek dihapus langsung di S3: tampilan berikutnya tetap dari cache Redis, tanpa ke S3.
        Storage::disk('normchat_attachments')->delete($message->attachment_path);
        $this->assertSame('isi catatan rapat', $this->actingAs($owner)->get(route('chat.attachment', [$group, $message]))->assertOk()->streamedContent());
    }

    public function test_document_text_is_read_from_s3_for_the_ai(): void
    {
        [$owner, $group] = $this->group();
        Storage::disk('normchat_attachments')->put('g/materi.md', "# Normalisasi\n1NF, 2NF, 3NF");
        $message = Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'g/materi.md', 'attachment_mime' => 'text/markdown', 'attachment_original_name' => 'materi.md']);

        $this->assertStringContainsString('1NF, 2NF, 3NF', (string) app(AttachmentTextExtractor::class)->extract($message));
    }

    public function test_canvas_and_export_are_served_from_s3(): void
    {
        [$owner, $group] = $this->group();
        Storage::disk('normchat_attachments')->put('g/kuis.html', '<h1>Kuis</h1>');
        $canvas = Message::create(['group_id' => $group->id, 'sender_type' => 'ai', 'message_type' => 'canvas', 'content' => 'Kuis',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'g/kuis.html', 'attachment_mime' => 'text/html', 'attachment_original_name' => 'kuis.html']);
        $this->actingAs($owner)->get(route('chat.canvas', [$group, $canvas]))->assertOk()->assertSee('<h1>Kuis</h1>', false);

        Storage::disk('normchat_exports')->put('group-1/riwayat.pdf', '%PDF-1.4 isi');
        $export = Export::create(['group_id' => $group->id, 'created_by' => $owner->id, 'file_type' => 'pdf', 'status' => 'done', 'storage_path' => 'group-1/riwayat.pdf', 'file_name' => 'Kelas BD.pdf', 'created_at' => now()]);
        $download = $this->actingAs($owner)->get(route('settings.export.download', [$group, $export]))->assertOk();
        $this->assertSame('%PDF-1.4 isi', $download->streamedContent());
    }

    public function test_old_server_files_move_to_s3_with_checksum_and_local_copies_removed(): void
    {
        foreach (['attachments/group-1/2026/10/a.png' => 'gambar', 'exports/group-1/r.pdf' => 'pdf', 'backups/b.json' => '{"messages":[]}'] as $path => $body) {
            File::ensureDirectoryExists(dirname("{$this->localRoot}/{$path}"));
            File::put("{$this->localRoot}/{$path}", $body);
        }
        Storage::disk('public')->put('avatars/' . str_repeat('a', 40) . '.png', 'foto');
        $user = User::factory()->create(['avatar_url' => 'http://localhost/storage/avatars/' . str_repeat('a', 40) . '.png']);

        $this->artisan('normchat:files-to-s3', ['--delete-local' => true])->assertSuccessful();

        $this->assertSame('gambar', Storage::disk('normchat_attachments')->get('group-1/2026/10/a.png'));
        $this->assertSame('pdf', Storage::disk('normchat_exports')->get('group-1/r.pdf'));
        $this->assertSame('{"messages":[]}', Storage::disk('normchat_backups')->get('b.json'));
        $this->assertSame('foto', Storage::disk('normchat_avatars')->get(str_repeat('a', 40) . '.png'));
        $this->assertFileDoesNotExist("{$this->localRoot}/attachments/group-1/2026/10/a.png");
        Storage::disk('public')->assertMissing('avatars/' . str_repeat('a', 40) . '.png');
        $this->assertSame(route('avatars.show', str_repeat('a', 40) . '.png'), $user->fresh()->avatar_url);

        // Dijalankan ulang: aman, tidak ada yang gagal.
        $this->artisan('normchat:files-to-s3')->assertSuccessful();
    }

    public function test_move_refuses_to_run_without_s3(): void
    {
        config()->set('filesystems.disks.normchat_exports.driver', 'local');

        $this->artisan('normchat:files-to-s3')->assertFailed();
    }

    private function group(): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Kelas BD', 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);

        return [$owner, $group];
    }
}
