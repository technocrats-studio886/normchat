<?php

namespace App\Services\Exports;

use App\Models\Message;
use App\Services\Chat\PollService;
use App\Services\Files\StoredFiles;

/** Menyiapkan pesan grup menjadi ExportEntry: pengirim, tautan, polling, dan lampiran. */
class ChatExportBuilder
{
    public function __construct(private readonly PollService $polls, private readonly StoredFiles $files)
    {
    }

    /** @return list<ExportEntry> */
    public function entriesFor(int $groupId): array
    {
        $messages = Message::query()
            ->where('group_id', $groupId)
            ->orderBy('created_at')
            ->with('sender:id,name')
            ->get();
        $polls = $this->polls->summariesFor($messages);

        return $messages->map(fn (Message $message) => new ExportEntry(
            $message->sender_type === 'ai' ? 'AI' : ($message->sender?->name ?: strtoupper((string) $message->sender_type)),
            (string) optional($message->created_at)->format('Y-m-d H:i:s'),
            trim((string) $message->content),
            $this->linksIn((string) $message->content),
            $polls[(int) $message->id] ?? null,
            $this->attachmentOf($message),
        ))->all();
    }

    /** @return list<string> */
    private function linksIn(string $text): array
    {
        preg_match_all('/https?:\/\/[^\s<]+/i', $text, $matches);

        return array_values(array_unique(array_filter(array_map('trim', $matches[0]))));
    }

    private function attachmentOf(Message $message): ?array
    {
        if (! $message->attachment_path) {
            return null;
        }

        $kind = $this->kindOf($message);

        return [
            'kind' => $kind,
            'name' => (string) ($message->attachment_original_name ?: basename((string) $message->attachment_path)),
            'mime' => (string) ($message->attachment_mime ?: 'application/octet-stream'),
            'url' => route('chat.attachment', ['group' => $message->group_id, 'message' => $message->id]),
            'image' => $kind === 'image' ? $this->binaryOf($message) : null,
        ];
    }

    private function kindOf(Message $message): string
    {
        $type = strtolower((string) $message->message_type);
        $mime = strtolower((string) $message->attachment_mime);
        $name = strtolower((string) $message->attachment_original_name);

        return match (true) {
            $type === 'image' || str_starts_with($mime, 'image/') => 'image',
            $type === 'video' || str_starts_with($mime, 'video/') => 'video',
            $type === 'voice' || str_starts_with($mime, 'audio/') => 'voice',
            (bool) preg_match('/\.(jpg|jpeg|png|webp|gif|bmp|heic|heif)$/i', $name) => 'image',
            (bool) preg_match('/\.(mp4|mov|m4v|webm|3gp|mkv)$/i', $name) => 'video',
            (bool) preg_match('/\.(mp3|wav|ogg|aac|m4a)$/i', $name) => 'voice',
            default => 'file',
        };
    }

    private function binaryOf(Message $message): ?string
    {
        if (! $message->attachment_disk) {
            return null;
        }

        $binary = $this->files->get($message->attachment_disk, (string) $message->attachment_path);

        return $binary !== '' ? $binary : null;
    }
}
