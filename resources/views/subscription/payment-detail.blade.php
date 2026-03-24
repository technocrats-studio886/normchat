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
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama</p>
                <p class="mt-0.5 text-sm font-bold text-slate-900">{{ $user->name }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Akun AI Provider</p>
                <div class="mt-1 flex items-center gap-2">
                    @php
                        $providerLabel = match($provider) {
                            'chatgpt' => 'ChatGPT (OpenAI)',
                            'claude' => 'Claude (Anthropic)',
                            'gemini' => 'Gemini (Google)',
                            default => ucfirst($provider),
                        };
                        $providerColor = match($provider) {
                            'chatgpt' => 'bg-[#10A37F]',
                            'claude' => 'bg-[#D97706]',
                            'gemini' => 'bg-[#4285F4]',
                            default => 'bg-slate-500',
                        };
                    @endphp
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md {{ $providerColor }} text-white">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 24C12 24 12 12 24 12C12 12 12 0 12 0C12 0 12 12 0 12C12 12 12 24 12 24Z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">{{ $providerLabel }}</p>
                        <p class="text-xs text-slate-400">{{ $email }}</p>
                    </div>
                    <span class="ml-auto rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Connected</span>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Metode Pembayaran</p>
                <p class="mt-0.5 text-sm text-slate-700">Virtual Account (Dummy)</p>
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
        </div>

        {{-- Pay Button --}}
        <form method="POST" action="{{ route('subscription.pay') }}" class="mt-6">
            @csrf
            <input type="hidden" name="plan" value="normchat-pro" />
            <button type="submit" class="btn-cta w-full py-4 text-sm font-extrabold uppercase tracking-wide">
                Bayar Sekarang
            </button>
        </form>

        <p class="mt-4 pb-4 text-center text-[11px] text-slate-400">
            Pembayaran aman. Bisa batal kapan saja.
        </p>
    </section>
@endsection
