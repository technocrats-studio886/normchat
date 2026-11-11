<?php

namespace App\Console\Commands;

use App\Services\Chat\Reminders;
use Illuminate\Console\Command;

/** Kirim pengingat yang sudah waktunya (App\Services\Chat\Reminders). Dijalankan tiap menit. */
class SendReminders extends Command
{
    protected $signature = 'normchat:send-reminders';

    protected $description = 'Kirim pengingat grup yang sudah waktunya';

    public function handle(Reminders $reminders): int
    {
        $this->info('Pengingat terkirim: ' . $reminders->sendDue() . '.');

        return self::SUCCESS;
    }
}
