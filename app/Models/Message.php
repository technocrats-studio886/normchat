<?php

namespace App\Models;

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
    ];

    /** Penanda yang membuat pesan diteruskan ke AI. */
    public const AI_MENTIONS = ['@ai', '@openai', '@chatgpt'];

    /** Pesan ditujukan ke AI bila menyebut salah satu mention, atau membalas pesan AI. */
    public static function isAiPrompt(string $content, ?self $replyTarget = null): bool
    {
        $normalized = strtolower($content);
        foreach (self::AI_MENTIONS as $mention) {
            if (str_contains($normalized, $mention)) {
                return true;
            }
        }

        return $replyTarget?->sender_type === 'ai';
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

    public function replyToMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
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
