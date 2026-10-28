<?php

namespace App\Services\Exports;

use FontLib\Font;

/**
 * Emoji untuk PDF. dompdf tidak bisa menggambar karakter di atas U+FFFF, jadi setiap emoji
 * digambar sebagai SVG kecil dari outline glyph Noto Emoji (resources/fonts, lisensi OFL).
 * Pemilih variasi, penyambung (ZWJ), dan warna kulit dibuang; emoji gabungan tampil per bagian.
 */
final class EmojiGlyphs
{
    private const FONT = 'fonts/NotoEmoji-Regular.ttf';

    private const PATTERN = '/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{3030}\x{303D}\x{3297}\x{3299}]/u';

    private ?array $cmap = null;

    private $glyf;

    private array $hmtx = [];

    /** @var array<int, string> */
    private array $cache = [];

    /** Ganti emoji di teks/HTML dengan <img> SVG setinggi huruf. Warna mengikuti $color. */
    public function inline(string $html, string $color = '#121C29'): string
    {
        $html = (string) preg_replace('/[\x{FE0E}\x{FE0F}\x{200D}\x{1F3FB}-\x{1F3FF}]/u', '', $html);

        return (string) preg_replace_callback(self::PATTERN, fn (array $match) => $this->image(mb_ord($match[0]), $color) ?? $match[0], $html);
    }

    private function image(int $codepoint, string $color): ?string
    {
        $key = $codepoint . $color;
        if (! isset($this->cache[$key])) {
            $svg = $this->svg($codepoint, $color);
            $this->cache[$key] = $svg === null ? '' : '<img class="emoji" alt="" src="data:image/svg+xml;base64,' . base64_encode($svg) . '">';
        }

        return $this->cache[$key] !== '' ? $this->cache[$key] : null;
    }

    private function svg(int $codepoint, string $color): ?string
    {
        $this->load();
        $glyphId = $this->cmap[$codepoint] ?? null;
        if ($glyphId === null || ! isset($this->glyf->data[$glyphId])) {
            return null;
        }

        $glyph = $this->glyf->data[$glyphId];
        $glyph->parseData();
        $path = $glyph->getSVGContours();
        $advance = (int) ($this->hmtx[$glyphId][0] ?? 2550);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -1900 ' . $advance . ' 2400"><path fill="' . $color . '" transform="scale(1,-1)" d="' . $path . '"/></svg>';
    }

    private function load(): void
    {
        if ($this->cmap !== null) {
            return;
        }

        $font = Font::load(resource_path(self::FONT));
        $font->parse();
        $this->cmap = [];
        foreach ($font->getData('cmap')['subtables'] as $table) {
            $this->cmap += $table['glyphIndexArray'] ?? [];
        }
        $this->glyf = $font->getTableObject('glyf');
        $this->hmtx = $font->getData('hmtx');
    }
}
