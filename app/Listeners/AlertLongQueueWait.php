<?php

namespace App\Listeners;

use App\Services\Admin\AdminAlerts;
use App\Support\Queues;
use Laravel\Horizon\Events\LongWaitDetected;

/** Antrean menumpuk melewati batas config/horizon.php "waits": banner + email superadmin, sekali per antrean per jam. */
class AlertLongQueueWait
{
    public function __construct(private readonly AdminAlerts $alerts) {}

    public function handle(LongWaitDetected $event): void
    {
        $queue = (string) $event->queue;
        $minutes = max(1, (int) round($event->seconds / 60));
        $this->alerts->raise(
            'queue.long_wait.'.$queue.'.'.now()->format('YmdH'),
            $queue === Queues::AI ? AdminAlerts::DANGER : AdminAlerts::WARNING,
            'Antrean '.Queues::label($queue).' menunggu sekitar '.$minutes.' menit',
            'Pekerjaan di antrean '.Queues::label($queue).' menunggu sekitar '.$minutes.' menit sebelum dikerjakan. '
                .'Periksa halaman Antrean di superadmin untuk melihat proses yang sedang berjalan.'
        );
    }
}
