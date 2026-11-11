<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Daftar milik grup berubah (sematan, tugas, pengingat, pesan terjadwal). Hanya tanda "berubah": tiap browser
 * mengambil ulang daftarnya sendiri, supaya isinya tetap mengikuti hak lihat masing-masing anggota.
 */
class GroupDataChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const PINS = 'pins';
    public const TASKS = 'tasks';
    public const REMINDERS = 'reminders';
    public const SCHEDULED = 'scheduled';

    /** $onlyUserId: perubahan pribadi (pesan terjadwal, pengingat pribadi) hanya ke kanal pemiliknya. */
    public function __construct(
        public int $groupId,
        public string $kind,
        public ?int $onlyUserId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'group.data.changed';
    }

    public function broadcastWith(): array
    {
        return ['group_id' => $this->groupId, 'kind' => $this->kind];
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            $this->onlyUserId !== null
                ? new PrivateChannel('App.Models.User.' . $this->onlyUserId)
                : new PrivateChannel('group.' . $this->groupId),
        ];
    }
}
