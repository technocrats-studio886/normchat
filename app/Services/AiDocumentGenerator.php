<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiDocumentGenerator
{
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_DOCX = 'docx';
    public const FORMAT_MD = 'md';
    public const FORMAT_TXT = 'txt';

    /**
     * Detect a document-generation request from a user prompt. Returns a normalized
     * format key (pdf|docx|md|txt) or null when the user is not asking for a doc.
     */
    public function detectRequestedFormat(string $content): ?string
    {
        $normalized = strtolower($content);
        $hasGenVerb = preg_match(
            '/\b(buat(?:kan|in)?|bikin(?:in)?|generate|create|export|render|tolong\s+buat|coba\s+buat)\b/u',
            $normalized
        ) === 1;

        if (! $hasGenVerb) {
            return null;
        }

        // PDF first (most specific phrasing)
        if (preg_match('/\b(pdf|file\s+pdf|dokumen\s+pdf)\b/u', $normalized) === 1) {
            return self::FORMAT_PDF;
        }

        if (preg_match('/\b(docx|doc|word|microsoft\s+word|file\s+word|dokumen\s+word)\b/u', $normalized) === 1) {
            return self::FORMAT_DOCX;
        }

        if (preg_match('/\b(markdown|file\s+md|\.md)\b/u', $normalized) === 1) {
            return self::FORMAT_MD;
        }

        if (preg_match('/\bfile\s+(text|teks|txt)\b/u', $normalized) === 1) {
            return self::FORMAT_TXT;
        }

        // Generic "buatkan dokumen / buat file" → default to PDF
        if (preg_match('/\b(dokumen|file|laporan|report|surat|catatan|notulen|ringkasan\s+file)\b/u', $normalized) === 1) {
            // Avoid colliding with image generation phrasing already handled separately.
            if (preg_match('/\b(gambar|foto|image|picture|illustration)\b/u', $normalized) === 1) {
                return null;
            }
            return self::FORMAT_PDF;
        }

        return null;
    }

    /**
     * Persist generated content to the attachments disk and return descriptor.
     *
     * @return array{path: string, mime: string, original_name: string, message_type: string}|null
     */
    public function storeDocument(int $groupId, string $format, string $markdown, ?string $titleHint = null): ?array
    {
        $format = strtolower($format);
        $title = $this->buildTitle($titleHint, $markdown);
        $slug = Str::slug($title) ?: 'normai-document';
        $uuid = (string) Str::uuid();

        try {
            switch ($format) {
                case self::FORMAT_DOCX:
                    [$binary, $mime, $extension] = $this->renderDocx($title, $markdown);
                    break;
                case self::FORMAT_MD:
                    $binary = $markdown;
                    $mime = 'text/markdown';
                    $extension = 'md';
                    break;
                case self::FORMAT_TXT:
                    $binary = $this->markdownToPlain($markdown);
                    $mime = 'text/plain';
                    $extension = 'txt';
                    break;
                case self::FORMAT_PDF:
                default:
                    [$binary, $mime, $extension] = $this->renderPdf($title, $markdown);
                    break;
            }
        } catch (Throwable $e) {
            Log::error('AI document render failed.', [
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $disk = 'normchat_attachments';
        $relativePath = sprintf(
            'group-%d/%s/ai-doc-%s.%s',
            $groupId,
            now()->format('Y/m'),
            $uuid,
            $extension
        );

        $directory = dirname($relativePath);
        if ($directory !== '.') {
            Storage::disk($disk)->makeDirectory($directory);
        }

        Storage::disk($disk)->put($relativePath, $binary);

        return [
            'path' => $relativePath,
            'mime' => $mime,
            'original_name' => $slug . '.' . $extension,
            'message_type' => 'file',
        ];
    }

    private function renderPdf(string $title, string $markdown): array
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \RuntimeException('DomPDF not installed.');
        }

        $html = $this->markdownToHtml($title, $markdown);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');

        return [(string) $pdf->output(), 'application/pdf', 'pdf'];
    }

    private function renderDocx(string $title, string $markdown): array
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            throw new \RuntimeException('PhpWord not installed.');
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        if ($title !== '') {
            $section->addTitle(htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 1);
        }

        foreach ($this->splitParagraphs($markdown) as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/u', $trimmed, $matches)) {
                $level = min(6, substr_count(explode(' ', $trimmed, 2)[0] ?? '#', '#'));
                $section->addTitle(htmlspecialchars(trim($matches[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $level);
                continue;
            }

            if (preg_match('/^(?:-|\*|\d+\.)\s+/u', $trimmed)) {
                foreach (preg_split('/\n+/', $trimmed) as $listLine) {
                    $listLine = trim($listLine);
                    if ($listLine === '') {
                        continue;
                    }
                    $clean = preg_replace('/^(?:-|\*|\d+\.)\s+/u', '', $listLine) ?? $listLine;
                    $section->addListItem(htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                }
                continue;
            }

            $section->addText(htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'normai-docx-');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for DOCX.');
        }

        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tmp);
            $binary = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return [
            $binary,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
        ];
    }

    private function buildTitle(?string $hint, string $markdown): string
    {
        $hint = trim((string) $hint);
        if ($hint !== '') {
            return mb_substr($hint, 0, 80);
        }

        foreach ($this->splitParagraphs($markdown) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $clean = preg_replace('/^#+\s*/u', '', $line) ?? $line;
            $clean = trim($clean);
            if ($clean !== '') {
                return mb_substr($clean, 0, 80);
            }
        }

        return 'Dokumen NormAI';
    }

    private function splitParagraphs(string $markdown): array
    {
        $normalized = preg_replace("/\r\n?/", "\n", $markdown) ?? $markdown;
        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [];
        return array_values(array_filter(array_map('trim', $paragraphs), fn ($p) => $p !== ''));
    }

    private function markdownToHtml(string $title, string $markdown): string
    {
        $bodyParts = [];
        $bodyParts[] = '<h1>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>';

        $listOpen = false;
        foreach (preg_split("/\n/", $markdown) ?: [] as $rawLine) {
            $line = rtrim($rawLine);

            if ($line === '') {
                if ($listOpen) {
                    $bodyParts[] = '</ul>';
                    $listOpen = false;
                }
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $matches)) {
                if ($listOpen) {
                    $bodyParts[] = '</ul>';
                    $listOpen = false;
                }
                $level = strlen($matches[1]);
                $bodyParts[] = '<h' . $level . '>' . $this->inlineMd(trim($matches[2])) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^(?:-|\*|\d+\.)\s+(.+)$/u', $line, $matches)) {
                if (! $listOpen) {
                    $bodyParts[] = '<ul>';
                    $listOpen = true;
                }
                $bodyParts[] = '<li>' . $this->inlineMd(trim($matches[1])) . '</li>';
                continue;
            }

            $bodyParts[] = '<p>' . $this->inlineMd($line) . '</p>';
        }

        if ($listOpen) {
            $bodyParts[] = '</ul>';
        }

        $css = 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111;line-height:1.55;padding:24px;}'
            . 'h1{font-size:22px;margin:0 0 14px;}h2{font-size:18px;margin:18px 0 8px;}h3{font-size:15px;margin:14px 0 6px;}'
            . 'p{margin:0 0 10px;}ul{margin:0 0 10px 18px;}li{margin:0 0 4px;}code{background:#f4f4f5;padding:1px 4px;border-radius:3px;}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</title><style>' . $css . '</style></head><body>' . implode("\n", $bodyParts) . '</body></html>';
    }

    private function inlineMd(string $line): string
    {
        $escaped = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/u', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        return $escaped;
    }

    private function markdownToPlain(string $markdown): string
    {
        $stripped = preg_replace('/^#{1,6}\s+/mu', '', $markdown) ?? $markdown;
        $stripped = preg_replace('/\*\*(.+?)\*\*/u', '$1', $stripped) ?? $stripped;
        $stripped = preg_replace('/\*(.+?)\*/u', '$1', $stripped) ?? $stripped;
        $stripped = preg_replace('/`([^`]+)`/u', '$1', $stripped) ?? $stripped;
        return trim($stripped);
    }
}
