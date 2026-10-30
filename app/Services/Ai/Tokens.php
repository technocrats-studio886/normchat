<?php

namespace App\Services\Ai;

/**
 * Perkiraan jumlah token sebelum request dikirim. Teks Indonesia rata-rata ±3,5 karakter per token
 * (lebih boros dari Inggris), gambar ±1.100 token. Tagihan tetap memakai angka usage dari API.
 */
final class Tokens
{
    public const CHARS_PER_TOKEN = 3.5;

    public const PER_IMAGE = 1_100;

    public static function text(?string $text): int
    {
        return (int) ceil(mb_strlen((string) $text) / self::CHARS_PER_TOKEN);
    }

    /** Pesan format chat completions (content string atau daftar bagian text/image_url). */
    public static function messages(array $messages): int
    {
        $tokens = 0;
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                $tokens += self::text($content);
                continue;
            }

            foreach ((array) $content as $part) {
                $tokens += ($part['type'] ?? '') === 'image_url' ? self::PER_IMAGE : self::text((string) ($part['text'] ?? ''));
            }
        }

        return $tokens;
    }
}
