<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMembershipChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $groupId,
        public int $targetUserId,
        public string $action,
        public ?string $roleKey = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'group.membership.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'target_user_id' => $this->targetUserId,
            'action' => $this->action,
            'role_key' => $this->roleKey,
        ];
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
}
