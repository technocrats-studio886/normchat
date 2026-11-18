<?php

namespace App\Services\Ai\Files;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** IDs address original text runs. Each row/paragraph stays together in the model's view. */
final class OfficeSections
{
    public static function process(string $bytes, string $ext, array $changes = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'normchat-office-');
        try {
            file_put_contents($path, $bytes);
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Dokumen Office gagal dibuka.');
            }
            $parts = [];
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($changes && str_starts_with($name, '_xmlsignatures/')) {
                    throw new RuntimeException('Revisi akan membatalkan tanda tangan digital file sumber.');
                }
                if ($ext === 'docx' ? preg_match('~^word/(?:document|header\d+|footer\d+|footnotes|endnotes)\.xml$~D', $name) : preg_match('~^ppt/slides/slide\d+\.xml$~D', $name)) {
                    $size += $zip->statIndex($i)['size'];
                    $parts[] = $name;
                }
            }
            if ($size > 12 * 1024 * 1024 || count($parts) > 300) {
                throw new RuntimeException('Bagilah dokumen menjadi beberapa bagian untuk pilot ini.');
            }
            natsort($parts);
            if ($ext === 'pptx') {
                $parts = self::slideOrder($zip, $parts);
            }
            $slideNo = 0;
            $sections = $applied = [];
            $counter = 0;
            foreach ($parts as $part) {
                $doc = new DOMDocument;
                if (!@$doc->loadXML($zip->getFromName($part), LIBXML_NONET) || $doc->doctype) {
                    throw new RuntimeException('Struktur dokumen tidak dikenali.');
                }
                $prefix = $ext === 'pptx' ? 'slide'.(++$slideNo).'.t'
                    : ($part === 'word/document.xml' ? 't' : pathinfo(basename($part), PATHINFO_FILENAME).'.t');
                $xp = new DOMXPath($doc, false);
                $xp->registerNamespace('o', $ext === 'docx'
                    ? 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                    : 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $dirty = false;
                $lastGroup = null;
                foreach ($xp->query('//o:t') as $node) {
                    if (trim($node->textContent) === '') {
                        continue;
                    }
                    $group = $xp->query('ancestor::o:tr[1]', $node)->item(0) ?? $xp->query('ancestor::o:p[1]', $node)->item(0);
                    $groupPath = $group?->getNodePath();
                    if ($lastGroup === null || $groupPath !== $lastGroup) {
                        $sections[] = [];
                    }
                    $lastGroup = $groupPath;
                    $id = $prefix.(++$counter);
                    $sections[array_key_last($sections)][$id] = $node->textContent;
                    if (isset($changes[$id])) {
                        if ($changes[$id]['old'] !== $node->textContent) {
                            throw new RuntimeException('Teks sumber telah berubah.');
                        }
                        $node->textContent = $changes[$id]['new'];
                        $applied[$id] = true;
                        $dirty = true;
                    }
                }
                if ($dirty && !$zip->addFromString($part, $doc->saveXML())) {
                    throw new RuntimeException('Perubahan gagal disimpan.');
                }
            }
            if (!$zip->close() || count($applied) !== count($changes)) {
                throw new RuntimeException('Bagian yang direvisi tidak cocok dengan sumber.');
            }

            return ['sections' => $sections, 'binary' => $changes ? file_get_contents($path) : null];
        } finally {
            @unlink($path);
        }
    }

    private static function slideOrder(ZipArchive $zip, array $slides): array
    {
        $targets = [];
        $order = [];
        foreach (['ppt/_rels/presentation.xml.rels', 'ppt/presentation.xml'] as $part) {
            $stat = $zip->statName($part);
            $doc = new DOMDocument;
            if (!$stat || $stat['size'] > 1024 * 1024 || !@$doc->loadXML($zip->getFromName($part), LIBXML_NONET) || $doc->doctype) {
                throw new RuntimeException('Urutan slide PowerPoint belum bisa dibaca.');
            }
            if (str_ends_with($part, '.rels')) {
                foreach ($doc->getElementsByTagName('Relationship') as $relation) {
                    $target = $relation->getAttribute('Target');
                    $targets[$relation->getAttribute('Id')] = str_starts_with($target, '/') ? substr($target, 1) : 'ppt/'.$target;
                }
            } else {
                foreach ($doc->getElementsByTagName('sldId') as $slide) {
                    $id = $slide->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                    $target = $targets[$id] ?? null;
                    if (!in_array($target, $slides, true) || in_array($target, $order, true)) {
                        throw new RuntimeException('Referensi slide PowerPoint belum bisa dibaca.');
                    }
                    $order[] = $target;
                }
            }
        }
        return $order;
    }
}
