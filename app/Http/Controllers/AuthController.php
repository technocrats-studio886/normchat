<?php

namespace App\Http\Controllers;

use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    // ── Landing ──────────────────────────────────────────────

    public function landing(Request $request): View|RedirectResponse
    {
        if ($request->has('next')) {
            $nextRoute = $request->query('next');
            if (is_string($nextRoute) && \Illuminate\Support\Facades\Route::has($nextRoute)) {
                session()->put('url.intended', route($nextRoute));
            }
        }

        if (Auth::check()) {
            return redirect($this->resolvePostLoginRedirect());
        }

        return view('auth.landing');
    }

    // ── Google SSO: Redirect ─────────────────────────────────

    public function redirectToGoogle(): RedirectResponse
    {
        $config = config('services.google');
        if (! $config || empty($config['client_id'])) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Google SSO belum dikonfigurasi.']);
        }

        $state = Str::random(40);
        session()->put('oauth.google.state', $state);

        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect'],
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    // ── Google SSO: Callback ─────────────────────────────────

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // Validate state (CSRF protection)
        $expectedState = session()->pull('oauth.google.state');
        $receivedState = (string) $request->query('state', '');

        if (! $expectedState || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Sesi OAuth tidak valid. Silakan coba lagi.']);
        }

        if ($request->has('error')) {
            $errorDesc = $request->query('error_description', 'Google menolak akses.');
            return redirect()->route('login')
                ->withErrors(['auth' => "Gagal login Google: $errorDesc"]);
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Authorization code tidak diterima.']);
        }

        $config = config('services.google');

        // Exchange code for token
        $tokenData = $this->exchangeCodeForToken($code, $config);
        if (! $tokenData) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Gagal menukar token dari Google.']);
        }

        // Get user profile
        $profile = $this->getGoogleProfile($tokenData['access_token']);
        if (! $profile) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Gagal mendapatkan profil dari Google.']);
        }

        // Create or update user
        $user = $this->createOrLoginUser($profile, $tokenData);

        Auth::login($user, true);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'auth.connect',
            'target_type' => User::class,
            'target_id' => $user->id,
            'metadata_json' => ['provider' => 'google', 'method' => 'sso'],
            'created_at' => now(),
        ]);

        return redirect($this->resolvePostLoginRedirect());
    }

    // ── Logout ───────────────────────────────────────────────

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

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

        $user = Auth::user();
        if ($user) {
            $hasActiveSubscription = Subscription::query()
                ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
                ->where('status', 'active')
                ->exists();

            if (session('subscription_paid')) {
                return route('groups.create');
            }

            if ($hasActiveSubscription) {
                return route('groups.index');
            }
        }

        return route('subscription.payment.detail');
    }

    // ── Private: Exchange authorization code for token ────────

    private function exchangeCodeForToken(string $code, array $config): ?array
    {
        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect'],
            ]);

            if ($response->failed()) {
                report("Google token exchange failed: " . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ── Private: Fetch Google user profile ────────────────────

    private function getGoogleProfile(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if ($response->failed()) {
                report("Google userinfo failed: " . $response->body());
                return null;
            }

            $data = $response->json();

            return [
                'id' => $data['sub'] ?? $data['id'] ?? null,
                'name' => $data['name'] ?? 'Google User',
                'email' => $data['email'] ?? null,
                'avatar' => $data['picture'] ?? null,
            ];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ── Private: Create or login user ────────────────────────

    private function createOrLoginUser(array $profile, array $tokenData): User
    {
        // Find by Google provider ID
        $user = User::query()
            ->where('auth_provider', 'google')
            ->where('provider_user_id', $profile['id'])
            ->first();

        // Try matching by email if provider ID not found
        if (! $user && ! empty($profile['email'])) {
            $user = User::query()->where('email', $profile['email'])->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'avatar_url' => $profile['avatar'],
                'auth_provider' => 'google',
                'provider_user_id' => $profile['id'],
                'email_verified_at' => now(),
            ]);
        } else {
            // Always update profile data from Google SSO
            $user->update([
                'name' => $profile['name'] ?: $user->name,
                'email' => $profile['email'] ?: $user->email,
                'avatar_url' => $profile['avatar'] ?? $user->avatar_url,
                'auth_provider' => 'google',
                'provider_user_id' => $profile['id'],
            ]);
        }

        // Save encrypted tokens on user
        $expiresAt = isset($tokenData['expires_in'])
            ? Carbon::now()->addSeconds((int) $tokenData['expires_in'])
            : null;

        $user->storeOAuthTokens(
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt,
        );

        return $user;
    }
}
