<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Interdotz\Sdk\InterdotzClient;
use Interdotz\Sdk\Exceptions\AuthException;
use Interdotz\Sdk\Exceptions\PaymentException;
use Throwable;

class InterdotzService
{
    private string $apiBase;
    private string $clientId;
    private string $clientSecret;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->apiBase = rtrim((string) config('services.interdotz.api_base'), '/');
        $this->clientId = (string) config('services.interdotz.client_id');
        $this->clientSecret = (string) config('services.interdotz.client_secret');
    }

    // ── HTTP Client Builder ─────────────────────────────────────

    private function client(string $accessToken, int $timeout = 15): PendingRequest
    {
        return Http::withToken($accessToken)
            ->withHeaders([
                'X-Client-Id' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
                'Accept' => 'application/json',
            ])
            ->timeout($timeout);
    }

    private function setLastError(?string $message): void
    {
        $this->lastError = $message !== null && $message !== ''
            ? trim($message)
            : null;
    }

    private function extractResponseError(Response $response): ?string
    {
        $message = (string) (
            $response->json('message')
            ?? $response->json('error')
            ?? ''
        );

        if ($message !== '') {
            return $message;
        }

        $errors = $response->json('errors');
        if (is_array($errors)) {
            foreach ($errors as $fieldErrors) {
                if (is_array($fieldErrors) && isset($fieldErrors[0]) && is_string($fieldErrors[0])) {
                    return $fieldErrors[0];
                }

                if (is_string($fieldErrors) && $fieldErrors !== '') {
                    return $fieldErrors;
                }
            }
        }

        return null;
    }

    private function resolveChargeUserId(string $ssoAccessToken, ?string $interdotzUserId): ?string
    {
        $provided = trim((string) $interdotzUserId);
        if ($provided !== '') {
            return $provided;
        }

        $resolved = $this->resolveInternalUserId($ssoAccessToken);
        if ($resolved !== null && $resolved !== '') {
            return $resolved;
        }

        $this->setLastError('Akun Interdotz belum terhubung untuk pembayaran. Silakan login ulang.');

        return null;
    }

    private function getClientToken(string $userId): ?string
    {
        try {
            $payload = [
                // Keep both key styles for compatibility across Interdotz deployments.
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
                'user_id' => $userId,
                'userId' => $userId,
            ];

            $headers = [
                'Accept' => 'application/json',
                'X-Client-Id' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ];

            $sendAuthRequest = static function (array $headers, array $payload, string $url, bool $asForm = false): Response {
                $request = Http::withHeaders($headers)->timeout(10);
                if ($asForm) {
                    $request = $request->asForm();
                }

                return $request->post($url, $payload);
            };

            $response = $sendAuthRequest($headers, $payload, "{$this->apiBase}/api/client/auth");

            // Some API deployments only accept form-encoded payloads for this endpoint.
            if (! $response->successful() && str_contains(strtolower((string) $response->body()), 'client_id is required')) {
                $response = $sendAuthRequest($headers, $payload, "{$this->apiBase}/api/client/auth", true);
            }

            if ($response->successful()) {
                $token = (string) (
                    $response->json('payload.access_token')
                    ?? $response->json('payload.accessToken')
                    ?? $response->json('access_token')
                    ?? $response->json('accessToken')
                    ?? ''
                );

                if ($token !== '') {
                    return $token;
                }

                $this->setLastError('Token autentikasi Interdotz tidak ditemukan.');
                Log::warning('Interdotz client auth succeeded but token missing.', [
                    'body' => $response->json(),
                ]);

                return null;
            }

            $error = $this->extractResponseError($response) ?? 'Autentikasi Interdotz gagal.';
            $this->setLastError($error);

            Log::warning('Interdotz client auth failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'client_id' => $this->clientId,
                'api_base' => $this->apiBase,
                'secret_length' => strlen($this->clientSecret),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz client auth exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function buildChargePayload(
        int $amount,
        string $referenceType,
        string $referenceId,
        string $userId,
        ?string $description = null,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'amount' => $amount,
            'referenceType' => $referenceType,
            'reference_type' => $referenceType,
            'referenceId' => $referenceId,
            'reference_id' => $referenceId,
            'userId' => $userId,
            'user_id' => $userId,
        ];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        if ($callbackUrl !== null) {
            $payload['callbackUrl'] = $callbackUrl;
            $payload['callback_url'] = $callbackUrl;
        }

        return $payload;
    }

    // ── User Balance ────────────────────────────────────────────

    public function getUserBalance(string $ssoAccessToken, ?string $interdotzUserId = null): ?array
    {
        $this->setLastError(null);

        try {
            $userId = $this->resolveChargeUserId($ssoAccessToken, $interdotzUserId);
            if (! $userId) {
                return null;
            }

            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            $response = $this->client($clientToken, 10)
                ->get("{$this->apiBase}/api/client/balance", [
                    'userId' => $userId,
                    'user_id' => $userId,
                ]);

            if ($response->successful()) {
                return $response->json('payload') ?? $response->json();
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Gagal mengecek saldo DU.');

            Log::warning('Interdotz balance check failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz balance exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Charge (Direct) ─────────────────────────────────────────

    public function charge(
        string $ssoAccessToken,
        int $amount,
        string $referenceType,
        string $referenceId,
        ?string $interdotzUserId = null
    ): ?array
    {
        $this->setLastError(null);

        try {
            $userId = $this->resolveChargeUserId($ssoAccessToken, $interdotzUserId);
            if (! $userId) {
                return null;
            }

            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            $response = $this->client($clientToken)
                ->post(
                    "{$this->apiBase}/api/client/charge",
                    $this->buildChargePayload($amount, $referenceType, $referenceId, $userId)
                );

            Log::info('Interdotz charge response.', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Charge DU gagal diproses.');

            Log::warning('Interdotz charge failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz charge exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Charge Request (User Approval) ──────────────────────────

    public function chargeRequest(
        string $ssoAccessToken,
        int $amount,
        string $referenceType,
        string $referenceId,
        string $description,
        string $callbackUrl,
        ?string $interdotzUserId = null
    ): ?array {
        $this->setLastError(null);

        try {
            $userId = $this->resolveChargeUserId($ssoAccessToken, $interdotzUserId);
            if (! $userId) {
                return null;
            }

            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            $response = $this->client($clientToken)
                ->post(
                    "{$this->apiBase}/api/client/charge/request",
                    $this->buildChargePayload($amount, $referenceType, $referenceId, $userId, $description, $callbackUrl)
                );

            Log::info('Interdotz charge request response.', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Permintaan pembayaran DU gagal diproses.');

            Log::warning('Interdotz charge request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz charge request exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Payments (Midtrans) — uses official SDK PaymentClient ───

    /**
     * Get the SDK InterdotzClient (lazy-loaded singleton).
     */
    private function getSdkClient(): InterdotzClient
    {
        return app(InterdotzClient::class);
    }

    public function createPayment(
        string $ssoAccessToken,
        string $referenceId,
        int $amount,
        string $currency,
        string $callbackUrl,
        array $customer,
        array $items,
        ?string $interdotzUserId = null,
        ?string $redirectUrl = null
    ): ?array {
        $this->setLastError(null);

        try {
            $userId = $this->resolveChargeUserId($ssoAccessToken, $interdotzUserId);
            if (! $userId) {
                return null;
            }

            // Use proven getClientToken() for auth (SDK auth has camelCase bug)
            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            // Use SDK PaymentClient for the actual payment creation
            $payment = $this->getSdkClient()->payment()->createMidtransPayment(
                accessToken: $clientToken,
                referenceId: $referenceId,
                amount:      $amount,
                items:       $items,
                redirectUrl: $redirectUrl ?? $callbackUrl,
                customer:    $customer,
                currency:    $currency,
            );

            Log::info('Interdotz Midtrans payment created via SDK.', [
                'reference_id' => $referenceId,
                'payment_id'   => $payment->id,
                'redirect_url' => $payment->redirectUrl,
            ]);

            // Return array for backward compatibility with controllers
            return [
                'payload' => [
                    'id'           => $payment->id,
                    'reference_id' => $payment->referenceId,
                    'amount'       => $payment->amount,
                    'currency'     => $payment->currency,
                    'status'       => $payment->status,
                    'snap_token'   => $payment->snapToken,
                    'redirect_url' => $payment->redirectUrl,
                    'expires_at'   => $payment->expiresAt,
                    'created_at'   => $payment->createdAt,
                ],
            ];
        } catch (PaymentException $e) {
            $this->setLastError('Pembayaran Midtrans gagal: ' . $e->getMessage());
            Log::warning('Interdotz Midtrans payment failed.', [
                'error'   => $e->getMessage(),
                'code'    => $e->getCode(),
                'context' => $e->getContext(),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz payment exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function getPaymentStatus(
        string $ssoAccessToken,
        string $paymentId,
        ?string $interdotzUserId = null
    ): ?array
    {
        $this->setLastError(null);

        try {
            $userId = $this->resolveChargeUserId($ssoAccessToken, $interdotzUserId);
            if (! $userId) {
                return null;
            }

            // Use proven getClientToken() for auth (SDK auth has camelCase bug)
            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            // Use SDK PaymentClient for the actual status check
            $status = $this->getSdkClient()->payment()->getMidtransPaymentStatus(
                accessToken: $clientToken,
                paymentId:   $paymentId,
            );

            // Return array for backward compatibility with controllers
            return [
                'payload' => [
                    'id'                     => $status->id,
                    'reference_id'           => $status->referenceId,
                    'amount'                 => $status->amount,
                    'currency'               => $status->currency,
                    'status'                 => $status->status,
                    'payment_method'         => $status->paymentMethod,
                    'gateway_transaction_id' => $status->gatewayTransactionId,
                    'paid_at'                => $status->paidAt,
                    'created_at'             => $status->createdAt,
                    'updated_at'             => $status->updatedAt,
                ],
            ];
        } catch (PaymentException $e) {
            $this->setLastError('Gagal mengambil status pembayaran: ' . $e->getMessage());
            Log::warning('Interdotz Midtrans status failed.', ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz payment status exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── User Profile Lookup ────────────────────────────────────

    public function resolveInternalUserId(string $ssoAccessToken): ?string
    {
        try {
            $response = Http::withToken($ssoAccessToken)
                ->timeout(10)
                ->get("{$this->apiBase}/api/profile");

            if ($response->successful()) {
                return $response->json('payload.id');
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Gagal mengambil profil Interdotz.');

            Log::warning('Interdotz profile lookup failed.', [
                'status' => $response->status(),
            ]);
        } catch (Throwable $e) {
            $this->setLastError('Gagal menghubungi server Interdotz.');
            Log::warning('Interdotz profile lookup exception.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
