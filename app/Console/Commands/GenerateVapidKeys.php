<?php

namespace App\Console\Commands;

use App\Services\Push\WebPush;
use Illuminate\Console\Command;

/** Pasangan kunci VAPID untuk Web Push; isi ke .env sebagai NORMCHAT_VAPID_PUBLIC_KEY/PRIVATE_KEY. */
class GenerateVapidKeys extends Command
{
    protected $signature = 'normchat:vapid-keys';

    protected $description = 'Buat pasangan kunci VAPID untuk notifikasi push';

    public function handle(): int
    {
        $keys = WebPush::generateKeys();
        $this->line('NORMCHAT_VAPID_PUBLIC_KEY=' . $keys['public']);
        $this->line('NORMCHAT_VAPID_PRIVATE_KEY=' . $keys['private']);
        $this->newLine();
        $this->comment('Mengganti kunci membuat semua langganan lama tidak berlaku; perangkat akan mendaftar ulang otomatis.');

        return self::SUCCESS;
    }
}
