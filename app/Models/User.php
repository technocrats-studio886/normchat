<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'name',
    'username',
    'email',
    'avatar_url',
    'auth_provider',
    'provider_user_id',
    'interdotz_id',
    'password',
    'access_token_encrypted',
    'refresh_token_encrypted',
    'token_expires_at',
    'api_key_encrypted',
])]
#[Hidden(['password', 'remember_token', 'access_token_encrypted', 'refresh_token_encrypted', 'api_key_encrypted'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function aiConnection(): HasOne
    {
        return $this->hasOne(AiConnection::class);
    }

    // ── Token helpers ────────────────────────────────────────

    public function storeOAuthTokens(string $accessToken, ?string $refreshToken, ?\DateTimeInterface $expiresAt): void
    {
        $this->update([
            'access_token_encrypted' => Crypt::encryptString($accessToken),
            'refresh_token_encrypted' => $refreshToken ? Crypt::encryptString($refreshToken) : null,
            'token_expires_at' => $expiresAt,
        ]);
    }

    public function storeApiKey(string $apiKey): void
    {
        $this->update([
            'api_key_encrypted' => Crypt::encryptString($apiKey),
        ]);
    }

    public function getAccessToken(): ?string
    {
        return $this->access_token_encrypted
            ? Crypt::decryptString($this->access_token_encrypted)
            : null;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refresh_token_encrypted
            ? Crypt::decryptString($this->refresh_token_encrypted)
            : null;
    }

    public function getApiKey(): ?string
    {
        return $this->api_key_encrypted
            ? Crypt::decryptString($this->api_key_encrypted)
            : null;
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function hasValidCredentials(): bool
    {
        $connectedLlm = $this->aiConnection()->first();
        $hasLlmToken = $connectedLlm?->decryptedAccessToken() !== null;

        return ($this->access_token_encrypted && ! $this->isTokenExpired())
            || $this->api_key_encrypted
            || $hasLlmToken;
    }

    /** Username yang ditampilkan: username tersimpan, atau diturunkan dari ID penyedia / email. */
    public function displayUsername(): string
    {
        $clean = fn (string $value) => strtolower(substr((string) preg_replace('/[^A-Za-z0-9._-]/', '', $value), 0, 40));

        if ((string) $this->username !== '') {
            return (string) $this->username;
        }

        $providerId = (string) ($this->provider_user_id ?? $this->interdotz_id ?? '');
        if ($providerId !== '') {
            return $clean($providerId);
        }

        $emailPrefix = strstr((string) $this->email, '@', true);

        return is_string($emailPrefix) && $emailPrefix !== '' ? $clean($emailPrefix) : 'user' . $this->id;
    }

    /** Nama penyedia login untuk ditampilkan ke user. */
    public function authProviderLabel(): string
    {
        return match ($this->auth_provider) {
            'google' => 'Google',
            'interdotz' => 'Interdotz',
            default => ucfirst((string) $this->auth_provider),
        };
    }

    /** ID user di Interdotz untuk API pembayaran (ULID internal, fallback ID SSO). */
    public function interdotzUserId(): string
    {
        return (string) ($this->interdotz_id ?: $this->provider_user_id ?: '');
    }

    /**
     * Superadmin = email di NORMCHAT_SUPERADMIN_EMAILS. Mengakses panel
     * /superadmin dan melewati pembayaran.
     */
    public function isSuperadmin(): bool
    {
        $emails = array_map('strtolower', (array) config('normchat.superadmin_emails', []));

        return $this->email !== null && in_array(strtolower($this->email), $emails, true);
    }
}
