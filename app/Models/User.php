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
}
