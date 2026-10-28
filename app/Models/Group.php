<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'password_hash',
        'approval_enabled',
        'share_id',
        'ai_provider',
        'ai_model',
        'ai_persona_style',
        'ai_persona_guardrails',
        'status',
        'member_daily_credit_limit',
    ];

    protected $casts = [
        'approval_enabled' => 'boolean',
        'member_daily_credit_limit' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->share_id)) {
                $group->share_id = static::generateUniqueShareId();
            }
        });
    }

    public static function generateUniqueShareId(): string
    {
        do {
            $id = strtoupper(Str::random(6));
        } while (static::withTrashed()->where('share_id', $id)->exists());

        return $id;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function aiConnections(): HasMany
    {
        return $this->hasMany(AiConnection::class, 'user_id', 'owner_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(GroupBackup::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** Grup aktif yang dimiliki atau diikuti (sebagai member aktif) oleh user. */
    /** Dibekukan superadmin: anggota masih bisa membaca, tetapi tidak bisa mengirim pesan atau memanggil Normi. */
    public const STATUS_FROZEN = 'frozen';

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('status', ['active', self::STATUS_FROZEN])
            ->where(fn (Builder $q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->active()->where('user_id', $user->id)));
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->owner_id === (int) $user->id;
    }

    /** Owner atau admin grup: boleh menghapus pesan siapa pun. */
    public function isModerator(?User $user): bool
    {
        return $this->isOwnedBy($user) || ($user !== null && $this->members()
            ->active()
            ->where('user_id', $user->id)
            ->whereHas('role', fn (Builder $role) => $role->whereIn('key', [Role::OWNER, Role::ADMIN]))
            ->exists());
    }

    public function hasActiveMember(?User $user): bool
    {
        return $user !== null && $this->members()->active()->where('user_id', $user->id)->exists();
    }

    public function groupToken(): HasOne
    {
        return $this->hasOne(GroupToken::class);
    }

    public function tokenContributions(): HasMany
    {
        return $this->hasMany(GroupTokenContribution::class);
    }

    /**
     * Model AI yang dipakai grup: pilihan owner/admin bila masih aktif di katalog,
     * selain itu model default dari superadmin.
     */
    public function resolvedAiModel(): ?AiModel
    {
        if ($this->ai_model) {
            $selected = AiModel::active()->where('model_id', $this->ai_model)->first();
            if ($selected) {
                return $selected;
            }
        }

        return AiModel::defaultModel();
    }
}
