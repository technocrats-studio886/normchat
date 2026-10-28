<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Riwayat chat sebagai PDF bergaya ruang chat (ramah cetak): pesan pengekspor di kanan, anggota
 * lain di kiri dengan foto dan nama. Kanan atas waktu ekspor, judul nama grup di tengah, logo
 * Normi samar di tengah setiap halaman (resources/images/export-watermark.png), kaki halaman berisi
 * nomor halaman dan nama pengekspor.
 */
class PdfChatExporter implements ChatExporter
{
    private const SILHOUETTE = '<path fill="#fff" d="M12 4.6a3.9 3.9 0 1 1 0 7.8 3.9 3.9 0 0 1 0-7.8Zm-6.9 15.9a7.1 7.1 0 0 1 13.8 0 16 16 0 0 1-13.8 0Z"/>';

    public function __construct(private readonly EmojiGlyphs $emoji = new EmojiGlyphs())
    {
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function render(ExportDocument $document): string
    {
        $pdf = Pdf::loadHTML($this->html($document))->setPaper('a4');
        $pdf->getDomPDF()->getOptions()->setChroot([public_path(), resource_path()]);
        $pdf->render();
        $this->decoratePages($pdf->getDomPDF()->getCanvas(), $document);

        return $pdf->output();
    }

    private function html(ExportDocument $document): string
    {
        $rows = '';
        $day = null;
        foreach ($document->entries as $entry) {
            $date = $entry->sentAt->format('Y-m-d');
            if ($date !== $day) {
                $rows .= '<div class="day"><span>' . e($entry->sentAt->translatedFormat('l, j F Y')) . '</span></div>';
                $day = $date;
            }
            $rows .= $this->row($entry);
        }

        // Tanda air (logo + "Normi Chat") elemen tetap pertama: tergambar di setiap halaman, di belakang isi.
        return '<html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<img class="watermark" src="' . e(resource_path('images/export-watermark.png')) . '">'
            . '<div class="exported">Diekspor ' . e($document->exportedAt->translatedFormat('j F Y, H.i')) . '</div>'
            . '<h1>' . $this->text($document->groupName) . '</h1>'
            . '<div class="sub">' . e($document->rangeLabel) . ' · ' . $document->count() . ' pesan</div>'
            . ($rows !== '' ? $rows : '<p class="empty">Tidak ada pesan pada rentang dan filter ini.</p>')
            . '</body></html>';
    }

    private function row(ExportEntry $entry): string
    {
        $side = $entry->isMine ? 'mine' : ($entry->isAi ? 'ai' : 'other');
        $bubble = '<div class="bubble">'
            . ($entry->runStart && ! $entry->isMine ? '<div class="name" style="color:' . $entry->color . '">' . $this->text($entry->senderName) . '</div>' : '')
            . ($entry->forwardedFrom ? '<div class="meta">Diteruskan dari <b>' . $this->text($entry->forwardedFrom) . '</b></div>' : '')
            . ($entry->quote ? '<div class="quote" style="border-color:' . $entry->color . '"><b>' . $this->text($entry->quote['name']) . '</b><br>' . $this->text(Str::limit($entry->quote['text'], 160)) . '</div>' : '')
            . $this->attachment($entry->attachment)
            . $this->body($entry)
            . $this->poll($entry->poll)
            . $this->marks($entry)
            . '<div class="time">' . $entry->sentAt->format('H.i') . '</div>'
            . '</div>';

        $spacing = $entry->runStart ? ' run-start' : '';
        if ($entry->isMine) {
            return '<table class="row mine' . $spacing . '"><tr><td class="gap"></td><td class="right">' . $bubble . '</td></tr></table>';
        }

        $avatar = $entry->runEnd ? $this->avatar($entry) : '';

        return '<table class="row ' . $side . $spacing . '"><tr><td class="av">' . $avatar . '</td><td>' . $bubble . '</td><td class="gap"></td></tr></table>';
    }

    private function body(ExportEntry $entry): string
    {
        if ($entry->content === '' || $entry->poll !== null) {
            return '';
        }

        // Jawaban Normi ditulis dengan markdown; pesan anggota apa adanya.
        $html = $entry->isAi
            ? Str::markdown($entry->content, ['html_input' => 'escape', 'allow_unsafe_links' => false])
            : '<p>' . nl2br(e($entry->content)) . '</p>';

        return '<div class="md">' . $this->emoji->inline($html) . '</div>';
    }

    /** Penanda pesan terhapus dan versi sebelum diedit. */
    private function marks(ExportEntry $entry): string
    {
        $html = '';
        if ($entry->deleted !== null) {
            $html .= '<div class="flag">Dihapus oleh ' . $this->text($entry->deleted['by']) . ' · ' . e($entry->deleted['at']->translatedFormat('j M Y, H.i')) . '</div>';
        }
        if ($entry->versions !== []) {
            $html .= '<div class="versions"><div class="meta">Diedit ' . e($entry->editedAt?->translatedFormat('j M Y, H.i')) . '. Sebelumnya:</div>';
            foreach ($entry->versions as $index => $version) {
                $html .= '<div class="version">' . ($index + 1) . '. ' . nl2br($this->text($version['content'])) . '</div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function poll(?array $poll): string
    {
        if ($poll === null) {
            return '';
        }

        $options = implode('', array_map(fn (array $option) => sprintf(
            '<div class="option">%d. %s <span>%d suara · %s%%</span></div>',
            $option['number'],
            $this->text($option['label']),
            $option['count'],
            number_format((float) $option['percent'], 1, ',', '.'),
        ), $poll['options']));

        return '<div class="poll"><div class="meta">Polling</div><b>' . $this->text($poll['question']) . '</b>' . $options
            . '<div class="meta">' . ($poll['total_votes'] > 0 ? $poll['total_votes'] . ' suara' : 'Belum ada yang memilih') . '</div></div>';
    }

    private function attachment(?array $attachment): string
    {
        if ($attachment === null) {
            return '';
        }

        if ($attachment['kind'] === 'image' && $attachment['image'] !== null && str_starts_with(strtolower($attachment['mime']), 'image/')) {
            return '<img class="photo" src="data:' . e($attachment['mime']) . ';base64,' . base64_encode($attachment['image']) . '">';
        }

        $label = ['image' => 'Gambar', 'video' => 'Video', 'voice' => 'Pesan suara', 'canvas' => 'Canvas', 'file' => 'Berkas'][$attachment['kind']] ?? 'Berkas';

        return '<div class="file">' . $label . ': ' . $this->text($attachment['name']) . '</div>';
    }

    private function avatar(ExportEntry $entry): string
    {
        if ($entry->isAi) {
            return '<img class="avatar" src="' . e(public_path('icons/icon-192.png')) . '">';
        }
        if ($entry->avatar !== null) {
            return '<img class="avatar" src="' . $entry->avatar . '">';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="' . $entry->color . '"/>' . self::SILHOUETTE . '</svg>';

        return '<img class="avatar" src="data:image/svg+xml;base64,' . base64_encode($svg) . '">';
    }

    private function text(string $value): string
    {
        return $this->emoji->inline(e($value));
    }

    /** Kaki setiap halaman: nomor halaman di kiri, nama pengekspor di kanan. */
    private function decoratePages($canvas, ExportDocument $document): void
    {
        $exporter = 'Diekspor oleh ' . $document->exporterName;

        $canvas->page_script(function (int $page, int $pages, $canvas, $fontMetrics) use ($exporter) {
            $width = $canvas->get_width();
            $height = $canvas->get_height();
            $font = $fontMetrics->getFont('DejaVu Sans');
            $grey = [0.36, 0.42, 0.5];

            $y = $height - 38;
            $canvas->line(42, $y - 6, $width - 42, $y - 6, [0.88, 0.9, 0.93], 0.5);
            $canvas->text(42, $y, "Halaman {$page} dari {$pages}", $font, 8, $grey);
            $canvas->text($width - 42 - $fontMetrics->getTextWidth($exporter, $font, 8), $y, $exporter, $font, 8, $grey);
        });
    }

    private function css(): string
    {
        return <<<'CSS'
            @page { margin: 46px 42px 62px; }
            .watermark { position: fixed; left: 50%; top: 50%; width: 240px; height: 282px; margin: -160px 0 0 -120px; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; line-height: 1.4; color: #142030; }
            .exported { text-align: right; font-size: 8px; color: #5d6c80; }
            h1 { text-align: center; font-size: 19px; margin: 8px 0 2px; }
            .sub { text-align: center; font-size: 8.5px; color: #5d6c80; margin-bottom: 10px; }
            .empty { text-align: center; color: #5d6c80; margin-top: 40px; }
            .day { text-align: center; margin: 12px 0 4px; }
            .day span { background: #eef1f5; color: #5d6c80; font-size: 8px; padding: 2px 9px; border-radius: 8px; }
            table.row { width: 100%; border-collapse: collapse; margin-top: 2px; page-break-inside: avoid; }
            table.row.run-start { margin-top: 7px; }
            td { padding: 0; vertical-align: bottom; }
            td.av { width: 24px; }
            td.gap { width: 18%; }
            td.right { text-align: right; }
            .avatar { width: 20px; height: 20px; border-radius: 10px; }
            .bubble { display: inline-block; text-align: left; padding: 5px 9px 4px; border-radius: 10px; max-width: 100%; }
            .mine .bubble { background: #e4e9f0; }
            .other .bubble { background: #ffffff; border: 0.6px solid #dfe4ea; }
            .ai .bubble { background: #fff3f1; border: 0.6px solid #fecbc3; }
            .name { font-weight: bold; font-size: 8.5px; margin-bottom: 1px; }
            .meta { font-size: 7.5px; color: #5d6c80; }
            .time { font-size: 7px; color: #5d6c80; text-align: right; margin-top: 2px; }
            .quote { border-left: 2px solid #1e2e43; background: #f3f5f8; padding: 2px 6px; margin: 2px 0 4px; font-size: 8px; color: #344458; }
            .md p { margin: 0 0 3px; }
            .md ul, .md ol { margin: 2px 0 3px 14px; padding: 0; }
            .md h1, .md h2, .md h3, .md h4 { font-size: 10px; margin: 4px 0 2px; }
            .md table { border-collapse: collapse; margin: 3px 0; }
            .md th, .md td { border: 0.5px solid #c9d2df; padding: 2px 4px; vertical-align: top; }
            .md code, .md pre { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; }
            .md pre { background: #f3f5f8; padding: 4px 6px; border-radius: 4px; white-space: pre-wrap; }
            .md blockquote { margin: 2px 0; padding-left: 6px; border-left: 2px solid #c9d2df; color: #445468; }
            .md a { color: #1d5aa0; }
            .emoji { height: 1.05em; width: auto; vertical-align: -0.15em; }
            .photo { max-width: 100%; max-height: 240px; border-radius: 6px; margin: 2px 0 3px; }
            .file { font-size: 8.5px; background: #f3f5f8; padding: 3px 6px; border-radius: 4px; margin: 2px 0; }
            .poll .option { margin-top: 2px; }
            .poll .option span { color: #5d6c80; font-size: 8px; }
            .flag { margin-top: 3px; font-size: 7.5px; color: #a1342a; font-weight: bold; }
            .versions { margin-top: 3px; padding-top: 3px; border-top: 0.5px dashed #c9d2df; }
            .version { font-size: 8px; color: #445468; margin-top: 1px; }
            CSS;
    }
}
