<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Services\Files\StoredFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto grup: dipotong persegi di tengah, diperkecil ke 512 px, disimpan WebP di disk normchat_avatars
 * (S3 di produksi) pada groups/{id}-{acak}.webp. Nama acak per unggahan, jadi cache StoredFiles aman.
 */
class GroupAvatars
{
    public const DISK = 'normchat_avatars';

    private const SIZE = 512;

    public function __construct(private readonly StoredFiles $files)
    {
    }

    /** Simpan foto baru dan kembalikan path-nya. Foto lama dihapus oleh pemanggil lewat delete(). */
    public function store(Group $group, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => 'Gambar tidak bisa dibaca. Coba JPG, PNG, atau WebP lain.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagewebp($target, null, 82);
        $body = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $path = 'groups/'.$group->id.'-'.Str::random(24).'.webp';
        Storage::disk(self::DISK)->put($path, $body);

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }
        Storage::disk(self::DISK)->delete($path);
        $this->files->forget(self::DISK, $path);
    }

    /** Isi foto grup; null bila grup tidak punya foto atau berkasnya hilang. */
    public function body(Group $group): ?string
    {
        return $group->avatar_path ? $this->files->get(self::DISK, $group->avatar_path) : null;
    }
}
