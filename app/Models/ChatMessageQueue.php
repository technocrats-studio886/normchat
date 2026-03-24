<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ChatMessageQueue extends Model
{
    protected $fillable = [
        'group_id',
        'message_id',
        'status',
        'queued_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
