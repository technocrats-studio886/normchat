<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'role_id',
        'status',
        'invited_by',
        'approved_by',
        'joined_at',
        'last_read_message_id',
        'last_read_at',
        'history_from_message_id',
        'muted_until',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
        'muted_until' => 'datetime',
    ];

    /** Notifikasi grup ini sedang dibisukan. */
    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
