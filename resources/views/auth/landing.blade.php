@extends('layouts.app')

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen items-center justify-center px-5 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-sm rounded-[28px] border bg-white p-8 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.4)]" style="border-color: var(--nc-line);">
            <div class="flex flex-col items-center text-center">
                <img src="{{ asset('normchat-logo.png') }}" alt="Normchat Logo" class="h-14 w-14 object-contain" loading="eager" decoding="async" />

                <h1 class="mt-4 font-display text-[34px] font-extrabold leading-none tracking-tight text-slate-900">Normchat</h1>
                <p class="mt-2 text-sm text-slate-500">Login dengan akun Interdotz untuk melanjutkan.</p>

                <div class="mt-7 w-full space-y-3">
                    <a href="{{ route('auth.interdotz.login') }}"
                              class="google-btn flex items-center justify-center rounded-full px-6 py-4 text-sm font-bold no-underline shadow-md transition hover:brightness-105 active:scale-[0.98]"
                              style="box-shadow: 0 12px 24px -16px color-mix(in srgb, var(--nc-primary) 45%, transparent);">
                        Login dengan Interdotz
                    </a>

                    <a href="{{ route('auth.interdotz.register') }}"
                       class="flex items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50 active:scale-[0.98]">
                        Buat akun Interdotz
                    </a>
                </div>

                <a href="{{ route('auth.interdotz.login') }}"
                         class="mt-4 text-xs font-semibold underline-offset-2 hover:underline"
                         style="color: var(--nc-primary);">
                    Lihat harga
                </a>
            </div>
        </div>
    </section>
@endsection
