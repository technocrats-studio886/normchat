<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Services\Profile\StorageUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, StorageUsage $storage): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'storage' => $storage->summaryFor($user),
            'username' => $user->displayUsername(),
            'loginCount' => AuditLog::where('actor_id', $user->id)->whereIn('action', ProfileHistoryController::LOGIN_ACTIONS)->count(),
            'transactionCount' => GroupTokenContribution::where('user_id', $user->id)->count(),
        ]);
    }

    public function account(Request $request): View
    {
        return view('profile.account', [
            'user' => $request->user(),
            'username' => $request->user()->displayUsername(),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $updates = ['name' => trim($validated['name'])];
        if ($request->hasFile('avatar')) {
            $this->deleteLocalAvatar((string) $user->avatar_url);
            $updates['avatar_url'] = Storage::disk('public')->url($request->file('avatar')->store('avatars', 'public'));
        }

        $user->update($updates);

        return redirect()->route('profile.account')->with('success', 'Akun berhasil diperbarui.');
    }

    public function storage(Request $request, StorageUsage $storage): View
    {
        $user = $request->user();

        return view('profile.storage', [
            'user' => $user,
            'storage' => $storage->summaryFor($user),
            'perGroup' => $storage->perGroupFor($user),
        ]);
    }

    /** Avatar lama yang tersimpan di disk public (/storage/...) dihapus; avatar dari Google dibiarkan. */
    private function deleteLocalAvatar(string $avatarUrl): void
    {
        $path = (string) parse_url($avatarUrl, PHP_URL_PATH);
        if (! str_starts_with($path, '/storage/')) {
            return;
        }

        $relativePath = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
