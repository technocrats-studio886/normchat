<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Services\Files\StoredFiles;
use App\Services\Profile\StorageUsage;
use App\Support\Themes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const AVATAR_DISK = 'normchat_avatars';

    private const AVATAR_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function show(Request $request, StorageUsage $storage): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'storage' => $storage->summaryFor($user),
            'username' => $user->displayUsername(),
            'loginCount' => AuditLog::where('actor_id', $user->id)->whereIn('action', ProfileHistoryController::LOGIN_ACTIONS)->count(),
            'transactionCount' => GroupTokenContribution::where('user_id', $user->id)->count(),
            'theme' => Themes::resolve($user->theme),
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
            $this->deleteStoredAvatar((string) $user->avatar_url);
            $updates['avatar_url'] = route('avatars.show', $request->file('avatar')->store('', self::AVATAR_DISK));
        }

        $user->update($updates);

        return redirect()->route('profile.account')->with('success', 'Profil disimpan.');
    }

    /** Simpan tema tampilan; default disimpan sebagai kosong. */
    public function updateTheme(Request $request): JsonResponse
    {
        $theme = $request->validate(['theme' => ['required', 'string', 'in:' . implode(',', Themes::ids())]])['theme'];
        $request->user()->update(['theme' => $theme === Themes::DEFAULT ? null : $theme]);

        return response()->json(['ok' => true, 'theme' => $theme]);
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

    /** Foto profil unggahan (di S3), disajikan lewat cache Redis; hanya untuk user yang login. */
    public function avatar(string $filename, StoredFiles $files): Response
    {
        $body = $files->get(self::AVATAR_DISK, $filename);
        abort_if($body === null, 404);

        return response($body, 200, [
            'Content-Type' => self::AVATAR_TYPES[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    /**
     * Avatar unggahan lama dihapus: di disk avatar (/avatars/...) atau, untuk unggahan sebelum
     * pindah ke S3, di disk public (/storage/...). Avatar dari Google dibiarkan.
     */
    private function deleteStoredAvatar(string $avatarUrl): void
    {
        $path = (string) parse_url($avatarUrl, PHP_URL_PATH);
        if (str_starts_with($path, '/avatars/')) {
            $filename = basename($path);
            Storage::disk(self::AVATAR_DISK)->delete($filename);
            app(StoredFiles::class)->forget(self::AVATAR_DISK, $filename);

            return;
        }
        if (! str_starts_with($path, '/storage/')) {
            return;
        }

        $relativePath = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
