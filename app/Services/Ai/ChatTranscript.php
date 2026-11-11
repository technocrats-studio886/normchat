<?php

namespace App\Services\Ai;

use App\Models\Message;
use App\Models\User;
use App\Services\Chat\PollService;
use App\Support\UserTime;
use Illuminate\Support\Collection;

/** Percakapan grup sebagai teks untuk diringkas Normi (ringkasan pesan terlewat, rekap mingguan). */
final class ChatTranscript
{
    /**
     * @param  Collection<int, Message>  $messages  urut lama → baru
     * @return array{0: string, 1: int} teks (pesan terbaru diutamakan bila melewati $maxTokens) dan jumlah pesan yang ikut
     */
    public static function build(Collection $messages, ?User $viewer, int $maxTokens): array
    {
        $lines = [];
        $budget = $maxTokens;
        foreach ($messages->reverse() as $message) {
            $line = self::line($message, $viewer);
            $cost = Tokens::text($line) + 4;
            if ($cost > $budget && $lines !== []) {
                break;
            }
            $budget -= $cost;
            $lines[] = $line;
        }

        return [implode("\n", array_reverse($lines)), count($lines)];
    }

    public static function line(Message $message, ?User $viewer = null): string
    {
        $sender = $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota');
        $content = trim((string) $message->content);
        $poll = $content !== '' ? app(PollService::class)->parse($content) : null;
        if ($poll !== null) {
            $content = 'Polling: ' . $poll['question'] . ' (' . implode(', ', array_map(fn (array $option) => $option['label'], $poll['options'])) . ')';
        }
        if ($message->sender_type === 'ai' && mb_strlen($content) > 600) {
            // Jawaban Normi bisa panjang; cukup awalnya.
            $content = mb_substr($content, 0, 600) . '…';
        }
        $attachment = match (true) {
            ! $message->attachment_path => '',
            $message->hasImageAttachment() => '[foto] ',
            $message->hasAudioAttachment() => '[pesan suara] ',
            default => '[file ' . ($message->attachment_original_name ?: 'lampiran') . '] ',
        };
        $replyTarget = $message->replyToMessage;
        $reply = $replyTarget ? '(membalas ' . ($replyTarget->sender_type === 'ai' ? 'Normi AI' : ($replyTarget->sender?->name ?? 'anggota')) . ') ' : '';

        return '[' . UserTime::format($message->created_at, 'D j/n H.i', $viewer) . '] ' . $sender . ': ' . $reply . $attachment . $content;
    }
}
