<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\NormkreditPricing;
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

        // Tawaran grup gratis dari setelan superadmin (App\Services\FreeGroups); kosong bila fiturnya mati.
        $pricing = app(NormkreditPricing::class)->settings();
        $freeCredits = ($pricing['free_groups_enabled'] ?? false) ? (float) ($pricing['free_first_group_credits'] ?? 0) : 0.0;

        return view('auth.landing', [
            'interdotzEnabled' => (bool) config('services.interdotz.enabled'),
            'freeCredits' => $freeCredits > 0 ? $freeCredits : null,
            'freeSlots' => max(0, (int) ($pricing['free_group_member_slots'] ?? 0)),
            'freeSecondCredits' => $freeCredits > 0 && (float) ($pricing['free_second_group_credits'] ?? 0) > 0 ? (float) $pricing['free_second_group_credits'] : null,
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
