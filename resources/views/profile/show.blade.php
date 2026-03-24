@extends('layouts.app', ['title' => 'Profile - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-3">
        <h1 class="mb-5 text-xl font-extrabold text-slate-900 font-display">Profile</h1>

        <div class="space-y-3">
            {{-- User info card --}}
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5">
                <h3 class="text-sm font-bold text-slate-900">{{ $user->name }}</h3>
                <p class="text-xs text-slate-500">{{ $user->email }}</p>
            </div>

            {{-- Subscription --}}
            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5">
                <span class="text-sm text-slate-700">Subscription</span>
                @php
                    $activeSub = $subscriptions->firstWhere('status', 'active');
                @endphp
                <span class="text-sm font-bold {{ $activeSub ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $activeSub ? 'Pro Active' : 'Inactive' }}
                </span>
            </div>

            {{-- AI Provider --}}
            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5">
                <span class="text-sm text-slate-700">AI Provider</span>
                <span class="text-sm font-bold {{ $user->aiConnection ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $user->aiConnection ? ucfirst($user->aiConnection->provider) . ' — Connected' : 'Belum connect' }}
                </span>
            </div>

            {{-- Keamanan Akun --}}
            <a href="{{ route('profile.security') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5">
                <span class="text-sm text-slate-700">Keamanan Akun</span>
                <span class="text-sm font-bold text-blue-600">Kelola</span>
            </a>

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-xl bg-rose-50 py-3.5 text-center text-sm font-semibold text-red-500 transition hover:bg-rose-100">
                    Logout
                </button>
            </form>
        </div>
    </section>
@endsection
