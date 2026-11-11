<?php

namespace App\Jobs;

use App\Models\Group;
use App\Services\Chat\WeeklyRecap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Buat dan posting rekap mingguan satu grup (App\Services\Chat\WeeklyRecap). Tidak diulang bila gagal. */
class GenerateWeeklyRecapJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $groupId)
    {
        $this->onQueue('default');
    }

    public function handle(WeeklyRecap $recap): void
    {
        $group = Group::with('owner')->find($this->groupId);
        if ($group && $recap->isDue($group)) {
            $recap->generate($group);
        }
    }
}
