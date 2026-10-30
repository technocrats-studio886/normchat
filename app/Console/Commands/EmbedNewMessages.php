<?php

namespace App\Console\Commands;

use App\Jobs\EmbedGroupMessagesJob;
use App\Models\AiUsageLog;
use App\Models\GroupContextSummary;
use App\Services\Ai\Context\MemoryMaintenance;
use Illuminate\Console\Command;

/**
 * Antrekan embedding pesan baru untuk grup yang ingatannya sudah dimulai dan memakai Normi dalam
 * 30 hari terakhir. Grup yang tidak pernah men-tag Normi tidak di-embed.
 */
class EmbedNewMessages extends Command
{
    protected $signature = 'normchat:embed-new-messages';

    protected $description = 'Antrekan embedding pesan baru untuk grup yang memakai Normi';

    public function handle(): int
    {
        $activeGroups = AiUsageLog::query()
            ->where('created_at', '>=', now()->subDays((int) config('normchat.context.active_days', 30)))
            ->where('charged_credits', '>', 0)
            ->distinct()
            ->pluck('group_id');

        $queued = 0;
        GroupContextSummary::query()->whereIn('group_id', $activeGroups)->each(function (GroupContextSummary $state) use (&$queued) {
            $latest = (int) MemoryMaintenance::eligible((int) $state->group_id)->max('id');
            if ($latest > (int) $state->embedded_until_message_id) {
                EmbedGroupMessagesJob::dispatch((int) $state->group_id);
                $queued++;
            }
        });
        $this->info("Grup diantrekan: {$queued}.");

        return self::SUCCESS;
    }
}
