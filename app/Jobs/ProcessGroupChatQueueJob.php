<?php

namespace App\Jobs;

use App\Models\ChatMessageQueue;
use App\Models\Message;
use App\Services\Ai\GroupAiAssistant;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Satu permintaan Normi per job, berurutan per grup (kunci group-chat-queue:{id}); grup lain dikerjakan proses
 * lain secara paralel (docs/rancangan-worker-dan-file-nov.md).
 *
 * Job yang tidak mendapat kunci langsung selesai: pemegang kunci selalu memeriksa ulang antrean grup setelah
 * melepas kunci dan mengirim job berikutnya, jadi tidak ada permintaan yang tertinggal. Jaring terakhirnya
 * normchat:release-stale-holds.
 */
class ProcessGroupChatQueueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Deep Research, Canvas, dan gambar bisa memakan beberapa menit; batas ini kini per permintaan. */
    public int $timeout = 600;

    public function __construct(public int $groupId)
    {
        $this->onQueue(Queues::AI);
    }

    public function handle(): void
    {
        $lock = Cache::lock(self::lockKey($this->groupId), $this->timeout + 30);
        if (! $lock->get()) {
            return;
        }

        try {
            $queueItem = $this->nextQueuedItem();
            if ($queueItem) {
                $this->process($queueItem);
            }
        } finally {
            $lock->release();
        }

        if ($this->nextQueuedItem()) {
            self::dispatch($this->groupId);
        }
    }

    public static function lockKey(int $groupId): string
    {
        return 'group-chat-queue:' . $groupId;
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
        $claimed = ChatMessageQueue::whereKey($queueItem->id)->where('status', 'queued')->update([
            'status' => 'processing', 'attempt' => max(1, $queueItem->attempt), 'retry_allowed' => $queueItem->replace_message_id === null,
        ]);
        if (! $claimed) {
            return;
        }

        $queueItem->refresh();
        try {
            $message = Message::find($queueItem->message_id);
            $completed = true;
            if ($message) {
                $replace = $queueItem->replace_message_id ? Message::find($queueItem->replace_message_id) : null;
                $completed = app(GroupAiAssistant::class)->handle($message, $replace, $queueItem);
            }

            $queueItem->update(['status' => $completed ? 'processed' : 'failed', 'processed_at' => now(), 'error_message' => null]);
        } catch (Throwable $e) {
            report($e);
            $queueItem->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 250),
                'processed_at' => now(),
            ]);
        }
    }

    /** Tag Horizon: job satu grup bisa dicari di metrik. */
    public function tags(): array
    {
        return ['group:' . $this->groupId];
    }
}
