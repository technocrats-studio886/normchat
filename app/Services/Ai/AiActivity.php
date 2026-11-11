<?php

namespace App\Services\Ai;

use App\Events\TypingStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Status Normi yang tampil ke grup selama sebuah permintaan dikerjakan (laporan user 9 dan 11 Nov 2026):
 * "sedang mengetik" hanya untuk jawaban teks; fitur lain melaporkan tahap yang benar-benar sedang berjalan,
 * mis. Deep Research menampilkan kueri yang sedang dicari.
 *
 * Status terakhir juga disimpan di cache, supaya anggota yang baru membuka chat langsung melihatnya
 * (ChatController menambahkannya ke pesan yang masih menunggu jawaban).
 */
final class AiActivity
{
    public const TYPING = 'typing';

    public const READING = 'reading';

    public const IMAGE = 'image';

    public const RESEARCH = 'research';

    public const CANVAS = 'canvas';

    public const CODE = 'code';

    public const FILE = 'file';

    public const LEARNING = 'learning';

    private const CACHE_TTL_SECONDS = 600;

    private const MAX_DETAIL_CHARS = 90;

    /** @var array<int, string> tanda status terakhir per grup, agar status yang sama tidak dikirim berulang */
    private static array $last = [];

    /** $onlyUserId: permintaan privat, statusnya hanya dikirim ke peminta dan tidak disimpan untuk anggota lain. */
    public static function report(int $groupId, string $activity, ?string $detail = null, ?int $onlyUserId = null): void
    {
        $detail = $detail !== null ? self::clean($detail) : null;
        $signature = $activity . '|' . $detail;
        if ((self::$last[$groupId] ?? null) === $signature) {
            return;
        }
        self::$last[$groupId] = $signature;

        try {
            Cache::put(self::key($groupId), ['activity' => $activity, 'detail' => $detail], self::CACHE_TTL_SECONDS);
        } catch (Throwable) {
            // Cache tidak tersedia: status tetap dikirim lewat realtime.
        }
        event(new TypingStatus($groupId, 'ai', null, 'Normi AI', true, $activity, $detail));
    }

    public static function stop(int $groupId, string $activity = self::TYPING, ?int $onlyUserId = null): void
    {
        unset(self::$last[$groupId]);
        if ($onlyUserId === null) {
            try {
                Cache::forget(self::key($groupId));
            } catch (Throwable) {
                // Lihat report().
            }
        }
        event(new TypingStatus($groupId, 'ai', null, 'Normi AI', false, $activity, null, $onlyUserId));
    }

    /** @return array{activity: string, detail: ?string}|null status yang sedang berjalan di grup ini */
    public static function current(int $groupId): ?array
    {
        try {
            $value = Cache::get(self::key($groupId));
        } catch (Throwable) {
            return null;
        }

        return is_array($value) && isset($value['activity']) ? $value : null;
    }

    /** Potongan teks permintaan untuk status, mis. "sedang memulai riset tentang harga cabai 2026". */
    public static function topic(string $text, int $limit = 60): string
    {
        $text = (string) preg_replace('/(?<![\w@])@[\w-]+/u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        // Kata pembuka permintaan tidak ikut: "tolong riset tentang X" → "X".
        $text = trim((string) preg_replace('/^(?:(?:tolong|coba|bisa|bantu|mohon|please|carikan|cariin|cari|riset(?:kan)?|research|telusuri|selidiki|analisis|analisa|buatkan|bikinin|bikin|buat|jelaskan|lakukan|deep research|saya|aku|dong|ya)\s+)+(?:(?:tentang|mengenai|soal|terkait|about)\s+)?/iu', '', $text));

        return Str::limit($text, $limit, '…');
    }

    private static function clean(string $detail): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $detail)), self::MAX_DETAIL_CHARS, '…');
    }

    private static function key(int $groupId): string
    {
        return 'ai-activity:' . $groupId;
    }
}
