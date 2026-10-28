<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** $onlyUserId: pemberitahuan Normi untuk satu anggota dikirim ke kanal pribadinya saja. */
    public function __construct(
        public int $groupId,
        public array $message,
        public ?int $onlyUserId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'message' => $this->message,
        ];
    }

    /**
     * Kanal penerima event.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            $this->onlyUserId !== null
                ? new PrivateChannel('App.Models.User.'.$this->onlyUserId)
                : new PrivateChannel('group.'.$this->groupId),
        ];
    }
}
