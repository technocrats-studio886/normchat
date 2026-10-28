<?php

namespace App\Services\Chat;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\MessageVersion;
use App\Models\PollVote;
use App\Models\User;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aksi pada pesan yang sudah terkirim: edit (dengan jawab ulang Normi), versi, hapus untuk
 * semua, hapus untuk saya, dan teruskan ke grup lain.
 */
class MessageActions
{
    /** Jenis pesan yang teksnya boleh diedit (teks biasa, atau keterangan gambar/video). */
    private const EDITABLE_TYPES = ['text', 'image', 'video'];

    public function __construct(
        private readonly MessagePresenter $presenter,
        private readonly MentionNotifier $mentions,
        private readonly StoredFiles $files,
        private readonly PollService $polls,
    ) {
    }

    public function canEdit(User $user, Message $message): bool
    {
        return ! $message->trashed()
            && $message->sender_type === 'user'
            && (int) $message->sender_id === (int) $user->id
            && $message->forwarded_from_name === null
            && in_array($message->message_type ?? 'text', self::EDITABLE_TYPES, true)
            && $this->polls->parse($message->content) === null;
    }

    /** Hapus untuk semua: pesan sendiri, pesan siapa pun bagi moderator, dan jawaban Normi bagi semua anggota. */
    public function canDeleteForAll(User $user, Group $group, Message $message): bool
    {
        if ($message->trashed() || $message->isNotice()) {
            return false;
        }

        if ($message->sender_type === 'ai') {
            return true;
        }

        return (int) $message->sender_id === (int) $user->id || $group->isModerator($user);
    }

    /** Normi masih menyusun jawaban untuk pesan ini (antrean aktif). */
    public function isAwaitingAi(Message $message): bool
    {
        return ChatMessageQueue::where('message_id', $message->id)->whereIn('status', ['queued', 'processing'])->exists();
    }

    /** Jawaban Normi terakhir untuk sebuah pertanyaan. */
    public function answerTo(Message $question): ?Message
    {
        return Message::where('reply_to_message_id', $question->id)
            ->where('sender_type', 'ai')
            ->whereNull('visible_to_user_id')
            ->latest('id')
            ->first();
    }

