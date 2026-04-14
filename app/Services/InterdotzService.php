<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Client-Id' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])
                ->timeout(10)
                ->post("{$this->apiBase}/api/client/auth", [
                    // Keep both key styles for compatibility across Interdotz deployments.
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'clientId' => $this->clientId,
                    'clientSecret' => $this->clientSecret,
                    'user_id' => $userId,
                    'userId' => $userId,
                ]);

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

    // ── Payments (Midtrans) ─────────────────────────────────────

    public function createPayment(
        string $ssoAccessToken,
        string $referenceId,
        int $amount,
        string $currency,
        string $callbackUrl,
        array $customer,
        array $items,
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
                ->post("{$this->apiBase}/api/client/payments", [
                    'referenceId' => $referenceId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'callbackUrl' => $callbackUrl,
                    'userId' => $userId,
                    'user_id' => $userId,
                    'customer' => $customer,
                    'items' => $items,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Pembuatan pembayaran gagal.');

            Log::warning('Interdotz payment creation failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
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

            $clientToken = $this->getClientToken($userId);
            if (! $clientToken) {
                return null;
            }

            $response = $this->client($clientToken, 10)
                ->get("{$this->apiBase}/api/client/payments/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            }

            $this->setLastError($this->extractResponseError($response) ?? 'Gagal mengambil status pembayaran.');
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
