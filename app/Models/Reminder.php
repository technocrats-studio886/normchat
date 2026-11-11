<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengingat grup atau pribadi (App\Services\Chat\Reminders). */
class Reminder extends Model
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';

    public const REPEATS = ['none', 'daily', 'weekly'];
    public const AUDIENCES = ['group', 'me'];

    protected $fillable = ['group_id', 'created_by', 'source_message_id', 'text', 'remind_at', 'repeat', 'audience', 'status', 'last_message_id', 'sent_count'];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_count' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPersonal(): bool
    {
        return $this->audience === 'me';
    }

    /** Pengingat ini terlihat oleh $user: pengingat grup, atau pengingat pribadinya sendiri. */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(fn ($q) => $q->where('audience', 'group')->orWhere('created_by', $user->id));
    }
}
