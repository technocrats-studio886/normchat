<?php

namespace App\Services\Payments;

use App\Exceptions\CheckoutFailedException;
use App\Models\PendingPayment;
use App\Models\User;
use App\Services\InterdotzService;

/**
 * Midtrans lewat API Interdotz, atas nama akun Interdotz milik user (butuh token
 * SSO Interdotz). Nonaktif selama NORMCHAT_INTERDOTZ_ENABLED=false.
 */
class InterdotzGateway implements PaymentGateway
{
    public function __construct(private readonly InterdotzService $interdotz)
    {
    }

    public function name(): string
    {
        return 'interdotz';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.interdotz.enabled') && $this->interdotz->isConfigured();
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $ssoToken = $request->customer->getAccessToken();
        if (! $ssoToken) {
            throw new CheckoutFailedException('Sesi login berakhir. Silakan logout dan login kembali.');
        }

        $response = $this->interdotz->createPayment(
            $ssoToken,
            $request->orderId,
            $request->amount,
            'IDR',
            $request->finishUrl,
            ['name' => (string) $request->customer->name, 'email' => (string) $request->customer->email, 'phone' => ''],
            [[
                'id' => $request->itemId,
                'name' => $request->itemName,
                'price' => $request->amount,
                'quantity' => 1,
            ]],
            $request->customer->interdotzUserId()
        );

        $payload = (array) ($response['payload'] ?? $response ?? []);
        $redirectUrl = (string) ($payload['redirect_url'] ?? $payload['redirectUrl'] ?? $payload['checkout_url'] ?? $payload['checkoutUrl'] ?? '');

        if ($redirectUrl === '') {
            throw new CheckoutFailedException($this->interdotz->getLastError() ?? 'Gagal membuat pembayaran Midtrans.');
        }

        return new CheckoutSession($redirectUrl, (string) ($payload['id'] ?? $payload['payment_id'] ?? ''));
    }

    public function fetchStatus(PendingPayment $pending, User $user): ?GatewayTransaction
    {
        $paymentId = (string) (($pending->metadata_json ?? [])['payment_id'] ?? '');
        $ssoToken = $user->getAccessToken();
        if ($paymentId === '' || ! $ssoToken) {
            return null;
        }

        $response = $this->interdotz->getPaymentStatus($ssoToken, $paymentId, $user->interdotzUserId());
        if (! $response) {
            return null;
        }

        $payload = (array) ($response['payload'] ?? $response);
        $status = strtolower((string) ($payload['status'] ?? ''));

        return new GatewayTransaction(
            GatewayStatus::state($status),
            $status,
            (int) ($payload['amount'] ?? $pending->expected_amount),
            (string) ($payload['gateway_transaction_id'] ?? $paymentId),
            (string) ($payload['payment_method'] ?? 'midtrans'),
            $payload['paid_at'] ?? now(),
        );
    }
}
