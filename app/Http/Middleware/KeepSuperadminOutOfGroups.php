<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Superadmin mengelola aplikasi, bukan anggota grup: tidak bisa membuat, bergabung, atau membuka chat. */
class KeepSuperadminOutOfGroups
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperadmin()) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Superadmin tidak bisa memakai fitur grup.'], 403)
                : redirect()->route('superadmin.index');
        }

        return $next($request);
    }
}
