<?php

namespace App\Services\Ai\Files;

use DOMDocument;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use ZipArchive;

/** Add one local picture to a finished document, keeping the original text, cells and pages. */
final class DocumentStamp
{
    private const OFFICE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    public static function apply(array $file, array $image): array
    {
        $file['binary'] = match ($file['ext']) {
            'pdf' => self::pdf($file['binary'], $image),
            'docx', 'xlsx', 'pptx' => self::office($file['binary'], $file['ext'], $image),
            default => throw new RuntimeException('Format ini tidak mendukung penyisipan gambar. Pilih PDF, Word, Excel, atau PowerPoint.'),
        };

        return $file;
    }

    private static function pdf(string $binary, array $image): string
    {
        $pdf = new Fpdi;
        $count = $pdf->setSourceFile(StreamReader::createByString($binary));
        $pdf->SetAutoPageBreak(false);
        $path = tempnam(sys_get_temp_dir(), 'nc-logo-');
        try {
            file_put_contents($path, $image['binary']);
            for ($page = 1; $page <= $count; $page++) {
                $tpl = $pdf->importPage($page, importExternalLinks: true);
                $size = $pdf->getTemplateSize($tpl);
                [$w, $h] = [$size['width'], $size['height']];
                $pdf->AddPage($size['orientation'], [$w, $h]);
                if ($page !== 1) {
                    $pdf->useTemplate($tpl);

                    continue;
                }
                $scale = min(0.2646, $w * 0.32 / $image['width'], $h * 0.12 / $image['height']);
                $iw = $image['width'] * $scale;
                $ih = $image['height'] * $scale;
                $band = $ih + 6;
                $contentWidth = $w * ($h - $band) / $h;
                $top = $image['position'] === 'top';
                $pdf->useTemplate($tpl, ($w - $contentWidth) / 2, $top ? $band : 0, $contentWidth);
                $x = match ($image['align']) {
                    'right' => $w - $iw - 8, 'center' => ($w - $iw) / 2, default => 8
                };
                $pdf->Image($path, $x, $top ? 4 : $h - $ih - 4, $iw, $ih, 'PNG');
            }

            return $pdf->Output('S');
        } finally {
            @unlink($path);
        }
    }

    private static function xml(ZipArchive $zip, string $path, ?string $default = null): DOMDocument
    {
        $raw = $zip->getFromName($path);
        $doc = new DOMDocument;
        if (! $doc->loadXML($raw === false ? ($default ?? '<missing/>') : $raw, LIBXML_NONET) || $doc->doctype) {
            throw new RuntimeException('Struktur dokumen tidak dapat dibaca.');
        }

        return $doc;
    }

