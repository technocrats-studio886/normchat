<?php

namespace App\Services\Payments;

use App\Exceptions\CheckoutFailedException;
use App\Models\PendingPayment;
use App\Models\User;

/** Penyedia halaman pembayaran. Implementasi aktif dipilih lewat config normchat.payment_gateway. */
interface PaymentGateway
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @throws CheckoutFailedException */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** Status terkini transaksi di gateway, atau null bila belum bisa diketahui. */
    public function fetchStatus(PendingPayment $pending, User $user): ?GatewayTransaction;
}
