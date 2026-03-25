<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'approval_enabled' => 'boolean',
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

    public function groupToken(): HasOne
    {
        return $this->hasOne(GroupToken::class);
    }

    public function tokenContributions(): HasMany
    {
        return $this->hasMany(GroupTokenContribution::class);
    }

    public function getModelMultiplier(): float
    {
        $provider = $this->ai_provider;
        $model = $this->ai_model;

        if (! $provider || ! $model) {
            return 1.0;
        }

        return (float) config("ai_models.providers.{$provider}.models.{$model}.multiplier", 1.0);
    }

    public function getModelLabel(): string
    {
        $provider = $this->ai_provider;
        $model = $this->ai_model;

        if (! $provider || ! $model) {
            return 'Belum dipilih';
        }

        $providerLabel = config("ai_models.providers.{$provider}.label", ucfirst($provider));
        $modelLabel = config("ai_models.providers.{$provider}.models.{$model}.label", $model);

        return "{$providerLabel} - {$modelLabel}";
    }
}
