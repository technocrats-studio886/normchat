<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\Message;
use App\Models\RecoveryLog;
use App\Models\User;
use App\Services\Ai\Context\MemoryMaintenance;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cadangan chat grup = titik pulih. Cadangan menyimpan seluruh kolom pesan yang ada saat itu;
 * memulihkan hanya mengembalikan pesan yang sejak itu terhapus, di posisi dan jam aslinya.
 * Pesan yang dikirim sesudahnya dan keanggotaan grup tidak diubah.
 */
class GroupBackups
{
    private const DISK = 'normchat_backups';

    /** Cadangan otomatis disimpan sekian hari. */
    public const KEEP_DAYS = 30;

    public function __construct(private readonly StoredFiles $files)
    {
    }

    /** Catat cadangan baru sebagai "sedang dibuat"; isinya ditulis oleh CreateBackupSnapshotJob. */
    public function start(Group $group, ?int $actorId, string $type = 'manual'): GroupBackup
    {
        return GroupBackup::create([
            'group_id' => $group->id,
            'backup_type' => $type,
            'storage_path' => '',
            'created_by' => $actorId,
            'created_at' => now(),
            'status' => 'pending',
        ]);
    }

    public function fill(GroupBackup $backup): void
    {
        $rows = Message::where('group_id', $backup->group_id)->orderBy('id')->get()
            ->map(fn (Message $message) => $message->getAttributes())
            ->all();

        $path = sprintf('group-%d/backup-%d-%s.json', $backup->group_id, $backup->id, now()->format('YmdHis'));
        Storage::disk(self::DISK)->put($path, json_encode(['version' => 2, 'messages' => $rows], JSON_UNESCAPED_UNICODE));

        $backup->update([
            'storage_path' => $path,
            'status' => 'ready',
            'message_count' => count($rows),
            'attachment_count' => count(array_filter($rows, fn (array $row) => ! empty($row['attachment_path']))),
            'last_message_id' => $rows === [] ? null : (int) end($rows)['id'],
        ]);
    }

    /** Jumlah pesan yang akan kembali bila cadangan ini dipulihkan. */
    public function restorableCount(GroupBackup $backup): int
    {
        $rows = $this->rowsOf($backup);

        return count($this->missingRows($backup, $rows)) + $this->trashedQuery($backup, $rows)->count();
    }

    /**
     * Kembalikan pesan yang terhapus sejak cadangan dibuat.
     *
     * @throws RuntimeException pesan aman ditampilkan ke user
     */
    public function restore(Group $group, GroupBackup $backup, User $actor): int
    {
        $rows = $this->rowsOf($backup);

        $restored = DB::transaction(function () use ($group, $backup, $actor, $rows) {
            $count = $this->trashedQuery($backup, $rows)->update(['deleted_at' => null, 'deleted_by' => null]);

            // Baris yang sudah benar-benar hilang (dari pemulihan versi lama) ditulis ulang dengan id aslinya.
            foreach ($this->missingRows($backup, $rows) as $row) {
                DB::table('messages')->insert($row);
                $count++;
            }

            RecoveryLog::create([
                'group_id' => $group->id,
                'backup_id' => $backup->id,
                'restored_by' => $actor->id,
                'restored_at' => now(),
            ]);
            AuditLog::record('settings.restore_backup', $actor->id, $backup, ['restored' => $count], $group->id);

            return $count;
        });
        if ($restored > 0) {
            app(MemoryMaintenance::class)->messagesRestored((int) $group->id);
        }

        return $restored;
    }

    /** Hapus cadangan yang lebih lama dari KEEP_DAYS beserta berkasnya. */
    public function prune(): int
    {
        $old = GroupBackup::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->get();
        foreach ($old as $backup) {
            if ($backup->storage_path) {
                Storage::disk(self::DISK)->delete($backup->storage_path);
            }
            $backup->delete();
        }

        return $old->count();
    }

    /** @return list<array<string, mixed>> */
    private function rowsOf(GroupBackup $backup): array
    {
        if ($backup->status !== 'ready') {
            throw new RuntimeException('Cadangan ini belum siap.');
        }

        $content = $this->files->get(self::DISK, (string) $backup->storage_path);
        $snapshot = $content === null ? null : json_decode($content, true);
        if (! is_array($snapshot) || ! is_array($snapshot['messages'] ?? null) || ($snapshot['version'] ?? 1) < 2) {
            throw new RuntimeException('Cadangan ini tidak bisa dipulihkan.');
        }

        return $snapshot['messages'];
    }

    private function trashedQuery(GroupBackup $backup, array $rows)
    {
        return Message::onlyTrashed()
            ->where('group_id', $backup->group_id)
            ->whereIn('id', array_column($rows, 'id'))
            ->where('deleted_at', '>=', $backup->created_at);
    }

    /** @return list<array<string, mixed>> */
    private function missingRows(GroupBackup $backup, array $rows): array
    {
        $existing = Message::withTrashed()->where('group_id', $backup->group_id)->whereIn('id', array_column($rows, 'id'))->pluck('id')->flip();

        return array_values(array_filter($rows, fn (array $row) => ! $existing->has($row['id'])));
    }
}
