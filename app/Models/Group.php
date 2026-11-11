<?php

namespace App\Models;

use App\Services\Ai\Context\MemoryMaintenance;
use App\Support\Bidi;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** Batas panjang nama dan deskripsi grup (keputusan user 5 Nov 2026). */
    public const NAME_MAX = 60;

    public const DESCRIPTION_MAX = 300;

    protected $fillable = [
        'name',
        'description',
        'avatar_path',
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
        'free_member_slots',
        'free_joins_used',
        'weekly_recap_enabled',
        'weekly_recap_day',
        'weekly_recap_hour',
        'weekly_recap_last_at',
    ];

    protected $casts = [
        'approval_enabled' => 'boolean',
        'member_daily_credit_limit' => 'float',
        'free_member_slots' => 'integer',
        'free_joins_used' => 'integer',
        'weekly_recap_enabled' => 'boolean',
        'weekly_recap_day' => 'integer',
        'weekly_recap_hour' => 'integer',
        'weekly_recap_last_at' => 'datetime',
    ];

    /** Karakter kendali arah dibuang (lihat App\Support\Bidi). */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => Bidi::strip($value));
    }

    /** Karakter kendali arah dibuang (lihat App\Support\Bidi). */
    protected function description(): Attribute
    {
        return Attribute::make(set: fn ($value) => Bidi::strip($value));
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->share_id)) {
                $group->share_id = static::generateUniqueShareId();
            }
        });
        // Ingatan Normi berisi isi chat: ikut dihapus bersama grup, dibangun ulang bila grup dipulihkan.
        static::deleted(fn (Group $group) => app(MemoryMaintenance::class)->purge((int) $group->id));
    }

    /** Grup gratis: dibuat tanpa bayar dan punya slot anggota gratis (App\Services\FreeGroups). */
    public function isFree(): bool
    {
        return (int) $this->free_member_slots > 0;
    }

    /** Sisa anggota yang masih bisa bergabung tanpa patungan. */
    public function freeSlotsLeft(): int
    {
        return max(0, (int) $this->free_member_slots - (int) $this->free_joins_used);
    }

    /** Foto grup untuk anggota; null bila grup memakai inisial. `v` memecah cache saat foto diganti. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('groups.avatar', ['group' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    /** Foto grup untuk calon anggota yang memegang kode grup (halaman /join/{kode}). */
    public function joinAvatarUrl(): ?string
    {
        return $this->avatar_path ? route('groups.join.avatar', ['shareId' => $this->share_id, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    /** Huruf pertama nama grup, dipakai bila belum ada foto. */
    public function initial(): string
    {
        return Str::upper(Str::substr((string) $this->name, 0, 1));
    }

    /** Grup tanpa kata sandi (grup gratis otomatis): gabung cukup dengan kode grup. */
    public function needsPassword(): bool
    {
        return filled($this->password_hash);
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
