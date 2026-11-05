<?php

namespace App\Services\Links;

/** Tautan http/https di teks pesan, tanpa tanda baca penutup yang ikut menempel. */
class LinkExtractor
{
    /** @return list<string> */
    public static function urls(?string $content, int $limit = PHP_INT_MAX): array
    {
        if ($content === null || ! preg_match_all('#https?://[^\s<>"\']+#iu', $content, $matches)) {
            return [];
        }
        $urls = [];
        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,;:!?)]}»”’');
            // Kurung tutup bagian dari URL bila kurung bukanya juga ada (mis. Wikipedia).
            if (substr_count($url, '(') > substr_count($url, ')')) {
                $url .= ')';
            }
            if (! in_array($url, $urls, true) && filter_var($url, FILTER_VALIDATE_URL)) {
                $urls[] = $url;
            }
            if (count($urls) >= $limit) {
                break;
            }
        }

        return $urls;
    }

    /** Teks pesan tanpa tautan, untuk potongan chat di tab Tautan. */
    public static function textWithout(?string $content): string
    {
        $text = str_replace(self::urls($content), ' ', (string) $content);
        $text = preg_replace('/\(\s*\)|\[\s*\]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = preg_replace('/\s+([,.;:!?])/u', '$1', $text);

        return trim((string) preg_replace('/^[,.;:!?\s]+/u', '', trim($text)));
    }
}
