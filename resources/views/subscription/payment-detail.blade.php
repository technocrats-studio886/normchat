@extends('layouts.app', ['title' => 'Payment Detail - Normchat'])

@section('content')
    <section class="page-shell pt-6">
        <a href="{{ route('subscription.pricing') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-2xl font-extrabold text-slate-900">Payment Detail</h1>
        <p class="mt-1 text-sm text-slate-500">Konfirmasi detail pembayaran langganan Normchat Pro.</p>

        {{-- Account Info --}}
        <div class="panel-card mt-6 rounded-2xl p-5 space-y-4">
            <div class="flex items-center gap-3">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-10 w-10 rounded-full object-cover" referrerpolicy="no-referrer" />
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                </div>
                <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-600">
                    Interdotz SSO
                </span>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status Aktivasi</p>
                <p class="mt-0.5 text-sm text-slate-700">Aktif instan setelah kamu lanjutkan.</p>
            </div>

            <hr class="border-slate-100" />

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Paket</p>
                    <p class="mt-0.5 text-sm font-bold text-slate-900">Normchat Pro</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Bulanan</p>
                    <p class="text-xl font-extrabold text-slate-900">Rp{{ number_format($planPrice, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs font-semibold text-emerald-700">Termasuk dalam paket:</p>
                <ul class="mt-1 space-y-0.5 text-xs text-emerald-600">
                    <li>&#10003; Full akses semua fitur</li>
                    <li>&#10003; 12 normkredit (dialokasikan ke grup)</li>
                </ul>
            </div>
        </div>

        {{-- Pay Button --}}
        <form method="POST" action="{{ route('subscription.pay') }}" class="mt-6">
            @csrf
            <input type="hidden" name="plan" value="normchat-pro" />
            <button type="submit" class="btn-cta w-full py-4 text-sm font-extrabold uppercase tracking-wide">
                Aktifkan Paket & Masuk Grup
            </button>
        </form>

        <p class="mt-4 pb-4 text-center text-[11px] text-slate-400">
            Ini simulasi aktivasi UI. Setelah klik, kamu langsung diarahkan ke workspace grup.
        </p>
    </section>
@endsection
