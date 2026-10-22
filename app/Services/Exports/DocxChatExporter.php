<?php

namespace App\Services\Exports;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Throwable;

class DocxChatExporter implements ChatExporter
{
    private const LINK_STYLE = ['color' => '1E2E43', 'underline' => 'single'];

    /** @var list<string> berkas gambar sementara, dihapus setelah dokumen ditulis */
    private array $tempFiles = [];

    public function extension(): string
    {
        return 'docx';
    }

    public function render(string $groupName, array $entries): string
    {
        $word = new PhpWord();
        $section = $word->addSection();
        $section->addTitle('Normi Chat Export - ' . $groupName, 1);
        $section->addText('Generated at: ' . now()->format('Y-m-d H:i:s'));
        $section->addTextBreak(1);

        foreach ($entries as $entry) {
            $section->addText(sprintf('[%s] %s', $entry->timestamp, $entry->sender), ['bold' => true]);
            if ($entry->content !== '') {
                $section->addText($entry->content);
            }

            $this->poll($section, $entry->poll);
            $this->links($section, $entry->links);
            $this->attachment($section, $entry->attachment);
            $section->addTextBreak(1);
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

    private function poll(Section $section, ?array $poll): void
    {
        if ($poll === null) {
            return;
        }

        $section->addText('Polling: ' . $poll['question'], ['bold' => true]);
        foreach ($poll['options'] as $option) {
            $section->addText(sprintf('%d. %s - %d vote (%.1f%%)', $option['number'], $option['label'], $option['count'], $option['percent']));
        }
        if ($poll['total_votes'] <= 0) {
            $section->addText('Belum ada vote');
        }
    }

    private function links(Section $section, array $links): void
    {
        if ($links === []) {
            return;
        }

        $section->addText('Link:', ['bold' => true]);
        foreach ($links as $url) {
            $this->link($section, $url);
        }
    }

    private function attachment(Section $section, ?array $attachment): void
    {
        if ($attachment === null) {
            return;
        }

        if ($attachment['kind'] === 'image' && $attachment['image'] !== null && $this->image($section, $attachment['image'])) {
            $section->addText('URL lampiran:');
            $this->link($section, $attachment['url']);

            return;
        }

        $section->addText(match ($attachment['kind']) {
            'image' => '[Gambar] ' . $attachment['name'],
            'video' => '[Video] ' . $attachment['name'] . ' (preview tidak tersedia di DOCX)',
            'voice' => '[Voice note] ' . $attachment['name'],
            default => '[File] ' . $attachment['name'] . ' (' . $attachment['mime'] . ')',
        });
        $section->addText('URL lampiran: ' . $attachment['url']);
    }

    private function image(Section $section, string $binary): bool
    {
        $path = tempnam(sys_get_temp_dir(), 'ncimg_');
        file_put_contents($path, $binary);
        $this->tempFiles[] = $path;

        try {
            $section->addImage($path, ['width' => 320, 'keepRatio' => true]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function link(Section $section, string $url): void
    {
        try {
            $section->addLink($url, $url, self::LINK_STYLE);
        } catch (Throwable) {
            $section->addText($url);
        }
    }
}
