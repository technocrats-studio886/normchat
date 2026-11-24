<?php

namespace App\Console\Commands;

use App\Jobs\ConvertDocumentJob;
use App\Jobs\IndexDocumentJob;
use App\Models\Message;
use App\Models\MessageDocument;
use App\Services\Ai\Documents\DocumentIndex;
use App\Services\Ai\Documents\DocumentStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Dijalankan scheduler tiap menit: konversi dokumen kanonik yang tertinggal (App\Services\Ai\Documents\DocumentStore)
 * dan indeks isinya (DocumentIndex) untuk semua grup.
 */
class IndexDocuments extends Command
{
    protected $signature = 'normchat:index-documents {--limit=20}';

    protected $description = 'Antrekan pengindeksan dokumen lampiran grup yang memakai Normi';

    public function handle(): int
    {
        // Jaring pengaman ConvertDocumentJob: dokumen yang belum punya dokumen kanonik (semua grup, keputusan user
        // 24 Nov 2026), dan dokumen gagal yang belum dicoba tiga kali.
        $converted = 0;
        Message::query()->whereNotNull('attachment_path')->whereNull('deleted_at')->where('message_type', 'file')
            ->where('created_at', '>=', now()->subDays(60))
            ->where(fn ($query) => $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('message_documents')->whereColumn('message_documents.message_id', 'messages.id'))
                ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('message_documents')->whereColumn('message_documents.message_id', 'messages.id')
                    ->where('status', MessageDocument::FAILED)->where('attempts', '<', 3)->where('error', '!=', 'page_limit')
                    ->where('updated_at', '<', now()->subMinutes(10))))
            ->orderByDesc('id')->limit((int) $this->option('limit'))->get()
            ->filter(fn (Message $message) => DocumentStore::supports($message))
            ->each(function (Message $message) use (&$converted) {
                ConvertDocumentJob::dispatch((int) $message->id);
                $converted++;
            });

        $queued = 0;
        DocumentIndex::pending()->limit((int) $this->option('limit'))->pluck('id')
            ->each(function ($id) use (&$queued) {
                // Satu job per dokumen; tanda ini mencegah dokumen yang sama diantrekan dua kali sebelum selesai.
                if (Cache::add('doc-index-queued:' . $id, true, now()->addMinutes(30))) {
                    IndexDocumentJob::dispatch((int) $id);
                    $queued++;
                }
            });
        $this->info("Dokumen dikonversi: {$converted}. Dokumen diantrekan untuk indeks: {$queued}.");

        return self::SUCCESS;
    }
}
