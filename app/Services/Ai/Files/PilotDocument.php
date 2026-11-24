<?php

namespace App\Services\Ai\Files;

use App\Models\Message;
use App\Services\AttachmentTextExtractor;
use App\Services\Files\StoredFiles;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PilotDocument
{
    public const READABLE_EXTENSIONS = ['pdf', 'docx', 'pptx', 'ppt', 'xlsx', 'txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'log', 'xml', 'yaml', 'yml'];

    public const MAX_BYTES = 8 * 1024 * 1024;

    public static function enabled(int $group): bool
    {
        return config('normchat.documents_all_groups') === true
            || in_array((string) $group, config('normchat.document_pilot_groups', []), true);
    }

    public static function supports(?Message $source): bool
    {
        return $source && $source->attachment_path && self::enabled($source->group_id)
            && in_array(self::extension($source), ['pdf', 'docx', 'pptx', 'ppt'], true);
    }

    public static function extension(Message $source): string
    {
        return strtolower(pathinfo((string) $source->attachment_original_name, PATHINFO_EXTENSION));
    }

    public static function bytes(Message $source): string
    {
        return app(StoredFiles::class)->get($source->attachment_disk, $source->attachment_path, self::MAX_BYTES)
            ?: throw new RuntimeException('Sumber belum terbaca. Normi dapat membaca dokumen hingga 8 MB.');
    }

    private static function convert(string $mode, string $binary, ?string $pages = null): string
    {
        $host = $mode === 'pdf' ? 'docling' : 'ppt-converter';
        try {
            $response = Http::connectTimeout(3)->timeout(100)->withBody($binary, 'application/octet-stream')
                ->withHeaders($pages ? ['X-Document-Pages' => $pages] : [])
                ->post("http://{$host}:8010/{$mode}");
        } catch (ConnectionException) {
            throw new RuntimeException('Pembaca lokal sedang sibuk atau belum tersedia. Coba ulang sebentar lagi.');
        }
        if ($response->status() === 409 && $mode === 'pdf' && (int) $response->json('pages') > 0) {
            throw new DocumentPageLimit((int) $response->json('pages'));
        }
        if (!$response->successful()) {
            throw new RuntimeException('Pembaca lokal belum bisa membaca dokumen lengkap. Kirim bagian yang diperlukan saja.');
        }
        return $response->body();
    }

    /**
     * Dokumen utuh dibaca dari dokumen kanonik yang tersimpan permanen (App\Services\Ai\Documents\DocumentStore);
     * rentang halaman PDF besar dibaca langsung dan disimpan sementara.
     */
    public static function read(Message $source, ?string $pages = null): array
    {
        if ($pages === null) {
            return app(\App\Services\Ai\Documents\DocumentStore::class)->ensure($source);
        }
        $key = 'document-pilot:v2:'.$source->group_id.':'.$source->id.':'.sha1($source->attachment_path).':'.$pages;

        return Cache::remember($key, now()->addHours(12), fn () => self::parse($source, $pages));
    }

    /** Baca dan konversi berkas sekarang juga (tanpa cache). */
    public static function parse(Message $source, ?string $pages = null): array
    {
        return (function () use ($source, $pages) {
            $bytes = self::bytes($source);
            $ext = self::extension($source);
            $tables = [];
            if ($ext === 'pdf') {
                $text = $pages ? null : app(AttachmentTextExtractor::class)->rendered(['binary' => $bytes, 'ext' => 'pdf', 'mime' => 'application/pdf'], 100000);
                if (mb_strlen(trim($text ?? '')) < 60 || preg_match('/\b(?:tabel|anggaran|jumlah|total|rincian|volume|inventaris|subtotal|qty)\b|\d[ \t]+\d|\t.+\t/iu', $text ?? '')
                    || (preg_match('~/Subtype\s*/Image\b~', $bytes) && mb_strlen($text ?? '') < 1000)) {
                    $parsed = json_decode(self::convert('pdf', $bytes, $pages), true);
                    $text = $parsed['text'] ?? '';
                    $tables = $parsed['tables'] ?? [];
                }
                $sections = [];
                foreach (preg_split('/\R/u', $text ?? '', -1, PREG_SPLIT_NO_EMPTY) as $i => $line) {
                    $sections[] = ['l'.($i + 1) => $line];
                }
            } else {
                $office = $ext === 'ppt' ? self::convert('ppt', $bytes) : $bytes;
                $sections = OfficeSections::process($office, $ext === 'ppt' ? 'pptx' : $ext)['sections'];
            }
            if (!$sections || strlen(self::json($sections)) > 350000) {
                throw new RuntimeException('Dokumen belum bisa dibaca lengkap. Kirim bagian yang diperlukan saja.');
            }
            return ['page_count' => $parsed['page_count'] ?? null, 'pages' => $pages, 'version' => substr(hash('sha256', $bytes), 0, 16), 'sections' => $sections, 'tables' => $tables,
                'converter' => $ext === 'pdf' ? (isset($parsed) ? 'docling' : 'pdf-text') : 'office'];
        })();
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** A broad question needs the whole source. Local edits use nearby sections and dependent totals. */
    public static function view(array $document, string $instruction, bool $focused): array
    {
        $sections = $document['sections'];
        $view = $sections;
        if ($focused && count($sections) > 5) {
            $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($instruction), -1, PREG_SPLIT_NO_EMPTY);
            $words = array_diff($words, explode(' ', 'normi file ubah ganti edit revisi pdf docx pptx menjadi jadi tolong dokumen berkas bagian yang dan untuk pada dari dengan ini itu sebelumnya'));
            $keep = [];
            foreach ($sections as $i => $row) {
                $line = mb_strtolower(implode(' ', $row));
                foreach ($words as $word) {
                    if (mb_strlen($word) >= 3 && str_contains($line, $word)) {
                        foreach ([$i-1, $i, $i+1] as $near) {
                            $keep[$near] = true;
                        }
                        break;
                    }
                }
            }
            if ($keep) {
                $financial = (bool) preg_match('/\b(?:ongkir|harga|biaya|pajak|nominal|total|rupiah|rp)\b/iu', $instruction);
                foreach ($sections as $i => $row) {
                    if ($i < 2 || ($financial && preg_match('/\d/u', implode(' ', $row))) || preg_match('/\b(?:total|jumlah|subtotal|harga|biaya|terbilang|pajak|saldo|pembayaran)\b/iu', implode(' ', $row))) {
                        $keep[$i] = true;
                    }
                }
                $view = array_values(array_intersect_key($sections, $keep));
            }
        }
        return ['version' => $document['version'], 'sections' => $view, 'partial' => count($view) < count($sections)];
    }

    public static function tableQuestion(array $doc): string
    {
        $rows = [];
        foreach ($doc['tables'] as $table) {
            $sample = array_map(fn ($row) => implode(' | ', $row), $table['rows']);
            $rows[] = 'Tabel '.$table['table']."\n".implode("\n", $sample);
        }
        return "Tolong periksa urutan kolom hasil pembacaan PDF. Balas “kolom benar” jika cocok. Untuk mengubah nama kolom, balas misalnya “Koreksi tabel 1: Barang | Jumlah”. Tuliskan nama semua kolom sesuai urutannya.\n\n".implode("\n\n", $rows);
    }
}