    private static function office(string $binary, string $ext, array $image): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nc-office-');
        try {
            file_put_contents($tmp, $binary);
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Dokumen Office tidak dapat dibuka.');
            }
            $id = 'nc'.bin2hex(random_bytes(6));
            $shape = random_int(10000, 2000000000);
            [$base, $part] = match ($ext) {
                'docx' => ['word', 'document.xml'],
                'xlsx' => ['xl', 'worksheets/sheet1.xml'],
                'pptx' => ['ppt', 'slides/slide1.xml'],
            };
            $path = "$base/$part";
            $doc = self::xml($zip, $path);
            $types = self::xml($zip, '[Content_Types].xml');
            $media = "$base/media/$id.png";
            $zip->addFromString($media, $image['binary']);
            self::type($types, $media, 'image/png');
            $cx = $image['width'] * 9525;
            $cy = $image['height'] * 9525;
            $top = $image['position'] === 'top';
            $align = $image['align'];
            $picture = function (string $p, string $ns, int $x = 0, int $y = 0) use ($shape, $id, $cx, $cy) {
                $extra = $p === 'p' ? '<p:nvPr/>' : '';

                return "<$p:pic xmlns:$p=\"$ns\" xmlns:a=\"".self::DRAWING.'" xmlns:r="'.self::OFFICE."\"><$p:nvPicPr><$p:cNvPr id=\"$shape\" name=\"Gambar\"/><$p:cNvPicPr/>$extra</$p:nvPicPr><$p:blipFill><a:blip r:embed=\"$id\"/><a:stretch><a:fillRect/></a:stretch></$p:blipFill><$p:spPr><a:xfrm><a:off x=\"$x\" y=\"$y\"/><a:ext cx=\"$cx\" cy=\"$cy\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></$p:spPr></$p:pic>";
            };
            if ($ext === 'docx') {
                $body = $doc->getElementsByTagNameNS('*', 'body')->item(0);
                $ns = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
                $graphic = $picture('pic', $ns);
                $drawing = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="'.self::DRAWING.'"><w:pPr><w:jc w:val="'.$align.'"/></w:pPr><w:r><w:drawing><wp:inline><wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="'.$shape.'" name="Gambar"/><a:graphic><a:graphicData uri="'.$ns.'">'.$graphic.'</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
                $node = $doc->createDocumentFragment();
                $node->appendXML($drawing);
                $body->insertBefore($node, $top ? $body->firstChild : $body->getElementsByTagNameNS('*', 'sectPr')->item(0));
                self::relation($zip, $path, $id, 'image', "media/$id.png");
            } elseif ($ext === 'pptx') {
                $tree = $doc->getElementsByTagNameNS('*', 'spTree')->item(0);
                $slideSize = self::xml($zip, 'ppt/presentation.xml')->getElementsByTagNameNS('*', 'sldSz')->item(0);
                $w = (int) $slideSize->getAttribute('cx');
                $h = (int) $slideSize->getAttribute('cy');
                // Reserve a band for the image on the original slide, without covering title or bullets.
                $band = $cy + 180000;
                $ratio = ($h - $band) / $h;
                foreach ($tree->getElementsByTagNameNS(self::DRAWING, 'xfrm') as $transform) {
                    $offset = $transform->getElementsByTagNameNS(self::DRAWING, 'off')->item(0);
                    $extent = $transform->getElementsByTagNameNS(self::DRAWING, 'ext')->item(0);
                    if (! $offset || ! $extent || ! (int) $extent->getAttribute('cy')) {
                        continue;
                    }
                    $offset->setAttribute('y', (string) round(((int) $offset->getAttribute('y')) * $ratio + ($top ? $band : 0)));
                    $extent->setAttribute('cy', (string) round(((int) $extent->getAttribute('cy')) * $ratio));
                }
                $x = match ($align) {
                    'right' => $w - $cx - 90000, 'center' => intdiv($w - $cx, 2), default => 90000
                };
                $node = $doc->createDocumentFragment();
                $node->appendXML($picture('p', $tree->namespaceURI, $x, $top ? 90000 : $h - $cy - 90000));
                $tree->appendChild($node);
                self::relation($zip, $path, $id, 'image', "../media/$id.png");
            } else {
                $ns = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
                $sheet = $doc->documentElement;
                $drawing = $sheet->getElementsByTagNameNS('*', 'drawing')->item(0);
                if ($drawing) {
                    $rid = $drawing->getAttributeNS(self::OFFICE, 'id');
                    $rels = self::xml($zip, "$base/worksheets/_rels/sheet1.xml.rels");
                    $drawingPath = null;
                    foreach ($rels->getElementsByTagName('Relationship') as $rel) {
                        if ($rel->getAttribute('Id') === $rid && preg_match('~^\.\./drawings/([\w-]+\.xml)$~D', $rel->getAttribute('Target'), $m)) {
                            $drawingPath = "$base/drawings/".$m[1];
                        }
                    }
                    if (! $drawingPath) {
                        throw new RuntimeException('Susunan gambar spreadsheet tidak dikenali.');
                    }
                } else {
                    $drawingPath = "$base/drawings/$id.xml";
                    $drawing = $sheet->appendChild($doc->createElementNS($sheet->namespaceURI, 'drawing'));
                    $drawing->setAttributeNS(self::OFFICE, 'r:id', $id);
                    self::relation($zip, $path, $id, 'drawing', "../drawings/$id.xml");
                    self::type($types, $drawingPath, 'application/vnd.openxmlformats-officedocument.drawing+xml');
                }
                $draw = self::xml($zip, $drawingPath, '<xdr:wsDr xmlns:xdr="'.$ns.'"/>');
                $rows = $doc->getElementsByTagNameNS('*', 'row');
                $row = $top ? 0 : (int) ($rows->length ? $rows->item($rows->length - 1)->getAttribute('r') : 0);
                if ($top && $rows->length) {
                    $first = $rows->item(0);
                    $first->setAttribute('ht', (string) max((float) $first->getAttribute('ht'), $image['height'] * .75 + 20));
                    $first->setAttribute('customHeight', '1');
                }
                $col = match ($align) {
                    'right' => 4, 'center' => 2, default => 0
                };
                $node = $draw->createDocumentFragment();
                $node->appendXML('<xdr:oneCellAnchor xmlns:xdr="'.$ns.'"><xdr:from><xdr:col>'.$col.'</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'.$row.'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:ext cx="'.$cx.'" cy="'.$cy.'"/>'.$picture('xdr', $ns).'<xdr:clientData/></xdr:oneCellAnchor>');
                $draw->documentElement->appendChild($node);
                $zip->addFromString($drawingPath, $draw->saveXML());
                self::relation($zip, $drawingPath, $id, 'image', "../media/$id.png");
            }
            $zip->addFromString($path, $doc->saveXML());
            $zip->addFromString('[Content_Types].xml', $types->saveXML());
            if (! $zip->close()) {
                throw new RuntimeException('Gagal menyimpan dokumen.');
            }

            return file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private static function type(DOMDocument $types, string $path, string $mime): void
    {
        $node = $types->createElementNS($types->documentElement->namespaceURI, 'Override');
        $node->setAttribute('PartName', '/'.$path);
        $node->setAttribute('ContentType', $mime);
        $types->documentElement->appendChild($node);
    }

    private static function relation(ZipArchive $zip, string $part, string $id, string $type, string $target): void
    {
        $path = dirname($part).'/_rels/'.basename($part).'.rels';
        $doc = self::xml($zip, $path, '<Relationships xmlns="'.self::PACKAGE.'"/>');
        $node = $doc->createElementNS(self::PACKAGE, 'Relationship');
        $node->setAttribute('Id', $id);
        $node->setAttribute('Type', self::OFFICE.'/'.$type);
        $node->setAttribute('Target', $target);
        $doc->documentElement->appendChild($node);
        $zip->addFromString($path, $doc->saveXML());
    }
}
