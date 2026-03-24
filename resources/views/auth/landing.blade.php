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
                Connect akun AI provider kamu untuk mulai. Tidak perlu daftar manual.
            </p>

            @php
                $logoMap = [
                    'chatgpt' => file_exists(public_path('provider-logos/chatgpt.png'))
                        ? asset('provider-logos/chatgpt.png')
                        : asset('normchat-logo.svg'),
                    'claude' => file_exists(public_path('provider-logos/claude.png'))
                        ? asset('provider-logos/claude.png')
                        : asset('normchat-logo.svg'),
                    'gemini' => file_exists(public_path('provider-logos/gemini.png'))
                        ? asset('provider-logos/gemini.png')
                        : asset('normchat-logo.svg'),
                ];
            @endphp

            {{-- Connect buttons --}}
            <div class="mt-8 space-y-3">
                {{-- ChatGPT → API key connect --}}
                <a href="{{ route('auth.connect.chatgpt') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white">
                        <img src="{{ $logoMap['chatgpt'] }}" alt="ChatGPT" class="h-full w-full object-contain" />
                    </span>
                    <span>Connect ChatGPT</span>
                </a>

                {{-- Claude → API key connect --}}
                <a href="{{ route('auth.connect.claude') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white">
                        <img src="{{ $logoMap['claude'] }}" alt="Claude" class="h-full w-full object-contain" />
                    </span>
                    <span>Connect Claude</span>
                </a>

                {{-- Gemini → Google OAuth --}}
                <a href="{{ route('auth.connect', 'gemini') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white">
                        <img src="{{ $logoMap['gemini'] }}" alt="Gemini" class="h-full w-full object-contain" />
                    </span>
                    <span>Connect Gemini</span>
                </a>
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                Token provider dienkripsi AES-256 dan hanya dipakai untuk akses AI di grup.
            </p>
            <a href="{{ route('subscription.pricing') }}"
               class="mt-2 text-center text-xs font-semibold text-slate-400 underline-offset-2 hover:text-slate-600 hover:underline">
                Lihat harga
            </a>
        </div>
    </section>
@endsection
