<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesi berakhir setelah config('normchat.session_idle_days') hari tanpa aksi user; setiap aksi
 * memulai hitungan dari awal. Request otomatis dari tab yang terbuka (tanda sudah dibaca,
 * statistik polling, otorisasi realtime) tidak dihitung sebagai aktivitas.
 */
class EndIdleSession
{
    public const LAST_ACTIVITY = 'last_activity_at';

    private const PASSIVE_ROUTES = ['chat.read', 'chat.polls.stats'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $session = $request->session();
        $lastActivity = (int) $session->get(self::LAST_ACTIVITY, 0);
        $idleLimit = (int) config('normchat.session_idle_days', 5) * 86400;

        if ($lastActivity > 0 && now()->getTimestamp() - $lastActivity >= $idleLimit) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            $message = 'Sesi berakhir karena ' . config('normchat.session_idle_days', 5) . ' hari tidak ada aktivitas. Masuk lagi untuk lanjut.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->withErrors(['auth' => $message]);
        }

        if ($lastActivity === 0 || ! $this->isPassive($request)) {
            $session->put(self::LAST_ACTIVITY, now()->getTimestamp());
        }

        return $next($request);
    }

    private function isPassive(Request $request): bool
    {
        return $request->routeIs(...self::PASSIVE_ROUTES) || $request->is('broadcasting/auth');
    }
}
