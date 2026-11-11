<?php

namespace App\Services\Ai\Documents;

use App\Models\DocumentChunk;
use App\Models\Group;
use App\Models\Message;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\AttachmentTextExtractor;
use Illuminate\Support\Facades\DB;

/**
 * Indeks isi dokumen lampiran grup (docs/rancangan-fitur-produktivitas-nov.md bagian 7): teks dipotong ±1.200
 * karakter per bagian dengan sedikit tumpang tindih, lalu diberi embedding. Dipakai DocumentSearch saat pertanyaan ke
 * Normi menyebut dokumen grup.
 */
class DocumentIndex
{
    private const CHUNK_CHARS = 1200;

    private const OVERLAP_CHARS = 150;

    /** Bagian paling banyak per dokumen (±180 ribu karakter). */
    private const MAX_CHUNKS = 150;

    private const EMBED_BATCH = 64;

    public function __construct(
        private readonly AttachmentTextExtractor $extractor,
        private readonly MaintenanceAi $ai,
    ) {
    }

    /** Pesan berlampiran dokumen yang belum diindeks. */
    public static function pending()
    {
        return Message::query()
            ->whereNull('doc_indexed_at')
            ->whereNull('deleted_at')
            ->whereNotNull('attachment_path')
            ->where('message_type', 'file')
            ->orderBy('id');
    }

    /** @return int jumlah bagian yang tersimpan (0 = tidak bisa dibaca) */
    public function index(Message $message): int
    {
        $group = Group::find($message->group_id);
        $text = $group ? $this->extractor->extract($message, self::CHUNK_CHARS * self::MAX_CHUNKS) : null;
        $chunks = $text !== null ? self::chunk($text) : [];
        if ($chunks === []) {
            $message->forceFill(['doc_indexed_at' => now()])->saveQuietly();

            return 0;
        }

        $vectors = [];
        foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
            $result = $this->ai->embed($group, array_map(fn (string $chunk) => $this->label($message) . "\n" . $chunk, $batch));
            if ($result === null) {
                // Embedding gagal/ditolak SpendGuard: dicoba lagi nanti; potongan kata kunci tetap tersimpan sekarang.
                $vectors = null;
                break;
            }
            $vectors = [...$vectors, ...$result];
        }

        DB::transaction(function () use ($message, $chunks, $vectors) {
            DocumentChunk::query()->where('message_id', $message->id)->delete();
            foreach ($chunks as $index => $chunk) {
                DocumentChunk::create([
                    'group_id' => $message->group_id,
                    'message_id' => $message->id,
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'embedding' => isset($vectors[$index]) ? json_encode($vectors[$index]) : null,
                ]);
            }
            $message->forceFill(['doc_indexed_at' => now()])->saveQuietly();
        });

        return count($chunks);
    }

    /** Pesan dihapus untuk semua: isi dokumennya tidak boleh muncul lagi di jawaban Normi. */
    public static function forget(Message $message): void
    {
        DocumentChunk::query()->where('message_id', $message->id)->delete();
    }

    /** @return list<string> */
    public static function chunk(string $text): array
    {
        $text = trim((string) preg_replace("/[ \t]+/u", ' ', str_replace("\r", '', $text)));
        if ($text === '') {
            return [];
        }
        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [$text];
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            // Paragraf yang sangat panjang dipecah per kalimat.
            $pieces = mb_strlen($paragraph) > self::CHUNK_CHARS
                ? (preg_split('/(?<=[.!?])\s+/u', $paragraph) ?: [$paragraph])
                : [$paragraph];
            foreach ($pieces as $piece) {
                while (mb_strlen($piece) > self::CHUNK_CHARS) {
                    $chunks[] = mb_substr($piece, 0, self::CHUNK_CHARS);
                    $piece = mb_substr($piece, self::CHUNK_CHARS - self::OVERLAP_CHARS);
                }
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_CHARS) {
                    $chunks[] = $current;
                    $current = mb_substr($current, -self::OVERLAP_CHARS) . "\n" . $piece;
                } else {
                    $current = $current === '' ? $piece : $current . "\n" . $piece;
                }
            }
            if (count($chunks) >= self::MAX_CHUNKS) {
                break;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_slice($chunks, 0, self::MAX_CHUNKS);
    }

    private function label(Message $message): string
    {
        return 'Dokumen: ' . ($message->attachment_original_name ?: 'lampiran');
    }
}
