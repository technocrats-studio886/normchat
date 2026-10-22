<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInterdotzClientRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isAuthorized($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'unauthorized interdotz request',
            'hint' => 'Provide valid X-Client-Id and X-Client-Secret headers, or a bearer token from Interdotz.',
        ], 401);
    }

    /**
     * Client ID + secret, atau client ID + bearer token, atau (bila diizinkan) bearer token saja.
     * Tanpa kredensial terkonfigurasi (lokal/dev), semua request diterima.
     */
    public static function isAuthorized(Request $request): bool
    {
        $expectedClientId = trim((string) config('services.interdotz.client_id'));
        $expectedClientSecret = trim((string) config('services.interdotz.client_secret'));
        if ($expectedClientId === '' && $expectedClientSecret === '') {
            return true;
        }

        $incomingClientId = self::credential($request, 'X-Client-Id', ['clientId', 'client_id']);
        $incomingClientSecret = self::credential($request, 'X-Client-Secret', ['clientSecret', 'client_secret']);
        $bearerToken = trim((string) $request->bearerToken());

        $clientIdMatches = $expectedClientId !== '' && $incomingClientId !== '' && hash_equals($expectedClientId, $incomingClientId);
        $clientSecretMatches = $expectedClientSecret !== '' && $incomingClientSecret !== '' && hash_equals($expectedClientSecret, $incomingClientSecret);

        return ($clientIdMatches && $clientSecretMatches)
            || ($clientIdMatches && $bearerToken !== '')
            || ((bool) config('normchat.allow_interdotz_bearer_only', true) && $bearerToken !== '');
    }

    private static function credential(Request $request, string $header, array $queryKeys): string
    {
        $value = $request->header($header);
        foreach ($queryKeys as $key) {
            $value ??= $request->query($key);
        }

        return trim((string) $value);
    }
}
