<?php

namespace App\Http\Controllers;

use App\Models\AiConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClaudeConnectionController extends Controller
{
    public function connect(): RedirectResponse
    {
        return redirect()->away('https://claude.ai/login');
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:10', 'max:8192'],
        ]);

        $user = Auth::user();
        abort_unless($user !== null, 401);

        AiConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'claude',
                'access_token' => encrypt($validated['token']),
                'refresh_token' => null,
                'expires_at' => null,
            ]
        );

        return redirect()->route('groups.create')->with('success', 'Claude berhasil terhubung. Anda sekarang bisa membuat group.');
    }
}
