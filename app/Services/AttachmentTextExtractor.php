<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AttachmentTextExtractor
{
    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB hard cap on file size we will read
    private const MAX_OUTPUT_CHARS = 16000;    // truncate extracted text to keep prompt manageable

    /**
     * Returns plain text extracted from a document attachment, or null when not applicable / failed.
     */
    public function extract(?Message $message): ?string
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
            $disk = Storage::disk($message->attachment_disk);
            if (! $disk->exists($message->attachment_path)) {
                return null;
            }

            $size = (int) $disk->size($message->attachment_path);
            if ($size <= 0 || $size > self::MAX_BYTES) {
                return null;
            }

            $text = $this->extractByType($disk->path($message->attachment_path), $extension, $mime);
            if ($text === null || trim($text) === '') {
                return null;
            }

            return $this->truncate($text);
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

        if (in_array($extension, ['pdf', 'docx'], true)) {
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

    private function truncate(string $text): string
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        if (mb_strlen($text) <= self::MAX_OUTPUT_CHARS) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_OUTPUT_CHARS) . "\n\n[…dipotong: file lebih panjang dari batas yang dibaca AI]";
    }
}
