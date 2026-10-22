<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\RecoveryLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Snapshot JSON pesan & anggota grup, dan pemulihannya. */
class GroupBackups
{
    private const DISK = 'normchat_backups';

    public function snapshot(Group $group, int $actorId): GroupBackup
    {
        $group->loadMissing(['members', 'messages']);

        $filename = sprintf('group-%d-backup-%s.json', $group->id, now()->format('YmdHis'));
        Storage::disk(self::DISK)->put($filename, json_encode([
            'group' => $group->only(['id', 'name', 'description', 'owner_id', 'approval_enabled']),
            'members' => $group->members->map->only(['user_id', 'role_id', 'status', 'joined_at'])->values()->all(),
            'messages' => $group->messages->map->only(['sender_type', 'sender_id', 'content', 'created_at'])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        return GroupBackup::create([
            'group_id' => $group->id,
            'backup_type' => 'snapshot',
            'storage_path' => $filename,
            'created_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    /**
     * Ganti seluruh pesan dan anggota grup dengan isi snapshot. Owner selalu dipertahankan.
     *
     * @throws RuntimeException pesan aman ditampilkan ke user
     */
    public function restore(Group $group, GroupBackup $backup, User $actor, ?string $reason): void
    {
        $snapshot = $this->readSnapshot($backup);

        DB::transaction(function () use ($group, $backup, $actor, $reason, $snapshot) {
            $this->restoreMessages($group, $snapshot['messages']);
            $this->restoreMembers($group, $snapshot['members']);

            RecoveryLog::create([
                'group_id' => $group->id,
                'backup_id' => $backup->id,
                'restored_by' => $actor->id,
                'restored_at' => now(),
                'reason' => $reason,
            ]);
            AuditLog::record('settings.restore_backup', $actor->id, $backup, ['reason' => $reason], $group->id);
        });
    }

    private function readSnapshot(GroupBackup $backup): array
    {
        if (! Storage::disk(self::DISK)->exists($backup->storage_path)) {
            throw new RuntimeException('File backup tidak ditemukan di storage.');
        }

        $snapshot = json_decode((string) Storage::disk(self::DISK)->get($backup->storage_path), true);
        if (! is_array($snapshot) || ! isset($snapshot['messages'], $snapshot['members'])) {
            throw new RuntimeException('Format backup tidak valid.');
        }

        return $snapshot;
    }

    private function restoreMessages(Group $group, array $messages): void
    {
        Message::where('group_id', $group->id)->forceDelete();

        foreach ($messages as $row) {
            Message::create([
                'group_id' => $group->id,
                'sender_type' => (string) ($row['sender_type'] ?? 'user'),
                'sender_id' => isset($row['sender_id']) ? (int) $row['sender_id'] : null,
                'content' => (string) ($row['content'] ?? ''),
            ]);
        }
    }

    private function restoreMembers(Group $group, array $members): void
    {
        $ownerId = (int) $group->owner_id;
        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $ownerId],
            ['role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]
        );

        $snapshotUserIds = collect($members)->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', '!=', $ownerId)
            ->when($snapshotUserIds !== [], fn ($query) => $query->whereNotIn('user_id', $snapshotUserIds))
            ->delete();

        foreach ($members as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0 || $userId === $ownerId || ! User::whereKey($userId)->exists()) {
                continue;
            }

            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $userId],
                [
                    'role_id' => (Role::find((int) ($row['role_id'] ?? 0)) ?? Role::forKey(Role::MEMBER))->id,
                    'status' => (string) ($row['status'] ?? 'active'),
                    'joined_at' => now(),
                ]
            );
        }
    }
}
