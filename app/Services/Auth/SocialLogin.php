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

        $attributes = [
            'name' => $name,
            'email' => $email,
            'avatar_url' => $avatarUrl ?: $user?->avatar_url,
            'auth_provider' => $provider,
            'provider_user_id' => $providerUserId,
        ];

        if (! $user) {
            return User::create($attributes + [
                'username' => $username ?: null,
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->username && $username) {
            $attributes['username'] = $username;
        }

        $user->update($attributes);

        return $user;
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
