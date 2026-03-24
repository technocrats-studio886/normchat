<?php

namespace App\Jobs;

use App\Models\Group;
use App\Models\GroupBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CreateBackupSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $groupId, public int $actorId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $group = Group::query()
            ->with(['members', 'messages'])
            ->find($this->groupId);

        if (! $group) {
            return;
        }

        $snapshot = [
            'group' => $group->only(['id', 'name', 'description', 'owner_id', 'approval_enabled']),
            'members' => $group->members->map->only(['user_id', 'role_id', 'status', 'joined_at'])->values()->all(),
            'messages' => $group->messages->map->only(['sender_type', 'sender_id', 'content', 'created_at'])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];

        $filename = sprintf('group-%d-backup-%s.json', $group->id, now()->format('YmdHis'));
        Storage::disk('normchat_backups')->put($filename, json_encode($snapshot, JSON_PRETTY_PRINT));

        GroupBackup::create([
            'group_id' => $group->id,
            'backup_type' => 'snapshot',
            'storage_path' => $filename,
            'created_by' => $this->actorId,
            'created_at' => now(),
        ]);
    }
}
