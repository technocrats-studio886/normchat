<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Pratinjau tautan sebuah pesan sudah dibaca: browser memasang kartunya di bubble tanpa memuat ulang. */
class LinkPreviewReady implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $groupId, public int $messageId, public ?array $card)
    {
    }

    public function broadcastAs(): string
    {
        return 'link.preview.ready';
    }

    public function broadcastWith(): array
    {
        return ['group_id' => $this->groupId, 'message_id' => $this->messageId, 'link_preview' => $this->card];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->groupId)];
    }
}
