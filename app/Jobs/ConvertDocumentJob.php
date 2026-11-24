<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Ai\Documents\DocumentStore;
use App\Services\Ai\Files\DocumentPageLimit;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Konversi satu lampiran dokumen ke dokumen kanonik begitu terkirim, ditag @normi atau tidak (keputusan user
 * 24 Nov 2026), lalu indeks isinya untuk konteks tambahan Normi (docs/rancangan-worker-dan-file-nov.md bagian D).
 */
class ConvertDocumentJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;

    /** Docling membaca PDF hingga 35 halaman dengan OCR; batas Docling sendiri 100 detik per berkas. */
    public int $timeout = 240;

    public function __construct(public int $messageId)
    {
        $this->onQueue(Queues::DOCUMENTS);
    }

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    public function handle(DocumentStore $store): void
    {
        $message = Message::find($this->messageId);
        if (! $message || ! DocumentStore::supports($message)) {
            return;
        }
        try {
            $store->convertAndStore($message, allowFallback: $this->attempts() >= $this->tries);
        } catch (DocumentPageLimit) {
            // PDF > 35 halaman tanpa lapisan teks: dibaca per rentang halaman saat user bertanya.
        } catch (RuntimeException) {
            // Docling sibuk atau sedang restart: coba lagi setelah jeda. Tidak melempar exception, supaya
            // pengiriman pesan tidak pernah ikut gagal (antrean sync di tes).
            if ($this->attempts() < $this->tries) {
                $this->release($this->attempts() === 1 ? 60 : 300);

                return;
            }
        }
        // Indeks pencarian memakai teks kanonik (atau pembaca teks lama bila konversi gagal).
        if ($message->message_type === 'file' && $message->doc_indexed_at === null) {
            IndexDocumentJob::dispatch($message->id);
        }
    }
}
