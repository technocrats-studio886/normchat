<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman HTML bergantung pada sesi (sudah masuk atau belum), jadi browser tidak boleh memakainya lagi
 * tanpa bertanya ke server. Dulu "no-store" (laporan user 7 Nov 2026: setelah keluar, halaman "Halo, Alex"
 * tampil lagi dari cache), tapi no-store membuat halaman tidak pernah masuk Back-Forward Cache: setiap
 * bolak-balik halaman selalu dimuat ulang penuh (laporan user 9 Nov 2026).
 *
 * Sekarang "no-cache": halaman boleh dipulihkan instan dari Back-Forward Cache saat tombol kembali/maju,
 * dan resources/js/session-guard.js memeriksa ke server setiap kali halaman dipulihkan dari cache, lalu
 * memuat ulang bila sesinya sudah berubah. Respons yang sudah mengatur masa cache sendiri dibiarkan.
 */
class PreventStaleHtml
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && ! str_contains((string) $response->headers->get('Cache-Control'), 'max-age')) {
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');
        }

        return $response;
    }
}
