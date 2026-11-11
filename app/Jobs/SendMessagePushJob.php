<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Push\MessagePush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Notifikasi push satu pesan, dijalankan setelah jeda singkat (normchat.push.delay_seconds) supaya pesan yang
 * langsung terbaca di perangkat lain tidak dikirim (App\Services\Push\MessagePush).
 */
class SendMessagePushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
    }

    public function handle(MessagePush $push): void
    {
        $message = Message::find($this->messageId);
        if ($message !== null) {
            $push->send($message);
        }
    }
}
