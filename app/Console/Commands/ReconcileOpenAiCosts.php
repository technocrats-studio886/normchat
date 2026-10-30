<?php

namespace App\Console\Commands;

use App\Services\Ai\CostReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Cocokkan biaya OpenAI dengan hitungan sistem; dijalankan tiap jam 07.00–23.00 WIB. Bawaannya kemarin (UTC,
 * data mungkin belum lengkap) dan lusa kemarin (sudah lengkap, jadi koreksi turun juga berlaku).
 */
class ReconcileOpenAiCosts extends Command
{
    protected $signature = 'normchat:reconcile-openai-costs {--date= : Hari UTC (Y-m-d), bawaan kemarin dan lusa kemarin}';

    protected $description = 'Bandingkan biaya nyata OpenAI dengan hitungan sistem dan koreksi pemakaian saldo OpenAI';

    public function handle(CostReconciliation $reconciliation): int
    {
        if (! $reconciliation->isConfigured()) {
            $this->info('Rekonsiliasi nonaktif: OPENAI_ADMIN_KEY / OPENAI_PROJECT_ID belum diisi.');

            return self::SUCCESS;
        }

        $days = $this->option('date')
            ? [CarbonImmutable::parse((string) $this->option('date'), 'UTC')]
            : [CarbonImmutable::now('UTC')->subDays(2), CarbonImmutable::now('UTC')->subDay()];

        foreach ($days as $day) {
            $row = $reconciliation->reconcile($day);
            if ($row === null) {
                $this->warn('Biaya OpenAI ' . $day->toDateString() . ' belum bisa dibaca; dicoba lagi nanti.');

                continue;
            }

            $this->info(sprintf('%s: OpenAI $%.4f, sistem $%.4f, selisih $%.4f.', $day->toDateString(), $row->openai_usd, $row->computed_usd, $row->diff_usd));
        }

        return self::SUCCESS;
    }
}
