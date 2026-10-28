<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountUnavailableException;
use App\Services\Auth\SocialLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/** Login dengan akun Google (OAuth via Laravel Socialite). */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failed('Masuk dengan Google belum tersedia.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SocialLogin $login): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failed('Masuk dengan Google belum tersedia.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google login failed.', ['error' => $e->getMessage()]);

            return $this->failed('Gagal masuk dengan Google. Coba lagi.');
        }

        $email = (string) $googleUser->getEmail();
        $emailVerified = (bool) ($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false);

        if ($email === '' || ! $emailVerified) {
            return $this->failed('Email Google kamu belum terverifikasi.');
        }

        try {
            $user = $login->findOrCreate(
                'google',
                (string) $googleUser->getId(),
                $email,
                (string) ($googleUser->getName() ?: $email),
                $googleUser->getAvatar(),
                strtolower(substr((string) preg_replace('/[^A-Za-z0-9._-]/', '', strstr($email, '@', true) ?: ''), 0, 40))
            );
        } catch (AccountUnavailableException $e) {
            return redirect()->route('login')->with('info', $e->getMessage());
        }

        $login->signIn($user, $request, 'google');

        return redirect()->intended(route('groups.index'));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['auth' => $message]);
    }
}
