<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Files\StoredFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pindahkan file user yang masih di disk server ke S3 (NORMCHAT_STORAGE=s3). Jalur file sama,
 * jadi data di database tidak berubah. Tiap salinan dibaca ulang dari S3 dan dicocokkan
 * checksum-nya; file lokal hanya dihapus (--delete-local) bila salinannya terbukti utuh.
 */
class MoveFilesToS3 extends Command
{
    protected $signature = 'normchat:files-to-s3 {--delete-local : Hapus file lokal setelah salinan di S3 terverifikasi}';

    protected $description = 'Salin lampiran, export, backup, dan avatar dari disk server ke S3';

    private const FOLDERS = ['attachments' => 'normchat_attachments', 'exports' => 'normchat_exports', 'backups' => 'normchat_backups'];

    public function handle(StoredFiles $files): int
    {
        foreach ([...array_values(self::FOLDERS), 'normchat_avatars'] as $disk) {
            if (config("filesystems.disks.{$disk}.driver") !== 's3') {
                $this->error("Disk {$disk} belum memakai S3. Set NORMCHAT_STORAGE=s3 lalu jalankan config:cache.");

                return self::FAILURE;
            }
        }

        $root = rtrim((string) config('filesystems.disks.normchat_root.root'), '/');
        $failed = 0;

        foreach (self::FOLDERS as $folder => $disk) {
            $local = Storage::build(['driver' => 'local', 'root' => "{$root}/{$folder}"]);
            foreach ($local->allFiles() as $path) {
                $failed += $this->move($local, $path, $disk, $path, $files) ? 0 : 1;
            }
        }

        // Avatar unggahan sebelum S3 ada di disk public (/storage/avatars/...).
        $public = Storage::disk('public');
        foreach ($public->files('avatars') as $path) {
            $filename = basename($path);
            if (! $this->move($public, $path, 'normchat_avatars', $filename, $files)) {
                $failed++;

                continue;
            }
            User::query()->where('avatar_url', 'like', '%/storage/' . $path)->update(['avatar_url' => route('avatars.show', $filename)]);
        }

        if ($failed > 0) {
            $this->error("{$failed} file gagal dipindahkan; file lokalnya tidak dihapus.");

            return self::FAILURE;
        }

        $this->info('Semua file sudah ada di S3.');

        return self::SUCCESS;
    }

    private function move($local, string $path, string $disk, string $target, StoredFiles $files): bool
    {
        try {
            $body = $local->get($path);
            $checksum = hash('sha256', (string) $body);
            $s3 = Storage::disk($disk);

            if (! $s3->exists($target) || hash('sha256', (string) $s3->get($target)) !== $checksum) {
                $s3->put($target, $body);
            }
            if (hash('sha256', (string) $s3->get($target)) !== $checksum) {
                $this->error("Checksum tidak cocok: {$disk}/{$target}");

                return false;
            }
        } catch (Throwable $e) {
            $this->error("Gagal: {$disk}/{$target} ({$e->getMessage()})");

            return false;
        }

        $files->forget($disk, $target);
        if ($this->option('delete-local')) {
            $local->delete($path);
        }
        $this->line("OK  {$disk}/{$target}" . ($this->option('delete-local') ? ' (lokal dihapus)' : ''));

        return true;
    }
}
