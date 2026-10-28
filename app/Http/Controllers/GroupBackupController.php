<?php

namespace App\Http\Controllers;

use App\Jobs\CreateBackupSnapshotJob;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Services\GroupBackups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Cadangan chat grup (hanya moderator): buat, pantau status, dan pulihkan pesan yang terhapus. */
class GroupBackupController extends Controller
{
    public function index(Group $group): JsonResponse
    {
        $this->authorize('createBackup', $group);

        return response()->json(['ok' => true, 'backups' => $group->backups()->with('creator:id,name')->latest('created_at')->get()->map(fn (GroupBackup $backup) => self::present($backup))]);
    }

    public function store(Request $request, Group $group, GroupBackups $backups): JsonResponse
    {
        $this->authorize('createBackup', $group);

        $backup = $backups->start($group, $request->user()->id);
        CreateBackupSnapshotJob::dispatch($backup->id);

        return response()->json(['ok' => true, 'backup' => self::present($backup->refresh()->load('creator:id,name'))], 201);
    }

    /** Jumlah pesan yang akan kembali, untuk dialog konfirmasi. */
    public function preview(Group $group, GroupBackup $backup, GroupBackups $backups): JsonResponse
    {
        $this->authorize('restoreBackup', $group);
        abort_unless((int) $backup->group_id === (int) $group->id, 404);

        try {
            return response()->json(['ok' => true, 'restorable' => $backups->restorableCount($backup)]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function restore(Request $request, Group $group, GroupBackup $backup, GroupBackups $backups): JsonResponse
    {
        $this->authorize('restoreBackup', $group);
        abort_unless((int) $backup->group_id === (int) $group->id, 404);

        try {
            return response()->json(['ok' => true, 'restored' => $backups->restore($group, $backup, $request->user())]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public static function present(GroupBackup $backup): array
    {
        return [
            'id' => $backup->id,
            'status' => $backup->status,
            'type' => $backup->backup_type,
            'created_at' => $backup->created_at?->toIso8601String(),
            'creator' => $backup->creator?->name,
            'message_count' => (int) $backup->message_count,
            'attachment_count' => (int) $backup->attachment_count,
            'preview_url' => route('settings.backup.preview', [$backup->group_id, $backup->id]),
            'restore_url' => route('settings.backup.restore', [$backup->group_id, $backup->id]),
        ];
    }
}
