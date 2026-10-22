<?php

namespace App\Services\Chat;

use App\Models\Message;

/** Bentuk pesan yang dikirim ke frontend (respons JSON dan event realtime). */
class MessagePresenter
{
    public function present(Message $message, ?string $senderName = null, array $extra = []): array
    {
        $message->loadMissing('replyToMessage.sender:id,name', 'sender:id,name');
        $replyTarget = $message->replyToMessage;
        $isEdited = (int) ($message->versions_count ?? $message->versions()->count()) > 0;

        return [
            'id' => $message->id,
            'message_type' => $message->message_type ?? 'text',
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName ?: $this->senderName($message),
            'content' => $message->content,
            'attachment_url' => $message->attachment_path
                ? route('chat.attachment', ['group' => $message->group_id, 'message' => $message->id])
                : null,
            'attachment_mime' => $message->attachment_mime,
            'attachment_original_name' => $message->attachment_original_name,
            'attachment_size' => $message->attachment_size,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'is_edited' => $isEdited,
            'edited_at' => $isEdited ? optional($message->updated_at)->toIso8601String() : null,
            'reply_to' => $replyTarget ? [
                'id' => (int) $replyTarget->id,
                'sender_name' => $this->senderName($replyTarget),
                'message_type' => (string) $replyTarget->message_type,
                'content' => (string) ($replyTarget->content ?? ''),
                'quote_text' => (string) ($message->reply_quote_text ?? ''),
                'attachment_original_name' => (string) ($replyTarget->attachment_original_name ?? ''),
            ] : null,
            ...$extra,
        ];
    }

    private function senderName(Message $message): string
    {
        return $message->sender_type === 'ai' ? 'NormAI' : ($message->sender?->name ?? 'User');
    }
}
