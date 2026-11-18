<?php

namespace App\Services\Ai\Files;

use App\Models\Message;
use App\Services\Files\StoredFiles;
use RuntimeException;

/** Only images from visible chat messages may become document media. Never fetch model URLs. */
final class DocumentImages
{
    public static function prepare(Message $message, array $position): array
    {
        $bytes = app(StoredFiles::class)->get($message->attachment_disk ?: 'normchat_attachments', $message->attachment_path, 15 * 1024 * 1024);
        $size = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (! $size || $size[0] * $size[1] > 20_000_000) {
            throw new RuntimeException('Gambar tidak terbaca atau resolusinya terlalu besar. Unggah PNG/JPG yang lebih kecil.');
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw new RuntimeException('Gambar tidak dapat dimasukkan. Unggah ulang sebagai PNG atau JPG.');
        }
        if (max($size[0], $size[1]) > 1000) {
            $factor = 1000 / max($size[0], $size[1]);
            $small = imagecreatetruecolor(max(1, (int) ($size[0] * $factor)), max(1, (int) ($size[1] * $factor)));
            imagealphablending($small, false);
            imagecopyresampled($small, $image, 0, 0, 0, 0, imagesx($small), imagesy($small), $size[0], $size[1]);
            $image = $small;
        }
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        $scale = min(140 / $size[0], 110 / $size[1]);

        return [
            'binary' => $png,
            'width' => max(1, (int) round($size[0] * $scale)),
            'height' => max(1, (int) round($size[1] * $scale)),
            ...$position,
        ];
    }
}
