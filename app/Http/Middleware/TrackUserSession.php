<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Support\LoginDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun > Perangkat & sesi: mencatat perangkat yang sedang masuk dan mengakhiri sesi yang dikeluarkan dari perangkat
 * lain ("Keluar dari semua perangkat lain" menaikkan users.session_version; satu perangkat: revoked_at).
 */
class TrackUserSession
{
    /** Waktu aktif terakhir diperbarui paling sering sekali per rentang ini. */
    private const TOUCH_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        // Versi dipasang saat login (AppServiceProvider). Kosong = sesi dari sebelum fitur ini: masih sah
        // hanya bila user belum pernah mengeluarkan perangkat lain.
        $version = $session->get('session_version');
        if ($version === null && (int) $user->session_version === 0) {
            $session->put('session_version', 0);
        } elseif ((int) $version !== (int) $user->session_version) {
            return $this->end($request);
        }

        $hash = UserSession::hashFor($session->getId());
        $row = UserSession::query()->where('session_hash', $hash)->first();
        if ($row?->revoked_at !== null) {
            return $this->end($request);
        }

        if (! $row) {
            $device = LoginDevice::describe(['user_agent' => (string) $request->userAgent()]);
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
            UserSession::query()->create([
                'user_id' => $user->id,
                'session_hash' => $hash,
                'device' => mb_substr(implode(' · ', array_filter([$device['device'], $device['detail']])) ?: 'Perangkat tidak dikenal', 0, 120),
                'ip' => $request->ip(),
                'last_active_at' => now(),
            ]);
        } elseif ($row->last_active_at->lt(now()->subSeconds(self::TOUCH_SECONDS))) {
            $row->update(['last_active_at' => now(), 'ip' => $request->ip()]);
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function end(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => 'Sesi ini sudah dikeluarkan dari perangkat lain.'], 401)
            : redirect()->route('login')->with('info', 'Kamu dikeluarkan dari perangkat ini lewat pengaturan akun. Silakan masuk lagi.');
    }
}
