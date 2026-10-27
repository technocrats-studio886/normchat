<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Files\StoredFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pembacaan file user lewat cache (agar tidak selalu meminta ke S3) dan avatar. */
class StoredFilesTest extends TestCase
{
    use RefreshDatabase;

    private StoredFiles $files;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('normchat_attachments');
        Storage::fake('normchat_avatars');
        $this->files = app(StoredFiles::class);
    }

    public function test_small_files_are_served_from_cache_after_the_first_read(): void
    {
        Storage::disk('normchat_attachments')->put('g/a.txt', 'halo');

        $this->assertSame(4, $this->files->size('normchat_attachments', 'g/a.txt'));
        $this->assertSame('halo', $this->files->get('normchat_attachments', 'g/a.txt'));

        // Bacaan berikutnya tidak menyentuh penyimpanan lagi.
        Storage::disk('normchat_attachments')->delete('g/a.txt');
        $this->assertSame(4, $this->files->size('normchat_attachments', 'g/a.txt'));
        $this->assertSame('halo', $this->files->get('normchat_attachments', 'g/a.txt'));
        $this->assertSame('halo', stream_get_contents($this->files->stream('normchat_attachments', 'g/a.txt')));

        $this->files->forget('normchat_attachments', 'g/a.txt');
        $this->assertNull($this->files->get('normchat_attachments', 'g/a.txt'));
    }

    public function test_large_files_are_not_kept_in_cache(): void
    {
        config()->set('normchat.files.cache_max_kb', 1);
        Storage::disk('normchat_attachments')->put('g/besar.bin', str_repeat('x', 2048));

        $this->assertSame(2048, strlen((string) $this->files->get('normchat_attachments', 'g/besar.bin')));
        $this->assertSame(2048, strlen(stream_get_contents($this->files->stream('normchat_attachments', 'g/besar.bin'))));

        Storage::disk('normchat_attachments')->delete('g/besar.bin');
        $this->assertNull($this->files->get('normchat_attachments', 'g/besar.bin'));
    }

    public function test_missing_files_are_not_cached(): void
    {
        $this->assertNull($this->files->size('normchat_attachments', 'g/nanti.txt'));

        Storage::disk('normchat_attachments')->put('g/nanti.txt', 'ada');
        $this->assertSame('ada', $this->files->get('normchat_attachments', 'g/nanti.txt'));
    }

    public function test_size_limit_is_respected(): void
    {
        Storage::disk('normchat_attachments')->put('g/a.txt', 'halo dunia');

        $this->assertNull($this->files->get('normchat_attachments', 'g/a.txt', 5));
        $this->assertSame('halo dunia', $this->files->get('normchat_attachments', 'g/a.txt', 10));
    }

    public function test_local_copy_is_a_temporary_file_that_is_always_removed(): void
    {
        Storage::disk('normchat_attachments')->put('g/catatan.md', '# Judul');
        $seen = null;

        $result = $this->files->withLocalCopy('normchat_attachments', 'g/catatan.md', function (string $path) use (&$seen) {
            $seen = $path;

            return file_get_contents($path);
        });

        $this->assertSame('# Judul', $result);
        $this->assertStringEndsWith('.md', $seen);
        $this->assertFileDoesNotExist($seen);
        $this->assertNull($this->files->withLocalCopy('normchat_attachments', 'g/tidak-ada.md', fn () => 'x'));
    }

    public function test_attachment_download_sends_its_length(): void
    {
        [$owner, $group] = $this->group();
        Storage::disk('normchat_attachments')->put('g/a.txt', 'halo');
        $message = Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'g/a.txt', 'attachment_mime' => 'text/plain', 'attachment_original_name' => 'a.txt']);

        $response = $this->actingAs($owner)->get(route('chat.attachment', [$group, $message]))
            ->assertOk()->assertHeader('Content-Length', '4');
        $this->assertSame('halo', $response->streamedContent());
    }

    public function test_avatar_is_stored_privately_and_served_to_signed_in_users(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);

        $this->actingAs($user)->post(route('profile.account.update'), ['name' => 'Noel', 'avatar' => $this->png('foto.png')])
            ->assertRedirect(route('profile.account'));

        $url = (string) $user->fresh()->avatar_url;
        $filename = basename((string) parse_url($url, PHP_URL_PATH));
        $this->assertSame(route('avatars.show', $filename), $url);
        Storage::disk('normchat_avatars')->assertExists($filename);

        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        // Foto baru menggantikan yang lama.
        $this->actingAs($user)->post(route('profile.account.update'), ['name' => 'Noel', 'avatar' => $this->png('baru.png')]);
        Storage::disk('normchat_avatars')->assertMissing($filename);
    }

    /** PNG 1×1 sungguhan (tanpa ekstensi GD). */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    private function group(): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Kelas BD', 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);

        return [$owner, $group];
    }
}
