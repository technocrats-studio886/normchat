<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountUnavailableException;
use App\Services\Auth\SocialLogin;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSO Interdotz. Saat ini dinonaktifkan (route dijaga middleware interdotz.enabled);
 * kode dipertahankan agar bisa dinyalakan lagi lewat NORMCHAT_INTERDOTZ_ENABLED.
 */
class InterdotzAuthController extends Controller
{
    public function redirectToLogin(): RedirectResponse
    {
        return $this->redirectToSso('login');
    }

    public function redirectToRegister(): RedirectResponse
    {
        return $this->redirectToSso('register');
    }

    public function callback(Request $request, SocialLogin $login): RedirectResponse
    {
        $accessToken = (string) $request->query('access_token', $request->query('accessToken', ''));
        $refreshToken = (string) $request->query('refresh_token', $request->query('refreshToken', ''));

        if ($accessToken === '') {
            return $this->failed('Login Interdotz dibatalkan atau token tidak diterima.');
        }

        $claims = $this->decodeJwtPayload($accessToken);
        if (! $claims || $this->isExpired($claims)) {
            return $this->failed('Token Interdotz tidak valid atau sudah kedaluwarsa.');
        }

        // Tanda tangan JWT tidak diverifikasi di sini; token baru dipercaya setelah
        // Interdotz sendiri menerimanya. Tanpa ini token palsu bisa login sebagai siapa pun.
        $profile = $this->fetchProfile($accessToken);
        if ($profile === null) {
            return $this->failed('Token Interdotz tidak valid atau sudah kedaluwarsa.');
        }

        $interdotzId = (string) ($claims['sub'] ?? $claims['userId'] ?? $claims['user_id'] ?? $claims['id'] ?? $claims['username'] ?? '');
        if ($interdotzId === '') {
            $interdotzId = substr(hash('sha256', $accessToken), 0, 32);
        }

        $username = strtolower(substr((string) preg_replace(
            '/[^A-Za-z0-9._-]/',
            '',
            (string) ($claims['username'] ?? $claims['preferred_username'] ?? $interdotzId)
        ), 0, 40));

        try {
            $user = $login->findOrCreate(
                'interdotz',
                $interdotzId,
                (string) ($claims['email'] ?? '') ?: $interdotzId . '@interdotz.user',
                (string) ($profile['name'] ?? $claims['name'] ?? $claims['username'] ?? $claims['preferred_username'] ?? $interdotzId),
                (string) ($profile['avatar_url'] ?? $claims['picture'] ?? $claims['avatar'] ?? '') ?: null,
                $username
            );
        } catch (AccountUnavailableException $e) {
            return redirect()->route('login')->with('info', $e->getMessage());
        }

        $user->storeOAuthTokens(
            $accessToken,
            $refreshToken !== '' ? $refreshToken : null,
            isset($claims['exp']) ? Carbon::createFromTimestamp((int) $claims['exp']) : null,
        );

        // ID internal Interdotz (ULID) dipakai untuk API pembayaran.
        $internalId = (string) ($profile['id'] ?? '');
        if ($internalId !== '' && $user->interdotz_id !== $internalId) {
            $user->update(['interdotz_id' => $internalId]);
        }

        $login->signIn($user, $request, 'interdotz');

        return redirect()->route('groups.index');
    }

    private function redirectToSso(string $page): RedirectResponse
    {
        $clientId = (string) config('services.interdotz.client_id');
        if ($clientId === '') {
            return $this->failed('Interdotz SSO belum dikonfigurasi.');
        }

        return redirect()->away(rtrim((string) config('services.interdotz.sso_base'), '/') . '/' . $page . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => (string) config('services.interdotz.redirect_uri', route('auth.interdotz.callback')),
        ]));
    }

    /** @return array<string,mixed>|null */
    private function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        $json = $decoded === false ? null : json_decode($decoded, true);

        return is_array($json) ? $json : null;
    }

    /** @param  array<string,mixed>  $claims */
    private function isExpired(array $claims): bool
    {
        return isset($claims['exp']) && (int) $claims['exp'] <= now()->timestamp;
    }

    /** @return array<string,mixed>|null */
    private function fetchProfile(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim((string) config('services.interdotz.api_base'), '/') . '/api/profile');

            if ($response->successful()) {
                return (array) ($response->json('payload') ?? []);
            }

            Log::warning('Interdotz profile fetch failed', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('Interdotz profile fetch threw', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['auth' => $message]);
    }
}
