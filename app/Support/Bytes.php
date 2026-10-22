<?php

namespace App\Support;

final class Bytes
{
    /** 1536 → "1,5 KB" (format angka Indonesia). */
    public static function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(count($units) - 1, (int) floor(log($bytes, 1024)));

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', '.') . ' ' . $units[$power];
    }
}
