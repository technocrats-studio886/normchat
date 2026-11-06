<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tur pengenalan (docs/rancangan-tur-dan-blok-kode.md): tandai sudah dilihat, atau jalankan lagi dari menu Akun. */
class TourController extends Controller
{
    public function seen(Request $request): JsonResponse
    {
        $request->user()->forceFill(['tour_seen_at' => now()])->save();
        $request->session()->forget('tour_due');

        return response()->json(['ok' => true]);
    }

    public function again(Request $request): RedirectResponse
    {
        $request->session()->put('tour_due', true);

        return redirect()->route('chat.last');
    }
}
