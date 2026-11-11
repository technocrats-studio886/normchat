<?php

namespace App\Console\Commands;

use App\Jobs\IndexDocumentJob;
use App\Models\AiUsageLog;
use App\Services\Ai\Documents\DocumentIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Antrekan pengindeksan dokumen lampiran (App\Services\Ai\Documents\DocumentIndex), termasuk dokumen lama, hanya
 * untuk grup yang memakai Normi dalam 30 hari terakhir (sama dengan embedding pesan). Grup yang baru mulai memakai
 * Normi ikut terindeks dokumen lamanya.
 */
class IndexDocuments extends Command
{
    protected $signature = 'normchat:index-documents {--limit=20}';

    protected $description = 'Antrekan pengindeksan dokumen lampiran grup yang memakai Normi';

    public function handle(): int
    {
        $activeGroups = AiUsageLog::query()
            ->where('created_at', '>=', now()->subDays((int) config('normchat.context.active_days', 30)))
            ->where('charged_credits', '>', 0)
            ->distinct()
            ->pluck('group_id');
        if ($activeGroups->isEmpty()) {
            return self::SUCCESS;
        }

        $queued = 0;
        DocumentIndex::pending()->whereIn('group_id', $activeGroups)->limit((int) $this->option('limit'))->pluck('id')
            ->each(function ($id) use (&$queued) {
                // Satu job per dokumen; tanda ini mencegah dokumen yang sama diantrekan dua kali sebelum selesai.
                if (Cache::add('doc-index-queued:' . $id, true, now()->addMinutes(30))) {
                    IndexDocumentJob::dispatch((int) $id);
                    $queued++;
                }
            });
        $this->info("Dokumen diantrekan: {$queued}.");

        return self::SUCCESS;
    }
}
