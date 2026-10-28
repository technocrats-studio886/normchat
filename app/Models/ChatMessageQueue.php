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
        'replace_message_id',
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

    /**
     * Antrekan pesan untuk Normi AI, kecuali sudah ada antrean aktif untuknya.
     * $replace: jawaban lama yang diganti oleh jawaban baru (jawab ulang setelah pertanyaan diedit).
     */
    public static function enqueue(Message $message, ?Message $replace = null): void
    {
        $alreadyActive = static::query()
            ->where('message_id', $message->id)
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if (! $alreadyActive) {
            static::create([
                'group_id' => $message->group_id,
                'message_id' => $message->id,
                'status' => 'queued',
                'queued_at' => now(),
                'replace_message_id' => $replace?->id,
            ]);
        }
    }
}
