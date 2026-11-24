<?php

namespace App\Support;

/**
 * Batas isi pesan chat. Kolom ketik (resources/js/chat/composer/length-limit.js) membaca angka yang sama lewat
 * data-max-length, jadi pesan kepanjangan ditolak saat diketik, bukan setelah tampil di chat.
 */
final class MessageRules
{
    public const MAX_CONTENT = 3000;

    /** Browser mengirim baris baru textarea sebagai CRLF; tanpa ini tiap baris baru terhitung dua karakter. */
    public static function normalizeNewlines(mixed $value): mixed
    {
        return is_string($value) ? str_replace(["\r\n", "\r"], "\n", $value) : $value;
    }

    public static function tooLongMessage(): string
    {
        return 'Pesan maksimal '.number_format(self::MAX_CONTENT, 0, ',', '.').' karakter. Pendekkan pesannya, atau kirim isinya sebagai file.';
    }
}
