<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Format lampiran chat yang boleh diunggah. Sengaja luas: media, dokumen kantor, data,
 * kode sumber, ebook, desain, dan arsip. Yang ditolak hanya dua golongan:
 * - markup yang bisa dijalankan browser sebagai halaman/skrip (HTML, SVG, …) — celah XSS;
 * - berkas eksekusi/installer (EXE, APK, BAT, …) — bisa membahayakan perangkat anggota lain.
 */
final class AttachmentRules
{
    public const MAX_KILOBYTES = 15360;

    public const ALLOWED = [
        'gambar' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'bmp', 'tif', 'tiff', 'avif'],
        'audio' => ['mp3', 'wav', 'ogg', 'oga', 'opus', 'aac', 'm4a', 'flac', 'amr', 'weba', 'webm'],
        'video' => ['mp4', 'mov', 'm4v', '3gp', 'mkv', 'avi', 'wmv', 'mpeg', 'mpg'],
        'dokumen' => ['pdf', 'txt', 'md', 'rtf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'csv', 'tsv', 'ppt', 'pptx', 'odp', 'epub'],
        'data' => ['json', 'xml', 'yaml', 'yml', 'sql', 'log', 'ipynb'],
        'kode' => ['py', 'java', 'c', 'h', 'cpp', 'hpp', 'cs', 'go', 'rs', 'rb', 'php', 'js', 'ts', 'jsx', 'tsx', 'kt', 'swift', 'dart', 'r', 'scala', 'lua', 'css', 'scss'],
        'desain' => ['psd', 'ai', 'fig', 'sketch', 'dwg', 'dxf', 'stl'],
        'arsip' => ['zip', 'rar', '7z', 'tar', 'gz', 'tgz'],
    ];

    /** Isi berkas yang tetap ditolak walau ekstensinya diganti agar lolos. */
    private const BLOCKED_MIME_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-executable',
        'application/x-sharedlib',
        'application/x-mach-binary',
        'application/vnd.android.package-archive',
        'application/x-msi',
    ];

    /** @return list<string> */
    public static function extensions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ALLOWED))));
    }

    /** Aturan validasi Laravel untuk field lampiran. */
    public static function validation(): array
    {
        return ['nullable', 'file', 'max:' . self::MAX_KILOBYTES, 'extensions:' . implode(',', self::extensions())];
    }

    public static function hasBlockedContent(UploadedFile $file): bool
    {
        return self::isBlockedMime((string) $file->getMimeType());
    }

    public static function isBlockedMime(string $mime): bool
    {
        return in_array(strtolower(trim(explode(';', $mime)[0])), self::BLOCKED_MIME_TYPES, true);
    }

    /** Nilai atribut `accept` untuk pemilih berkas di browser. */
    public static function acceptAttribute(): string
    {
        return 'image/*,audio/*,video/*,' . implode(',', array_map(fn ($ext) => '.' . $ext, self::extensions()));
    }

    public static function rejectionMessage(): string
    {
        return 'Jenis file ini tidak bisa dikirim. Kirim gambar, video, audio, dokumen, atau file ZIP.';
    }
}
