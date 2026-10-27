<?php

namespace App\Support;

/**
 * Tema tampilan yang bisa dipilih user, dipasang sebagai data-theme di <html>.
 * Warnanya didefinisikan di resources/css/app.css; di sini nama, warna contoh, dan warna bar browser.
 */
final class Themes
{
    public const DEFAULT = 'default';

    public const OPTIONS = [
        'default' => ['label' => 'Default', 'swatch' => '#F3F5F8', 'browser' => '#ffffff'],
        'navy' => ['label' => 'Navy', 'swatch' => '#121C29', 'browser' => '#121C29'],
        'coral' => ['label' => 'Coral', 'swatch' => '#FFF3F1', 'browser' => '#FFF3F1'],
    ];

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::OPTIONS);
    }

    /** Tema yang tidak dikenal (mis. sudah dihapus) jatuh ke default. */
    public static function resolve(?string $id): string
    {
        return isset(self::OPTIONS[$id]) ? $id : self::DEFAULT;
    }
}
