<?php

namespace App\Http\Controllers;

use App\Jobs\CreateBackupSnapshotJob;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Services\GroupBackups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GroupBackupController extends Controller
{
    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('createBackup', $group);

        CreateBackupSnapshotJob::dispatch($group->id, $request->user()->id);

        return back()->with('success', 'Cadangan sedang dibuat.');
    }

    public function restore(Request $request, Group $group, GroupBackup $backup, GroupBackups $backups): RedirectResponse
    {
        $this->authorize('restoreBackup', $group);
        abort_unless((int) $backup->group_id === (int) $group->id, 404);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        try {
            $backups->restore($group, $backup, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('success', 'Chat berhasil dipulihkan.');
    }
}