    /**
     * Simpan edit. $regenerate: pesan yang ditujukan ke Normi dijawab ulang (memakai Normkredit);
     * jawaban lama diganti di tempat, kecuali sudah dibalas anggota lain.
     *
     * @throws RuntimeException pesan aman ditampilkan ke user
     */
    public function edit(User $user, Group $group, Message $message, string $content, bool $regenerate, ?string $aiModel = null): Message
    {
        if (! $this->canEdit($user, $message)) {
            throw new RuntimeException('Pesan ini tidak bisa diedit.');
        }
        if ($this->isAwaitingAi($message)) {
            throw new RuntimeException('Tunggu Normi selesai menjawab.');
        }

        $previous = (string) ($message->content ?? '');
        if ($previous === $content) {
            return $message;
        }

        $answer = $this->answerTo($message);
        DB::transaction(function () use ($user, $message, $previous, $content, $answer) {
            MessageVersion::create([
                'message_id' => $message->id,
                'version_number' => ((int) $message->versions()->max('version_number')) + 1,
                'content_snapshot' => $previous,
                'answer_snapshot' => $answer ? $this->snapshotOf($answer) : null,
                'edited_by' => $user->id,
                'edited_at' => now(),
            ]);
            $message->update(['content' => $content]);
        });

        // Hanya mention yang baru ditambahkan yang diberi notifikasi.
        $newHandles = array_values(array_diff($this->mentions->handlesIn($content), $this->mentions->handlesIn($previous)));
        $this->mentions->notify($group, $message, $user, $content, $newHandles);

        if ($regenerate && $message->isAddressedToAi()) {
            // Jawab ulang memakai model yang sedang dipilih di composer, bila masih aktif.
            if ($aiModel !== null && AiModel::active()->where('model_id', $aiModel)->exists()) {
                $message->update(['ai_model' => $aiModel]);
            }
            $replace = $answer && ! $answer->replies()->exists() ? $answer : null;
            ChatMessageQueue::enqueue($message, $replace);
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        AuditLog::record('chat.edit_message', $user->id, $message, [
            'old_length' => mb_strlen($previous),
            'new_length' => mb_strlen($content),
            'regenerate' => $regenerate,
        ], $group->id);

        return $message->refresh()->loadCount('versions');
    }

    /**
     * Semua versi pesan, dari yang pertama sampai yang sekarang, masing-masing dengan jawaban
     * Normi saat itu (null bila tidak ada).
     *
     * @return list<array{number: int, content: string, edited_at: ?string, answer: ?array}>
     */
    public function versions(Message $message): array
    {
        $rows = $message->versions()->orderBy('version_number')->get();
        $current = $this->answerTo($message);
        $versions = [];

        foreach ($rows as $index => $row) {
            $versions[] = [
                'number' => $index + 1,
                'content' => (string) $row->content_snapshot,
                'created_at' => optional($index === 0 ? $message->created_at : $rows[$index - 1]->edited_at)->toIso8601String(),
                'answer' => $row->answer_snapshot,
            ];
        }

        $versions[] = [
            'number' => count($versions) + 1,
            'content' => (string) $message->content,
            'created_at' => optional($rows->last()?->edited_at ?? $message->created_at)->toIso8601String(),
            'answer' => $current ? $this->snapshotOf($current) : null,
        ];

        return $versions;
    }

    public function deleteForAll(User $user, Group $group, Message $message): void
    {
        DB::transaction(function () use ($user, $message) {
            PollVote::where('poll_message_id', $message->id)->delete();
            $message->update(['deleted_by' => $user->id]);
            $message->delete();
        });

        event(new MessageDeleted((int) $group->id, $this->presenter->present($message)));
        AuditLog::record('chat.delete_message', $user->id, $message, [
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'message_type' => $message->message_type,
        ], $group->id);
    }

    /**
     * Hapus untuk saya. $messageIds null = semua pesan grup sampai sekarang ("Bersihkan chat").
     *
     * @param  list<int>|null  $messageIds
     */
    public function hideFor(User $user, Group $group, ?array $messageIds): int
    {
        $ids = Message::withTrashed()
            ->where('group_id', $group->id)
            ->when($messageIds !== null, fn ($query) => $query->whereIn('id', $messageIds))
            ->whereNotIn('id', MessageHide::where('user_id', $user->id)->select('message_id'))
            ->select(DB::raw((int) $user->id . ' as user_id'), 'id as message_id', DB::raw('CURRENT_TIMESTAMP as created_at'));

        return MessageHide::query()->insertUsing(['user_id', 'message_id', 'created_at'], $ids);
    }

    /**
     * Teruskan pesan ke grup lain milik user. Lampiran disalin; @normi di pesan terusan tidak
     * memanggil Normi.
     *
     * @param  iterable<Group>  $targets
     * @return int jumlah grup tujuan
     */
    public function forward(User $user, Message $source, iterable $targets): int
    {
        $count = 0;
        foreach ($targets as $target) {
            $copy = Message::create([
                'group_id' => $target->id,
                'message_type' => $source->message_type ?? 'text',
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'content' => $source->content,
                'forwarded_from_name' => $source->forwarded_from_name ?? $this->presenter->senderName($source),
                'forwarded_from_message_id' => $source->id,
                ...$this->copyAttachment($source, $target),
            ]);

            event(new MessageSent((int) $target->id, $this->presenter->present($copy, $user->name)));
            AuditLog::record('chat.forward_message', $user->id, $copy, ['from_message_id' => $source->id], $target->id);
            $count++;
        }

        return $count;
    }

    /** @return array<string, mixed> kolom lampiran untuk salinan di grup tujuan */
    private function copyAttachment(Message $source, Group $target): array
    {
        if (! $source->attachment_path || ! $source->attachment_disk) {
            return [];
        }

        $path = sprintf('group-%d/%s-%s', $target->id, now()->format('YmdHis'), basename((string) $source->attachment_path));
        $this->files->copy((string) $source->attachment_disk, (string) $source->attachment_path, $path);

        return [
            'attachment_disk' => $source->attachment_disk,
            'attachment_path' => $path,
            'attachment_mime' => $source->attachment_mime,
            'attachment_original_name' => $source->attachment_original_name,
            'attachment_size' => $source->attachment_size,
        ];
    }

    /** Isi jawaban Normi yang disimpan bersama versi pertanyaannya. */
    private function snapshotOf(Message $answer): array
    {
        return [
            'content' => (string) ($answer->content ?? ''),
            'message_type' => (string) ($answer->message_type ?? 'text'),
            'attachment_original_name' => $answer->attachment_original_name,
        ];
    }
}
