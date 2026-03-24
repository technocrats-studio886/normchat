<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasLlmConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->aiConnection || $user->aiConnection->decryptedAccessToken() === null) {
            return redirect()->route('login')
                ->withErrors(['llm' => 'You must connect an LLM before creating a group']);
        }

        return $next($request);
    }
}
