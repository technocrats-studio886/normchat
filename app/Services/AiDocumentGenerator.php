<?php

namespace App\Services;

/** Render Markdown ke DOCX untuk file buatan Normi (App\Services\Ai\Files\FileBuilder). */
class AiDocumentGenerator
{
    /** Isi DOCX dari Markdown. */
    public function renderDocxBinary(string $title, string $markdown): string
    {
        $markdown = trim((string) preg_replace('/^\s*```(?:markdown|md)?\s*\n|\n```\s*$/u', '', $markdown));
        // Judul sudah ditulis sebagai Title; baris "# Judul" yang sama tidak diulang.
        $markdown = (string) preg_replace('/^\s*#\s+' . preg_quote($title, '/') . '\s*\n/u', '', $markdown, 1);

        return $this->renderDocx($title, $markdown)[0];
    }

    private function renderDocx(string $title, string $markdown): array
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            throw new \RuntimeException('PhpWord not installed.');
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);
        // Tanpa gaya judul, addTitle() menulis paragraf biasa: judul tampil seperti teks isi di Word (eval 23 Nov 2026).
        foreach ([1 => 18, 2 => 14, 3 => 12.5, 4 => 11.5, 5 => 11, 6 => 11] as $level => $size) {
            $phpWord->addTitleStyle($level, ['size' => $size, 'bold' => true, 'color' => '111111'], ['spaceBefore' => $level === 1 ? 0 : 240, 'spaceAfter' => 120, 'keepNext' => true]);
        }
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

            // Tabel Markdown: | a | b | (baris pemisah |---| dilewati), baris pertama tebal.
            $lines = preg_split('/\n/', $trimmed) ?: [];
            if (count($lines) >= 2 && collect($lines)->every(fn ($line) => str_starts_with(trim($line), '|'))) {
                $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CFD6DE', 'cellMargin' => 80]);
                $rowIndex = 0;
                foreach ($lines as $line) {
                    if (preg_match('/^\s*\|?\s*:?-{2,}/', $line)) {
                        continue;
                    }
                    $table->addRow();
                    foreach (array_map('trim', explode('|', trim(trim($line), '|'))) as $cell) {
                        $this->addRichText($table->addCell(2400)->addTextRun(), $cell, $rowIndex === 0);
                    }
                    $rowIndex++;
                }
                continue;
            }

            if (preg_match('/^(?:-|\*|\d+\.)\s+/u', $trimmed)) {
                foreach ($lines as $listLine) {
                    $listLine = trim($listLine);
                    if ($listLine === '') {
                        continue;
                    }
                    $clean = preg_replace('/^(?:-|\*|\d+\.)\s+/u', '', $listLine) ?? $listLine;
                    $this->addRichText($section->addListItemRun(), $clean);
                }
                continue;
            }

            if (str_starts_with($trimmed, '```')) {
                $code = trim((string) preg_replace('/^```[\w+#.-]*\s*\n?|\n?```$/u', '', $trimmed));
                foreach (preg_split('/\n/', $code) ?: [] as $codeLine) {
                    $section->addText(htmlspecialchars($codeLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ['name' => 'Consolas', 'size' => 9.5]);
                }
                continue;
            }

            $run = $section->addTextRun();
            foreach ($lines as $i => $line) {
                if ($i > 0) {
                    $run->addTextBreak();
                }
                $this->addRichText($run, trim($line));
            }
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

    /** Teks dengan **tebal**, *miring*, dan `kode` sebagai run terpisah. */
    private function addRichText($run, string $text, bool $bold = false): void
    {
        foreach (preg_split('/(\*\*[^*]+\*\*|`[^`]+`|(?<!\*)\*[^*\s][^*]*\*(?!\*))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $style = $bold ? ['bold' => true] : [];
            if (str_starts_with($part, '**') && str_ends_with($part, '**') && mb_strlen($part) > 4) {
                $part = mb_substr($part, 2, -2);
                $style['bold'] = true;
            } elseif (str_starts_with($part, '`') && str_ends_with($part, '`') && mb_strlen($part) > 2) {
                $part = mb_substr($part, 1, -1);
                $style['name'] = 'Consolas';
            } elseif (str_starts_with($part, '*') && str_ends_with($part, '*') && mb_strlen($part) > 2) {
                $part = mb_substr($part, 1, -1);
                $style['italic'] = true;
            }
            $run->addText(htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $style);
        }
    }

    /**
     * Blok Markdown dipisah per jenis, bukan hanya per baris kosong: "## Petunjuk" yang langsung diikuti teks,
     * atau paragraf yang langsung diikuti daftar/tabel, sah di Markdown dan sering ditulis model
     * (eval 23 Nov 2026: judul tampil sebagai "## Petunjuk" mentah di Word). Isi code fence tidak disentuh.
     */
    private function splitParagraphs(string $markdown): array
    {
        $normalized = preg_replace("/\r\n?/", "\n", $markdown) ?? $markdown;
        $blocks = [];
        $current = [];
        $kind = null;
        $fence = false;
        $flush = function () use (&$blocks, &$current, &$kind) {
            if ($current !== []) {
                $blocks[] = implode("\n", $current);
            }
            $current = [];
            $kind = null;
        };
        foreach (explode("\n", $normalized) as $line) {
            $trimmed = trim($line);
            if ($fence) {
                $current[] = $line;
                if (str_starts_with($trimmed, '```')) {
                    $fence = false;
                    $flush();
                }
                continue;
            }
            if ($trimmed === '') {
                $flush();
                continue;
            }
            if (str_starts_with($trimmed, '```')) {
                $flush();
                $current[] = $line;
                $fence = true;
                continue;
            }
            $lineKind = match (true) {
                (bool) preg_match('/^#{1,6}\s+\S/u', $trimmed) => 'heading',
                str_starts_with($trimmed, '|') => 'table',
                (bool) preg_match('/^(?:-|\*|\d+\.)\s+/u', $trimmed) => 'list',
                // Pilihan "a. …" atau baris menjorok di bawah butir daftar tetap bagian daftarnya (soal pilihan ganda).
                $kind === 'list' && (preg_match('/^\s/', $line) === 1 || preg_match('/^[a-eA-E][.)]\s/u', $trimmed) === 1) => 'list',
                default => 'text',
            };
            if ($lineKind === 'heading' || ($kind !== null && $kind !== $lineKind)) {
                $flush();
            }
            $current[] = $line;
            $kind = $lineKind;
            if ($lineKind === 'heading') {
                $flush();
            }
        }
        $flush();

        return array_values(array_filter(array_map('trim', $blocks), fn ($p) => $p !== ''));
    }
}
