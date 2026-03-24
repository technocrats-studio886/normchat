<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = Auth::user();

        $subscriptions = Subscription::query()
            ->whereHas('group.members', fn ($query) => $query->where('user_id', $user->id))
            ->orWhereHas('group', fn ($query) => $query->where('owner_id', $user->id))
            ->with('group')
            ->latest()
            ->get();

        return view('profile.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
        ]);
    }

    public function security(): View
    {
        $user = Auth::user();

        return view('profile.security', [
            'user' => $user,
        ]);
    }
}
