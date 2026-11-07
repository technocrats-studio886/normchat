<?php

namespace App\Services\Auth;

use App\Exceptions\AccountUnavailableException;
use App\Http\Middleware\EndIdleSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login lewat penyedia identitas (Google, Interdotz): cari user berdasarkan ID
 * di penyedia, lalu email; buat bila belum ada; lalu buka sesi. Akun yang dihapus atau diblokir
 * superadmin ditolak (AccountUnavailableException).
 */
class SocialLogin
{
    public function findOrCreate(
        string $provider,
        string $providerUserId,
        string $email,
        string $name,
        ?string $avatarUrl = null,
        ?string $username = null
    ): User {
        $user = User::withTrashed()
            ->where('auth_provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user?->trashed()) {
            throw new AccountUnavailableException('Akun ini sudah dihapus. Hubungi admin Normi Chat bila ingin memulihkannya.');
        }
        if ($user?->blocked_at !== null) {
            throw new AccountUnavailableException('Akun ini diblokir. Hubungi admin Normi Chat bila menurutmu ini keliru.');
        }

        if (! $user) {
            return User::create([
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'auth_provider' => $provider,
                'provider_user_id' => $providerUserId,
                'username' => $username ?: null,
                'email_verified_at' => now(),
            ]);
        }

        // Nama dan foto dari penyedia hanya bekal awal. Nama yang diubah pengguna di Akun tidak boleh tertimpa
        // nama Google di login berikutnya (laporan user 7 Nov 2026); foto yang diunggah sendiri juga dipertahankan.
        // Foto dari penyedia tetap disegarkan karena alamatnya bisa kedaluwarsa.
        $attributes = [
            'email' => $email,
            'auth_provider' => $provider,
            'provider_user_id' => $providerUserId,
        ];
        if (trim((string) $user->name) === '') {
            $attributes['name'] = $name;
        }
        if ($avatarUrl && ! self::isUploadedAvatar((string) $user->avatar_url)) {
            $attributes['avatar_url'] = $avatarUrl;
        }
        if (! $user->username && $username) {
            $attributes['username'] = $username;
        }

        $user->update($attributes);

        return $user;
    }

    /** Foto yang diunggah lewat Akun (ProfileController::updateAccount): /avatars/… atau /storage/… di normi.chat. */
    private static function isUploadedAvatar(string $avatarUrl): bool
    {
        $host = (string) parse_url($avatarUrl, PHP_URL_HOST);
        if ($host !== '' && $host !== (string) parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return false;
        }
        $path = (string) parse_url($avatarUrl, PHP_URL_PATH);

        return str_starts_with($path, '/avatars/') || str_starts_with($path, '/storage/');
    }

    public function signIn(User $user, Request $request, string $provider, string $method = 'sso'): void
    {
        // Tanpa "remember me": sesi berakhir setelah 5 hari tidak aktif (EndIdleSession).
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(EndIdleSession::LAST_ACTIVITY, now()->getTimestamp());

        AuditLog::record('auth.connect', $user->id, $user, [
            'provider' => $provider,
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
