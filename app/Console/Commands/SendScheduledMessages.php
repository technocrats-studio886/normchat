<?php

namespace App\Console\Commands;

use App\Services\Chat\ScheduledMessages;
use Illuminate\Console\Command;

/** Kirim pesan terjadwal yang sudah waktunya (App\Services\Chat\ScheduledMessages). Dijalankan tiap menit. */
class SendScheduledMessages extends Command
{
    protected $signature = 'normchat:send-scheduled';

    protected $description = 'Kirim pesan terjadwal yang sudah waktunya';

    public function handle(ScheduledMessages $scheduled): int
    {
        $this->info('Pesan terjadwal terkirim: ' . $scheduled->sendDue() . '.');

        return self::SUCCESS;
    }
}
