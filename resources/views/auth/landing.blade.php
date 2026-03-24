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

            {{-- Connect buttons --}}
            <div class="mt-8 space-y-3">
                {{-- ChatGPT → API key connect --}}
                <a href="{{ route('auth.connect.chatgpt') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#10A37F]">
                        {{-- OpenAI sparkle icon --}}
                        <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"/>
                        </svg>
                    </span>
                    <span>Connect ChatGPT</span>
                </a>

                {{-- Claude → API key connect --}}
                <a href="{{ route('auth.connect.claude') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#D97706]">
                        {{-- Anthropic icon --}}
                        <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M13.827 3.52h3.603L24 20.48h-3.603l-6.57-16.96zm-7.258 0H10.172L16.74 20.48H13.14L11.06 15.14H5.56l-2.07 5.34H0L6.569 3.52zM6.67 12.292h3.264L8.303 7.572l-1.634 4.72z"/>
                        </svg>
                    </span>
                    <span>Connect Claude</span>
                </a>

                {{-- Gemini → Google OAuth --}}
                <a href="{{ route('auth.connect', 'gemini') }}"
                   class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#4285F4]">
                        {{-- Google G icon --}}
                        <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#fff"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fff"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/>
                        </svg>
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
