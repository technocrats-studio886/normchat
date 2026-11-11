<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pesan teks yang dikirim otomatis pada waktunya (App\Services\Chat\ScheduledMessages). */
class ScheduledMessage extends Model
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';

    protected $fillable = ['group_id', 'user_id', 'content', 'reply_to_message_id', 'ai_mode', 'ai_model', 'send_at', 'status', 'sent_message_id', 'error'];

    protected $casts = [
        'send_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
