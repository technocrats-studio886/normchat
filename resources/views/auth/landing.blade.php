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
                Login dengan akun Google kamu untuk mulai. Cepat, aman, tanpa ribet.
            </p>

            {{-- Google SSO button --}}
            <div class="mt-8">
                <a href="{{ route('auth.google') }}"
                   class="google-btn flex items-center justify-center gap-3 rounded-full px-6 py-4 text-sm font-bold no-underline shadow-lg shadow-blue-900/20 transition hover:brightness-105 active:scale-[0.98]">
                    <span class="google-mark">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    </span>
                    <span>Login dengan Google</span>
                </a>
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                Login aman menggunakan Google SSO. Data kamu terenkripsi dan terlindungi.
            </p>
            <a href="{{ route('subscription.pricing') }}"
               class="mt-2 text-center text-xs font-semibold text-slate-400 underline-offset-2 hover:text-slate-600 hover:underline">
                Lihat harga
            </a>
        </div>
    </section>
@endsection
