<?php

namespace App\Services\Ai\Context;

use Carbon\CarbonImmutable;

/**
 * Rentang waktu yang dirujuk pertanyaan ("kemarin", "tadi pagi", "minggu lalu", "tanggal 5", …), dihitung
 * menurut zona waktu penanya. Dipakai HistorySearch untuk memprioritaskan pesan di rentang itu, bukan
 * menyaring: anggota sering menyebut "kemarin" untuk hal dua hari lalu.
 */
final class TimeRange
{
    private const DAYS = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, "jum'at" => 5, 'sabtu' => 6, 'minggu' => 7];

    private const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
    }

    public function contains(?\DateTimeInterface $time): bool
    {
        return $time !== null && $time >= $this->from && $time < $this->to;
    }

    /**
     * Rentang pertama yang disebut di $question, atau null. $now sudah dalam zona waktu penanya; hasilnya
     * dalam zona waktu aplikasi (sama dengan kolom created_at).
     */
    public static function in(string $question, CarbonImmutable $now): ?self
    {
        $text = mb_strtolower($question);
        $today = $now->startOfDay();
        $has = fn (string $pattern) => preg_match('/(?<![\p{L}])(?:' . $pattern . ')(?![\p{L}])/u', $text, $match) === 1 ? $match : null;

        // Frasa yang lebih panjang dulu: "minggu kemarin" bukan "kemarin", "tadi pagi" bukan "tadi".
        $range = match (true) {
            (bool) $has('minggu lalu|pekan lalu|minggu kemarin|minggu kemaren|pekan kemarin') => [$today->startOfWeek()->subWeek(), $today->startOfWeek()],
            (bool) $has('minggu ini|pekan ini') => [$today->startOfWeek(), $now],
            (bool) $has('bulan lalu|bulan kemarin|bulan kemaren') => [$today->startOfMonth()->subMonthNoOverflow(), $today->startOfMonth()],
            (bool) $has('bulan ini') => [$today->startOfMonth(), $now],
            (bool) $has('kemarin lusa|kemaren lusa') => [$today->subDays(2), $today->subDay()],
            (bool) $has('tadi malam|semalam|kemarin malam|kemaren malam') => [$today->subDay()->setTime(18, 0), $today->setTime(5, 0)],
            (bool) $has('kemarin|kemaren') => [$today->subDay(), $today],
            (bool) $has('tadi pagi') => [$today->setTime(4, 0), $today->setTime(11, 0)],
            (bool) $has('tadi siang') => [$today->setTime(11, 0), $today->setTime(15, 0)],
            (bool) $has('tadi sore') => [$today->setTime(15, 0), $today->setTime(18, 30)],
            // "tadi" saja ("itu tadi") merujuk obrolan barusan yang sudah ada di jendela riwayat, bukan rentang waktu.
            (bool) $has('hari ini') => [$today, $now],
            default => null,
        };

        $range ??= self::daysAgo($has, $today) ?? self::date($text, $today) ?? self::weekday($has, $today);

        return $range === null ? null : new self(
            $range[0]->setTimezone(config('app.timezone')),
            $range[1]->setTimezone(config('app.timezone')),
        );
    }

    /** "3 hari lalu", "2 hari yang lalu". */
    private static function daysAgo(\Closure $has, CarbonImmutable $today): ?array
    {
        $match = $has('(\d{1,2}) hari (?:yang )?lalu');
        if ($match === null || (int) $match[1] < 1) {
            return null;
        }
        $day = $today->subDays((int) $match[1]);

        return [$day, $day->addDay()];
    }

    /** "tanggal 5", "tanggal 5 november": tanggal itu yang terakhir sudah lewat. */
    private static function date(string $text, CarbonImmutable $today): ?array
    {
        if (preg_match('/(?<![\p{L}])(?:tanggal|tgl\.?)\s*(\d{1,2})(?:\s+(' . implode('|', array_keys(self::MONTHS)) . '))?(?![\p{L}])/u', $text, $match) !== 1) {
            return null;
        }
        $dayOfMonth = (int) $match[1];
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return null;
        }

        $month = isset($match[2]) ? self::MONTHS[$match[2]] : null;
        $candidate = $month === null
            ? self::clampDay($today->startOfMonth(), $dayOfMonth)
            : self::clampDay($today->setDate($today->year, $month, 1), $dayOfMonth);
        if ($candidate > $today) {
            $candidate = $month === null
                ? self::clampDay($today->startOfMonth()->subMonthNoOverflow(), $dayOfMonth)
                : self::clampDay($today->setDate($today->year - 1, $month, 1), $dayOfMonth);
        }

        return [$candidate, $candidate->addDay()];
    }

    /** "Senin", "hari Minggu": hari itu yang terakhir sebelum hari ini. "Minggu" hanya bila didahului "hari". */
    private static function weekday(\Closure $has, CarbonImmutable $today): ?array
    {
        foreach (self::DAYS as $name => $iso) {
            $pattern = $iso === 7 ? 'hari minggu' : "(?:hari )?{$name}";
            if ($has($pattern) === null) {
                continue;
            }
            $back = ($today->dayOfWeekIso - $iso + 7) % 7 ?: 7;
            $day = $today->subDays($back);

            return [$day, $day->addDay()];
        }

        return null;
    }

    private static function clampDay(CarbonImmutable $monthStart, int $day): CarbonImmutable
    {
        return $monthStart->setDay(min($day, $monthStart->daysInMonth))->startOfDay();
    }
}
