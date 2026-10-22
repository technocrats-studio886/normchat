<?php

namespace App\Services\Payments;

use App\Models\User;

final class CheckoutRequest
{
    public function __construct(
        public readonly User $customer,
        public readonly string $orderId,
        public readonly int $amount,
        public readonly string $itemId,
        public readonly string $itemName,
        public readonly string $finishUrl,
    ) {
    }
}
