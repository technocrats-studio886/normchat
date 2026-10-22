<?php

namespace App\Services\Credits;

/** Normkredit yang masuk dari satu pembelian; seluruhnya menjadi kredit grup. */
final class Funding
{
    public function __construct(
        public readonly float $credits,
        public readonly int $paidAmount = 0,
        public readonly ?string $reference = null,
        public readonly string $paymentMethod = 'midtrans',
    ) {
    }

    public static function bypass(float $credits, string $reference): self
    {
        return new self($credits, 0, $reference, 'bypass');
    }

    public function isBypass(): bool
    {
        return $this->paymentMethod === 'bypass';
    }
}
