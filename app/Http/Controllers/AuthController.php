<?php

namespace App\Http\Controllers;

use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    // Gemini uses real Google OAuth; ChatGPT & Claude use API key connect
    private const OAUTH_PROVIDERS = ['gemini'];
    private const APIKEY_PROVIDERS = ['chatgpt', 'claude'];
    private const ALL_PROVIDERS = ['chatgpt', 'claude', 'gemini'];

    // ── Landing ──────────────────────────────────────────────

    public function landing(Request $request): View|RedirectResponse
    {
        // Store intended redirect (e.g. from pricing page)
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

    // ── API Key Connect Form (ChatGPT & Claude) ──────────────

    public function showApiKeyForm(string $provider): View|RedirectResponse
    {
        abort_unless(in_array($provider, self::APIKEY_PROVIDERS, true), 404);

        if (Auth::check()) {
            return redirect($this->resolvePostLoginRedirect());
        }

        return view('auth.api-key-connect', ['provider' => $provider]);
    }

    public function handleApiKeyConnect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::APIKEY_PROVIDERS, true), 404);

        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10', 'max:8192'],
        ]);

        // Validate API key with the provider
        $keyValid = $this->validateApiKey($provider, $validated['api_key']);
        if (! $keyValid) {
            return back()
                ->withErrors(['api_key' => "API key tidak valid. Pastikan key dari $provider aktif dan benar."]);
        }

        // Generate a stable provider_user_id from the API key (hash to avoid storing raw key as ID)
        $providerUserId = hash('sha256', $provider . ':' . $validated['api_key']);

        // Find existing user by provider+hash
        $user = User::query()
            ->where('auth_provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if (! $user) {
            $user = $this->createApiKeyUserWithUniqueEmail($provider, $providerUserId);
        }

        // Store API key encrypted on user
        $user->storeApiKey($validated['api_key']);

        // Also create/update AiConnection for use in chat AI responses
        AiConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => $provider === 'chatgpt' ? 'openai' : 'claude',
                'access_token' => encrypt($validated['api_key']),
                'refresh_token' => null,
                'expires_at' => null,
            ]
        );

        Auth::login($user, true);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'auth.connect',
            'target_type' => User::class,
            'target_id' => $user->id,
            'metadata_json' => ['provider' => $provider, 'method' => 'api_key'],
            'created_at' => now(),
        ]);

        return redirect($this->resolvePostLoginRedirect());
    }

    // ── OAuth: Redirect to Provider (Gemini/Google) ──────────

    public function connectProvider(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::OAUTH_PROVIDERS, true), 404);

        $config = config("services.ai_providers.$provider");
        if (! $config || empty($config['client_id'])) {
            return redirect()->route('login')
                ->withErrors(['auth' => "Provider $provider belum dikonfigurasi (client_id kosong)."]);
        }

        // Generate CSRF state
        $state = Str::random(40);
        session()->put("oauth.$provider.state", $state);

        // Build OAuth authorization URL
        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect'],
            'response_type' => 'code',
            'scope' => $config['scopes'],
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect($config['authorize_url'] . '?' . $params);
    }

    // ── OAuth: Handle Callback (Gemini/Google) ───────────────

    public function handleCallback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::OAUTH_PROVIDERS, true), 404);

        // Validate state (CSRF protection)
        $expectedState = session()->pull("oauth.$provider.state");
        $receivedState = (string) $request->query('state', '');

        if (! $expectedState || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Sesi OAuth tidak valid. Silakan coba lagi.']);
        }

        // Check for error from provider
        if ($request->has('error')) {
            $errorDesc = $request->query('error_description', 'Provider menolak akses.');
            return redirect()->route('login')
                ->withErrors(['auth' => "Gagal connect $provider: $errorDesc"]);
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->route('login')
                ->withErrors(['auth' => 'Authorization code tidak diterima.']);
        }

        $config = config("services.ai_providers.$provider");

        // Exchange code for token
        $tokenData = $this->exchangeCodeForToken($code, $config);
        if (! $tokenData) {
            return redirect()->route('login')
                ->withErrors(['auth' => "Gagal menukar token dari $provider."]);
        }

        // Get user profile from provider
        $profile = $this->getUserProfile($provider, $tokenData['access_token'], $config);
        if (! $profile) {
            return redirect()->route('login')
                ->withErrors(['auth' => "Gagal mendapatkan profil dari $provider."]);
        }

        // Create or login user + save tokens
        $user = $this->createOrLoginUser($provider, $profile, $tokenData);

        // Also create AiConnection for Gemini
        AiConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'gemini',
                'access_token' => encrypt($tokenData['access_token']),
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => isset($tokenData['expires_in'])
                    ? Carbon::now()->addSeconds((int) $tokenData['expires_in'])
                    : null,
            ]
        );

        Auth::login($user, true);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'auth.connect',
            'target_type' => User::class,
            'target_id' => $user->id,
            'metadata_json' => ['provider' => $provider, 'method' => 'oauth'],
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
        // If there's an explicit intended URL (e.g. from pricing → payment), use it
        $intended = session()->pull('url.intended');
        if ($intended) {
            return $intended;
        }

        // Check if user already has an active subscription (returning user)
        $user = Auth::user();
        if ($user) {
            $hasActiveSubscription = Subscription::query()
                ->whereHas('group', fn ($q) => $q->where('owner_id', $user->id))
                ->where('status', 'active')
                ->exists();

            if ($hasActiveSubscription || session('subscription_paid')) {
                return route('groups.index');
            }
        }

        // New user without subscription → go to subscription gate
        return route('subscription.payment.detail');
    }

    // ── Private: Validate API key with provider ──────────────

    private function validateApiKey(string $provider, string $apiKey): bool
    {
        try {
            if ($provider === 'chatgpt') {
                // Validate OpenAI API key by listing models (10s timeout)
                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->get('https://api.openai.com/v1/models', ['limit' => 1]);

                return $response->successful();
            }

            if ($provider === 'claude') {
                // Validate Anthropic API key by sending a minimal request (10s timeout)
                $response = Http::timeout(10)->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-sonnet-4-20250514',
                    'max_tokens' => 1,
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                ]);

                // 200 = valid key, 400 = valid key but bad request (still valid)
                return $response->status() !== 401 && $response->status() !== 403;
            }

            return false;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    // ── Private: Exchange authorization code for token ────────

    private function exchangeCodeForToken(string $code, array $config): ?array
    {
        try {
            $response = Http::asForm()->post($config['token_url'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect'],
            ]);

            if ($response->failed()) {
                report("OAuth token exchange failed: " . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ── Private: Fetch user profile from provider ────────────

    private function getUserProfile(string $provider, string $accessToken, array $config): ?array
    {
        try {
            $response = Http::withToken($accessToken)->get($config['userinfo_url']);

            if ($response->failed()) {
                report("OAuth userinfo failed for $provider: " . $response->body());
                return null;
            }

            $data = $response->json();

            return match ($provider) {
                'gemini' => [
                    'id' => $data['sub'] ?? $data['id'] ?? Str::random(16),
                    'name' => $data['name'] ?? 'Gemini User',
                    'email' => $data['email'] ?? null,
                    'avatar' => $data['picture'] ?? null,
                ],
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ── Private: Create or login user ────────────────────────

    private function createOrLoginUser(string $provider, array $profile, array $tokenData): User
    {
        $user = User::query()
            ->where('auth_provider', $provider)
            ->where('provider_user_id', $profile['id'])
            ->first();

        if (! $user && ! empty($profile['email'])) {
            // Merge account when the same person connects with another provider.
            $user = User::query()->where('email', $profile['email'])->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $profile['name'],
                'email' => $profile['email'] ?? $this->makeUniqueNormchatEmail($provider, $profile['id']),
                'avatar_url' => $profile['avatar'] ?? null,
                'auth_provider' => $provider,
                'provider_user_id' => $profile['id'],
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'name' => $profile['name'] ?: $user->name,
                'email' => $profile['email'] ?: $user->email,
                'avatar_url' => $profile['avatar'] ?? $user->avatar_url,
                'auth_provider' => $provider,
                'provider_user_id' => $profile['id'],
            ]);
        }

        // Save encrypted tokens
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

    private function createApiKeyUserWithUniqueEmail(string $provider, string $providerUserId): User
    {
        $email = $this->makeUniqueNormchatEmail($provider, $providerUserId);

        try {
            return User::create([
                'name' => ucfirst($provider) . ' User',
                'email' => $email,
                'auth_provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email_verified_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Handle race condition on unique email generation by retrying once.
            if ((string) $e->getCode() !== '23505') {
                throw $e;
            }

            return User::create([
                'name' => ucfirst($provider) . ' User',
                'email' => $this->makeUniqueNormchatEmail($provider, $providerUserId),
                'auth_provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email_verified_at' => now(),
            ]);
        }
    }

    private function makeUniqueNormchatEmail(string $provider, string $seed): string
    {
        $base = $provider . '-' . substr(hash('sha256', $provider . ':' . $seed), 0, 12);
        $candidate = $base . '@normchat.local';
        $counter = 2;

        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = $base . '-' . $counter . '@normchat.local';
            $counter++;
        }

        return $candidate;
    }
}
