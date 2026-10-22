<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Services\Credits\CreditWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Halaman pengaturan grup: profil, anggota, arsip media/file/tautan, dan riwayat kontribusi. */
class SettingsController extends Controller
{
    private const ARCHIVE_LIMIT = 60;

    private const AUDIT_LIMIT = 15;

    public function show(Request $request, Group $group, CreditWallet $wallet): View
    {
        $this->authorize('view', $group);
        $user = $request->user();

        $group->load(['groupToken', 'members.user', 'members.role']);
        $canEditProfile = $user->can('editGroupProfile', $group);

        return view('settings.show', [
            'group' => $group,
            'members' => $group->members->where('status', 'active')->values(),
            'auditLogs' => AuditLog::where('group_id', $group->id)->latest('created_at')->take(self::AUDIT_LIMIT)->get(),
            'mediaMessages' => $this->archive($group)->where('message_type', 'image')->get(),
            'fileMessages' => $this->archive($group)->whereNotIn('message_type', ['image', 'voice'])->get(),
            'linkMessages' => $this->linkMessages($group),
            'canManageSettings' => $canEditProfile,
            'canEditProfile' => $canEditProfile,
            'canManageBilling' => $user->can('manageBilling', $group),
            'canManageAiPersona' => $user->can('manageAiPersona', $group),
            'canManageMembers' => $user->can('manageMembers', $group),
            'canExportChat' => $user->can('exportChat', $group),
            'canCreateBackup' => $user->can('createBackup', $group),
            'creditBalance' => $wallet->balanceFor($group, $user),
        ]);
    }

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
            'approval_enabled' => $request->boolean('approval_enabled'),
        ];
        if (! empty($validated['password'])) {
            $updates['password_hash'] = Hash::make($validated['password']);
        }

        $group->update($updates);
        AuditLog::record('settings.update_group_profile', $request->user()->id, $group, [
            'updated_fields' => array_keys($updates),
            'password_updated' => isset($updates['password_hash']),
        ]);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function transactionHistory(Group $group): View
    {
        $this->authorize('view', $group);

        return view('settings.transactions', [
            'group' => $group,
            'contributions' => GroupTokenContribution::where('group_id', $group->id)->with('user:id,name')->latest('created_at')->get(),
        ]);
    }

    /** Pesan berlampiran terbaru grup. */
    private function archive(Group $group): \Illuminate\Database\Eloquent\Builder
    {
        return Message::query()
            ->where('group_id', $group->id)
            ->whereNotNull('attachment_path')
            ->latest('created_at')
            ->take(self::ARCHIVE_LIMIT);
    }

    /** Pesan terbaru yang berisi tautan, dengan daftar URL-nya di `extracted_urls`. */
    private function linkMessages(Group $group): Collection
    {
        return Message::query()
            ->where('group_id', $group->id)
            ->where('content', 'like', '%http%')
            ->latest('created_at')
            ->take(self::ARCHIVE_LIMIT)
            ->get()
            ->each(function (Message $message) {
                preg_match_all('/https?:\/\/[^\s<]+/i', (string) $message->content, $urls);
                $message->extracted_urls = $urls[0] ?? [];
            })
            ->filter(fn (Message $message) => $message->extracted_urls !== [])
            ->values();
    }
}
