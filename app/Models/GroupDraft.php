<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Draf pesan yang belum dikirim, per user per grup; sinkron antar perangkat dan berlaku 24 jam. */
class GroupDraft extends Model
{
    public const TTL_HOURS = 24;

    public const MAX_LENGTH = 10000;

    protected $fillable = ['group_id', 'user_id', 'content', 'reply_to_message_id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Draf yang masih berlaku (disentuh dalam 24 jam terakhir). */
    public function scopeUnexpired(Builder $query): Builder
    {
        return $query->where('updated_at', '>=', now()->subHours(self::TTL_HOURS));
    }
}
