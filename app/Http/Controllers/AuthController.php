<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function landing(Request $request): View|RedirectResponse
    {
        $nextRoute = $request->query('next');
        if (is_string($nextRoute) && Route::has($nextRoute)) {
            session()->put('url.intended', route($nextRoute));
        }

        if (Auth::check()) {
            return redirect()->intended(route('groups.index'));
        }

        return view('auth.landing', [
            'interdotzEnabled' => (bool) config('services.interdotz.enabled'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->revokeInterdotzSession($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::record('auth.logout', $user?->id, $user, [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->route('login');
    }

    private function revokeInterdotzSession(?User $user): void
    {
        $refreshToken = $user?->getRefreshToken();
        if (! $refreshToken || ! config('services.interdotz.enabled')) {
            return;
        }

        try {
            Http::timeout(5)->post(rtrim((string) config('services.interdotz.api_base'), '/') . '/api/auth/logout', [
                'refresh_token' => $refreshToken,
                'refreshToken' => $refreshToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Interdotz logout failed', ['error' => $e->getMessage()]);
        }
    }
}
