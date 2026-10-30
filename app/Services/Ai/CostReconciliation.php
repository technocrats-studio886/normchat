<?php

namespace App\Services\Ai;

use App\Models\AiCostReconciliation;
use App\Models\AiSpendHold;
use App\Services\Admin\AdminAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rekonsiliasi harian: biaya nyata satu hari (UTC, sama dengan bucket OpenAI) dari OpenAI Costs API
 * dibandingkan dengan biaya yang dicatat SpendGuard sendiri hari itu (ai_spend_holds yang selesai), lalu
 * selisihnya dikoreksi di SpendGuard. Pembandingnya sumber yang sama dengan yang dikoreksi, jadi biaya
 * yang tidak punya log pemakaian (mis. jawaban error) tidak terhitung dua kali.
 * Data OpenAI bisa terlambat beberapa jam, jadi hari yang sama boleh direkonsiliasi ulang; yang
 * diterapkan hanya perubahan sejak rekonsiliasi sebelumnya. Selama data hari itu belum dianggap lengkap,
 * koreksi hanya boleh menambah pemakaian (data parsial yang lebih kecil tidak boleh membuka anggaran).
 * Tagihan NC tidak pernah diubah.
 */
class CostReconciliation
{
    private const ENDPOINT = 'https://api.openai.com/v1/organization/costs';

    /** Data satu hari dianggap lengkap sekian jam setelah hari itu berakhir (UTC). */
    private const COMPLETE_AFTER_HOURS = 18;

    public function __construct(
        private readonly SpendGuard $spend,
        private readonly AdminAlerts $alerts,
    ) {
    }

    public function isConfigured(): bool
    {
        return (string) config('services.openai.admin_key') !== '' && (string) config('services.openai.project_id') !== '';
    }

    /** @return AiCostReconciliation|null null bila belum dikonfigurasi atau OpenAI belum bisa dibaca */
    public function reconcile(CarbonImmutable $dayUtc): ?AiCostReconciliation
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $start = $dayUtc->utc()->startOfDay();
        $openai = $this->openAiCost($start);
        if ($openai === null) {
            return null;
        }

        $computed = (float) AiSpendHold::query()
            ->where('status', AiSpendHold::SETTLED)
            ->whereBetween('updated_at', [$start, $start->endOfDay()])
            ->sum('actual_usd');
        $complete = now()->greaterThan($start->endOfDay()->addHours(self::COMPLETE_AFTER_HOURS));
        if ($openai <= 0 && $computed > 0 && ! $complete) {
            // Bucket sudah ada tapi biayanya belum masuk: belum siap, jangan dianggap $0.
            return null;
        }
        $diff = $openai - $computed;

        $row = AiCostReconciliation::query()->firstOrNew(['date' => $start->toDateString()]);
        $alreadyApplied = $row->exists ? (float) $row->applied_usd : 0.0;
        $target = $complete ? $diff : max($diff, $alreadyApplied);
        $this->spend->applyCorrection($start, $target - $alreadyApplied);

        $row->fill([
            'computed_usd' => $computed,
            'openai_usd' => $openai,
            'diff_usd' => $diff,
            'applied_usd' => $target,
            'diff_percent' => $computed > 0 ? round($diff / $computed * 100, 2) : null,
            'applied_at' => now(),
        ])->save();

        $threshold = (float) config('normchat.spend.reconcile_alert_percent', 5);
        if ($row->diff_percent !== null && abs($row->diff_percent) > $threshold && $complete) {
            $fmt = fn (float $value) => '$' . number_format($value, 4, ',', '.');
            $this->alerts->raise(
                'reconcile:' . $start->toDateString(),
                AdminAlerts::WARNING,
                'Biaya OpenAI ' . $start->toDateString() . ' beda ' . number_format($row->diff_percent, 1, ',', '.') . '% dari hitungan sistem',
                'Biaya nyata ' . $fmt($openai) . ', hitungan sistem ' . $fmt($computed) . '. Pemakaian saldo OpenAI sudah dikoreksi;'
                    . ' periksa apakah tarif model di versi harga masih sesuai dengan halaman harga OpenAI.'
            );
        }

        return $row;
    }

    /** Total biaya project Normi Chat satu hari dari Costs API (USD), atau null bila gagal / belum ada. */
    private function openAiCost(CarbonImmutable $start): ?float
    {
        $query = http_build_query([
            'start_time' => $start->getTimestamp(),
            'end_time' => $start->addDay()->getTimestamp(),
            'bucket_width' => '1d',
            'limit' => 1,
        ]) . '&project_ids[]=' . rawurlencode((string) config('services.openai.project_id'));

        $total = 0.0;
        $found = false;
        $page = null;
        try {
            do {
                $response = Http::withToken((string) config('services.openai.admin_key'))
                    ->timeout(30)
                    ->get(self::ENDPOINT . '?' . $query . ($page ? '&page=' . rawurlencode($page) : ''));
                if ($response->failed()) {
                    Log::warning('OpenAI costs request failed.', ['status' => $response->status()]);

                    return null;
                }

                foreach ((array) $response->json('data', []) as $bucket) {
                    $found = true;
                    foreach ((array) ($bucket['results'] ?? []) as $result) {
                        $total += (float) data_get($result, 'amount.value', 0);
                    }
                }
                $page = $response->json('next_page');
            } while ($page);
        } catch (Throwable $e) {
            Log::warning('OpenAI costs request exception.', ['error' => $e->getMessage()]);

            return null;
        }

        return $found ? $total : null;
    }
}
