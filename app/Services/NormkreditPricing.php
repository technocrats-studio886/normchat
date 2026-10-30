<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ExchangeRate;
use App\Services\Payments\PaymentQuote;
use Illuminate\Support\Facades\Cache;

/**
 * Sumber tunggal harga Normkredit.
 *
 * Default ada di config('normchat.pricing'); superadmin menimpanya lewat tabel
 * app_settings (setiap perubahan juga menjadi versi harga, App\Services\Pricing\PricingVersions).
 * Kurs USD→IDR diisi superadmin setiap hari (tabel exchange_rates); .env hanya cadangan
 * sampai kurs pertama diisi.
 */
class NormkreditPricing
{
    private const CACHE_KEY = 'normchat:pricing-settings';

    private const RATE_CACHE_KEY = 'normchat:exchange-rate';

    /** Kurs yang dikunci untuk satu request AI (lihat locked()). */
    private ?array $lockedRate = null;

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
        'max_input_tokens' => 'int',
        'daily_group_credit_limit' => 'float',
        'card_margin_percent' => 'float',
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

    // ── Kurs ─────────────────────────────────────────────────

    /** Kurs yang dipakai menagih: dikunci untuk request ini, atau kurs harian terakhir, atau .env. */
    public function usdToIdr(): float
    {
        return (float) ($this->rate()['applied_rate'] ?? config('normchat.usd_to_idr', 17950));
    }

    /** Id kurs yang dipakai (null = kurs cadangan .env). */
    public function exchangeRateId(): ?int
    {
        return $this->rate()['id'] ?? null;
    }

    /**
     * Salinan dengan kurs saat ini dikunci, dipakai sepanjang satu request AI: hold, settle, dan log
     * memakai kurs yang sama walau superadmin mengganti kurs di tengah jalan.
     */
    public function locked(): static
    {
        $copy = clone $this;
        $copy->lockedRate = $this->rate() ?? [];

        return $copy;
    }

    /** @return array{id: int, market_rate: float, card_margin_percent: float, applied_rate: float, created_at: string}|null */
    public function rate(): ?array
    {
        if ($this->lockedRate !== null) {
            return $this->lockedRate === [] ? null : $this->lockedRate;
        }

        return Cache::rememberForever(self::RATE_CACHE_KEY, function () {
            $rate = ExchangeRate::query()->latest('id')->first();

            return $rate ? [
                'id' => $rate->id,
                'market_rate' => $rate->market_rate,
                'card_margin_percent' => $rate->card_margin_percent,
                'applied_rate' => $rate->applied_rate,
                'created_at' => $rate->created_at->toIso8601String(),
            ] : null;
        });
    }

    public function cardMarginPercent(): float
    {
        return max(0.0, (float) $this->get('card_margin_percent'));
    }

    /** Kurs pasar dari superadmin; yang dipakai menagih = kurs × (1 + selisih kartu). */
    public function setExchangeRate(float $marketRate, ?int $userId, ?string $note = null): ExchangeRate
    {
        $margin = $this->cardMarginPercent();
        $rate = ExchangeRate::create([
            'market_rate' => round($marketRate, 2),
            'card_margin_percent' => $margin,
            'applied_rate' => round($marketRate * (1 + $margin / 100), 2),
            'set_by' => $userId,
            'note' => $note,
        ]);
        Cache::forget(self::RATE_CACHE_KEY);

        return $rate;
    }

    /**
     * Konversi biaya provider (USD) × multiplier menjadi Normkredit yang ditagih.
     * Presisi 0,0001 NC (sama dengan kolom saldo), dibulatkan ke atas di digit keempat.
     */
    public function creditsForCost(float $costUsd, float $multiplier = 1.0): float
    {
        if ($costUsd <= 0) {
            return 0.0;
        }

        $rupiah = $costUsd * $multiplier * $this->usdToIdr();

        return max(0.0001, ceil(round($rupiah / $this->rupiahPerCredit() * 10000, 6)) / 10000);
    }
}
