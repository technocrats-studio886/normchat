<?php

namespace App\Services;

use App\Models\Message;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\Log;
use Throwable;

class AttachmentTextExtractor
{
    private const MAX_BYTES = 8 * 1024 * 1024; // batas berkas yang dibaca: 8 MB
    private const MAX_OUTPUT_CHARS = 16000;    // truncate extracted text to keep prompt manageable

    /**
     * Teks polos dari lampiran dokumen, atau null bila bukan dokumen atau gagal dibaca.
     * $maxChars: batas teks (bawaan untuk prompt); pengindeks dokumen grup memakai batas lebih besar.
     */
    public function extract(?Message $message, ?int $maxChars = null): ?string
    {
        if (! $message || ! $message->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        $extension = strtolower(pathinfo((string) ($message->attachment_original_name ?? $message->attachment_path), PATHINFO_EXTENSION));
        $mime = strtolower((string) $message->attachment_mime);

        if (! $this->isDocumentLike($extension, $mime)) {
            return null;
        }

        try {
            // Pembaca PDF/DOCX butuh file di disk; file di S3 disalin sementara.
            $text = app(StoredFiles::class)->withLocalCopy(
                $message->attachment_disk,
                $message->attachment_path,
                fn (string $path) => $this->extractByType($path, $extension, $mime),
                self::MAX_BYTES
            );
            if ($text === null || trim($text) === '') {
                return null;
            }

            return $this->truncate($text, $maxChars ?? self::MAX_OUTPUT_CHARS);
        } catch (Throwable $e) {
            Log::warning('Failed to extract attachment text.', [
                'message_id' => $message->id,
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function isDocumentLike(string $extension, string $mime): bool
    {
        $textExtensions = ['txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'log', 'xml', 'yaml', 'yml', 'html', 'htm'];
        if (in_array($extension, $textExtensions, true)) {
            return true;
        }

        if (in_array($extension, ['pdf', 'docx', 'pptx', 'xlsx'], true)) {
            return true;
        }

        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        if (in_array($mime, ['application/pdf', 'application/json', 'application/xml'], true)) {
            return true;
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return true;
        }

        return false;
    }

    private function extractByType(string $absolutePath, string $extension, string $mime): ?string
    {
        if ($extension === 'pdf' || $mime === 'application/pdf') {
            return $this->extractPdf($absolutePath);
        }

        if ($extension === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return $this->extractDocx($absolutePath);
        }

        if ($extension === 'pptx' || $extension === 'xlsx') {
            return $extension === 'pptx' ? $this->extractPptx($absolutePath) : $this->extractXlsx($absolutePath);
        }

        // Plain text & friendly formats
        $raw = @file_get_contents($absolutePath, false, null, 0, self::MAX_BYTES);
        if ($raw === false) {
            return null;
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'auto');
            if (is_string($converted)) {
                $raw = $converted;
            }
        }

        return $raw;
    }

    private function extractPdf(string $absolutePath): ?string
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            return null;
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $document = $parser->parseFile($absolutePath);
            $text = (string) $document->getText();

            return trim($text) !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('PDF parse failed.', ['path' => $absolutePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractDocx(string $absolutePath): ?string
    {
        if (! class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            return null;
        }

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($absolutePath);
            $lines = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $this->collectDocxText($element, $lines);
                }
            }

            $text = trim(implode("\n", array_filter($lines, fn ($line) => trim((string) $line) !== '')));

            return $text !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('DOCX parse failed.', ['path' => $absolutePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function collectDocxText($element, array &$lines): void
    {
        if (method_exists($element, 'getText')) {
            $value = $element->getText();
            if (is_string($value) && $value !== '') {
                $lines[] = $value;
                return;
            }
        }

        if (method_exists($element, 'getElements')) {
            $children = $element->getElements();
            if (is_array($children) || $children instanceof \Traversable) {
                $buffer = [];
                foreach ($children as $child) {
                    $childLines = [];
                    $this->collectDocxText($child, $childLines);
                    if ($childLines !== []) {
                        $buffer = array_merge($buffer, $childLines);
                    }
                }

                if ($buffer !== []) {
                    $lines[] = implode(' ', $buffer);
                }
            }
        }
    }

    /** Teks tiap slide PowerPoint, berurutan ("Slide 1", "Slide 2", ...). */
    private function extractPptx(string $absolutePath): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            return null;
        }
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $match) === 1) {
                $xml = (string) $zip->getFromIndex($i);
                // Satu paragraf (<a:p>) satu baris; isi teks ada di <a:t>.
                $paragraphs = [];
                preg_match_all('#<a:p\b.*?</a:p>#s', $xml, $blocks);
                foreach ($blocks[0] as $block) {
                    preg_match_all('#<a:t>(.*?)</a:t>#s', $block, $runs);
                    $line = trim(html_entity_decode(implode('', $runs[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($line !== '') {
                        $paragraphs[] = $line;
                    }
                }
                $slides[(int) $match[1]] = $paragraphs;
            }
        }
        $zip->close();
        ksort($slides);
        $text = implode("\n\n", array_map(fn (int $number, array $lines) => "Slide {$number}\n" . implode("\n", $lines), array_keys($slides), $slides));

        return trim($text) !== '' ? $text : null;
    }

    /** Isi sel tiap sheet Excel sebagai baris teks dipisah " | ". */
    private function extractXlsx(string $absolutePath): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            return null;
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml)) {
            preg_match_all('#<si>(.*?)</si>#s', $sharedXml, $items);
            foreach ($items[1] as $item) {
                preg_match_all('#<t[^>]*>(.*?)</t>#s', $item, $texts);
                $shared[] = html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        $sheets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $match) !== 1) {
                continue;
            }
            $rows = [];
            preg_match_all('#<row\b[^>]*>(.*?)</row>#s', (string) $zip->getFromIndex($i), $rowBlocks);
            foreach (array_slice($rowBlocks[1], 0, 2000) as $rowXml) {
                $cells = [];
                preg_match_all('#<c\b([^>]*?)(?:/>|>(.*?)</c>)#s', $rowXml, $cellBlocks, PREG_SET_ORDER);
                foreach ($cellBlocks as $cell) {
                    $attrs = $cell[1];
                    $inner = $cell[2] ?? '';
                    $value = '';
                    if (str_contains($attrs, 't="s"') && preg_match('#<v>(\d+)</v>#', $inner, $v) === 1) {
                        $value = $shared[(int) $v[1]] ?? '';
                    } elseif (preg_match('#<t[^>]*>(.*?)</t>#s', $inner, $t) === 1) {
                        $value = html_entity_decode($t[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    } elseif (preg_match('#<v>(.*?)</v>#s', $inner, $v) === 1) {
                        $value = $v[1];
                    }
                    $cells[] = trim($value);
                }
                if (array_filter($cells, fn ($cell) => $cell !== '') !== []) {
                    $rows[] = implode(' | ', $cells);
                }
            }
            $sheets[(int) $match[1]] = "Sheet {$match[1]}\n" . implode("\n", $rows);
        }
        $zip->close();
        ksort($sheets);
        $text = implode("\n\n", $sheets);

        return trim($text) !== '' ? $text : null;
    }

    private function truncate(string $text, int $maxChars = self::MAX_OUTPUT_CHARS): string
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars) . "\n\n[…dipotong: file lebih panjang dari batas yang dibaca AI]";
    }
}
