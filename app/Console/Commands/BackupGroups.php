<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\Message;
use App\Services\GroupBackups;
use Illuminate\Console\Command;

/** Cadangan otomatis tiap malam untuk grup yang punya pesan baru sejak cadangan terakhirnya. */
class BackupGroups extends Command
{
    protected $signature = 'normchat:backup-groups';

    protected $description = 'Cadangkan chat grup yang ada pesan baru, lalu hapus cadangan lebih dari 30 hari';

    public function handle(GroupBackups $backups): int
    {
        $created = 0;
        Group::query()->where('status', 'active')->each(function (Group $group) use ($backups, &$created) {
            $latest = (int) Message::where('group_id', $group->id)->max('id');
            $backedUp = (int) GroupBackup::where('group_id', $group->id)->where('status', 'ready')->max('last_message_id');
            if ($latest > $backedUp) {
                $backups->fill($backups->start($group, null, 'auto'));
                $created++;
            }
        });

        $pruned = $backups->prune();
        $this->info("Cadangan baru: {$created}. Cadangan lama dihapus: {$pruned}.");

        return self::SUCCESS;
    }
}
