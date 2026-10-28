<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang diblokir superadmin langsung keluar dari semua sesinya pada permintaan berikutnya. */
class EndBlockedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->blocked_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Akun ini diblokir.'], 403)
                : redirect()->route('login')->with('info', 'Akun ini diblokir. Hubungi admin Normi Chat bila menurutmu ini keliru.');
        }

        return $next($request);
    }
}
