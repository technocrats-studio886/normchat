@extends('layouts.app', ['title' => 'Pricing - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="page-shell px-5 pb-10 pt-6">
        <a href="{{ route('landing') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="page-title mt-3">Pricing</h1>
        <p class="page-subtitle">Satu paket simpel untuk mulai bikin grup privat tim Anda.</p>

        <div class="panel-card mt-6 rounded-3xl p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-500">Normchat Pro</p>
            <p class="mt-2 text-4xl font-extrabold text-slate-900">Rp15.000<span class="text-base font-medium text-slate-400">/bulan</span></p>

            <ul class="mt-5 space-y-2 text-sm text-slate-600">
                <li>• Buat grup sebagai owner</li>
                <li>• Share group via ID + password</li>
                <li>• Connect AI provider (ChatGPT, Claude, Gemini)</li>
                <li>• Backup dan export histori chat</li>
            </ul>
        </div>

        <a href="{{ route('login', ['next' => 'subscription.payment.detail']) }}"
           class="btn-cta mt-6 py-4">
            Connect AI Provider
        </a>

        <p class="mt-3 text-center text-[11px] text-slate-400">
            Setelah connect, lanjut ke halaman pembayaran.
        </p>
    </section>
@endsection
