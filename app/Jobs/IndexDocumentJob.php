<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Ai\Documents\DocumentIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Indeks isi satu dokumen lampiran (App\Services\Ai\Documents\DocumentIndex). */
class IndexDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $messageId)
    {
        $this->onQueue('default');
    }

    public function handle(DocumentIndex $index): void
    {
        $message = Message::query()->whereNull('doc_indexed_at')->find($this->messageId);
        if ($message) {
            $index->index($message);
        }
        Cache::forget('doc-index-queued:' . $this->messageId);
    }
}
