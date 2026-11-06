<?php

namespace App\Support;

/**
 * Karakter kendali arah teks (LRE, RLE, PDF, LRO, RLO, LRI, RLI, FSI, PDI) dari input pengguna.
 * Tanpa dibuang, "Dimas U+202E gnp.exe" tampil sebagai "Dimas exe.png", "tagihan U+202E 0005" sebagai
 * "5000", dan kalimat di sekitarnya (pemberitahuan, pratinjau daftar grup) ikut terbalik.
 * Teks Arab/Ibrani tetap tampil benar tanpa karakter ini (algoritme bidi bawaan browser).
 */
final class Bidi
{
    private const CONTROLS = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public static function strip(mixed $text): mixed
    {
        return is_string($text) ? (preg_replace(self::CONTROLS, '', $text) ?? $text) : $text;
    }
}
