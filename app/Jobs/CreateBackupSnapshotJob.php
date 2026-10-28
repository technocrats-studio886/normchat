<?php

namespace App\Jobs;

use App\Models\GroupBackup;
use App\Services\GroupBackups;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Menulis isi cadangan yang sudah dicatat GroupBackups::start(). */
class CreateBackupSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $backupId)
    {
        $this->onQueue('default');
    }

    public function handle(GroupBackups $backups): void
    {
        $backup = GroupBackup::find($this->backupId);
        if ($backup && $backup->status === 'pending') {
            $backups->fill($backup);
        }
    }

    public function failed(Throwable $e): void
    {
        GroupBackup::whereKey($this->backupId)->update(['status' => 'failed']);
    }
}
