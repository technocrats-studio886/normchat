<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ringkasan satu pembahasan di grup (lapisan kedua ingatan Normi). */
class GroupContextTopic extends Model
{
    protected $fillable = [
        'group_id',
        'title',
        'summary',
        'first_message_id',
        'last_message_id',
        'key_message_ids',
        'embedding',
        'last_activity_at',
        'stale_at',
    ];

    protected $casts = [
        'first_message_id' => 'integer',
        'last_message_id' => 'integer',
        'key_message_ids' => 'array',
        'last_activity_at' => 'datetime',
        'stale_at' => 'datetime',
    ];

    protected $hidden = ['embedding'];

    /** Topik yang masih valid dan cakupannya berakhir sebelum pesan $beforeMessageId. */
    public function scopeUsableBefore(Builder $query, int $groupId, int $beforeMessageId): Builder
    {
        return $query->where('group_id', $groupId)
            ->whereNull('stale_at')
            ->where('last_message_id', '<', $beforeMessageId);
    }
}
