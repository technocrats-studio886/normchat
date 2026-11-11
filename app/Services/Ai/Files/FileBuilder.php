<?php

namespace App\Services\Ai\Files;

use App\Services\AiDocumentGenerator;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Mengubah spesifikasi file dari Normi menjadi berkas jadi:
 * - pdf/docx dari Markdown, xlsx dari sheets (atau CSV), pptx dari slides (atau Markdown berjudul ##);
 * - format teks apa pun ditulis apa adanya;
 * - beberapa file sekaligus dibungkus satu ZIP.
 */
final class FileBuilder
{
    private const MAX_TEXT_BYTES = 2 * 1024 * 1024;

    private const MAX_FILES = 40;

    /**
     * @param  list<array<string, mixed>>  $files  [{name, content?, sheets?, slides?}, ...]
     * @param  string|null  $preferred  format yang diminta user, dipakai bila nama file tidak berekstensi
     * @return array{binary: string, mime: string, name: string, ext: string}
     */
    public function build(array $files, ?string $preferred, string $title, bool $forceZip = false): array
    {
        $files = array_values(array_filter(array_slice($files, 0, self::MAX_FILES), 'is_array'));
        if ($files === []) {
            throw new RuntimeException('Tidak ada file untuk dibuat.');
        }

        $built = [];
        foreach ($files as $index => $file) {
            $built[] = $this->one($file, count($files) === 1 ? $preferred : null, $title, $index);
        }

        if (count($built) === 1 && ! $forceZip && $built[0]['ext'] !== 'zip') {
            return $built[0];
        }

        return $this->zip($built, $title);
    }

    /** PDF berisi gambar-gambar (satu gambar per halaman), untuk "ubah foto ini jadi pdf". */
    public function imagesToPdf(array $images, string $title): array
    {
        $pages = [];
        foreach ($images as $image) {
            $pages[] = '<div class="page"><img src="data:' . e($image['mime']) . ';base64,' . base64_encode($image['binary']) . '"></div>';
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:24px}body{margin:0}'
            . '.page{page-break-after:always;text-align:center}.page:last-child{page-break-after:auto}'
            . 'img{max-width:100%;max-height:1060px}</style></head><body>' . implode('', $pages) . '</body></html>';

        return [
            'binary' => (string) \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output(),
            'mime' => 'application/pdf',
            'name' => $this->fileName($title, 'pdf'),
            'ext' => 'pdf',
        ];
    }

    /** @return array{binary: string, mime: string, name: string, ext: string} */
    private function one(array $file, ?string $preferred, string $title, int $index): array
    {
        $name = trim((string) ($file['name'] ?? ''));
        $ext = FileFormats::extensionOf($name) ?: ($preferred ?? '') ?: $this->guessExtension($file);
        $name = $this->safeName($name, $ext, $title, $index);
        $content = $file['content'] ?? '';
        $content = is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $binary = match ($ext) {
            'pdf' => $this->pdf($this->docTitle($content, $title), $content),
            'docx' => app(AiDocumentGenerator::class)->renderDocxBinary($this->docTitle($content, $title), $content),
            'xlsx' => XlsxWriter::write($this->sheets($file, $content)),
            'pptx' => PptxWriter::write($this->docTitle($content, $title), $this->slides($file, $content)),
            'csv', 'tsv' => $this->delimited($file, $content, $ext === 'tsv' ? "\t" : ','),
            default => $this->text($content, $ext),
        };

        if ($binary === '') {
            throw new RuntimeException("File {$name} kosong.");
        }

        return ['binary' => $binary, 'mime' => FileFormats::mime($ext), 'name' => $name, 'ext' => $ext];
    }

    private function guessExtension(array $file): string
    {
        return isset($file['sheets']) ? 'xlsx' : (isset($file['slides']) ? 'pptx' : 'txt');
    }

    private function text(string $content, string $ext): string
    {
        // Model kadang membungkus isi file dengan code fence.
        if (! in_array($ext, ['md', 'txt'], true)) {
            $content = (string) preg_replace('/^\s*```[\w+#.-]*\s*\n|\n```\s*$/u', '', $content);
        }
        if (strlen($content) > self::MAX_TEXT_BYTES) {
            $content = mb_strcut($content, 0, self::MAX_TEXT_BYTES);
        }
        $content = rtrim($content) . "\n";

        // CSV/teks dibuka Excel/Notepad Windows dengan benar bila diawali BOM UTF-8.
        return in_array($ext, ['csv', 'tsv'], true) ? "\xEF\xBB\xBF" . $content : $content;
    }

    private function delimited(array $file, string $content, string $separator): string
    {
        $rows = $this->sheets($file, $content)[0]['rows'] ?? [];
        if ($content !== '' && ! isset($file['sheets'])) {
            return $this->text($content, $separator === "\t" ? 'tsv' : 'csv');
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($cell) => is_scalar($cell) || $cell === null ? $cell : json_encode($cell), (array) $row), $separator, '"', '');
        }
        rewind($stream);

        return "\xEF\xBB\xBF" . stream_get_contents($stream);
    }

    /** @return list<array{name: string, rows: list<list<mixed>>}> */
    private function sheets(array $file, string $content): array
    {
        if (isset($file['sheets']) && is_array($file['sheets'])) {
            return array_values(array_map(fn ($sheet) => [
                'name' => (string) ($sheet['name'] ?? ''),
                'rows' => array_values(array_filter((array) ($sheet['rows'] ?? []), 'is_array')),
            ], array_filter($file['sheets'], 'is_array')));
        }
        if (isset($file['rows']) && is_array($file['rows'])) {
            return [['name' => 'Sheet1', 'rows' => array_values(array_filter($file['rows'], 'is_array'))]];
        }

        // Cadangan: tabel Markdown atau CSV di content.
        $rows = [];
        foreach (preg_split('/\r?\n/', trim($content)) ?: [] as $line) {
            if (trim($line) === '' || preg_match('/^\s*\|?\s*:?-{2,}/', $line)) {
                continue;
            }
            $rows[] = str_contains($line, '|')
                ? array_map('trim', explode('|', trim(trim($line), '|')))
                : str_getcsv($line, ',', '"', '');
        }

        return [['name' => 'Sheet1', 'rows' => $rows]];
    }

    /** @return list<array<string, mixed>> */
    private function slides(array $file, string $content): array
    {
        if (isset($file['slides']) && is_array($file['slides'])) {
            return array_values(array_filter($file['slides'], 'is_array'));
        }

        // Cadangan: Markdown, "# Judul" = slide judul, setiap "## …" = satu slide, poin "- …" = isi.
        $slides = [];
        $current = null;
        foreach (preg_split('/\r?\n/', $content) ?: [] as $line) {
            if (preg_match('/^#\s+(.+)$/u', $line, $m) && $slides === [] && $current === null) {
                $current = ['title' => trim($m[1]), 'subtitle' => ''];
            } elseif (preg_match('/^#{2,3}\s+(.+)$/u', $line, $m)) {
                if ($current !== null) {
                    $slides[] = $current;
                }
                $current = ['title' => trim($m[1]), 'bullets' => []];
            } elseif (trim($line) !== '' && $current !== null) {
                if (isset($current['bullets'])) {
                    $current['bullets'][] = $line;
                } else {
                    $current['subtitle'] = trim(($current['subtitle'] ?? '') . ' ' . trim($line));
                }
            }
        }
        if ($current !== null) {
            $slides[] = $current;
        }

        return $slides;
    }

    private function pdf(string $title, string $markdown): string
    {
        $markdown = trim((string) preg_replace('/^\s*```(?:markdown|md)?\s*\n|\n```\s*$/u', '', $markdown));
        $body = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        if (! preg_match('/^\s*#\s/u', $markdown)) {
            $body = '<h1>' . e($title) . '</h1>' . $body;
        }

        $css = '@page{margin:56px 52px}body{font-family:DejaVu Sans,sans-serif;font-size:11.5px;color:#1f2933;line-height:1.55}'
            . 'h1{font-size:21px;margin:0 0 14px;color:#111}h2{font-size:16px;margin:20px 0 8px;color:#111}h3{font-size:13.5px;margin:16px 0 6px}'
            . 'p{margin:0 0 9px}ul,ol{margin:0 0 9px 18px;padding:0}li{margin:0 0 3px}'
            . 'table{border-collapse:collapse;width:100%;margin:8px 0 12px}th,td{border:1px solid #cfd6de;padding:5px 7px;text-align:left;vertical-align:top}'
            . 'th{background:#eef2f6}code{font-family:DejaVu Sans Mono,monospace;background:#f2f4f7;padding:1px 3px;font-size:10.5px}'
            . 'pre{background:#f2f4f7;padding:9px;white-space:pre-wrap;font-size:10px}pre code{background:none;padding:0}'
            . 'blockquote{margin:0 0 9px;padding:4px 10px;border-left:3px solid #cfd6de;color:#4b5563}a{color:#1d5aa0}';

        return (string) \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<!doctype html><html><head><meta charset="utf-8"><title>' . e($title)
            . '</title><style>' . $css . '</style></head><body>' . $body . '</body></html>')->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }

    private function docTitle(string $content, string $fallback): string
    {
        return preg_match('/^\s*#\s+(.+)$/mu', $content, $m) ? Str::limit(trim($m[1]), 80, '') : $fallback;
    }

    /** @param  list<array{binary: string, name: string}>  $built */
    private function zip(array $built, string $title): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'normi-zip-');
        if ($tmp === false) {
            throw new RuntimeException('Tidak bisa membuat ZIP.');
        }

        try {
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::OVERWRITE);
            $used = [];
            foreach ($built as $file) {
                $path = $file['name'];
                for ($i = 2; isset($used[strtolower($path)]); $i++) {
                    $path = preg_replace('/(\.[^.\/]+)?$/', "-{$i}$1", $file['name'], 1);
                }
                $used[strtolower($path)] = true;
                $zip->addFromString($path, $file['binary']);
            }
            $zip->close();
            $binary = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return ['binary' => $binary, 'mime' => 'application/zip', 'name' => $this->fileName($title, 'zip'), 'ext' => 'zip'];
    }

    /** Nama aman; jalur folder di dalam ZIP ("src/App.jsx") tetap dipertahankan. */
    private function safeName(string $name, string $ext, string $title, int $index): string
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $name)), fn ($part) => $part !== '' && $part !== '.' && $part !== '..'));
        $parts = array_map(fn ($part) => trim((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $part), ' .-'), $parts);
        $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
        if ($parts === []) {
            return $this->fileName($index > 0 ? "{$title} {$index}" : $title, $ext);
        }

        $base = array_pop($parts);
        if (FileFormats::extensionOf($base) === '' && ! str_contains($base, '.')) {
            $base .= '.' . $ext;
        }

        return implode('/', [...array_map(fn ($p) => mb_substr($p, 0, 60), $parts), mb_substr($base, 0, 100)]);
    }

    private function fileName(string $title, string $ext): string
    {
        return (Str::slug(Str::limit($title, 60, '')) ?: 'normi-file') . '.' . $ext;
    }
}
