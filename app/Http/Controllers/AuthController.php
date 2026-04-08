<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthController extends Controller
{
    // ── Landing ──────────────────────────────────────────────

    public function landing(Request $request): View|RedirectResponse
    {
        if ($request->has('next')) {
            $nextRoute = $request->query('next');
            if (is_string($nextRoute) && Route::has($nextRoute)) {
                session()->put('url.intended', route($nextRoute));
            }
        }

        if (Auth::check()) {
            return redirect($this->resolvePostLoginRedirect());
        }

        return view('auth.landing');
    }

    // ── Interdotz SSO ───────────────────────────────────────

    public function redirectToInterdotz(): RedirectResponse
    {
        if (empty((string) config('services.interdotz.client_id'))) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Interdotz SSO belum dikonfigurasi.']);
        }

        return redirect()->away($this->ssoUrl('login'));
    }

    public function registerAtInterdotz(): RedirectResponse
    {
        if (empty((string) config('services.interdotz.client_id'))) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Interdotz SSO belum dikonfigurasi.']);
        }

        return redirect()->away($this->ssoUrl('register'));
    }

    public function handleInterdotzCallback(Request $request): RedirectResponse
    {
        $accessToken = (string) $request->query('access_token', $request->query('accessToken', ''));
        $refreshToken = (string) $request->query('refresh_token', $request->query('refreshToken', ''));

        if ($accessToken === '') {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Login Interdotz dibatalkan atau token tidak diterima.']);
        }

        $claims = $this->decodeJwtPayload($accessToken);
        if (! $claims) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Token Interdotz tidak valid.']);
        }

        $profile = $this->fetchProfile($accessToken);

        $user = $this->createOrLoginUser($claims, $profile, $accessToken, $refreshToken);

        Auth::login($user, true);
        $request->session()->regenerate();

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'auth.connect',
            'target_type' => User::class,
            'target_id' => $user->id,
            'metadata_json' => ['provider' => 'interdotz', 'method' => 'sso'],
            'created_at' => now(),
        ]);

        $redirectUrl = (string) $request->query('redirect_url', $request->query('redirectUrl', ''));
        $targetPath = parse_url($redirectUrl, PHP_URL_PATH) ?: '';
        if (is_string($targetPath) && str_starts_with($targetPath, '/')) {
            return redirect()->to($targetPath);
        }

        return redirect($this->resolvePostLoginRedirect());
    }

    // ── Logout ───────────────────────────────────────────────

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        $refreshToken = Auth::user()?->getRefreshToken();

        if ($refreshToken) {
            try {
                Http::timeout(5)->post($this->apiUrl('/api/auth/logout'), [
                    'refresh_token' => $refreshToken,
                    'refreshToken' => $refreshToken,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Interdotz logout failed', ['error' => $e->getMessage()]);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::create([
            'actor_id' => $userId,
            'action' => 'auth.logout',
            'target_type' => User::class,
            'target_id' => $userId,
            'created_at' => now(),
        ]);

        return redirect()->route('login');
    }

    // ── Post-login redirect logic ────────────────────────────

    private function resolvePostLoginRedirect(): string
    {
        $intended = session()->pull('url.intended');
        if ($intended) {
            return $intended;
        }

        return route('subscription.payment.detail');
    }

    private function ssoUrl(string $page): string
    {
        $base = rtrim((string) config('services.interdotz.sso_base'), '/');

        return $base . '/' . ltrim($page, '/') . '?' . http_build_query([
            'client_id' => (string) config('services.interdotz.client_id'),
            'redirect_uri' => (string) config('services.interdotz.redirect_uri', route('auth.interdotz.callback')),
        ]);
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.interdotz.api_base'), '/') . $path;
    }

    /** @return array<string,mixed>|null */
    private function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $padLength = strlen($payload) % 4;
        if ($padLength > 0) {
            $payload .= str_repeat('=', 4 - $padLength);
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $json = json_decode($decoded, true);
        return is_array($json) ? $json : null;
    }

    /** @return array<string,mixed>|null */
    private function fetchProfile(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl('/api/profile'));

            if ($response->successful()) {
                return (array) ($response->json('payload') ?? []);
            }

            Log::warning('Interdotz profile fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Interdotz profile fetch threw', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $claims
     * @param  array<string,mixed>|null  $profile
     */
    private function createOrLoginUser(array $claims, ?array $profile, string $accessToken, string $refreshToken): User
    {
        $interdotzId = (string) (
            $claims['sub']
            ?? $claims['userId']
            ?? $claims['user_id']
            ?? $claims['id']
            ?? $claims['username']
            ?? ''
        );

        if ($interdotzId === '') {
            $interdotzId = substr(hash('sha256', $accessToken), 0, 32);
        }

        $username = (string) ($claims['username'] ?? $claims['preferred_username'] ?? $interdotzId);
        $email = (string) ($claims['email'] ?? '');
        $name = (string) (
            $profile['name']
            ?? $claims['name']
            ?? $claims['username']
            ?? $claims['preferred_username']
            ?? $interdotzId
            ?? 'Interdotz User'
        );
        $avatar = (string) (
            $profile['avatar_url']
            ?? $claims['picture']
            ?? $claims['avatar']
            ?? ''
        );

        if ($email === '') {
            $email = $interdotzId . '@interdotz.user';
        }

        $user = User::query()
            ->where('auth_provider', 'interdotz')
            ->where('provider_user_id', $interdotzId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatar !== '' ? $avatar : null,
                'auth_provider' => 'interdotz',
                'provider_user_id' => $interdotzId,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'name' => $name !== '' ? $name : $user->name,
                'email' => $email !== '' ? $email : $user->email,
                'avatar_url' => $avatar !== '' ? $avatar : $user->avatar_url,
                'auth_provider' => 'interdotz',
                'provider_user_id' => $interdotzId,
            ]);
        }

        $expiresAt = isset($claims['exp']) ? Carbon::createFromTimestamp((int) $claims['exp']) : null;

        $user->storeOAuthTokens(
            $accessToken,
            $refreshToken !== '' ? $refreshToken : null,
            $expiresAt,
        );

        return $user;
    }
}
