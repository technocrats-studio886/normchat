<?php

namespace App\Support;

use App\Models\User;

/**
 * Tema tampilan per akun: mode (terang/gelap/ikuti sistem) dan warna aksen.
 * Dipasang di <html data-theme-mode data-mode data-accent>; warnanya di resources/css/app.css.
 */
final class Themes
{
    public const MODES = [
        'system' => 'Ikuti sistem',
        'light' => 'Terang',
        'dark' => 'Gelap',
    ];

    /** Aksen: warna di mode terang & gelap; keduanya ≥ 4,5:1 untuk teks putih. */
    public const ACCENTS = [
        'navy' => ['label' => 'Navy', 'light' => '#1e2e43', 'dark' => '#3b5677'],
        'toska' => ['label' => 'Toska', 'light' => '#0f6f68', 'dark' => '#197a72'],
        'biru' => ['label' => 'Biru laut', 'light' => '#1d5aa0', 'dark' => '#3a74b8'],
        'hijau' => ['label' => 'Hijau daun', 'light' => '#2f6a3e', 'dark' => '#3f8452'],
        'bata' => ['label' => 'Bata', 'light' => '#a8432f', 'dark' => '#b04f3a'],
    ];

    /** Warna bar browser (meta theme-color) per mode. */
    public const BROWSER = ['light' => '#ffffff', 'dark' => '#121C29'];

    public const DEFAULT_MODE = 'system';

    public const DEFAULT_ACCENT = 'navy';

    /** @return array{mode: string, accent: string} pilihan yang dikenal; tamu selalu terang. */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return ['mode' => 'light', 'accent' => self::DEFAULT_ACCENT];
        }

        return [
            'mode' => isset(self::MODES[$user->theme_mode]) ? $user->theme_mode : self::DEFAULT_MODE,
            'accent' => isset(self::ACCENTS[$user->theme_accent]) ? $user->theme_accent : self::DEFAULT_ACCENT,
        ];
    }

    public static function label(array $theme): string
    {
        return self::MODES[$theme['mode']] . ' · ' . self::ACCENTS[$theme['accent']]['label'];
    }
}
