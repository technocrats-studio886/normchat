<?php

namespace Tests\Feature;

use App\Services\AiDocumentGenerator;
use Tests\TestCase;
use ZipArchive;

/** Markdown tanpa baris kosong antarblok tetap dirender benar di Word (eval 23 Nov 2026). */
class DocxMarkdownBlocksTest extends TestCase
{
    public function test_headings_lists_and_tables_without_blank_lines_are_not_left_raw(): void
    {
        $markdown = "# Latihan Pecahan\n**Nama:** ______\n## Petunjuk\nKerjakan dengan teliti.\n## Soal\n1. Sederhanakan 12/18.\n   a. 2/3\n   b. 3/4\nTabel nilai:\n| Nama | Nilai |\n|---|---|\n| Ani | 80 |\n```\n## bukan judul\n```";
        $xml = $this->documentXml(app(AiDocumentGenerator::class)->renderDocxBinary('Latihan Pecahan', $markdown));
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('</w:p>', "\n", $xml))));

        $this->assertStringNotContainsString('## Petunjuk', $text);
        $this->assertStringNotContainsString('## Soal', $text);
        $this->assertStringNotContainsString('| Nama', $text);
        $this->assertStringContainsString('## bukan judul', $text, 'Isi code fence harus apa adanya.');
        $this->assertGreaterThanOrEqual(3, substr_count($xml, 'w:pStyle w:val="Heading'), 'Judul tidak menjadi Heading.');
        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertGreaterThanOrEqual(3, substr_count($xml, '<w:numPr>'), 'Butir daftar dan pilihan jawaban tidak menjadi daftar.');
    }

    private function documentXml(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive;
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmp);

        return $xml;
    }
}
