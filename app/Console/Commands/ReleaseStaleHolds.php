<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\CreditHold;
use App\Services\Ai\SpendGuard;
use App\Services\Credits\CreditWallet;
use Illuminate\Console\Command;

/**
 * Tahanan Normkredit dan biaya OpenAI yang tidak pernah diselesaikan (job mati di tengah jalan)
 * dikembalikan setelah batas menit di config('normchat.spend.stale_hold_minutes').
 */
class ReleaseStaleHolds extends Command
{
    protected $signature = 'normchat:release-stale-holds';

    protected $description = 'Kembalikan tahanan Normkredit dan biaya OpenAI yang macet';

    public function handle(CreditWallet $wallet, SpendGuard $spend): int
    {
        $before = now()->subMinutes((int) config('normchat.spend.stale_hold_minutes', 15));
        $credits = CreditHold::query()->where('status', CreditHold::HELD)->where('created_at', '<', $before)->get();
        $credits->each(fn (CreditHold $hold) => $wallet->release($hold));
        $spendHolds = $spend->releaseStale();

        // A fatal worker exit skips its catch/finally. Never replay a possibly paid request.
        $stale = ChatMessageQueue::where('status', 'processing')->where('updated_at', '<', min($before, now()->subSeconds(660)));
        (clone $stale)->whereExists(function ($query) {
            $query->selectRaw('1')->from('messages as answer')
                ->whereColumn('answer.reply_to_message_id', 'chat_message_queues.message_id')
                ->whereColumn('answer.group_id', 'chat_message_queues.group_id')
                ->whereColumn('answer.updated_at', '>=', 'chat_message_queues.updated_at')
                ->where('answer.sender_type', 'ai')->whereNull('answer.deleted_at');
        })->update(['status' => 'processed', 'processed_at' => now()]);
        $stale->update(['status' => 'failed', 'processed_at' => now(), 'error_message' => 'Worker berhenti sebelum jawaban selesai.']);

        // Pulihkan juga kiriman yang tersimpan sebelum job sempat masuk ke Redis.
        ChatMessageQueue::where('status', 'queued')->where('queued_at', '<', now()->subMinutes(2))
            ->distinct()->pluck('group_id')->each(fn ($groupId) => ProcessGroupChatQueueJob::dispatch((int) $groupId));

        $this->info("Tahanan dilepas: {$credits->count()} Normkredit, {$spendHolds} biaya OpenAI.");

        return self::SUCCESS;
    }
}
