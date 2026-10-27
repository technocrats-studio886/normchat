<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy untuk halaman HTML: skrip hanya dari normi.chat atau tag bernonce
 * (inline & CDN di layout), jadi skrip sisipan XSS tidak berjalan. Respons yang sudah punya
 * CSP sendiri (lampiran, canvas) tidak disentuh.
 *
 * Sengaja tanpa form-action: Chrome menerapkannya juga pada redirect setelah submit, padahal
 * form buat grup/gabung/isi Normkredit diarahkan ke halaman pembayaran Midtrans.
 */
class AddContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $realtime = (array) config('normchat.csp.realtime', []);
        // Server dev Vite (npm run dev) memuat skrip & HMR dari host lain.
        $vite = Vite::isRunningHot() ? [rtrim((string) file_get_contents(public_path('hot')), '/')] : [];
        $viteSocket = array_map(fn (string $url) => preg_replace('#^http#', 'ws', $url), $vite);

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$vite],
            // Atribut style="" dan gaya bawaan SVG Mermaid butuh inline.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net/npm/quill@2.0.3/', ...$vite],
            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            // Avatar akun Google dan penyedia lain; blob: untuk pratinjau lampiran sebelum dikirim.
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'media-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", ...$realtime, ...$vite, ...$viteSocket],
            // Pratinjau canvas dimuat dari route canvas di normi.chat.
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'report-uri' => ['/api/csp-report'],
        ];

        return implode('; ', array_map(
            fn (string $name, array $sources) => $name . ' ' . implode(' ', array_unique($sources)),
            array_keys($directives),
            $directives
        ));
    }
}
