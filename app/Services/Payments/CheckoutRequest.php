<?php

namespace App\Services\Payments;

use App\Models\User;

final class CheckoutRequest
{
    public function __construct(
        public readonly User $customer,
        public readonly string $orderId,
        /** Total yang ditagih (harga paket + biaya layanan). */
        public readonly int $amount,
        public readonly string $itemId,
        public readonly string $itemName,
        public readonly string $finishUrl,
        /** Bagian dari $amount yang tampil sebagai baris tersendiri di halaman pembayaran. */
        public readonly int $serviceFee = 0,
        public readonly string $serviceFeeLabel = 'Biaya layanan',
    ) {
    }

    public function itemPrice(): int
    {
        return $this->amount - $this->serviceFee;
    }
}
