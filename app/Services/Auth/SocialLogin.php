<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login lewat penyedia identitas (Google, Interdotz): cari user berdasarkan ID
 * di penyedia, lalu email; buat bila belum ada; lalu buka sesi.
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
        $user = User::query()
            ->where('auth_provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first()
            ?? User::query()->where('email', $email)->first();

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
        Auth::login($user, true);
        $request->session()->regenerate();

        AuditLog::record('auth.connect', $user->id, $user, [
            'provider' => $provider,
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
