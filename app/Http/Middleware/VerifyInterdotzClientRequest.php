<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInterdotzClientRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedClientId = trim((string) config('services.interdotz.client_id'));
        $expectedClientSecret = trim((string) config('services.interdotz.client_secret'));
        $allowBearerOnly = (bool) config('normchat.allow_interdotz_bearer_only', true);

        // Keep local/dev environment working when Interdotz credentials are not configured.
        if ($expectedClientId === '' && $expectedClientSecret === '') {
            return $next($request);
        }

        $incomingClientId = $this->getIncomingClientId($request);
        $incomingClientSecret = $this->getIncomingClientSecret($request);
        $bearerToken = trim((string) $request->bearerToken());

        $clientIdMatches = $expectedClientId !== ''
            && $incomingClientId !== ''
            && hash_equals($expectedClientId, $incomingClientId);

        $clientSecretMatches = $expectedClientSecret !== ''
            && $incomingClientSecret !== ''
            && hash_equals($expectedClientSecret, $incomingClientSecret);

        if ($clientIdMatches && $clientSecretMatches) {
            return $next($request);
        }

        // Interdotz may call with bearer client token + client id.
        if ($clientIdMatches && $bearerToken !== '') {
            return $next($request);
        }

        // Compatibility fallback for deployments that only forward bearer token.
        if ($allowBearerOnly && $bearerToken !== '') {
            return $next($request);
        }

        return response()->json([
            'message' => 'unauthorized interdotz request',
            'hint' => 'Provide valid X-Client-Id and X-Client-Secret headers, or a bearer token from Interdotz.',
        ], 401);
    }

    private function getIncomingClientId(Request $request): string
    {
        return trim((string) (
            $request->header('X-Client-Id')
            ?? $request->query('clientId')
            ?? $request->query('client_id')
            ?? ''
        ));
    }

    private function getIncomingClientSecret(Request $request): string
    {
        return trim((string) (
            $request->header('X-Client-Secret')
            ?? $request->query('clientSecret')
            ?? $request->query('client_secret')
            ?? ''
        ));
    }
}
