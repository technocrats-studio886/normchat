<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;

/** Jam yang ditulis server memakai zona waktu user (dikirim browser); bawaan WIB. */
final class UserTime
{
    public const DEFAULT_ZONE = 'Asia/Jakarta';

    public static function zone(?User $user = null): string
    {
        $zone = (string) (($user ?? auth()->user())?->timezone ?? '');

        return in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : self::DEFAULT_ZONE;
    }

    public static function local(?CarbonInterface $time, ?User $user = null): ?CarbonInterface
    {
        return $time?->copy()->setTimezone(self::zone($user));
    }

    /** Format Indonesia, mis. "27 Okt 2026, 19.40" (pola Carbon translatedFormat). */
    public static function format(?CarbonInterface $time, string $pattern = 'j M Y, H.i', ?User $user = null): string
    {
        return (string) self::local($time, $user)?->translatedFormat($pattern);
    }
}
