@extends('layouts.app')

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen items-center justify-center px-5 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-sm rounded-[28px] border bg-white p-8 shadow-[0_18px_40px_-34px_rgba(18, 28, 41,0.4)]" style="border-color: var(--nc-line);">
            <div class="flex flex-col items-center text-center">
                <img src="{{ asset('brand/logo-mark.svg') }}" alt="Normi Chat Logo" class="h-14 w-14 object-contain" loading="eager" decoding="async" />

                <h1 class="mt-4 font-display text-[34px] font-extrabold leading-none tracking-tight text-slate-900">Normi Chat</h1>
                <p class="mt-2 text-sm text-slate-500">Masuk untuk melanjutkan.</p>

                @error('auth')
                    <p class="mt-4 w-full rounded-xl bg-danger-soft px-3 py-2 text-xs font-semibold text-danger-ink">{{ $message }}</p>
                @enderror

                <div class="mt-7 w-full space-y-3">
                    <a href="{{ route('auth.google.redirect') }}"
                       class="flex items-center justify-center gap-3 rounded-full border border-slate-200 bg-white px-6 py-4 text-sm font-bold text-slate-800 no-underline shadow-md transition hover:bg-slate-50 active:scale-[0.98]">
                        <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                            <path fill="#C43D2E" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                            <path fill="#C43D2E" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                            <path fill="#7186A3" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                            <path fill="#526884" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                        </svg>
                        Masuk dengan Google
                    </a>

                    @if($interdotzEnabled)
                        <a href="{{ route('auth.interdotz.login') }}"
                           class="flex items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50 active:scale-[0.98]">
                            Masuk dengan Interdotz
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
