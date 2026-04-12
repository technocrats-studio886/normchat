@extends('layouts.app', ['title' => 'Profile - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <h1 class="mb-5 font-display text-xl font-extrabold text-slate-900">Profile</h1>

        <div class="space-y-3">
            {{-- User info card with avatar --}}
            <div class="flex items-center gap-4 rounded-2xl border border-[#dbe6ff] bg-white px-4 py-4 shadow-sm">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-14 w-14 rounded-full object-cover ring-2 ring-blue-100" referrerpolicy="no-referrer" />
                @else
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 text-xl font-bold text-blue-600">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-base font-bold text-slate-900">{{ $user->name }}</h3>
                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-600">
                        Interdotz SSO
                    </span>
                </div>
            </div>

            {{-- Subscription --}}
            <div class="flex items-center justify-between rounded-2xl border border-[#dbe6ff] bg-white px-4 py-3.5 shadow-sm">
                <span class="text-sm text-slate-700">Subscription</span>
                @php
                    $activeSub = $subscriptions->firstWhere('status', 'active');
                @endphp
                <span class="text-sm font-bold {{ $activeSub ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $activeSub ? 'Pro Active' : 'Inactive' }}
                </span>
            </div>

            {{-- Normkredit per Grup --}}
            @php
                $totalCredits = 0;
                foreach ($subscriptions as $sub) {
                    if ($sub->status === 'active' && $sub->group) {
                        $totalCredits += ($sub->group->groupToken?->credits ?? 0);
                    }
                }
            @endphp
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-4 py-3.5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-700">Total Normkredit Grup</span>
                    <span class="text-sm font-bold text-blue-600">{{ number_format($totalCredits, 1) }} normkredit</span>
                </div>
                <p class="mt-0.5 text-[11px] text-slate-400">1 normkredit = Rp2.500</p>
                <a href="{{ route('subscription.tokens.buy') }}" class="mt-2 block text-xs font-semibold text-blue-500 hover:text-blue-700">
                    Top-up Normkredit &rarr;
                </a>
            </div>

            {{-- Keamanan Akun --}}
            <a href="{{ route('profile.security') }}" class="flex items-center justify-between rounded-2xl border border-[#dbe6ff] bg-white px-4 py-3.5 shadow-sm">
                <span class="text-sm text-slate-700">Keamanan Akun</span>
                <span class="text-sm font-bold text-blue-600">Kelola</span>
            </a>

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-2xl bg-rose-50 py-3.5 text-center text-sm font-semibold text-red-500 transition hover:bg-rose-100">
                    Logout
                </button>
            </form>
        </div>
    </section>
@endsection
