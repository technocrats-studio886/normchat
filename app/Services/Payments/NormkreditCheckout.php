<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Exceptions\CheckoutFailedException;
use App\Models\Group;
use App\Models\PendingPayment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Memulai pembayaran sejumlah Normkredit di gateway aktif dan mencatatnya sebagai
 * PendingPayment sampai webhook / halaman callback mengonfirmasi.
 */
class NormkreditCheckout
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * @return string URL halaman pembayaran
     *
     * @throws CheckoutFailedException
     */
    public function start(User $user, Group $group, PaymentPurpose $purpose, PaymentQuote $quote, array $metadata = []): string
    {
        if (! $this->gateway->isConfigured()) {
            throw new CheckoutFailedException('Sistem pembayaran belum dikonfigurasi.');
        }

        $orderId = $this->uniqueOrderId($purpose->orderPrefix());

        // Biaya layanan dilebur ke satu item agar tagihan QRIS hanya satu baris.
        $session = $this->gateway->createCheckout(new CheckoutRequest(
            customer: $user,
            orderId: $orderId,
            amount: $quote->total(),
            itemId: $purpose->itemId((int) $group->id),
            itemName: $purpose->itemName((string) $group->name, $quote->credits),
            finishUrl: route('groups.payment.callback', ['order' => $orderId]),
        ));

        PendingPayment::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'order_id' => $orderId,
            'payment_type' => $purpose->value,
            'expected_amount' => $quote->total(),
            'status' => 'pending',
            'metadata_json' => array_merge([
                'action' => $purpose->value,
                'group_name' => $group->name,
                ...$quote->toMetadata(),
                'payment_method' => 'qris',
                'gateway' => $this->gateway->name(),
                'payment_id' => $session->paymentId,
            ], $metadata),
            'expires_at' => now()->addHour(),
        ]);

        return $session->redirectUrl;
    }

    private function uniqueOrderId(string $prefix): string
    {
        do {
            $orderId = $prefix . '-' . now()->format('His') . '-' . strtoupper(Str::random(8));
        } while (PendingPayment::where('order_id', $orderId)->exists());

        return $orderId;
    }
}
