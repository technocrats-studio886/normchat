<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLlmConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not logged in → Laravel's 'auth' middleware handles this
        if (! $user) {
            // Save intended URL (including invite links) so after connect we come back
            session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login');
        }

        // User is logged in but has no valid LLM credentials → force re-connect
        if (! $user->hasValidCredentials()) {
            session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login')
                ->with('info', 'Silakan sambungkan ulang akses AI kamu.');
        }

        return $next($request);
    }
}
