<?php

namespace App\Services\Ai\Files;

/**
 * Format file yang bisa dibuat Normi (laporan user 11 Nov 2026: "apapun yang diminta, buat file dalam format
 * apapun"). Format "rendered" dibuat server dari isi terstruktur (PDF, DOCX, XLSX, PPTX, ZIP); format lain adalah
 * teks yang ditulis model apa adanya (CSV, JSON, HTML, kode program, dll.).
 */
final class FileFormats
{
    /** @var array<string, array{mime: string, label: string}> */
    private const RENDERED = [
        'pdf' => ['mime' => 'application/pdf', 'label' => 'PDF'],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'label' => 'Word'],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'label' => 'Excel'],
        'pptx' => ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'label' => 'PowerPoint'],
        'zip' => ['mime' => 'application/zip', 'label' => 'ZIP'],
    ];

    /** @var array<string, string> ekstensi teks → MIME (selain itu text/plain) */
    private const TEXT = [
        'txt' => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv', 'tsv' => 'text/tab-separated-values',
        'json' => 'application/json', 'html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css', 'xml' => 'application/xml',
        'yaml' => 'application/yaml', 'yml' => 'application/yaml', 'toml' => 'application/toml', 'ini' => 'text/plain',
        'env' => 'text/plain', 'sql' => 'application/sql', 'svg' => 'image/svg+xml', 'ics' => 'text/calendar',
        'vcf' => 'text/vcard', 'srt' => 'application/x-subrip', 'vtt' => 'text/vtt', 'tex' => 'application/x-tex',
        'rtf' => 'application/rtf', 'log' => 'text/plain', 'mermaid' => 'text/plain', 'puml' => 'text/plain',
        'js' => 'text/javascript', 'mjs' => 'text/javascript', 'ts' => 'text/plain', 'tsx' => 'text/plain', 'jsx' => 'text/plain',
        'vue' => 'text/plain', 'svelte' => 'text/plain', 'py' => 'text/x-python', 'ipynb' => 'application/json',
        'php' => 'text/plain', 'java' => 'text/plain', 'kt' => 'text/plain', 'swift' => 'text/plain', 'dart' => 'text/plain',
        'go' => 'text/plain', 'rs' => 'text/plain', 'c' => 'text/plain', 'h' => 'text/plain', 'cpp' => 'text/plain',
        'hpp' => 'text/plain', 'cs' => 'text/plain', 'rb' => 'text/plain', 'lua' => 'text/plain', 'r' => 'text/plain',
        'm' => 'text/plain', 'scala' => 'text/plain', 'sh' => 'text/plain', 'bat' => 'text/plain', 'ps1' => 'text/plain',
        'dockerfile' => 'text/plain', 'gradle' => 'text/plain', 'sol' => 'text/plain', 'asm' => 'text/plain',
        'ino' => 'text/plain', 'pde' => 'text/plain', 'gitignore' => 'text/plain',
    ];

    /**
     * Kata yang dipakai orang untuk menyebut format (huruf kecil) → ekstensi. Urutan penting: yang lebih spesifik dulu.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'powerpoint' => 'pptx', 'power point' => 'pptx', 'pptx' => 'pptx', 'ppt' => 'pptx', 'slide presentasi' => 'pptx',
        'file presentasi' => 'pptx', 'bahan presentasi' => 'pptx', 'salindia' => 'pptx', 'slide' => 'pptx',
        'spreadsheet' => 'xlsx', 'excel' => 'xlsx', 'xlsx' => 'xlsx', 'xls' => 'xlsx', 'lembar kerja' => 'xlsx',
        'google sheet' => 'xlsx', 'microsoft word' => 'docx', 'ms word' => 'docx', 'word' => 'docx', 'docx' => 'docx', 'doc' => 'docx',
        'pdf' => 'pdf', 'csv' => 'csv', 'tsv' => 'tsv', 'json' => 'json', 'markdown' => 'md', 'html' => 'html',
        'xml' => 'xml', 'yaml' => 'yaml', 'yml' => 'yaml', 'sql' => 'sql', 'svg' => 'svg', 'ics' => 'ics',
        'file kalender' => 'ics', 'undangan kalender' => 'ics', 'vcard' => 'vcf', 'vcf' => 'vcf', 'kontak' => 'vcf',
        'subtitle' => 'srt', 'srt' => 'srt', 'latex' => 'tex', 'notebook' => 'ipynb', 'jupyter' => 'ipynb', 'rtf' => 'rtf',
        'zip' => 'zip', 'arsip' => 'zip', 'txt' => 'txt', 'teks' => 'txt', 'text' => 'txt',
        'python' => 'py', 'javascript' => 'js', 'typescript' => 'ts', 'php' => 'php', 'java' => 'java', 'kotlin' => 'kt',
        'swift' => 'swift', 'dart' => 'dart', 'golang' => 'go', 'rust' => 'rs', 'c++' => 'cpp', 'c#' => 'cs', 'ruby' => 'rb',
        'bash' => 'sh', 'shell script' => 'sh', 'powershell' => 'ps1', 'arduino' => 'ino', 'solidity' => 'sol', 'css' => 'css',
    ];

    public static function isRendered(string $ext): bool
    {
        return isset(self::RENDERED[$ext]);
    }

    public static function isKnown(string $ext): bool
    {
        return isset(self::RENDERED[$ext]) || isset(self::TEXT[$ext]);
    }

    public static function mime(string $ext): string
    {
        return self::RENDERED[$ext]['mime'] ?? self::TEXT[$ext] ?? 'text/plain';
    }

    /** Nama format untuk status dan keterangan, mis. "Excel", "PDF", "PY". */
    public static function label(string $ext): string
    {
        return self::RENDERED[$ext]['label'] ?? strtoupper($ext);
    }

    /** Ekstensi dari nama file ("laporan.XLSX" → "xlsx", "Dockerfile" → "dockerfile"); '' bila tidak dikenal. */
    public static function extensionOf(string $name): string
    {
        $base = strtolower(basename($name));
        $ext = str_contains($base, '.') ? substr($base, strrpos($base, '.') + 1) : $base;

        return self::isKnown($ext) ? $ext : '';
    }
}
