<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Services\Payments\PaymentQuote;
use Illuminate\Support\Facades\Cache;

/**
 * Sumber tunggal harga Normkredit.
 *
 * Default ada di config('normchat.pricing'); superadmin menimpanya lewat tabel
 * app_settings. Kurs USD sengaja hanya dari .env.
 */
class NormkreditPricing
{
    private const CACHE_KEY = 'normchat:pricing-settings';

    /** Kunci yang boleh diubah superadmin, beserta tipenya. */
    public const EDITABLE = [
        'rupiah_per_credit' => 'int',
        'group_creation_credits' => 'int',
        'package_credits' => 'int',
        'max_packages_per_payment' => 'int',
        'qris_fee_percent' => 'float',
        'media_multiplier' => 'float',
        'max_credits_per_request' => 'float',
        'max_output_tokens' => 'int',
        'daily_group_credit_limit' => 'float',
    ];

    public function settings(): array
    {
        $overrides = Cache::rememberForever(self::CACHE_KEY, function () {
            return AppSetting::query()
                ->whereIn('key', array_keys(self::EDITABLE))
                ->pluck('value', 'key')
                ->all();
        });

        $settings = (array) config('normchat.pricing', []);
        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $settings[$key] = self::EDITABLE[$key] === 'int' ? (int) $value : (float) $value;
            }
        }

        return $settings;
    }

    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::EDITABLE) as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function get(string $key): int|float
    {
        return $this->settings()[$key];
    }

    public function rupiahPerCredit(): int
    {
        return max(1, (int) $this->get('rupiah_per_credit'));
    }

    // ── Pembelian ────────────────────────────────────────────

    public function groupCreationCredits(): int
    {
        return max(1, (int) $this->get('group_creation_credits'));
    }

    /** Satu paket patungan / top-up. */
    public function packageCredits(): int
    {
        return max(1, (int) $this->get('package_credits'));
    }

    public function maxPackagesPerPayment(): int
    {
        return max(1, (int) $this->get('max_packages_per_payment'));
    }

    public function quoteGroupCreation(): PaymentQuote
    {
        return $this->quote($this->groupCreationCredits());
    }

    public function quotePackages(int $packages): PaymentQuote
    {
        return $this->quote($packages * $this->packageCredits());
    }

    public function quote(int $credits): PaymentQuote
    {
        $baseAmount = $credits * $this->rupiahPerCredit();

        return new PaymentQuote($credits, $baseAmount, $this->serviceFee($baseAmount));
    }

    /** Persentase biaya layanan QRIS untuk ditampilkan: 0.7 → "0,7". */
    public function feePercentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->get('qris_fee_percent'), 2, ',', '.'), '0'), ',');
    }

    /** Biaya layanan QRIS, dibulatkan ke atas ke rupiah. */
    public function serviceFee(int $baseAmount): int
    {
        return (int) ceil(round($baseAmount * (float) $this->get('qris_fee_percent') / 100, 6));
    }

    // ── Pemakaian AI ─────────────────────────────────────────

    public function mediaMultiplier(): float
    {
        return max(0.0, (float) $this->get('media_multiplier'));
    }

    public function usdToIdr(): float
    {
        return (float) config('normchat.usd_to_idr', 17950);
    }

    /**
     * Konversi biaya provider (USD) × multiplier menjadi Normkredit yang ditagih.
     * Dibulatkan ke atas ke 0,01 NK supaya request kecil tetap tercatat.
     */
    public function creditsForCost(float $costUsd, float $multiplier = 1.0): float
    {
        if ($costUsd <= 0) {
            return 0.0;
        }

        $rupiah = $costUsd * $multiplier * $this->usdToIdr();

        return max(0.01, ceil(round($rupiah / $this->rupiahPerCredit() * 100, 6)) / 100);
    }
}
