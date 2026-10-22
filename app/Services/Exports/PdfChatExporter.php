<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfChatExporter implements ChatExporter
{
    private const BOX = 'margin-top:8px;padding:8px;border:1px solid #C9D2DF;border-radius:8px;background:#F3F5F8;';

    public function extension(): string
    {
        return 'pdf';
    }

    public function render(string $groupName, array $entries): string
    {
        $rows = array_map(fn (ExportEntry $entry) => sprintf(
            '<div style="border:1px solid #E4E9F0;border-radius:10px;padding:10px 12px;margin-bottom:10px;">'
                . '<div style="font-size:11px;color:#64748b;margin-bottom:6px;">[%s] <strong style="color:#121C29;">%s</strong></div>'
                . '%s%s%s%s'
            . '</div>',
            e($entry->timestamp),
            e($entry->sender),
            $entry->content !== '' ? '<div style="font-size:12px;color:#121C29;line-height:1.45;">' . nl2br(e($entry->content)) . '</div>' : '',
            $this->poll($entry->poll),
            $this->links($entry->links),
            $this->attachment($entry->attachment),
        ), $entries);

        $html = sprintf(
            '<html><body style="font-family: DejaVu Sans, sans-serif;"><h2>Normi Chat Export - %s</h2><p>Generated at: %s</p><div>%s</div></body></html>',
            e($groupName),
            e(now()->format('Y-m-d H:i:s')),
            implode('', $rows)
        );

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private function poll(?array $poll): string
    {
        if ($poll === null) {
            return '';
        }

        $options = implode('', array_map(fn (array $option) => sprintf(
            '<li style="margin-bottom:2px;">%d. %s - %d vote (%.1f%%)</li>',
            $option['number'],
            e($option['label']),
            $option['count'],
            $option['percent']
        ), $poll['options']));
        $summary = $poll['total_votes'] > 0 ? $poll['total_votes'] . ' total vote' : 'Belum ada vote';

        return '<div style="' . self::BOX . '">'
            . '<div style="font-size:12px;font-weight:700;margin-bottom:4px;">Polling: ' . e($poll['question']) . '</div>'
            . '<ul style="margin:0 0 4px 14px;padding:0;font-size:11px;">' . $options . '</ul>'
            . '<div style="font-size:10px;color:#475569;">' . e($summary) . '</div>'
            . '</div>';
    }

    private function links(array $links): string
    {
        if ($links === []) {
            return '';
        }

        $items = implode('', array_map(
            fn (string $url) => '<li style="margin-bottom:2px;"><a href="' . e($url) . '" style="color:#7186A3;text-decoration:underline;">' . e($url) . '</a></li>',
            $links
        ));

        return '<div style="margin-top:8px;"><div style="font-size:11px;font-weight:700;color:#344458;">Link</div>'
            . '<ul style="margin:2px 0 0 14px;padding:0;font-size:11px;color:#7186A3;">' . $items . '</ul></div>';
    }

    private function attachment(?array $attachment): string
    {
        if ($attachment === null) {
            return '';
        }

        $name = e($attachment['name']);
        $mime = e($attachment['mime']);
        $url = '<div style="margin-top:6px;font-size:10px;color:#7186A3;">URL: <a href="' . e($attachment['url']) . '" style="color:#7186A3;text-decoration:underline;">' . e($attachment['url']) . '</a></div>';

        return match ($attachment['kind']) {
            'image' => $attachment['image'] !== null && str_starts_with(strtolower($attachment['mime']), 'image/')
                ? '<div style="margin-top:8px;"><img src="data:' . $attachment['mime'] . ';base64,' . base64_encode($attachment['image'])
                    . '" style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid #C9D2DF;" />' . $url . '</div>'
                : '<div style="margin-top:8px;font-size:11px;color:#344458;">[Gambar] ' . $name . $url . '</div>',
            'video' => '<div style="' . self::BOX . 'font-size:11px;color:#121C29;"><strong>[Video]</strong> ' . $name . ' <span style="color:#64748b;">(' . $mime . ')</span>' . $url . '</div>',
            'voice' => '<div style="' . self::BOX . 'font-size:11px;color:#121C29;"><strong>[Voice note]</strong> ' . $name . $url . '</div>',
            default => '<div style="' . self::BOX . 'font-size:11px;color:#121C29;"><strong>[File]</strong> ' . $name . ' <span style="color:#64748b;">(' . $mime . ')</span>' . $url . '</div>',
        };
    }
}
