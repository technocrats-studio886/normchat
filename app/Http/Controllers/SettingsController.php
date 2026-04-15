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
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Models\RecoveryLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{

    public function updateGroupProfile(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('editGroupProfile', $group);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'approval_enabled' => ['nullable'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $updates = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'approval_enabled' => (bool) ($validated['approval_enabled'] ?? false),
        ];

        if (! empty($validated['password'])) {
            $updates['password_hash'] = Hash::make($validated['password']);
        }

        $group->update($updates);

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => Auth::id(),
            'action' => 'settings.update_group_profile',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'updated_fields' => array_keys($updates),
                'password_updated' => array_key_exists('password_hash', $updates),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Pengaturan grup berhasil disimpan.');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);

        $user = Auth::user();
        $canEditProfile = $user?->can('editGroupProfile', $group) ?? false;
        $canManageBilling = $user?->can('manageBilling', $group) ?? false;
        $canManageAiPersona = $user?->can('manageAiPersona', $group) ?? false;
        $canManageMembers = $user?->can('manageMembers', $group) ?? false;
        $canExportChat = $user?->can('exportChat', $group) ?? false;
        $canCreateBackup = $user?->can('createBackup', $group) ?? false;

        $group->load(['groupToken', 'members.user', 'members.role']);
        $members = $group->members->where('status', 'active')->values();

        $auditLogs = AuditLog::query()
            ->where('group_id', $group->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        $mediaMessages = Message::query()
            ->where('group_id', $group->id)
            ->where('message_type', 'image')
            ->whereNotNull('attachment_path')
            ->latest('created_at')
            ->take(60)
            ->get();

        $fileMessages = Message::query()
            ->where('group_id', $group->id)
            ->whereNotNull('attachment_path')
            ->whereNotIn('message_type', ['image', 'voice'])
            ->latest('created_at')
            ->take(60)
            ->get();

        $linkMessages = Message::query()
            ->where('group_id', $group->id)
            ->where('content', 'like', '%http%')
            ->latest('created_at')
            ->take(60)
            ->get()
            ->map(function ($m) {
                preg_match_all('/https?:\/\/[^\s<]+/i', (string) $m->content, $urls);
                $m->extracted_urls = $urls[0] ?? [];
                return $m;
            })
            ->filter(fn ($m) => count($m->extracted_urls) > 0)
            ->values();

        return view('settings.show', [
            'group' => $group,
            'auditLogs' => $auditLogs,
            'mediaMessages' => $mediaMessages,
            'fileMessages' => $fileMessages,
            'linkMessages' => $linkMessages,
            'members' => $members,
            // legacy flag = true if user can edit anything (owner)
            'canManageSettings' => $canEditProfile,
            'canEditProfile' => $canEditProfile,
            'canManageBilling' => $canManageBilling,
            'canManageAiPersona' => $canManageAiPersona,
            'canManageMembers' => $canManageMembers,
            'canExportChat' => $canExportChat,
            'canCreateBackup' => $canCreateBackup,
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
            'file_name' => sprintf('%s.%s', $group->name, $validated['file_type']),
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
            $downloadName = $export->file_name ?: sprintf('%s.%s', $group->name, $validated['file_type']);
            return Storage::disk('normchat_exports')->download($export->storage_path, $downloadName);
        }

        return back()->withErrors(['export' => 'Export belum berhasil diproses. Coba ulangi beberapa saat lagi.']);
    }

    public function downloadExport(Group $group, Export $export): StreamedResponse|RedirectResponse
    {
        $this->authorize('exportChat', $group);

        abort_unless((int) $export->group_id === (int) $group->id, 404);

        if ($export->status !== 'done' || ! $export->storage_path || ! Storage::disk('normchat_exports')->exists($export->storage_path)) {
            return back()->withErrors(['export' => 'File export tidak tersedia.']);
        }

        $downloadName = $export->file_name ?: sprintf('%s.%s', $group->name, $export->file_type);

        return Storage::disk('normchat_exports')->download($export->storage_path, $downloadName);
    }

    public function transactionHistory(Group $group): View
    {
        $this->authorize('view', $group);

        $this->reconcileLegacyMissingTokens($group);

        $contributions = GroupTokenContribution::query()
            ->where('group_id', $group->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->get();

        return view('settings.transactions', [
            'group' => $group,
            'contributions' => $contributions,
        ]);
    }

    public function historyExport(Group $group): View
    {
        $this->authorize('view', $group);

        $user = Auth::user();
        $canExportChat = $user?->can('exportChat', $group) ?? false;
        $canCreateBackup = $user?->can('createBackup', $group) ?? false;
        $canRestoreBackup = $user?->can('restoreBackup', $group) ?? false;

        $group->load([
            'exports' => fn ($query) => $query->latest('created_at'),
            'backups' => fn ($query) => $query->with('creator')->latest('created_at'),
        ]);

        return view('settings.history-export', [
            'group' => $group,
            'canExportChat' => $canExportChat,
            'canCreateBackup' => $canCreateBackup,
            'canRestoreBackup' => $canRestoreBackup,
        ]);
    }

    public function aiPersonaEditor(Group $group): View
    {
        $this->authorize('view', $group);

        $canManageAiPersona = Auth::user()?->can('manageAiPersona', $group) ?? false;

        return view('settings.ai-persona', [
            'group' => $group,
            'canManageAiPersona' => $canManageAiPersona,
        ]);
    }

    public function saveAiPersona(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageAiPersona', $group);

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

    public function createBackup(Group $group): RedirectResponse
    {
        $this->authorize('createBackup', $group);

        CreateBackupSnapshotJob::dispatch($group->id, Auth::id());

        return back()->with('success', 'Job backup snapshot dikirim ke queue Redis.');
    }

    public function createAiConnection(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageAiPersona', $group);

        $validated = $request->validate([
            'provider_name' => ['required', 'in:openai'],
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

        return back()->with('success', 'Koneksi AI berhasil disimpan.');
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

    private function reconcileLegacyMissingTokens(Group $group): void
    {
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);
        if ($duPer12Nk <= 0) {
            return;
        }

        $legacyRows = GroupTokenContribution::query()
            ->where('group_id', $group->id)
            ->where('token_amount', '<=', 0)
            ->where('price_paid', '>', 0)
            ->whereIn('source', [
                'patungan',
                'topup',
                'interdotz_topup',
                'interdotz_charge_topup',
            ])
            ->orderBy('id')
            ->get();

        if ($legacyRows->isEmpty()) {
            return;
        }

        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );

        foreach ($legacyRows as $row) {
            $duPaid = (int) ($row->price_paid ?? 0);
            $expectedTokens = (int) round(($duPaid * 12 * 2500) / $duPer12Nk);
            if ($expectedTokens <= 0) {
                continue;
            }

            $sameReferenceAlreadyCredited = false;
            if ($row->payment_reference) {
                $sameReferenceAlreadyCredited = GroupTokenContribution::query()
                    ->where('group_id', $group->id)
                    ->where('payment_reference', (string) $row->payment_reference)
                    ->where('id', '!=', $row->id)
                    ->where('token_amount', '>', 0)
                    ->exists();
            }

            $row->token_amount = $expectedTokens;
            $row->save();

            if (! $sameReferenceAlreadyCredited) {
                $groupToken->addTokens($expectedTokens);
            }

            AuditLog::create([
                'group_id' => $group->id,
                'actor_id' => Auth::id(),
                'action' => 'settings.reconcile_tokens',
                'target_type' => GroupTokenContribution::class,
                'target_id' => $row->id,
                'metadata_json' => [
                    'source' => (string) $row->source,
                    'du_paid' => $duPaid,
                    'token_amount' => $expectedTokens,
                    'payment_reference' => (string) ($row->payment_reference ?? ''),
                    'credited_balance' => ! $sameReferenceAlreadyCredited,
                ],
                'created_at' => now(),
            ]);
        }
    }
}
