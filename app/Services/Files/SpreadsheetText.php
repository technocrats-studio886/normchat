<?php

namespace App\Services\Files;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;
use ZipArchive;

final class SpreadsheetText
{
    public function read(string $path, int $limit): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $size += $zip->statIndex($i)['size'];
        }
        // PhpSpreadsheet materializes cell objects; check the decompressed workbook before loading it.
        try {
            if ($size > 20 * 1024 * 1024) {
                return null;
            }
            $cells = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (! preg_match('~^xl/worksheets/[^/]+\.xml$~', $zip->getNameIndex($i))) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                $cells += preg_match_all('/<(?:\w+:)?c(?=[\s\/>])/', $xml);
                if ($cells > 25000) {
                    return null;
                }
            }
            unset($xml);
        } finally {
            $zip->close();
        }
        $reader = (new Xlsx)->setReadDataOnly(true)->setReadEmptyCells(false);
        $book = $reader->load($path);
        $book->getCalculationEngine()->cyclicFormulaCount = 0;
        $lines = [];
        $length = 0;
        try {
            foreach ($book->getWorksheetIterator() as $sheet) {
                $coords = $sheet->getCoordinates();
                $formulas = [];
                // Neutralize unsupported formulas before calculating, including dependencies.
                foreach ($coords as $address) {
                    $cell = $sheet->getCell($address);
                    if (! $cell->isFormula()) {
                        continue;
                    }
                    $formula = (string) $cell->getValue();
                    $cached = $cell->getOldCalculatedValue();
                    $supported = self::supported($formula);
                    $formulas[$address] = [$formula, $cached, $supported];
                    if (! $supported) {
                        if (is_numeric($cached)) {
                            $cell->setValueExplicit((float) $cached, DataType::TYPE_NUMERIC);
                        } else {
                            $cell->setValueExplicit('#N/A', DataType::TYPE_ERROR);
                        }
                    }
                }
                $lines[] = 'Sheet '.$sheet->getTitle();
                foreach ($coords as $address) {
                    $cell = $sheet->getCell($address);
                    $value = $cell->getValue();
                    if (isset($formulas[$address])) {
                        [$formula, $cached, $supported] = $formulas[$address];
                        $result = null;
                        if ($supported) {
                            try {
                                $result = $cell->getCalculatedValue();
                            } catch (Throwable) {
                            }
                        }
                        $value = $formula.' → '.(is_numeric($result) && is_finite((float) $result) ? $result : 'belum bisa dihitung');
                        if ($cached !== null && $cached !== '') {
                            $value .= ' (hasil tersimpan: '.$cached.')';
                        }
                    }
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $line = $address.': '.(is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string) $value);
                    $lines[] = $line;
                    $length += mb_strlen($line) + 1;
                    if ($length > $limit) {
                        break 2;
                    }
                }
            }

            return implode("\n", $lines);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private static function supported(string $formula): bool
    {
        $expr = strtoupper(substr($formula, 1));
        preg_match_all('/([A-Z_][A-Z0-9_.]*)\s*\(/', $expr, $functions);
        if (array_diff($functions[1], ['SUM']) !== []) {
            return false;
        }
        if (strlen($expr) > 500 || ! preg_match('/^(?:SUM(?=\s*\()|\$?[A-Z]{1,3}\$?[1-9]\d{0,6}(?!\s*\()|\d+(?:\.\d+)?|[\s()+*\/,%:^.-])+$/D', $expr)) {
            return false;
        }
        preg_match_all('/(\$?[A-Z]+\$?\d+)\s*:\s*(\$?[A-Z]+\$?\d+)/', $expr, $ranges, PREG_SET_ORDER);
        foreach ($ranges as [, $start, $end]) {
            [[$x,$y],[$xx,$yy]] = Coordinate::rangeBoundaries(str_replace('$', '', "$start:$end"));
            if ((abs($x - $xx) + 1) * (abs($y - $yy) + 1) > 20000) {
                return false;
            }
        }

        return true;
    }
}
