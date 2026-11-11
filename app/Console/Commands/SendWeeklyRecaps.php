<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWeeklyRecapJob;
use App\Models\Group;
use App\Services\Chat\WeeklyRecap;
use Illuminate\Console\Command;

/** Antrekan rekap mingguan grup yang jadwalnya jatuh pada jam ini (App\Services\Chat\WeeklyRecap). */
class SendWeeklyRecaps extends Command
{
    protected $signature = 'normchat:weekly-recaps';

    protected $description = 'Antrekan rekap mingguan grup yang jadwalnya tiba';

    public function handle(WeeklyRecap $recap): int
    {
        $queued = 0;
        Group::query()->where('weekly_recap_enabled', true)->where('status', 'active')->with('owner')
            ->each(function (Group $group) use ($recap, &$queued) {
                if ($recap->isDue($group)) {
                    GenerateWeeklyRecapJob::dispatch((int) $group->id);
                    $queued++;
                }
            });
        $this->info("Rekap diantrekan: {$queued}.");

        return self::SUCCESS;
    }
}
