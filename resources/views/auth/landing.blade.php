@extends('layouts.app')

@section('disableAppJs', '1')

@section('content')
    <section class="page-shell flex min-h-screen flex-col bg-transparent px-0 pb-0 pt-0">
        {{-- Header --}}
        <header class="border-b border-[#dbe6ff] bg-white px-5 py-5">
            <div class="inline-flex items-center gap-2">
                <img src="{{ asset('normchat-logo.svg') }}" alt="Normchat Logo" class="h-9 w-9 rounded-xl" />
                <h1 class="font-display text-xl font-extrabold tracking-tight text-slate-900">Normchat</h1>
            </div>
        </header>

        {{-- Main content --}}
        <div class="flex flex-1 flex-col justify-end px-6 pb-12">
            <h2 class="font-display text-[26px] font-extrabold leading-tight text-slate-900">
                Start chatting with AI
            </h2>
            <p class="mt-2 text-sm text-slate-500">
                Login dengan akun Interdotz kamu untuk mulai. Cepat, aman, tanpa ribet.
            </p>

            {{-- Interdotz SSO buttons --}}
            <div class="mt-8">
                <a href="{{ route('auth.interdotz.login') }}"
                   class="google-btn flex items-center justify-center gap-3 rounded-full px-6 py-4 text-sm font-bold no-underline shadow-lg shadow-blue-900/20 transition hover:brightness-105 active:scale-[0.98]">
                    <span class="google-mark">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                    </span>
                    <span>Login dengan Interdotz</span>
                </a>

                <a href="{{ route('auth.interdotz.register') }}"
                   class="mt-3 flex items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50 active:scale-[0.98]">
                    Buat akun Interdotz
                </a>
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                Login aman menggunakan Interdotz SSO. Data kamu terenkripsi dan terlindungi.
            </p>
            <a href="{{ route('subscription.pricing') }}"
               class="mt-2 text-center text-xs font-semibold text-slate-400 underline-offset-2 hover:text-slate-600 hover:underline">
                Lihat harga
            </a>
        </div>
    </section>
@endsection
