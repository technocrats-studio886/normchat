<?php

namespace App\Services\Exports;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use Throwable;

/**
 * Riwayat chat sebagai DOCX dengan susunan yang sama dengan PDF: pesan pengekspor di kanan,
 * anggota lain di kiri, tanda air logo di kepala halaman, kaki halaman berisi nomor halaman
 * dan nama pengekspor. Sudut bubble tidak bisa dibulatkan di Word.
 */
class DocxChatExporter implements ChatExporter
{
    private const GREY = '5D6C80';

    /** Lebar isi halaman A4 dengan margin 2 cm, dalam twip. */
    private const CONTENT_WIDTH = 9638;

    /** @var list<string> berkas gambar sementara, dihapus setelah dokumen ditulis */
    private array $tempFiles = [];

    public function extension(): string
    {
        return 'docx';
    }

    public function render(ExportDocument $document): string
    {
        $word = new PhpWord();
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1134, 'marginRight' => 1134]);

        $header = $section->addHeader();
        $header->addWatermark(resource_path('images/export-watermark.png'), [
            'width' => 200, 'height' => 235, 'positioning' => 'absolute',
            'posHorizontal' => 'center', 'posVertical' => 'center', 'posHorizontalRel' => 'page', 'posVerticalRel' => 'page',
        ]);
        $footer = $section->addFooter()->addTable(['width' => 5000, 'unit' => 'pct']);
        $footer->addRow();
        $footer->addCell(self::CONTENT_WIDTH / 2)->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8, 'color' => self::GREY]);
        $footer->addCell(self::CONTENT_WIDTH / 2)->addText('Diekspor oleh ' . $document->exporterName, ['size' => 8, 'color' => self::GREY], ['alignment' => Jc::END]);

        $section->addText('Diekspor ' . $document->exportedAt->translatedFormat('j F Y, H.i'), ['size' => 8, 'color' => self::GREY], ['alignment' => Jc::END]);
        $section->addText($document->groupName, ['size' => 20, 'bold' => true], ['alignment' => Jc::CENTER, 'spaceBefore' => 120, 'spaceAfter' => 40]);
        $section->addText($document->rangeLabel . ' · ' . $document->count() . ' pesan', ['size' => 8, 'color' => self::GREY], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);

        $day = null;
        foreach ($document->entries as $entry) {
            $date = $entry->sentAt->format('Y-m-d');
            if ($date !== $day) {
                $section->addText($entry->sentAt->translatedFormat('l, j F Y'), ['size' => 8, 'color' => self::GREY], ['alignment' => Jc::CENTER, 'spaceBefore' => 160, 'spaceAfter' => 80]);
                $day = $date;
            }
            $this->row($section, $entry);
        }
        if ($document->entries === []) {
            $section->addText('Tidak ada pesan pada rentang dan filter ini.', ['color' => self::GREY], ['alignment' => Jc::CENTER]);
        }

        $path = tempnam(sys_get_temp_dir(), 'ncdocx_');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return (string) file_get_contents($path);
        } finally {
            foreach ([$path, ...$this->tempFiles] as $file) {
                @unlink($file);
            }
            $this->tempFiles = [];
        }
    }

    private function row($section, ExportEntry $entry): void
    {
        $table = $section->addTable(['width' => 5000, 'unit' => 'pct', 'cellMargin' => 0]);
        $table->addRow(null, ['cantSplit' => true]);
        $style = $entry->isMine
            ? ['bgColor' => 'E4E9F0']
            : ($entry->isAi ? ['bgColor' => 'FFF3F1', 'borderSize' => 4, 'borderColor' => 'FECBC3'] : ['bgColor' => 'FFFFFF', 'borderSize' => 4, 'borderColor' => 'DFE4EA']);
        $style += ['valign' => 'bottom'];
        $spacing = ['spaceBefore' => $entry->runStart ? 100 : 30];

        if ($entry->isMine) {
            $table->addCell((int) (self::CONTENT_WIDTH * 0.22));
            $this->bubble($table->addCell((int) (self::CONTENT_WIDTH * 0.78), $style), $entry, $spacing);

            return;
        }

        $avatarCell = $table->addCell(500, ['valign' => 'bottom']);
        if ($entry->runEnd) {
            $this->avatar($avatarCell, $entry);
        }
        $this->bubble($table->addCell((int) (self::CONTENT_WIDTH * 0.72), $style), $entry, $spacing);
        $table->addCell((int) (self::CONTENT_WIDTH * 0.18));
    }

    private function bubble(Cell $cell, ExportEntry $entry, array $spacing): void
    {
        $tight = ['spaceAfter' => 20, 'indentation' => ['left' => 110, 'right' => 110]];
        if ($entry->runStart && ! $entry->isMine) {
            $cell->addText($entry->senderName, ['bold' => true, 'size' => 9, 'color' => ltrim($entry->color, '#')], $tight + $spacing);
        } else {
            $cell->addText('', ['size' => 2], $spacing);
        }
        if ($entry->forwardedFrom) {
            $cell->addText('Diteruskan dari ' . $entry->forwardedFrom, ['size' => 8, 'color' => self::GREY, 'italic' => true], $tight);
        }
        if ($entry->quote) {
            $cell->addText($entry->quote['name'] . ': ' . Str::limit($entry->quote['text'], 160), ['size' => 8, 'color' => '344458', 'italic' => true], $tight + ['shading' => ['fill' => 'F3F5F8']]);
        }
        $this->attachment($cell, $entry->attachment, $tight);
        $this->content($cell, $entry, $tight);
        $this->poll($cell, $entry->poll, $tight);

        if ($entry->deleted !== null) {
            $cell->addText('Dihapus oleh ' . $entry->deleted['by'] . ' · ' . $entry->deleted['at']->translatedFormat('j M Y, H.i'), ['size' => 7.5, 'bold' => true, 'color' => 'A1342A'], $tight);
        }
        if ($entry->versions !== []) {
            $cell->addText('Diedit ' . $entry->editedAt?->translatedFormat('j M Y, H.i') . '. Sebelumnya:', ['size' => 7.5, 'color' => self::GREY], $tight);
            foreach ($entry->versions as $index => $version) {
                $cell->addText(($index + 1) . '. ' . $version['content'], ['size' => 8, 'color' => '445468'], $tight);
            }
        }
        $cell->addText($entry->sentAt->format('H.i'), ['size' => 7, 'color' => self::GREY], ['alignment' => Jc::END, 'spaceAfter' => 60, 'indentation' => ['right' => 110]]);
    }

    private function content(Cell $cell, ExportEntry $entry, array $paragraph): void
    {
        if ($entry->content === '' || $entry->poll !== null) {
            return;
        }

        // Jawaban Normi (markdown) diubah ke HTML lalu ke Word; bila gagal dibaca, ditulis sebagai teks.
        if ($entry->isAi) {
            try {
                Html::addHtml($cell, Str::markdown($entry->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]));

                return;
            } catch (Throwable) {
            }
        }

        foreach (preg_split('/\R/u', $entry->content) as $line) {
            $cell->addText($line, null, $paragraph);
        }
    }

    private function poll(Cell $cell, ?array $poll, array $paragraph): void
    {
        if ($poll === null) {
            return;
        }

        $cell->addText('Polling: ' . $poll['question'], ['bold' => true], $paragraph);
        foreach ($poll['options'] as $option) {
            $cell->addText(sprintf('%d. %s · %d suara (%s%%)', $option['number'], $option['label'], $option['count'], number_format((float) $option['percent'], 1, ',', '.')), ['size' => 9], $paragraph);
        }
        $cell->addText($poll['total_votes'] > 0 ? $poll['total_votes'] . ' suara' : 'Belum ada yang memilih', ['size' => 8, 'color' => self::GREY], $paragraph);
    }

    private function attachment(Cell $cell, ?array $attachment, array $paragraph): void
    {
        if ($attachment === null) {
            return;
        }

        if ($attachment['kind'] === 'image' && $attachment['image'] !== null && $this->image($cell, $attachment['image'])) {
            return;
        }

        $label = ['image' => 'Gambar', 'video' => 'Video', 'voice' => 'Pesan suara', 'canvas' => 'Canvas', 'file' => 'Berkas'][$attachment['kind']] ?? 'Berkas';
        $cell->addText($label . ': ' . $attachment['name'], ['size' => 8.5, 'color' => '344458'], $paragraph);
    }

    private function avatar(Cell $cell, ExportEntry $entry): void
    {
        if ($entry->isAi) {
            $cell->addImage(public_path('icons/icon-192.png'), ['width' => 18, 'height' => 18]);

            return;
        }
        if ($entry->avatar !== null && preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $entry->avatar, $match)) {
            $this->image($cell, base64_decode($match[2]), ['width' => 18, 'height' => 18]);
        }
    }

    private function image(Cell $cell, string $binary, array $style = ['width' => 220]): bool
    {
        $path = tempnam(sys_get_temp_dir(), 'ncimg_');
        $this->tempFiles[] = $path;
        file_put_contents($path, $binary);
        try {
            $cell->addImage($path, $style);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
