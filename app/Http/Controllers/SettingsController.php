<?php

namespace App\Http\Controllers;

use App\Jobs\CreateBackupSnapshotJob;
use App\Jobs\GenerateExportJob;
use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\Export;
use App\Models\Group;
use App\Models\GroupBackup;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\RecoveryLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{

    public function show(Group $group): View
    {
        $this->authorize('manageSettings', $group);

        $group->load(['groupToken', 'exports', 'backups.creator']);

        $auditLogs = AuditLog::query()
            ->where('group_id', $group->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        return view('settings.show', [
            'group' => $group,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function createExport(Request $request, Group $group): RedirectResponse|StreamedResponse
    {
        $this->authorize('exportChat', $group);

        $validated = $request->validate([
            'file_type' => ['required', 'in:pdf,docx'],
        ]);

        $export = Export::create([
            'group_id' => $group->id,
            'file_name' => sprintf('normchat-group-%d.%s', $group->id, $validated['file_type']),
            'storage_path' => '',
            'file_type' => $validated['file_type'],
            'status' => 'queued',
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        // Generate immediately so user gets direct download on button click.
        (new GenerateExportJob($export->id))->handle();

        $export->refresh();
        if ($export->status === 'done' && $export->storage_path && Storage::disk('normchat_exports')->exists($export->storage_path)) {
            $downloadName = sprintf('normchat-group-%d-export-%d.%s', $group->id, $export->id, $validated['file_type']);
            return Storage::disk('normchat_exports')->download($export->storage_path, $downloadName);
        }

        return back()->withErrors(['export' => 'Export belum berhasil diproses. Coba ulangi beberapa saat lagi.']);
    }

    public function historyExport(Group $group): View
    {
        $this->authorize('manageSettings', $group);

        $group->load([
            'exports' => fn ($query) => $query->latest('created_at'),
            'backups' => fn ($query) => $query->with('creator')->latest('created_at'),
        ]);

        return view('settings.history-export', [
            'group' => $group,
        ]);
    }

    public function aiPersonaEditor(Group $group): View
    {
        $this->authorize('manageSettings', $group);

        return view('settings.ai-persona', [
            'group' => $group,
        ]);
    }

    public function saveAiPersona(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageSettings', $group);

        $validated = $request->validate([
            'ai_persona_style' => ['nullable', 'string', 'max:1000'],
            'ai_persona_guardrails' => ['nullable', 'string', 'max:1000'],
        ]);

        $group->update($validated);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'settings.update_ai_persona',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => ['updated_fields' => array_keys($validated)],
            'created_at' => now(),
        ]);

        return back()->with('success', 'AI Persona berhasil disimpan.');
    }

    public function seatManagement(Group $group): View
    {
        $this->authorize('manageSettings', $group);

        $group->load(['subscription.seats', 'members.user', 'members.role']);

        $subscription = $group->subscription;
        $activeSeatCount = (int) ($subscription?->included_seats ?? 2);
        $activeMemberCount = $group->members()->where('status', 'active')->count();
        $includedSeats = (int) ($subscription?->included_seats ?? 2);
        $extraSeats = max($activeMemberCount - $includedSeats, 0);

        return view('settings.seat-management', [
            'group' => $group,
            'subscription' => $subscription,
            'activeSeatCount' => $activeSeatCount,
            'activeMemberCount' => $activeMemberCount,
            'includedSeats' => $includedSeats,
            'extraSeats' => $extraSeats,
        ]);
    }

    public function createBackup(Group $group): RedirectResponse
    {
        $this->authorize('createBackup', $group);

        CreateBackupSnapshotJob::dispatch($group->id, Auth::id());

        return back()->with('success', 'Job backup snapshot dikirim ke queue Redis.');
    }

    public function createAiConnection(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageSettings', $group);

        $validated = $request->validate([
            'provider_name' => ['required', 'in:openai,claude,gemini'],
            'access_token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);

        $connection = AiConnection::query()->updateOrCreate(
            ['user_id' => (int) Auth::id()],
            [
                'provider' => $validated['provider_name'],
                'access_token' => encrypt($validated['access_token']),
                'refresh_token' => null,
                'expires_at' => null,
            ]
        );

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'settings.set_group_ai_provider',
            'target_type' => AiConnection::class,
            'target_id' => $connection->id,
            'metadata_json' => ['provider' => $validated['provider_name']],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Provider AI grup disimpan: '.strtoupper($validated['provider_name']).'.');
    }

    public function restoreBackup(Request $request, Group $group, GroupBackup $backup): RedirectResponse
    {
        $this->authorize('restoreBackup', $group);

        if ((int) $backup->group_id !== (int) $group->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Storage::disk('normchat_backups')->exists($backup->storage_path)) {
            return back()->withErrors(['backup' => 'File backup tidak ditemukan di storage.']);
        }

        $raw = Storage::disk('normchat_backups')->get($backup->storage_path);
        $snapshot = json_decode($raw, true);

        if (! is_array($snapshot) || ! isset($snapshot['messages']) || ! isset($snapshot['members'])) {
            return back()->withErrors(['backup' => 'Format backup tidak valid.']);
        }

        DB::transaction(function () use ($group, $backup, $snapshot, $validated): void {
            Message::query()->where('group_id', $group->id)->forceDelete();

            foreach (($snapshot['messages'] ?? []) as $row) {
                Message::query()->create([
                    'group_id' => $group->id,
                    'sender_type' => (string) ($row['sender_type'] ?? 'user'),
                    'sender_id' => isset($row['sender_id']) ? (int) $row['sender_id'] : null,
                    'content' => (string) ($row['content'] ?? ''),
                ]);
            }

            $owner = User::query()->find($group->owner_id);
            $ownerRole = Role::firstWhere('key', 'owner');
            if ($owner && $ownerRole) {
                GroupMember::query()->updateOrCreate(
                    ['group_id' => $group->id, 'user_id' => $owner->id],
                    ['role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]
                );
            }

            $snapshotUserIds = collect($snapshot['members'] ?? [])
                ->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            GroupMember::query()
                ->where('group_id', $group->id)
                ->where('user_id', '!=', (int) $group->owner_id)
                ->when($snapshotUserIds->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $snapshotUserIds->all()))
                ->delete();

            foreach (($snapshot['members'] ?? []) as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId <= 0 || $userId === (int) $group->owner_id) {
                    continue;
                }

                if (! User::query()->whereKey($userId)->exists()) {
                    continue;
                }

                $role = Role::query()->find((int) ($row['role_id'] ?? 0))
                    ?: Role::firstWhere('key', 'member')
                    ?: Role::firstWhere('key', 'admin');

                if (! $role) {
                    continue;
                }

                GroupMember::query()->updateOrCreate(
                    ['group_id' => $group->id, 'user_id' => $userId],
                    [
                        'role_id' => $role->id,
                        'status' => (string) ($row['status'] ?? 'active'),
                        'joined_at' => now(),
                    ]
                );
            }

            RecoveryLog::query()->create([
                'group_id' => $group->id,
                'backup_id' => $backup->id,
                'restored_by' => Auth::id(),
                'restored_at' => now(),
                'reason' => $validated['reason'] ?? null,
            ]);

            AuditLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => Auth::id(),
                'action' => 'settings.restore_backup',
                'target_type' => GroupBackup::class,
                'target_id' => $backup->id,
                'metadata_json' => ['reason' => $validated['reason'] ?? null],
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Backup berhasil dipulihkan. Riwayat recovery telah dicatat.');
    }
}
