<?php

namespace App\Services\Ai\Files;

use RuntimeException;
use ZipArchive;

/**
 * Workbook XLSX minimal (SpreadsheetML) tanpa paket tambahan: beberapa sheet, baris pertama tebal sebagai
 * header, angka disimpan sebagai angka, rumus diawali "=" disimpan sebagai rumus, lebar kolom menyesuaikan isi.
 */
final class XlsxWriter
{
    private const MAX_SHEETS = 20;

    private const MAX_ROWS = 5000;

    private const MAX_COLUMNS = 100;

    /**
     * @param  list<array{name?: string, rows?: list<list<mixed>>}>  $sheets
     */
    public static function write(array $sheets): string
    {
        $sheets = array_values(array_filter(array_slice($sheets, 0, self::MAX_SHEETS), 'is_array'));
        if ($sheets === []) {
            $sheets = [['name' => 'Sheet1', 'rows' => []]];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'normi-xlsx-');
        if ($tmp === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara XLSX.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Tidak bisa menulis XLSX.');
            }

            $names = [];
            foreach ($sheets as $index => $sheet) {
                $names[] = self::sheetName((string) ($sheet['name'] ?? ''), $index, $names);
            }

            $overrides = '';
            $workbookSheets = '';
            $workbookRels = '';
            foreach ($names as $index => $name) {
                $n = $index + 1;
                $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $workbookSheets .= '<sheet name="' . self::xml($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
                $workbookRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
                $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', self::sheetXml((array) ($sheets[$index]['rows'] ?? [])));
            }
            $stylesId = count($names) + 1;

            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . $overrides
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '</Relationships>');
            $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:creator>Normi AI</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
                . '</cp:coreProperties>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets>' . $workbookSheets . '</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $workbookRels
                . '<Relationship Id="rId' . $stylesId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF5"/><bgColor indexed="64"/></patternFill></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>');
            $zip->close();

            $binary = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return $binary;
    }

    /** @param  list<mixed>  $rows */
    private static function sheetXml(array $rows): string
    {
        $rows = array_values(array_slice(array_filter($rows, 'is_array'), 0, self::MAX_ROWS));
        $widths = [];
        $body = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach (array_values(array_slice($row, 0, self::MAX_COLUMNS)) as $c => $value) {
                $ref = self::column($c) . ($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                $cells .= self::cell($ref, $value, $style);
                $widths[$c] = max($widths[$c] ?? 8, min(60, mb_strlen(is_scalar($value) ? (string) $value : '') + 2));
            }
            $body .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }

        $cols = '';
        foreach ($widths as $c => $width) {
            $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $width . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($rows !== [] ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '')
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $body . '</sheetData></worksheet>';
    }

    private static function cell(string $ref, mixed $value, string $style): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"' . $style . '/>';
        }
        if (is_bool($value)) {
            return '<c r="' . $ref . '"' . $style . ' t="b"><v>' . ($value ? 1 : 0) . '</v></c>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
        }

        $text = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        if (str_starts_with($text, '=') && mb_strlen($text) > 1) {
            return '<c r="' . $ref . '"' . $style . '><f>' . self::xml(substr($text, 1)) . '</f></c>';
        }
        // Angka yang ditulis sebagai teks ("1500000", "12.5") tetap angka agar bisa dijumlahkan.
        if (preg_match('/^-?\d{1,15}(\.\d+)?$/', $text) === 1 && ! preg_match('/^0\d/', $text)) {
            return '<c r="' . $ref . '"' . $style . '><v>' . $text . '</v></c>';
        }

        return '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . self::xml(mb_substr($text, 0, 32000)) . '</t></is></c>';
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + (($n - 1) % 26)) . $name;
        }

        return $name;
    }

    /** @param  list<string>  $taken */
    private static function sheetName(string $name, int $index, array $taken): string
    {
        $name = trim((string) preg_replace('/[\[\]\*\?\/\\\\:]/u', ' ', $name));
        $name = mb_substr($name !== '' ? $name : 'Sheet' . ($index + 1), 0, 31);
        $candidate = $name;
        for ($i = 2; in_array(mb_strtolower($candidate), array_map('mb_strtolower', $taken), true); $i++) {
            $candidate = mb_substr($name, 0, 28) . ' ' . $i;
        }

        return $candidate;
    }

    private static function xml(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
