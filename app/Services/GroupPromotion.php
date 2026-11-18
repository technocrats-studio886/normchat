<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Satu penawaran yang dipakai marketing, form grup, dan snapshot checkout. */
class GroupPromotion
{
    public const TIMEZONE = 'Asia/Makassar';

    public function __construct(private readonly NormkreditPricing $pricing)
    {
    }

    public function offer(?\App\Services\Payments\PaymentQuote $quote = null): array
    {
        $settings = $this->pricing->settings();
        $quote ??= $this->pricing->quoteGroupCreation();
        $active = false;
        $start = $settings['group_promo_starts_at'] ?? '';
        $end = $settings['group_promo_ends_at'] ?? '';
        if (($settings['group_promo_enabled'] ?? false) && $start !== '' && $end !== '') {
            $now = CarbonImmutable::now('UTC');
            $active = $now->greaterThanOrEqualTo(CarbonImmutable::parse($start))
                && $now->lessThan(CarbonImmutable::parse($end));
        }
        $value = max(0, (float) ($settings['group_promo_value'] ?? 50));
        $bonus = $active ? round(($settings['group_promo_type'] ?? 'fixed') === 'percent'
            ? $quote->credits * $value / 100 : $value, 4) : 0.0;

        return [
            'base_credits' => $quote->credits,
            'base_amount' => $quote->baseAmount,
            'bonus_credits' => $bonus,
            'total_credits' => $quote->credits + $bonus,
            'active' => $active && $bonus > 0,
            'ends_at' => $end,
            'free_member_slots' => max(0, (int) ($settings['paid_group_member_slots'] ?? 2)),
        ];
    }

    public function sentence(): string
    {
        $offer = $this->offer();
        $nc = fn ($value) => rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
        $text = 'Dengan Rp' . number_format($offer['base_amount'], 0, ',', '.') . ', kamu mendapatkan '
            . $nc($offer['base_credits']) . ' NC';

        return $text . ($offer['active'] ? ' + bonus ' . $nc($offer['bonus_credits']) . ' NC selama promo berlaku.' : '.')
            . ' Saldo masuk setelah pembayaran berhasil.';
    }
}
