<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Services\Groups\GroupAvatars;
use App\Services\Groups\GroupInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Info grup (HP: halaman penuh; desktop: jendela/panel di ruang chat), simpan profil grup, riwayat patungan. */
class SettingsController extends Controller
{
    public function show(Request $request, Group $group, GroupInfo $info): View
    {
        $this->authorize('view', $group);

        return view('settings.show', $info->for($group, $request->user()));
    }

    public function updateGroupProfile(Request $request, Group $group, GroupAvatars $avatars): RedirectResponse
    {
        $this->authorize('editGroupProfile', $group);

        // Profil atas (nama, deskripsi, foto) dan kata sandi di tab Pengaturan memakai form terpisah:
        // field yang tidak dikirim tidak diubah.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
            'info_view' => ['nullable', 'in:main,manage'],
        ], [
            'avatar.image' => 'Foto grup harus berupa gambar.',
            'avatar.mimes' => 'Foto grup harus JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto grup maksimal 5 MB.',
            'password.min' => 'Kata sandi minimal 4 karakter.',
        ]);

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }
        if ($request->has('description')) {
            $updates['description'] = $validated['description'] ?? null;
        }
        if (! empty($validated['password'])) {
            $updates['password_hash'] = Hash::make($validated['password']);
        }

        $oldAvatar = $group->avatar_path;
        $avatarChange = null;
        if ($request->hasFile('avatar')) {
            $updates['avatar_path'] = $avatars->store($group, $request->file('avatar'));
            $avatarChange = 'updated';
        } elseif ($request->boolean('remove_avatar') && $oldAvatar) {
            $updates['avatar_path'] = null;
            $avatarChange = 'removed';
        }

        if ($updates !== []) {
            $group->update($updates);
        }
        if ($avatarChange) {
            $avatars->delete($oldAvatar);
        }
        AuditLog::record('settings.update_group_profile', $request->user()->id, $group, [
            'updated_fields' => array_keys($updates),
            'password_updated' => isset($updates['password_hash']),
            'avatar_updated' => $avatarChange === 'updated',
            'avatar_removed' => $avatarChange === 'removed',
        ]);

        return back()
            ->with('success', isset($updates['password_hash']) && count($updates) === 1 ? 'Kata sandi grup diganti.' : 'Info grup disimpan.')
            ->with('group_info', $validated['info_view'] ?? 'main');
    }

    public function transactionHistory(Group $group): View
    {
        $this->authorize('view', $group);

        return view('settings.transactions', [
            'group' => $group,
            'contributions' => GroupTokenContribution::where('group_id', $group->id)->with('user:id,name')->latest('created_at')->get(),
        ]);
    }
}
