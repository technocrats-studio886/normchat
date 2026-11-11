<?php

namespace App\Services\Ai\Files;

use RuntimeException;
use ZipArchive;

/**
 * Presentasi PPTX 16:9 dari daftar slide (judul + poin), dibangun di atas template kosong
 * resources/templates/normi-blank.pptx (dibuat dengan python-pptx, lisensi MIT) tanpa paket tambahan.
 * Slide pertama memakai tata letak judul bila hanya berisi judul dan subjudul.
 */
final class PptxWriter
{
    private const MAX_SLIDES = 60;

    private const MAX_BULLETS = 14;

    private const SLIDE_WIDTH = 12192000;

    private const SLIDE_HEIGHT = 6858000;

    /**
     * @param  list<array{title?: string, subtitle?: string, bullets?: list<string|array{text?: string, level?: int}>, notes?: string}>  $slides
     */
    public static function write(string $title, array $slides): string
    {
        $slides = array_values(array_filter(array_slice($slides, 0, self::MAX_SLIDES), 'is_array'));
        if ($slides === []) {
            $slides = [['title' => $title !== '' ? $title : 'Presentasi', 'subtitle' => 'Dibuat oleh Normi AI']];
        }

        $template = resource_path('templates/normi-blank.pptx');
        $tmp = tempnam(sys_get_temp_dir(), 'normi-pptx-');
        if ($tmp === false || ! copy($template, $tmp)) {
            throw new RuntimeException('Template PPTX tidak tersedia.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Tidak bisa membuka template PPTX.');
            }

            $types = (string) $zip->getFromName('[Content_Types].xml');
            $presentation = (string) $zip->getFromName('ppt/presentation.xml');
            $rels = (string) $zip->getFromName('ppt/_rels/presentation.xml.rels');

            $overrides = '';
            $ids = '';
            $newRels = '';
            foreach ($slides as $index => $slide) {
                $n = $index + 1;
                $isTitleSlide = $index === 0 && empty($slide['bullets']);
                $zip->addFromString("ppt/slides/slide{$n}.xml", $isTitleSlide ? self::titleSlide($slide) : self::contentSlide($slide));
                $zip->addFromString("ppt/slides/_rels/slide{$n}.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout' . ($isTitleSlide ? 1 : 2) . '.xml"/>'
                    . '</Relationships>');
                $overrides .= '<Override PartName="/ppt/slides/slide' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
                $ids .= '<p:sldId id="' . (255 + $n) . '" r:id="rIdS' . $n . '"/>';
                $newRels .= '<Relationship Id="rIdS' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $n . '.xml"/>';
            }

            $zip->addFromString('[Content_Types].xml', str_replace('</Types>', $overrides . '</Types>', $types));
            $zip->addFromString('ppt/_rels/presentation.xml.rels', str_replace('</Relationships>', $newRels . '</Relationships>', $rels));
            $presentation = str_replace('</p:sldMasterIdLst>', '</p:sldMasterIdLst><p:sldIdLst>' . $ids . '</p:sldIdLst>', $presentation);
            $presentation = (string) preg_replace('/<p:sldSz[^>]*\/>/', '<p:sldSz cx="' . self::SLIDE_WIDTH . '" cy="' . self::SLIDE_HEIGHT . '"/>', $presentation);
            $zip->addFromString('ppt/presentation.xml', $presentation);
            $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:title>' . self::xml($title) . '</dc:title><dc:creator>Normi AI</dc:creator>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
                . '</cp:coreProperties>');
            $zip->deleteName('docProps/thumbnail.jpeg');
            $zip->addFromString('_rels/.rels', (string) preg_replace('/<Relationship [^>]*thumbnail[^>]*\/>/', '', (string) $zip->getFromName('_rels/.rels')));
            $zip->close();

            $binary = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return $binary;
    }

    private static function titleSlide(array $slide): string
    {
        $title = self::shape(2, 'Title 1', '<p:ph type="ctrTitle"/>', [914400, 2130425, 10363200, 1470025], self::paragraph((string) ($slide['title'] ?? ''), 0, 4400, true));
        $subtitle = trim((string) ($slide['subtitle'] ?? ''));
        $sub = $subtitle !== ''
            ? self::shape(3, 'Subtitle 2', '<p:ph type="subTitle" idx="1"/>', [1828800, 3886200, 8534400, 1752600], self::paragraph($subtitle, 0, 2400))
            : '';

        return self::slide($title . $sub);
    }

    private static function contentSlide(array $slide): string
    {
        $bullets = array_slice((array) ($slide['bullets'] ?? []), 0, self::MAX_BULLETS);
        $size = count($bullets) > 7 ? 1600 : (count($bullets) > 5 ? 2000 : 2400);
        $paragraphs = '';
        foreach ($bullets as $bullet) {
            $text = is_array($bullet) ? (string) ($bullet['text'] ?? '') : (string) $bullet;
            $level = is_array($bullet) ? max(0, min(4, (int) ($bullet['level'] ?? 0))) : 0;
            if (! is_array($bullet) && preg_match('/^(\s+)[-*•]?\s*/u', $text, $indent)) {
                $level = min(4, intdiv(strlen($indent[1]), 2));
            }
            // Penanda poin "- " / "* " / "• " dibuang; "**tebal**" di awal poin tetap utuh.
            $text = trim((string) preg_replace('/^\s*[-*•]\s+/u', '', $text));
            if ($text !== '') {
                $paragraphs .= self::paragraph($text, $level, $level > 0 ? $size - 200 : $size);
            }
        }

        $title = self::shape(2, 'Title 1', '<p:ph type="title"/>', [609600, 304800, 10972800, 1143000], self::paragraph((string) ($slide['title'] ?? ''), 0, 3600, true));
        $body = $paragraphs !== ''
            ? self::shape(3, 'Content Placeholder 2', '<p:ph idx="1"/>', [609600, 1600200, 10972800, 4754880], $paragraphs, '<a:normAutofit/>')
            : '';

        return self::slide($title . $body);
    }

    /** @param  array{0: int, 1: int, 2: int, 3: int}  $box */
    private static function shape(int $id, string $name, string $placeholder, array $box, string $paragraphs, string $fit = ''): string
    {
        [$x, $y, $cx, $cy] = $box;

        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="' . $name . '"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr>' . $placeholder . '</p:nvPr></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm></p:spPr>'
            . '<p:txBody><a:bodyPr>' . $fit . '</a:bodyPr><a:lstStyle/>' . $paragraphs . '</p:txBody></p:sp>';
    }

    private static function paragraph(string $text, int $level, int $size, bool $bold = false): string
    {
        // **tebal** di dalam poin ditulis sebagai run tebal.
        $runs = '';
        foreach (preg_split('/(\*\*[^*]+\*\*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $isBold = $bold || (str_starts_with($part, '**') && str_ends_with($part, '**'));
            $plain = $isBold && str_starts_with($part, '**') ? substr($part, 2, -2) : $part;
            $runs .= '<a:r><a:rPr lang="id-ID" sz="' . $size . '"' . ($isBold ? ' b="1"' : '') . ' dirty="0"/><a:t>' . self::xml($plain) . '</a:t></a:r>';
        }

        return '<a:p>' . ($level > 0 ? '<a:pPr lvl="' . $level . '"/>' : '') . $runs . '<a:endParaRPr lang="id-ID" sz="' . $size . '"/></a:p>';
    }

    private static function slide(string $shapes): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . $shapes
            . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    private static function xml(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
