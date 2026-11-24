<?php

namespace App\Services\Ai\Documents;

use App\Models\Message;
use App\Models\MessageDocument;
use App\Services\Ai\Files\DocumentPageLimit;
use App\Services\Ai\Files\PilotDocument;
use App\Services\Ai\Tokens;
use App\Services\AttachmentTextExtractor;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Dokumen kanonik: setiap lampiran dokumen dikonversi sekali lalu disimpan permanen (docs/rancangan-worker-dan-
 * file-nov.md bagian D). Bentuknya sama dengan PilotDocument::parse (bagian ber-id, tabel, versi) ditambah teks,
 * daftar judul, dan perkiraan token, supaya revisi asli Word/PowerPoint tetap memakai id yang sama.
 *
 * JSON ini untuk mesin. Yang dikirim ke model adalah proyeksi ringkasnya (DocumentProjection).
 */
class DocumentStore
{
    private const DISK = 'normchat_attachments';

    /** Format yang dibaca PilotDocument (struktur bagian & tabel); format lain dibaca sebagai teks. */
    private const STRUCTURED = ['pdf', 'docx', 'pptx', 'ppt'];

    private const MAX_TEXT_CHARS = 400_000;

    public function __construct(private readonly AttachmentTextExtractor $extractor) {}

    /** Lampiran ini dokumen yang bisa dikonversi (bukan gambar, audio, atau video). */
    public static function supports(?Message $message): bool
    {
        if (! $message?->attachment_path || ! $message->attachment_disk || $message->hasImageAttachment() || $message->hasAudioAttachment()
            || in_array($message->message_type, ['image', 'voice', 'video'], true)) {
            return false;
        }

        return in_array(PilotDocument::extension($message), [...PilotDocument::READABLE_EXTENSIONS, 'html', 'htm'], true);
    }

    /** Dokumen kanonik yang sudah tersimpan, atau null bila belum dikonversi/gagal. */
    public function get(Message $message): ?array
    {
        $record = MessageDocument::where('message_id', $message->id)->first();
        if ($record?->status !== MessageDocument::READY || ! $record->storage_path) {
            return null;
        }

        return Cache::remember('doc-canonical:'.$record->id.':'.$record->version, now()->addHour(), function () use ($record) {
            $json = app(StoredFiles::class)->get((string) $record->storage_disk, (string) $record->storage_path);

            return is_string($json) ? json_decode($json, true) : null;
        }) ?: null;
    }

    /**
     * Dokumen kanonik, dikonversi sekarang bila job belum sempat (Normi dipanggil tepat setelah dokumen terkirim).
     *
     * @throws RuntimeException pesan aman untuk user
     * @throws DocumentPageLimit PDF lebih dari 35 halaman tanpa lapisan teks: user memilih rentang halaman
     */
    public function ensure(Message $message): array
    {
        return $this->get($message) ?? $this->convertAndStore($message, allowFallback: true);
    }

    /**
     * $allowFallback: bila Docling tidak tersedia, pakai lapisan teks PDF supaya Normi tetap bisa menjawab. Job latar
     * belakang memakai false agar dicoba ulang dan hasil akhirnya tetap lengkap dengan tabel.
     */
    public function convertAndStore(Message $message, bool $allowFallback = false): array
    {
        $record = MessageDocument::firstOrCreate(['message_id' => $message->id], ['group_id' => $message->group_id]);
        if (! self::supports($message)) {
            $record->update(['status' => MessageDocument::UNSUPPORTED]);
            throw new RuntimeException('Format lampiran ini belum bisa dibaca Normi.');
        }

        try {
            $document = $this->convert($message, $allowFallback);
        } catch (DocumentPageLimit $limit) {
            $record->update(['status' => MessageDocument::FAILED, 'page_count' => $limit->pages, 'error' => 'page_limit', 'attempts' => $record->attempts + 1]);
            throw $limit;
        } catch (Throwable $error) {
            $record->update(['status' => MessageDocument::FAILED, 'error' => mb_substr($error->getMessage(), 0, 250), 'attempts' => $record->attempts + 1]);
            throw $error instanceof RuntimeException ? $error : new RuntimeException('Dokumen belum bisa dibaca. Coba kirim ulang.', 0, $error);
        }

        $path = sprintf('group-%d/docs/%d-%s.json', $message->group_id, $message->id, $document['version']);
        if (! Storage::disk(self::DISK)->put($path, json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
            throw new RuntimeException('Dokumen belum bisa disimpan. Coba lagi.');
        }
        $old = $record->storage_path;
        $record->update([
            'status' => MessageDocument::READY, 'converter' => $document['converter'], 'ext' => $document['ext'], 'version' => $document['version'],
            'page_count' => $document['page_count'], 'chars' => mb_strlen($document['text']), 'tokens' => $document['tokens'],
            'outline' => array_slice($document['outline'], 0, 60), 'storage_disk' => self::DISK, 'storage_path' => $path,
            'error' => null, 'converted_at' => now(),
        ]);
        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }

        return $document;
    }

