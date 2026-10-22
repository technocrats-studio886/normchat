<?php

namespace App\Services\Payments;

/** Status transaksi dari gateway, sudah dinormalisasi menjadi paid / failed / pending. */
final class GatewayTransaction
{
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const PENDING = 'pending';

    public function __construct(
        public readonly string $state,
        public readonly string $gatewayStatus,
        public readonly int $amount,
        public readonly string $reference,
        public readonly string $paymentMethod,
        public readonly mixed $paidAt = null,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this->state === self::FAILED;
    }
}
