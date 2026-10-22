<?php

namespace App\Services\Credits;

/**
 * Normkredit yang masuk dari satu pembelian, beserta berapa persen yang dibagikan
 * ke kredit bersama (sisanya menjadi kredit terlindungi milik pembeli).
 */
final class Funding
{
    public const DEFAULT_SHARE_PERCENT = 50.0;

    public function __construct(
        public readonly float $credits,
        public readonly float $sharePercent = self::DEFAULT_SHARE_PERCENT,
        public readonly int $paidAmount = 0,
        public readonly ?string $reference = null,
        public readonly string $paymentMethod = 'midtrans',
    ) {
    }

    public static function bypass(float $credits, float $sharePercent, string $reference): self
    {
        return new self($credits, $sharePercent, 0, $reference, 'bypass');
    }

    public function isBypass(): bool
    {
        return $this->paymentMethod === 'bypass';
    }
}
