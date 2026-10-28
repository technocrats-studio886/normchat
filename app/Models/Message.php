<?php

namespace App\Models;

use App\Enums\AiMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_id',
        'message_type',
        'sender_type',
        'sender_id',
        'reply_to_message_id',
        'reply_quote_text',
        'content',
        'attachment_disk',
        'attachment_path',
        'attachment_mime',
        'attachment_original_name',
        'attachment_size',
        'ai_model',
        'ai_mode',
        'deleted_by',
        'visible_to_user_id',
        'forwarded_from_name',
        'forwarded_from_message_id',
    ];

    protected $casts = [
        'ai_mode' => AiMode::class,
    ];

    /** Penanda yang membuat pesan diteruskan ke AI (Normi AI). */
    public const AI_MENTIONS = ['@normi'];

    /**
     * Pesan ditujukan ke AI bila menyebut @normi sebagai kata utuh (bukan @normianto),
     * atau membalas pesan AI.
     */
    public static function isAiPrompt(string $content, ?self $replyTarget = null): bool
    {
        foreach (self::AI_MENTIONS as $mention) {
            if (preg_match('/(?<![\\w@])' . preg_quote($mention, '/') . '(?![\\w])/iu', $content) === 1) {
                return true;
            }
        }

        return $replyTarget?->sender_type === 'ai';
    }

    /** Pemberitahuan Normi untuk satu anggota (mis. saldo habis), bukan jawaban untuk grup. */
    public function isNotice(): bool
    {
        return $this->visible_to_user_id !== null;
    }

    /** Pesan yang boleh dilihat user: semua kecuali pemberitahuan untuk anggota lain. */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(fn ($q) => $q->whereNull('visible_to_user_id')->orWhere('visible_to_user_id', $user->id));
    }

    public function isAddressedToAi(): bool
    {
        return self::isAiPrompt((string) $this->content, $this->replyToMessage);
    }

    public function hasImageAttachment(): bool
    {
        return strtolower((string) $this->message_type) === 'image'
            || str_starts_with(strtolower((string) $this->attachment_mime), 'image/');
    }

    public function hasAudioAttachment(): bool
    {
        $mime = strtolower((string) $this->attachment_mime);

        return strtolower((string) $this->message_type) === 'voice'
            || str_starts_with($mime, 'audio/')
            || $mime === 'video/webm';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MessageVersion::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Yang menghapus pesan untuk semua. */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /** Termasuk pesan yang sudah dihapus, agar kutipannya bisa tampil "Pesan ini dihapus". */
    public function replyToMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id')->withTrashed();
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }

    /** Biaya Normkredit untuk pesan jawaban AI ini. */
    public function aiUsage(): HasOne
    {
        return $this->hasOne(AiUsageLog::class, 'response_message_id');
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'poll_message_id');
    }
}
