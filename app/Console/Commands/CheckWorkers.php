<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminAlerts;
use App\Services\Queue\WorkerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Dijalankan scheduler tiap menit: master Horizon mati lebih dari 2 menit memunculkan peringatan superadmin,
 * dan peringatan itu ditutup sendiri begitu worker hidup lagi (docs/rancangan-worker-dan-file-nov.md).
 */
class CheckWorkers extends Command
{
    protected $signature = 'normchat:check-workers';

    protected $description = 'Periksa apakah worker antrean (Horizon) berjalan';

    private const DOWN_SINCE = 'normchat:workers-down-since';

    public function handle(WorkerStatus $workers, AdminAlerts $alerts): int
    {
        if ($workers->running()) {
            Cache::forget(self::DOWN_SINCE);
            $alerts->resolve('queue.down.');
            $this->info('Worker berjalan.');

            return self::SUCCESS;
        }

        $since = (int) Cache::get(self::DOWN_SINCE, 0);
        if ($since === 0) {
            Cache::put(self::DOWN_SINCE, $since = now()->timestamp, now()->addDay());
        }
        if (now()->timestamp - $since >= 120) {
            $alerts->raise('queue.down.'.$since, AdminAlerts::DANGER, 'Worker antrean berhenti',
                'Worker antrean Normi Chat tidak berjalan sejak '.now()->setTimestamp($since)->timezone(\App\Support\UserTime::DEFAULT_ZONE)->format('j M H.i').' WIB. '
                .'Normi tidak menjawab dan notifikasi tidak terkirim sampai container queue berjalan lagi.');
        }
        $this->warn('Worker tidak berjalan.');

        return self::FAILURE;
    }
}
