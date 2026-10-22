<?php

namespace App\Services\Payments;

/**
 * Rincian satu pembelian Normkredit. `total` yang ditagih di QRIS sudah termasuk
 * biaya layanan, dan sama dengan gross_amount yang diverifikasi webhook Midtrans.
 */
final class PaymentQuote
{
    public function __construct(
        public readonly int $credits,
        public readonly int $baseAmount,
        public readonly int $serviceFee,
    ) {
    }

    public function total(): int
    {
        return $this->baseAmount + $this->serviceFee;
    }

    public function toMetadata(): array
    {
        return [
            'credits' => $this->credits,
            'base_amount' => $this->baseAmount,
            'service_fee' => $this->serviceFee,
        ];
    }
}
