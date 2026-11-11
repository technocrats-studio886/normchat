<?php

namespace App\Services\Chat;

use App\Enums\AiMode;
use App\Models\GroupMember;
use App\Models\Message;
use App\Services\Ai\AiActivity;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\Links\LinkPreviews;

/** Bentuk pesan yang dikirim ke frontend (respons JSON dan event realtime). */
class MessagePresenter
{
    public function present(Message $message, ?string $senderName = null, array $extra = []): array
    {
        $message->loadMissing('replyToMessage.sender:id,name', 'sender:id,name,avatar_url');
        $replyTarget = $message->replyToMessage;
        $isEdited = (int) ($message->versions_count ?? $message->versions()->count()) > 0;
        $isDeleted = $message->trashed();

        $presented = [
            'id' => $message->id,
            'message_type' => $message->message_type ?? 'text',
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName ?: $this->senderName($message),
            'sender_avatar_url' => $message->sender_type === 'ai' ? null : $message->sender?->avatar_url,
            // Isi pesan yang dihapus untuk semua tetap di server (ekspor), tapi tidak dikirim ke browser.
            'content' => $isDeleted ? null : $message->content,
            'attachment_url' => ! $isDeleted && $message->attachment_path
                ? self::attachmentUrl($message)
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
            // Fitur Normi pesan ini: mode permintaan, atau fitur yang menghasilkan jawaban Normi.
            'ai_mode' => $isDeleted ? null : $this->aiModeOf($message),
            // Tombol "pakai fitur" di bawah jawaban Normi (GroupAiAssistant::suggestionMeta).
            'suggestion' => $isDeleted ? null : $this->suggestionOf($message),
            // ZIP kode proyek asli di samping Canvas (Next.js, Laravel, Flutter, ...).
            'canvas_project' => ! $isDeleted && $message->message_type === CanvasResponder::MESSAGE_TYPE && ! empty($message->system_meta['canvas_project_path'] ?? null) ? [
                'url' => route('chat.canvas.project', ['group' => $message->group_id, 'message' => $message->id]),
                'name' => (string) ($message->system_meta['canvas_project_name'] ?? 'proyek.zip'),
                'size' => (int) ($message->system_meta['canvas_project_size'] ?? 0),
                'files' => (int) ($message->system_meta['canvas_project_files'] ?? 0),
            ] : null,
            // Pengingat yang dibuat jawaban Normi ini (tombol Batalkan selama masih menunggu).
            'reminder' => $isDeleted ? null : $this->reminderOf($message),
            // Jawaban Normi yang menambahkan tugas: tombol "Buka Tugas grup".
            'tasks_added' => ! $isDeleted && $message->sender_type === 'ai' ? (int) ($message->system_meta['tasks_added'] ?? 0) : 0,
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
            // Kartu pratinjau tautan pertama (docs/rancangan-pratinjau-tautan.md); null sampai dibaca.
            'link_preview' => $isDeleted || $message->sender_type !== 'user' ? null : app(LinkPreviews::class)->cardFor($message),
            // Pemberitahuan di tengah chat (App\Services\Chat\SystemMessages); teksnya disusun di browser per penonton.
            'system' => $message->sender_type === Message::SENDER_SYSTEM ? [
                'event' => (string) $message->system_event,
                'meta' => (array) ($message->system_meta ?? []),
                'text' => (string) $message->content,
                // Pemilik masih bisa mengirim riwayat ke anggota yang bergabung ini.
                'history_pending' => $message->system_event === SystemMessages::MEMBER_JOINED && $this->historyPending($message),
            ] : null,
            ...$extra,
        ];

        // Pertanyaan yang masih dikerjakan Normi: status terakhirnya (AiActivity), untuk anggota yang baru membuka chat.
        if (($presented['awaiting_ai'] ?? false) === true && ! array_key_exists('ai_activity', $presented)) {
            $presented['ai_activity'] = AiActivity::current((int) $message->group_id);
        }

        return $presented;
    }

    /** @return array{id: int, status: string, when: string, created_by: ?int, personal: bool}|null */
    private function reminderOf(Message $message): ?array
    {
        $id = (int) ($message->system_meta['reminder_id'] ?? 0);
        if ($message->sender_type !== 'ai' || $id <= 0 || ! empty($message->system_meta['reminder_fired'] ?? null)) {
            return null;
        }
        $reminder = \App\Models\Reminder::find($id);
        if (! $reminder || (int) $reminder->group_id !== (int) $message->group_id) {
            return null;
        }

        return [
            'id' => (int) $reminder->id,
            'status' => (string) $reminder->status,
            'when' => Reminders::whenLabel($reminder, $reminder->creator),
            'created_by' => $reminder->created_by ? (int) $reminder->created_by : null,
            'personal' => $reminder->isPersonal(),
        ];
    }

    /** @return array{mode: string, tag: string, label: string, reply_to: int, text: string}|null */
    private function suggestionOf(Message $message): ?array
    {
        if ($message->sender_type !== 'ai') {
            return null;
        }
        $meta = (array) ($message->system_meta ?? []);
        $mode = AiMode::tryFrom((string) ($meta['suggest_mode'] ?? ''));
        if ($mode === null) {
            return null;
        }

        return [
            'mode' => $mode->value,
            'tag' => $mode->tag(),
            'label' => $mode->label(),
            'reply_to' => (int) ($meta['suggest_reply_to'] ?? $message->reply_to_message_id ?? 0),
            'text' => (string) ($meta['suggest_text'] ?? ''),
        ];
    }

    /**
     * URL lampiran berversi: isi lampiran satu pesan bisa berganti (jawab ulang Normi), jadi URL ikut
     * berubah dan browser boleh menyimpan file di cache selamanya (ChatController::attachment).
     */
    public static function attachmentUrl(Message $message): string
    {
        return route('chat.attachment', [
            'group' => $message->group_id,
            'message' => $message->id,
            'v' => self::attachmentVersion($message),
        ]);
    }

    public static function attachmentVersion(Message $message): string
    {
        return substr(hash('xxh3', $message->attachment_disk . '|' . $message->attachment_path . '|' . $message->attachment_size), 0, 12);
    }

    private function aiModeOf(Message $message): ?string
    {
        if ($message->ai_mode) {
            return $message->ai_mode->value;
        }
        if ($message->sender_type !== 'ai' || $message->isNotice()) {
            return null;
        }

        // Jawaban lama (sebelum jawaban ditandai modenya): ikuti mode pertanyaannya atau jenis lampirannya.
        return $message->replyToMessage?->ai_mode?->value ?? match ((string) $message->message_type) {
            'image' => \App\Enums\AiMode::Image->value,
            CanvasResponder::MESSAGE_TYPE => \App\Enums\AiMode::Canvas->value,
            default => null,
        };
    }

    private function historyPending(Message $message): bool
    {
        $memberId = (int) ($message->system_meta['actor_id'] ?? 0);

        return $memberId > 0 && GroupMember::query()
            ->where('group_id', $message->group_id)
            ->where('user_id', $memberId)
            ->where('status', 'active')
            ->whereNotNull('history_from_message_id')
            ->exists();
    }

    public function senderName(Message $message): string
    {
        return match ($message->sender_type) {
            'ai' => 'Normi AI',
            Message::SENDER_SYSTEM => 'Normi Chat',
            default => $message->sender?->name ?? 'Anggota',
        };
    }
}
