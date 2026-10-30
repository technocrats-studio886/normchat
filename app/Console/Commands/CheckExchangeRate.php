<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminAlerts;
use App\Services\NormkreditPricing;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Ingatkan superadmin (banner + email) bila kurs harian belum diperbarui lebih dari 24 jam. */
class CheckExchangeRate extends Command
{
    protected $signature = 'normchat:check-exchange-rate';

    protected $description = 'Peringatkan superadmin bila kurs USD→IDR belum diperbarui';

    public function handle(NormkreditPricing $pricing, AdminAlerts $alerts): int
    {
        $rate = $pricing->rate();
        $hours = (int) config('normchat.spend.rate_stale_hours', 24);

        if ($rate === null) {
            $alerts->raise('rate:never', AdminAlerts::WARNING, 'Kurs USD→IDR belum pernah diisi',
                'Tagihan Normkredit memakai kurs cadangan dari .env (Rp' . number_format($pricing->usdToIdr(), 0, ',', '.')
                . '). Isi kurs hari ini di halaman Biaya OpenAI.');

            return self::SUCCESS;
        }

        $setAt = CarbonImmutable::parse($rate['created_at']);
        if ($setAt->lessThan(now()->subHours($hours))) {
            $alerts->raise('rate:stale:' . $rate['id'], AdminAlerts::WARNING, "Kurs USD→IDR belum diperbarui lebih dari {$hours} jam",
                'Kurs terakhir Rp' . number_format($rate['market_rate'], 0, ',', '.') . ' (diisi '
                . $setAt->setTimezone(UserTime::DEFAULT_ZONE)->translatedFormat('j M Y H.i')
                . '). Tagihan tetap memakai kurs itu sampai diperbarui.');
        }

        return self::SUCCESS;
    }
}
