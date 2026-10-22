<?php

namespace App\Jobs;

use App\Models\Group;
use App\Services\GroupBackups;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateBackupSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $groupId, public int $actorId)
    {
        $this->onQueue('default');
    }

    public function handle(GroupBackups $backups): void
    {
        $group = Group::find($this->groupId);
        if ($group) {
            $backups->snapshot($group, $this->actorId);
        }
    }
}
