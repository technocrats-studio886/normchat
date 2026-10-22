<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingStatus implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $groupId,
        public string $actorType,
        public ?int $senderId,
        public string $senderName,
        public bool $isTyping,
    ) {}

    public function broadcastAs(): string
    {
        return 'typing.status';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('group.'.$this->groupId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'actor_type' => $this->actorType,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'is_typing' => $this->isTyping,
        ];
    }
}
