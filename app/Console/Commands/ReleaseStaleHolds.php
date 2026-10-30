<?php

namespace App\Console\Commands;

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

        $this->info("Tahanan dilepas: {$credits->count()} Normkredit, {$spendHolds} biaya OpenAI.");

        return self::SUCCESS;
    }
}
