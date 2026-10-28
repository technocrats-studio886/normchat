<?php

namespace App\Services\Chat;

use App\Models\Message;

/** Bentuk pesan yang dikirim ke frontend (respons JSON dan event realtime). */
class MessagePresenter
{
    public function present(Message $message, ?string $senderName = null, array $extra = []): array
    {
        $message->loadMissing('replyToMessage.sender:id,name', 'sender:id,name,avatar_url');
        $replyTarget = $message->replyToMessage;
        $isEdited = (int) ($message->versions_count ?? $message->versions()->count()) > 0;
        $isDeleted = $message->trashed();

        return [
            'id' => $message->id,
            'message_type' => $message->message_type ?? 'text',
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName ?: $this->senderName($message),
            'sender_avatar_url' => $message->sender_type === 'ai' ? null : $message->sender?->avatar_url,
            // Isi pesan yang dihapus untuk semua tetap di server (ekspor), tapi tidak dikirim ke browser.
            'content' => $isDeleted ? null : $message->content,
            'attachment_url' => ! $isDeleted && $message->attachment_path
                ? route('chat.attachment', ['group' => $message->group_id, 'message' => $message->id])
                : null,
            'attachment_mime' => $isDeleted ? null : $message->attachment_mime,
            'attachment_original_name' => $isDeleted ? null : $message->attachment_original_name,
            'attachment_size' => $isDeleted ? null : $message->attachment_size,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'is_edited' => $isEdited && ! $isDeleted,
            'edited_at' => $isEdited ? optional($message->updated_at)->toIso8601String() : null,
            'is_deleted' => $isDeleted,
            'deleted_by_moderator' => $isDeleted && $message->sender_type === 'user'
                && $message->deleted_by !== null && (int) $message->deleted_by !== (int) $message->sender_id,
            // Pemberitahuan Normi hanya untuk penanya; jawaban = balasan Normi atas pertanyaan.
            'is_notice' => $message->isNotice(),
            'is_answer' => $message->sender_type === 'ai' && $replyTarget !== null && ! $message->isNotice(),
            'forwarded_from' => $isDeleted ? null : $message->forwarded_from_name,
            'reply_to' => $replyTarget && ! $isDeleted ? [
                'id' => (int) $replyTarget->id,
                'sender_name' => $this->senderName($replyTarget),
                'sender_type' => (string) $replyTarget->sender_type,
                'sender_id' => $replyTarget->sender_id ? (int) $replyTarget->sender_id : null,
                'message_type' => (string) $replyTarget->message_type,
                'is_deleted' => $replyTarget->trashed(),
                'content' => $replyTarget->trashed() ? '' : (string) ($replyTarget->content ?? ''),
                'quote_text' => $replyTarget->trashed() ? '' : (string) ($message->reply_quote_text ?? ''),
                'attachment_original_name' => $replyTarget->trashed() ? '' : (string) ($replyTarget->attachment_original_name ?? ''),
            ] : null,
            ...$extra,
        ];
    }

    public function senderName(Message $message): string
    {
        return $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota');
    }
}
