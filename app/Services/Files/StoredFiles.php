<?php

namespace App\Services\Files;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Satu pintu untuk membaca file user (lampiran, export, backup, avatar) yang tersimpan di S3.
 *
 * Ukuran file dan isi file kecil di-cache di Redis (config normchat.files), jadi membuka ulang
 * gambar, canvas, atau dokumen tidak selalu meminta ke AWS. File tidak pernah ditimpa (nama acak
 * per unggahan), sehingga cache tidak perlu dibersihkan kecuali file dihapus (forget()).
 */
class StoredFiles
{
    /** Ukuran dalam byte; null bila file tidak ada atau tidak bisa dibaca. */
    public function size(string $disk, string $path): ?int
    {
        $key = $this->key($disk, $path, 'size');
        $cached = Cache::get($key);
        if (is_int($cached)) {
            return $cached;
        }

        try {
            $storage = Storage::disk($disk);
            if (! $storage->exists($path)) {
                // Tidak di-cache: file bisa muncul belakangan (mis. saat file lama disalin ke S3).
                return null;
            }
            $size = (int) $storage->size($path);
        } catch (Throwable) {
            return null;
        }

        Cache::put($key, $size, $this->ttl());

        return $size;
    }

    /** Isi file; null bila tidak ada, gagal dibaca, atau lebih besar dari $maxBytes. */
    public function get(string $disk, string $path, ?int $maxBytes = null): ?string
    {
        $size = $this->size($disk, $path);
        if ($size === null || ($maxBytes !== null && $size > $maxBytes)) {
            return null;
        }

        $read = function () use ($disk, $path) {
            try {
                return Storage::disk($disk)->get($path);
            } catch (Throwable) {
                return null;
            }
        };

        return $this->cacheable($size) ? Cache::remember($this->key($disk, $path, 'body'), $this->ttl(), $read) : $read();
    }

    /**
     * Isi file sebagai stream: dari cache untuk file kecil, langsung dari S3 untuk file besar.
     *
     * @return resource|null
     */
    public function stream(string $disk, string $path)
    {
        $size = $this->size($disk, $path);
        if ($size === null) {
            return null;
        }

        if ($this->cacheable($size)) {
            $body = $this->get($disk, $path);
            if ($body === null) {
                return null;
            }
            $stream = fopen('php://temp', 'r+b');
            fwrite($stream, $body);
            rewind($stream);

            return $stream;
        }

        try {
            $stream = Storage::disk($disk)->readStream($path);
        } catch (Throwable) {
            return null;
        }

        return is_resource($stream) ? $stream : null;
    }

    /**
     * Jalankan $callback dengan path file lokal sementara (untuk pustaka yang hanya bisa membaca
     * file di disk, mis. pembaca PDF/DOCX). File sementara selalu dihapus setelahnya.
     */
    public function withLocalCopy(string $disk, string $path, callable $callback, ?int $maxBytes = null): mixed
    {
        $body = $this->get($disk, $path, $maxBytes);
        if ($body === null) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $local = tempnam(sys_get_temp_dir(), 'nc-file-') . ($extension !== '' ? '.' . $extension : '');
        file_put_contents($local, $body);

        try {
            return $callback($local);
        } finally {
            @unlink($local);
        }
    }

    public function forget(string $disk, string $path): void
    {
        Cache::forget($this->key($disk, $path, 'size'));
        Cache::forget($this->key($disk, $path, 'body'));
    }

    private function cacheable(int $size): bool
    {
        return $size <= (int) config('normchat.files.cache_max_kb', 2048) * 1024;
    }

    private function ttl(): int
    {
        return (int) config('normchat.files.cache_hours', 24) * 3600;
    }

    private function key(string $disk, string $path, string $part): string
    {
        return "files:{$disk}:" . sha1($path) . ":{$part}";
    }
}
