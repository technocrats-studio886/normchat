<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;

/** Statistik angka untuk superadmin, tanpa mengambil isi pesan. Tidak memanggil API provider. */
class CostDiagnostics
{
    public function lastThirtyDays(): array
    {
        $kinds = [];
        $reasoning = 0;
        $cacheWrites = 0;
        $input = 0;
        $cached = 0;
        AiUsageLog::query()->where('created_at', '>=', now()->subDays(30))
            ->select(['id', 'kind', 'provider_cost_usd', 'breakdown_json'])
            ->chunkById(500, function ($logs) use (&$kinds, &$reasoning, &$cacheWrites, &$input, &$cached) {
                foreach ($logs as $log) {
                    $kinds[$log->kind] ??= ['kind' => $log->kind, 'calls' => 0, 'usd' => 0.0];
                    $kinds[$log->kind]['calls']++;
                    $kinds[$log->kind]['usd'] += (float) $log->provider_cost_usd;
                    foreach ((array) ($log->breakdown_json['lines'] ?? []) as $line) {
                        $tokens = (array) ($line['tokens'] ?? []);
                        $reasoning += (int) ($tokens['reasoning_tokens'] ?? 0);
                        $cacheWrites += (int) ($tokens['cache_write_tokens'] ?? 0);
                        $input += (int) ($tokens['input_tokens'] ?? 0);
                        $cached += (int) ($tokens['cached_input_tokens'] ?? 0);
                    }
                }
            });
        usort($kinds, fn ($a, $b) => $b['usd'] <=> $a['usd']);

        return ['kinds' => $kinds, 'reasoning' => $reasoning, 'cache_writes' => $cacheWrites,
            'cache_hit_percent' => $input > 0 ? round(100 * $cached / $input, 2) : 0.0];
    }
}
