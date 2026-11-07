<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman HTML bergantung pada sesi (sudah masuk atau belum), jadi browser tidak boleh menampilkannya
 * dari cache saat tombol kembali ditekan. Tanpa ini, setelah keluar halaman "Halo, Alex" bisa tampil lagi
 * dari cache dan permintaan latarnya berulang kali dialihkan ke /login (laporan user 7 Nov 2026);
 * sebaliknya setelah masuk, tombol kembali menampilkan halaman masuk lagi.
 * Respons yang sudah mengatur masa cache sendiri (lampiran, canvas) dibiarkan.
 */
class PreventStaleHtml
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && ! str_contains((string) $response->headers->get('Cache-Control'), 'max-age')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
