<?php

namespace App\Services\Chat;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupDraft;
use App\Models\Message;
use App\Models\User;
use App\Services\Links\LinkPreviews;
use Illuminate\Support\Facades\Cache;

/**
 * Langkah setelah pesan anggota tersimpan (antrean Normi, realtime, pratinjau tautan, mention), dipakai bersama oleh
 * ChatController::store dan pesan terjadwal, supaya pesan terjadwal berperilaku persis seperti pesan yang diketik.
 */
class MessageSender
{
    public function __construct(
        private readonly MessagePresenter $presenter,
        private readonly MentionNotifier $mentions,
    ) {
    }

    /**
     * Kirim pesan teks atas nama $user (dipakai pesan terjadwal). Dikunci per grup seperti ChatController::store.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function sendText(Group $group, User $user, string $content, ?int $replyToId = null, ?string $aiMode = null, ?string $aiModel = null): Message
    {
        $message = Cache::lock('group-chat-submit:' . $group->id, 10)->block(5, function () use ($group, $user, $content, $replyToId, $aiMode, $aiModel) {
            $replyTarget = $replyToId ? Message::where('group_id', $group->id)->find($replyToId) : null;
            $message = Message::create([
                'group_id' => $group->id,
                'message_type' => 'text',
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'reply_to_message_id' => $replyTarget?->id,
                'content' => $content,
            ]);
            if (Message::isAiPrompt($content, $replyTarget)) {
                $model = $aiModel && AiModel::active()->where('model_id', $aiModel)->exists() ? $aiModel : null;
                $message->update(['ai_model' => $model, 'ai_mode' => AiMode::tryFrom((string) $aiMode) ?? AiMode::fromContent($content)]);
                ChatMessageQueue::enqueue($message);
            }
            AuditLog::record('chat.send_message', $user->id, $message, ['scheduled' => true], $group->id);

            return $message;
        });

        $this->afterCreate($group, $message, $user);

        return $message;
    }

    /** @return array<string, mixed> payload pesan yang dikirim ke browser */
    public function afterCreate(Group $group, Message $message, User $user): array
    {
        $message->loadCount('versions');
        $toAi = $message->isAddressedToAi();
        if ($toAi) {
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        $payload = $this->presenter->present($message, $user->name, [
            'group_credits_remaining' => $group->groupToken?->credits ?? 0,
            'awaiting_ai' => $toAi,
        ]);
        event(new MessageSent($group->id, $payload));
        // Pratinjau tautan dibaca di antrean; kartunya menyusul lewat event link.preview.ready.
        app(LinkPreviews::class)->forNewMessage($message);

        $content = trim((string) $message->content);
        if ($content !== '') {
            $this->mentions->notify($group, $message, $user, $content);
        }

        return $payload;
    }
}
