<?php

namespace App\Services\Ai\Files;

/**
 * Mengenali permintaan membuat file dari teks pesan ("@normi ubah jadi pdf", "buatkan excel anggaran",
 * "simpan sebagai file python", "jadikan zip"). Kasus nyata 10 Nov 2026: "@normi ubah jadi pdf" dengan lampiran
 * screenshot dijawab "Aku belum bisa mengirim file PDF", karena kata "ubah" tidak dikenali.
 */
final class FileRequest
{
    private const VERBS = '/\b(buat(?:kan|in)?|bikin(?:in|kan)?|generate|create|make|export|ekspor|render|ubah(?:kan)?|jadi(?:kan|in)?|convert|konversi(?:kan)?|simpan(?:kan)?|kirim(?:kan|in)?|unduh(?:an)?|download|susun(?:kan)?|tulis(?:kan)?|siapkan|save|compile|cetak|print)\b/u';

    /** Format yang cukup disebut namanya ("buatkan excel anggaran"). Format lain perlu kata "file"/ekstensi. */
    private const STRONG = ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'zip'];

    /** Kata yang menandai format lemah benar-benar diminta sebagai file. */
    private const FILE_WORDS = '/\b(file|berkas|dokumen|unduh(?:an)?|download|lampiran|attachment|simpan(?:kan)?\s+sebagai|save\s+as)\b/u';

    /** @param  list<string>  $formats */
    private function __construct(
        public readonly array $formats,
        public readonly bool $isConversion,
    ) {
    }

    /** Format pertama yang diminta; null = biarkan Normi memilih (tag @normi-file tanpa format). */
    public function format(): ?string
    {
        return $this->formats[0] ?? null;
    }

    /** Permintaan file lewat tag @normi-file: format dipilih dari teks bila ada. */
    public static function forFileMode(string $content): self
    {
        $detected = self::detect($content, requireVerb: false);

        return $detected ?? new self([], false);
    }

    public static function detect(string $content, bool $requireVerb = true): ?self
    {
        $text = mb_strtolower(trim((string) preg_replace('/(?<![\w@])@[\w-]+/u', ' ', $content)));
        if ($text === '' || ($requireVerb && preg_match(self::VERBS, $text) !== 1)) {
            return null;
        }

        $hasFileWord = preg_match(self::FILE_WORDS, $text) === 1;
        $found = [];

        // Ekstensi eksplisit: ".py", "format .xlsx", "nama file laporan.pdf".
        // Nama pustaka seperti "next.js", "chart.js", "vue.js" bukan permintaan file .js.
        if (preg_match_all('/(?:^|[\s(])([\w-]*)\.([a-z0-9]{1,10})\b/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[2] as $index => [$ext, $offset]) {
                $ext = $ext === 'yml' ? 'yaml' : ($ext === 'xls' ? 'xlsx' : ($ext === 'doc' ? 'docx' : ($ext === 'ppt' ? 'pptx' : $ext)));
                $bareExtension = $matches[1][$index][0] === '';
                // "main.py" dihitung; "next.js"/"chart.js" (nama pustaka) hanya bila memang diminta sebagai file.
                if (FileFormats::isKnown($ext) && ($bareExtension || $hasFileWord || $ext !== 'js')) {
                    $found[$offset] = $ext;
                }
            }
        }

        foreach (FileFormats::ALIASES as $alias => $ext) {
            $pattern = '/(?<![\p{L}\p{N}._-])' . preg_quote($alias, '/') . '(?![\p{L}\p{N}_-])/u';
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            if (! in_array($ext, self::STRONG, true) && ! $hasFileWord) {
                continue;
            }
            // "word" di dalam frasa seperti "password" sudah tersaring oleh batas kata; "slide" dan "presentasi" diterima.
            $found[$match[0][1]] ??= $ext;
        }

        if (preg_match('/\bpresentasi\b/u', $text, $match, PREG_OFFSET_CAPTURE) === 1 && ! in_array('pptx', $found, true)
            && preg_match('/\b(buat(?:kan|in)?|bikin(?:in|kan)?|siapkan|susun(?:kan)?)\s+(?:\w+\s+){0,2}presentasi\b/u', $text) === 1) {
            $found[$match[0][1]] = 'pptx';
        }

        ksort($found);
        $formats = array_values(array_unique($found));

        // "buatkan dokumen notulen rapat" tanpa format: PDF seperti sebelumnya (kecuali permintaan gambar).
        if ($formats === [] && preg_match('/\b(dokumen|file|berkas|laporan|report|surat|notulen|notula)\b/u', $text) === 1
            && preg_match('/\b(gambar|foto|image|picture|ilustrasi|illustration|logo|poster)\b/u', $text) !== 1
            && ($requireVerb ? preg_match('/\b(buat(?:kan|in)?|bikin(?:in|kan)?|generate|create|export|ekspor|render|siapkan)\b/u', $text) === 1 : true)) {
            $formats = ['pdf'];
        }

        if ($formats === []) {
            return null;
        }

        $isConversion = preg_match('/\b(ubah(?:kan)?|jadi(?:kan|in)?|convert|konversi(?:kan)?|simpan(?:kan)?|export|ekspor|cetak|print)\b/u', $text) === 1;

        return new self($formats, $isConversion);
    }
}
