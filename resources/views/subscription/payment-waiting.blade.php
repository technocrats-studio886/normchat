@extends('layouts.app', ['title' => 'Menunggu Pembayaran - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="page-shell pt-6 pb-10">
        <h1 class="font-display text-2xl font-extrabold text-slate-900">Menunggu Pembayaran</h1>
        <p class="mt-1 text-sm text-slate-500">Selesaikan pembayaran via Trakteer dengan instruksi di bawah.</p>

        {{-- Order Card --}}
        <div class="panel-card mt-6 rounded-2xl p-5 space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kode Order</p>
                <div class="mt-1 flex items-center gap-2">
                    <p class="font-mono text-lg font-extrabold text-blue-700" id="orderCode">{{ $pending->order_id }}</p>
                    <button type="button" onclick="copyOrder()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-500 transition hover:bg-slate-50">
                        Salin
                    </button>
                </div>
            </div>

            <hr class="border-slate-100" />

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Bayar</p>
                    <p class="mt-0.5 text-2xl font-extrabold text-slate-900">Rp{{ number_format($pending->expected_amount, 0, ',', '.') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Tipe</p>
                    <p class="text-sm font-bold text-slate-700">
                        @switch($pending->payment_type)
                            @case('subscription') Subscription @break
                            @case('topup') Top-up Token @break
                            @case('add_seat') Tambah Seat @break
                        @endswitch
                    </p>
                </div>
            </div>

            @if($pending->expires_at)
                <div class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                    Berlaku sampai: <span class="font-bold">{{ $pending->expires_at->format('d M Y, H:i') }} WIB</span>
                </div>
            @endif
        </div>

        {{-- Instructions --}}
        <div class="mt-4 rounded-2xl border-2 border-blue-200 bg-blue-50 p-5 space-y-3">
            <p class="text-sm font-extrabold text-blue-900">Cara Bayar via Trakteer:</p>
            <ol class="space-y-2 text-sm text-blue-800">
                <li class="flex gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">1</span>
                    <span>Klik tombol <strong>"Buka Trakteer"</strong> di bawah</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">2</span>
                    <span>Pilih nominal <strong>Rp{{ number_format($pending->expected_amount, 0, ',', '.') }}</strong> (harus pas!)</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">3</span>
                    <span>Di kolom <strong>"Pesan Dukungan"</strong>, paste kode order: <code class="rounded bg-blue-200 px-1.5 py-0.5 font-mono text-xs font-bold text-blue-900">{{ $pending->order_id }}</code></span>
                </li>
                <li class="flex gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">4</span>
                    <span>Selesaikan pembayaran (QRIS/VA/E-Wallet)</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">5</span>
                    <span>Halaman ini <strong>otomatis redirect</strong> saat pembayaran dikonfirmasi</span>
                </li>
            </ol>

            <div class="rounded-lg bg-amber-100 px-3 py-2 text-xs text-amber-800">
                <strong>Penting:</strong> Nominal harus tepat <strong>Rp{{ number_format($pending->expected_amount, 0, ',', '.') }}</strong> dan kode order harus ada di pesan dukungan. Jika tidak cocok, pembayaran tidak akan terverifikasi otomatis.
            </div>
        </div>

        {{-- Open Trakteer Button --}}
        <a href="{{ $trakteerUrl }}" target="_blank" rel="noopener noreferrer"
           class="btn-cta mt-6 flex w-full items-center justify-center gap-2 py-4 text-sm font-extrabold uppercase tracking-wide">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            Buka Trakteer
        </a>

        {{-- Status Indicator --}}
        <div class="mt-6 flex items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white p-4" id="statusBox">
            <div class="h-3 w-3 animate-pulse rounded-full bg-amber-400" id="statusDot"></div>
            <p class="text-sm font-semibold text-slate-600" id="statusText">Menunggu pembayaran...</p>
        </div>

        <p class="mt-3 text-center text-[11px] text-slate-400">
            Halaman ini otomatis cek status setiap 5 detik. Jangan tutup halaman ini.
        </p>
    </section>

    <script>
        function copyOrder() {
            const code = document.getElementById('orderCode').textContent.trim();
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.target;
                btn.textContent = 'Disalin!';
                setTimeout(() => { btn.textContent = 'Salin'; }, 2000);
            });
        }

        // Poll payment status every 5 seconds
        const ORDER_ID = @json($pending->order_id);
        const STATUS_URL = @json(route('subscription.payment.status'));
        let polling = true;

        async function checkStatus() {
            if (!polling) return;

            try {
                const res = await fetch(`${STATUS_URL}?order_id=${encodeURIComponent(ORDER_ID)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (data.status === 'paid') {
                    polling = false;
                    document.getElementById('statusDot').className = 'h-3 w-3 rounded-full bg-emerald-500';
                    document.getElementById('statusText').textContent = 'Pembayaran dikonfirmasi! Redirecting...';
                    document.getElementById('statusBox').className = 'mt-6 flex items-center justify-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4';

                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else if (data.status === 'expired') {
                    polling = false;
                    document.getElementById('statusDot').className = 'h-3 w-3 rounded-full bg-red-500';
                    document.getElementById('statusText').textContent = 'Order expired. Silakan buat order baru.';
                    document.getElementById('statusBox').className = 'mt-6 flex items-center justify-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4';
                }
            } catch (e) {
                // Ignore fetch errors, will retry
            }
        }

        setInterval(checkStatus, 5000);
        checkStatus();
    </script>
@endsection
