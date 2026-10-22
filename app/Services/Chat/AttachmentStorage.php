<?php

namespace App\Services\Chat;

use App\Models\Group;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Menyimpan lampiran chat dan menentukan jenis pesannya (image, voice, video, file). */
class AttachmentStorage
{
    public const DISK = 'normchat_attachments';

    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', '3gp', 'mkv'];

    /**
     * @return array{message_type: string, attachment_disk: string, attachment_path: string,
     *               attachment_mime: string, attachment_size: int, attachment_original_name: string}
     */
    public function store(UploadedFile $file, Group $group): array
    {
        $type = $this->messageTypeOf($file);
        $extension = strtolower($file->getClientOriginalExtension()) ?: ($type === 'image' ? 'jpg' : 'webm');
        $path = sprintf('group-%d/%s/%s.%s', $group->id, now()->format('Y/m'), Str::uuid(), $extension);

        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return [
            'message_type' => $type,
            'attachment_disk' => self::DISK,
            'attachment_path' => $path,
            'attachment_mime' => $this->mimeFor($type, strtolower((string) $file->getClientMimeType()), $extension),
            'attachment_size' => (int) $file->getSize(),
            'attachment_original_name' => (string) $file->getClientOriginalName(),
        ];
    }

    private function messageTypeOf(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $clientMime = strtolower((string) $file->getClientMimeType());
        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/'), str_starts_with($clientMime, 'audio/') => 'voice',
            str_starts_with($mime, 'video/'), str_starts_with($clientMime, 'video/') => 'video',
            in_array($extension, self::AUDIO_EXTENSIONS, true) => 'voice',
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            default => 'file',
        };
    }

    private function mimeFor(string $type, string $clientMime, string $extension): string
    {
        if ($type === 'voice') {
            return str_starts_with($clientMime, 'audio/') ? $clientMime : match ($extension) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'aac' => 'audio/aac',
                'm4a' => 'audio/m4a',
                default => 'audio/webm',
            };
        }

        if ($type === 'video') {
            return str_starts_with($clientMime, 'video/') ? $clientMime : match ($extension) {
                'mov' => 'video/quicktime',
                'm4v' => 'video/x-m4v',
                'webm' => 'video/webm',
                '3gp' => 'video/3gpp',
                'mkv' => 'video/x-matroska',
                default => 'video/mp4',
            };
        }

        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }
}
