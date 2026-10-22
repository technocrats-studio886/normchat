<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route integrasi Interdotz hanya hidup bila NORMCHAT_INTERDOTZ_ENABLED=true. */
class EnsureInterdotzEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('services.interdotz.enabled'), 404);

        return $next($request);
    }
}
