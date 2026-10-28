<?php

namespace App\Services\Chat;

use App\Models\Message;
use Illuminate\Support\Str;

/** Ringkasan satu baris isi pesan (pratinjau sidebar, notifikasi): tanpa markdown dan emoji ikon. */
final class MessageSummary
{
    public static function of(Message $message): string
    {
        if ($message->trashed()) {
            return 'Pesan ini dihapus';
        }

        $poll = app(PollService::class)->parse($message->content);
        if ($poll !== null) {
            return 'Polling: ' . $poll['question'];
        }

        $text = Str::squish(self::plain((string) $message->content));
        if ($text !== '') {
            return $text;
        }

        return match ($message->message_type) {
            'image' => 'Gambar',
            'voice' => 'Pesan suara',
            'video' => 'Video',
            'canvas' => 'Canvas',
            default => 'Lampiran',
        };
    }

    /** Buang penanda markdown yang paling sering dipakai Normi: tebal, miring, kode, judul, kutipan, tautan. */
    private static function plain(string $markdown): string
    {
        $text = preg_replace('/```[a-z]*\n?|```/i', '', $markdown);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/^\s{0,3}(#{1,6}\s+|>\s?|[-*+]\s+|\d+[.)]\s+)/m', '', $text);
        $text = preg_replace('/(\*\*|__|\*|_|`|~~)(.+?)\1/s', '$2', $text);

        return str_replace('|', ' ', (string) $text);
    }
}
