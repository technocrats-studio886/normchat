<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyInterdotzWebhookRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = trim((string) config('normchat.webhook_secret', ''));

        // Do not block environments that intentionally run without webhook secret.
        if ($secret === '') {
            return $next($request);
        }

        $rawBody = (string) $request->getContent();
        $providedSecret = trim((string) (
            $request->header('X-Webhook-Secret')
            ?? $request->header('X-Interdotz-Secret')
            ?? ''
        ));

        if ($providedSecret !== '' && hash_equals($secret, $providedSecret)) {
            return $next($request);
        }

        $providedSignature = trim((string) (
            $request->header('X-Interdotz-Signature')
            ?? $request->header('X-Signature')
            ?? ''
        ));

        if ($providedSignature !== '' && $this->isValidSignature($providedSignature, $secret, $rawBody, $request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'invalid webhook signature',
        ], 401);
    }

    private function isValidSignature(string $providedSignature, string $secret, string $rawBody, Request $request): bool
    {
        $normalized = trim($providedSignature);
        if (Str::startsWith($normalized, 'sha256=')) {
            $normalized = substr($normalized, 7);
        }

        $bodyHash = hash_hmac('sha256', $rawBody, $secret);
        if (hash_equals($bodyHash, $normalized)) {
            return true;
        }

        $timestamp = trim((string) ($request->header('X-Interdotz-Timestamp') ?? ''));
        if ($timestamp === '') {
            return false;
        }

        $timestampedHash = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($timestampedHash, $normalized);
    }
}