    /** Hapus dokumen kanonik (pesan dihapus untuk semua). */
    public function forget(Message $message): void
    {
        $record = MessageDocument::where('message_id', $message->id)->first();
        if ($record?->storage_path) {
            Storage::disk((string) $record->storage_disk)->delete($record->storage_path);
        }
        $record?->delete();
    }

    private function convert(Message $message, bool $allowFallback): array
    {
        $ext = PilotDocument::extension($message);
        if (in_array($ext, self::STRUCTURED, true)) {
            try {
                $document = PilotDocument::parse($message);
            } catch (DocumentPageLimit $limit) {
                // Lebih dari 35 halaman: Docling membaca per rentang, tapi lapisan teks seluruh halaman tetap berguna.
                $document = $this->pdfTextLayer($message) ?? throw $limit;
                $document['page_count'] = $limit->pages;
            } catch (RuntimeException $error) {
                if (! $allowFallback || $ext !== 'pdf' || ! str_contains($error->getMessage(), 'Pembaca lokal')) {
                    throw $error;
                }
                $document = $this->pdfTextLayer($message) ?? throw $error;
            }
        } else {
            $text = $this->extractor->extract($message, self::MAX_TEXT_CHARS);
            if ($text === null || trim($text) === '') {
                throw new RuntimeException('Isi dokumen kosong atau belum bisa dibaca.');
            }
            $bytes = (string) app(StoredFiles::class)->get($message->attachment_disk, $message->attachment_path, PilotDocument::MAX_BYTES);
            $document = ['page_count' => null, 'pages' => null, 'version' => substr(hash('sha256', $bytes !== '' ? $bytes : $text), 0, 16),
                'sections' => self::lineSections($text), 'tables' => [], 'converter' => 'text'];
        }

        $text = implode("\n", array_map(fn (array $row) => implode(' | ', $row), $document['sections']));

        return [...$document, 'format' => 1, 'ext' => $ext, 'text' => $text, 'outline' => self::outline($document['sections']), 'tokens' => Tokens::text($text)];
    }

    private function pdfTextLayer(Message $message): ?array
    {
        $bytes = PilotDocument::bytes($message);
        $text = $this->extractor->rendered(['binary' => $bytes, 'ext' => 'pdf', 'mime' => 'application/pdf'], self::MAX_TEXT_CHARS);
        if (mb_strlen(trim((string) $text)) < 60) {
            return null;
        }

        return ['page_count' => null, 'pages' => null, 'version' => substr(hash('sha256', $bytes), 0, 16),
            'sections' => self::lineSections((string) $text), 'tables' => [], 'converter' => 'pdf-text'];
    }

    /** @return list<array<string, string>> */
    private static function lineSections(string $text): array
    {
        $sections = [];
        foreach (preg_split('/\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            if (trim($line) !== '') {
                $sections[] = ['l'.(count($sections) + 1) => rtrim($line)];
            }
        }

        return $sections;
    }

    /**
     * Judul bagian: "# …" dari Docling/Markdown, "BAB I …", atau penomoran "2.1 Tujuan" yang pendek.
     *
     * @return list<array{id: string, text: string}>
     */
    public static function outline(array $sections): array
    {
        $outline = [];
        foreach ($sections as $row) {
            $id = (string) array_key_first($row);
            $line = trim((string) ($row[$id] ?? ''));
            if (preg_match('/^#{1,4}\s+\S/u', $line)
                || (mb_strlen($line) <= 90 && preg_match('/^(?:BAB\s+[IVXLC\d]+\b|(?:\d+\.){1,3}\d*\s+\p{Lu}|[IVX]+\.\s+\p{Lu})/u', $line))) {
                $outline[] = ['id' => $id, 'text' => trim((string) preg_replace('/^#+\s*/u', '', $line))];
            }
        }

        return $outline;
    }
}
