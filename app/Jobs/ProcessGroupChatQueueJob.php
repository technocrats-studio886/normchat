<?php

namespace App\Jobs;

use App\Models\ChatMessageQueue;
use App\Models\Message;
use App\Services\Ai\GroupAiAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Memproses antrean pesan satu grup secara berurutan (dikunci per grup) dan
 * menyerahkan tiap pesan ke Normi AI.
 */
class ProcessGroupChatQueueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Deep Research, Canvas, dan gambar bisa memakan beberapa menit. */
    public int $timeout = 600;

    public function __construct(public int $groupId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $lock = Cache::lock('group-chat-queue:' . $this->groupId, $this->timeout);

        if (! $lock->get()) {
            $this->release(2);

            return;
        }

        try {
            while ($queueItem = $this->nextQueuedItem()) {
                $this->process($queueItem);
            }
        } finally {
            $lock->release();
        }
    }

    private function nextQueuedItem(): ?ChatMessageQueue
    {
        return ChatMessageQueue::query()
            ->where('group_id', $this->groupId)
            ->where('status', 'queued')
            ->orderBy('id')
            ->first();
    }

    private function process(ChatMessageQueue $queueItem): void
    {
        $queueItem->update(['status' => 'processing']);

        try {
            $message = Message::find($queueItem->message_id);
            if ($message) {
                $replace = $queueItem->replace_message_id ? Message::find($queueItem->replace_message_id) : null;
                app(GroupAiAssistant::class)->handle($message, $replace);
            }

            $queueItem->update(['status' => 'processed', 'processed_at' => now(), 'error_message' => null]);
        } catch (Throwable $e) {
            $queueItem->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 250),
                'processed_at' => now(),
            ]);
        }
    }
}
