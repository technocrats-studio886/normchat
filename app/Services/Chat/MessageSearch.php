<?php

namespace App\Services\Chat;

use App\Models\Group;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cari pesan di seluruh riwayat grup yang boleh dilihat user (docs/rancangan-cari-pesan.md).
 * Yang ikut: isi pesan dan nama berkas lampiran. Yang tidak: pesan terhapus, pemberitahuan sistem,
 * pemberitahuan Normi untuk orang lain, pesan sebelum batas riwayat anggota baru, dan pesan yang
 * disembunyikan user untuk dirinya sendiri.
 */
class MessageSearch
{
    public const MIN_LENGTH = 2;

    public const PAGE_SIZE = 30;

    private const SNIPPET_LENGTH = 90;

    public function run(Group $group, User $viewer, string $rawQuery, int $beforeId = 0): array
    {
        $query = $this->normalize($rawQuery);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return ['query' => $query, 'total' => 0, 'has_more' => false, 'results' => []];
        }

        $base = $this->matching($group, $viewer, $query);
        $total = (clone $base)->count();

        $rows = $base
            ->when($beforeId > 0, fn (Builder $q) => $q->where('messages.id', '<', $beforeId))
            ->with('sender:id,name,avatar_url')
            ->orderByDesc('messages.id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        return [
            'query' => $query,
            'total' => $total,
            'has_more' => $rows->count() > self::PAGE_SIZE,
            'results' => $rows->take(self::PAGE_SIZE)->map(fn (Message $message) => $this->result($message, $viewer, $query))->values()->all(),
        ];
    }

    private function matching(Group $group, User $viewer, string $query): Builder
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Message::query()
            ->where('messages.group_id', $group->id)
            ->visibleTo($viewer)
            ->where(fn (Builder $q) => $q->whereNull('messages.sender_type')->orWhere('messages.sender_type', '!=', Message::SENDER_SYSTEM))
            ->whereNotIn('messages.id', MessageHide::where('user_id', $viewer->id)->select('message_id'))
            ->where(fn (Builder $q) => $q
                ->where('messages.content', $operator, $like)
                ->orWhere('messages.attachment_original_name', $operator, $like));
    }

    private function result(Message $message, User $viewer, string $query): array
    {
        $isMine = $message->sender_type === 'user' && (int) $message->sender_id === (int) $viewer->id;

        return [
            'id' => $message->id,
            'sender_type' => (string) $message->sender_type,
            'sender_id' => $message->sender_id ? (int) $message->sender_id : null,
            'sender_name' => match (true) {
                $message->sender_type === 'ai' => 'Normi AI',
                $isMine => 'Kamu',
                default => $message->sender?->name ?? 'Anggota',
            },
            'sender_avatar_url' => $message->sender_type === 'ai' ? null : $message->sender?->avatar_url,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'snippet' => $this->snippet($message, $query),
        ];
    }

    /** Sekitar 90 karakter di sekitar kata pertama yang cocok, dengan "…" di sisi yang dipotong. */
    public function snippet(Message $message, string $query): string
    {
        $text = $this->readable((string) $message->content);
        $name = trim((string) $message->attachment_original_name);
        // Kata hanya ada di nama berkas: tampilkan nama berkasnya.
        if ($name !== '' && mb_stripos($text, $query) === false) {
            $text = $name;
        }

        $at = mb_stripos($text, $query);
        if ($at === false || mb_strlen($text) <= self::SNIPPET_LENGTH) {
            return Str::limit($text, self::SNIPPET_LENGTH, '…');
        }

        $room = max(0, self::SNIPPET_LENGTH - mb_strlen($query));
        $start = max(0, $at - intdiv($room, 3));
        $slice = mb_substr($text, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '') . trim($slice) . ($start + self::SNIPPET_LENGTH < mb_strlen($text) ? '…' : '');
    }

    /** Satu baris bersih: markdown ringan dan penanda polling dibuang, spasi dirapatkan. */
    private function readable(string $content): string
    {
        $content = preg_replace('/^📊 POLL:\s*/u', 'Polling: ', $content) ?? $content;
        $content = preg_replace('/[*_`#>]+/u', '', $content) ?? $content;

        return trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
    }

    private function normalize(string $query): string
    {
        return trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
    }
}
