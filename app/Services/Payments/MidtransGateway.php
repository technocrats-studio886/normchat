<?php

namespace App\Services\Payments;

use App\Exceptions\CheckoutFailedException;
use App\Models\PendingPayment;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Midtrans Snap langsung dengan server key Normchat.
 * Docs: Snap API (/snap/v1/transactions), Status API (/v2/{order_id}/status),
 * notifikasi diverifikasi dengan SHA512(order_id + status_code + gross_amount + server_key).
 */
class MidtransGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    public function isConfigured(): bool
    {
        return $this->serverKey() !== '';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        try {
            $response = $this->client()->post($this->snapBaseUrl() . '/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $request->orderId,
                    'gross_amount' => $request->amount,
                ],
                'item_details' => [[
                    'id' => $request->itemId,
                    'price' => $request->amount,
                    'quantity' => 1,
                    'name' => Str::limit($request->itemName, 50, ''),
                ]],
                // QRIS saja; bila hanya satu metode, Snap langsung membuka alur QRIS.
                'enabled_payments' => ['other_qris'],
                'customer_details' => [
                    'first_name' => Str::limit((string) $request->customer->name, 255, ''),
                    'email' => (string) $request->customer->email,
                ],
                'callbacks' => ['finish' => $request->finishUrl],
            ]);
        } catch (Throwable $e) {
            Log::warning('Midtrans Snap request failed.', ['order_id' => $request->orderId, 'error' => $e->getMessage()]);

            throw new CheckoutFailedException('Gagal menghubungi Midtrans. Coba lagi beberapa saat.');
        }

        $redirectUrl = (string) $response->json('redirect_url', '');
        if ($response->failed() || $redirectUrl === '') {
            Log::warning('Midtrans Snap rejected transaction.', [
                'order_id' => $request->orderId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new CheckoutFailedException('Gagal membuat pembayaran Midtrans.');
        }

        return new CheckoutSession($redirectUrl, (string) $response->json('token', ''));
    }

    public function fetchStatus(PendingPayment $pending, User $user): ?GatewayTransaction
    {
        try {
            $response = $this->client()->get($this->apiBaseUrl() . '/v2/' . rawurlencode($pending->order_id) . '/status');
        } catch (Throwable $e) {
            Log::warning('Midtrans status request failed.', ['order_id' => $pending->order_id, 'error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $this->toTransaction((array) $response->json()) : null;
    }

    /** Payload notifikasi / status API → GatewayTransaction. */
    public function toTransaction(array $payload): GatewayTransaction
    {
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraud = strtolower((string) ($payload['fraud_status'] ?? ''));

        $state = match (true) {
            $status === 'settlement', $status === 'capture' && in_array($fraud, ['', 'accept'], true) => GatewayTransaction::PAID,
            in_array($status, ['deny', 'cancel', 'expire', 'failure'], true) => GatewayTransaction::FAILED,
            default => GatewayTransaction::PENDING,
        };

        return new GatewayTransaction(
            $state,
            $status,
            (int) round((float) ($payload['gross_amount'] ?? 0)),
            (string) ($payload['transaction_id'] ?? $payload['order_id'] ?? ''),
            (string) ($payload['payment_type'] ?? 'midtrans'),
            $payload['settlement_time'] ?? $payload['transaction_time'] ?? null,
        );
    }

    public function hasValidSignature(array $payload): bool
    {
        $signature = (string) ($payload['signature_key'] ?? '');
        if ($signature === '' || ! $this->isConfigured()) {
            return false;
        }

        $expected = hash('sha512', ($payload['order_id'] ?? '') . ($payload['status_code'] ?? '') . ($payload['gross_amount'] ?? '') . $this->serverKey());

        return hash_equals($expected, $signature);
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->serverKey(), '')->acceptJson()->asJson()->timeout(15);
    }

    private function serverKey(): string
    {
        return (string) config('services.midtrans.server_key', '');
    }

    private function snapBaseUrl(): string
    {
        return config('services.midtrans.is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        return config('services.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
