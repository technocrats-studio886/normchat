<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChatMessageQueue extends Model
{
    protected $fillable = [
        'group_id',
        'message_id',
        'status',
        'queued_at',
        'processed_at',
        'error_message',
        'attempt',
        'activity',
        'retry_allowed',
        'replace_message_id',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'retry_allowed' => 'boolean',
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
        DB::transaction(function () use ($message, $replace) {
            Message::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $active = static::where('message_id', $message->id)->whereIn('status', ['queued', 'processing'])->exists();
            if (! $active) {
                static::create([
                    'group_id' => $message->group_id,
                    'message_id' => $message->id,
                    'status' => 'queued',
                    'queued_at' => now(),
                    'attempt' => 1,
                    'replace_message_id' => $replace?->id,
                ]);
            }
        });
    }

    /** Dipakai untuk tampilan dan diperiksa ulang di bawah kunci saat retry. */
    public function publicState(bool $activeGroup): array
    {
        $state = ['status' => $this->status, 'activity' => $this->activity, 'attempt' => $this->attempt, 'can_retry' => false];
        if ($this->status !== 'failed') {
            return $state;
        }

        if (Message::withTrashed()->where('reply_to_message_id', $this->message_id)->where('sender_type', 'ai')->exists()) {
            if (! $this->replace_message_id) {
                $state['status'] = 'processed';

                return $state;
            }

            // Jawab ulang setelah edit gagal; jawaban lama masih ada. Edit lagi untuk mencoba.
            return $state + ['hint' => 'resend'];
        }
        if (! $activeGroup) {
            return $state + ['hint' => 'group_inactive'];
        }
        // Jawab ulang setelah edit, tugas, dan pengingat tidak dicoba ulang otomatis; user cukup mengedit atau mengirim ulang.
        if (! $this->retry_allowed || $this->replace_message_id || $this->attempt < 1) {
            return $state + ['hint' => 'resend'];
        }

        $holds = CreditHold::where('request_message_id', $this->message_id);
        if ((clone $holds)->where('status', CreditHold::SETTLED)->where('amount', '>', 0)->exists()) {
            // Normkredit sudah terpotong tapi jawaban tidak tersimpan: perlu dicek admin.
            return $state + ['hint' => 'admin'];
        }
        if ((clone $holds)->where('status', CreditHold::HELD)->exists()) {
            $state['status'] = 'checking';
            return $state;
        }

        $state['can_retry'] = true;
        return $state;
    }
}
