@extends('layouts.app', ['title' => 'Pricing - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="page-shell px-5 pb-10 pt-6">
        <a href="{{ route('landing') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="page-title mt-3">Pricing</h1>
        <p class="page-subtitle">Satu paket simpel. Normkredit milik grup, bisa patungan.</p>

        {{-- Subscription Plan --}}
        <div class="panel-card mt-6 rounded-3xl p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-500">Normchat Pro</p>
            <p class="mt-2 text-4xl font-extrabold text-slate-900">Rp30.000<span class="text-base font-medium text-slate-400">/grup</span></p>

            <ul class="mt-5 space-y-2 text-sm text-slate-600">
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-emerald-500">&#10003;</span>
                    Full akses semua fitur
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-emerald-500">&#10003;</span>
                    12 normkredit included (30.000 token AI)
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-emerald-500">&#10003;</span>
                    AI assistant aktif otomatis untuk semua member
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-emerald-500">&#10003;</span>
                    Normkredit milik grup, semua member bisa patungan
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-emerald-500">&#10003;</span>
                    Backup & export histori chat
                </li>
            </ul>
        </div>

        {{-- Extra Normkredit --}}
        <div class="panel-card mt-4 rounded-3xl p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-500">Tambah Normkredit?</p>
            <p class="mt-1 text-xs text-slate-500">Opsional. 1 normkredit = 2.500 token = Rp2.500</p>

            {{-- Mode Toggle --}}
            <div class="mt-3 flex gap-2">
                <button type="button" onclick="setMode('credit')"
                        data-mode-btn="credit"
                        class="flex-1 rounded-xl border-2 border-blue-500 bg-blue-50 px-3 py-2 text-center text-xs font-bold text-blue-700 transition">
                    By Normkredit
                </button>
                <button type="button" onclick="setMode('price')"
                        data-mode-btn="price"
                        class="flex-1 rounded-xl border-2 border-slate-200 bg-white px-3 py-2 text-center text-xs font-bold text-slate-500 transition">
                    By Nominal (Rp)
                </button>
            </div>

            {{-- Input Area --}}
            <div class="mt-3">
                {{-- By Credit --}}
                <div id="creditPanel">
                    <input type="number" id="creditInput" min="0" step="1" value="0" placeholder="Jumlah normkredit"
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-lg font-bold text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                           oninput="calc()" />
                    <p class="mt-1 text-right text-xs text-slate-400">
                        = <span id="creditResult" class="font-semibold text-slate-600">Rp0 &middot; 0 token</span>
                    </p>
                </div>

                {{-- By Price --}}
                <div id="pricePanel" class="hidden">
                    <input type="number" id="priceInput" min="0" step="1000" value="0" placeholder="Nominal rupiah"
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-lg font-bold text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                           oninput="calc()" />
                    <p class="mt-1 text-right text-xs text-slate-400">
                        = <span id="priceResult" class="font-semibold text-slate-600">0 normkredit &middot; 0 token</span>
                    </p>
                </div>
            </div>

            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5">
                <p class="text-xs text-amber-700">
                    <span class="font-bold">Model multiplier:</span> Model canggih (misal 2x) pakai 100 token actual = dipotong 200 dari saldo grup.
                </p>
            </div>
        </div>

        {{-- Order Summary --}}
        <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Total Pesanan</p>

            <div class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-600">Biaya Pembuatan Grup</span>
                    <span class="font-bold text-slate-900">Rp30.000</span>
                </div>
                <div class="flex justify-between" id="extraRow">
                    <span class="text-slate-600">Tambahan normkredit</span>
                    <span class="font-bold text-slate-900" id="extraPrice">Rp0</span>
                </div>
                <hr class="border-slate-100" />
                <div class="flex justify-between">
                    <span class="font-bold text-slate-800">Total bayar</span>
                    <span class="text-xl font-extrabold text-blue-600" id="totalPrice">Rp30.000</span>
                </div>
            </div>

            <p class="mt-2 text-[11px] text-slate-400" id="totalSummary">
                Dapat 12 normkredit (30.000 token) per pembuatan grup.
            </p>
        </div>

        <a href="{{ route('subscription.payment.detail') }}"
           class="btn-cta mt-6 py-4">
            Lanjut Aktivasi Paket
        </a>

        <p class="mt-3 text-center text-[11px] text-slate-400">
            Klik lanjut, aktivasi paket, lalu langsung masuk ke workspace grup.
        </p>
    </section>

    <script>
        const SUB_PRICE = 30000;
        const PRICE_PER_NK = 2500;
        const TOKENS_PER_NK = 2500;
        const SUB_NK = 12;

        let mode = 'credit';

        function fmt(n) { return 'Rp' + n.toLocaleString('id-ID'); }
        function fmtToken(n) {
            if (n >= 1000000) return (n/1000000).toLocaleString('id-ID',{maximumFractionDigits:1}) + 'M';
            if (n >= 1000) return (n/1000).toLocaleString('id-ID',{maximumFractionDigits:1}) + 'K';
            return n.toLocaleString('id-ID');
        }

        function setMode(m) {
            mode = m;
            document.getElementById('creditPanel').classList.toggle('hidden', m !== 'credit');
            document.getElementById('pricePanel').classList.toggle('hidden', m !== 'price');
            document.querySelectorAll('[data-mode-btn]').forEach(btn => {
                const on = btn.dataset.modeBtn === m;
                btn.className = 'flex-1 rounded-xl border-2 px-3 py-2 text-center text-xs font-bold transition '
                    + (on ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500');
            });
            calc();
        }

        function calc() {
            let extraNk = 0, extraRp = 0;

            if (mode === 'credit') {
                extraNk = parseFloat(document.getElementById('creditInput').value) || 0;
                extraRp = Math.ceil(extraNk * PRICE_PER_NK);
                const tokens = Math.floor(extraNk * TOKENS_PER_NK);
                document.getElementById('creditResult').innerHTML =
                    fmt(extraRp) + ' &middot; ' + fmtToken(tokens) + ' token';
            } else {
                extraRp = parseInt(document.getElementById('priceInput').value) || 0;
                extraNk = extraRp / PRICE_PER_NK;
                const tokens = Math.floor(extraNk * TOKENS_PER_NK);
                const nkText = extraNk % 1 === 0 ? extraNk : extraNk.toFixed(1);
                document.getElementById('priceResult').innerHTML =
                    nkText + ' normkredit &middot; ' + fmtToken(tokens) + ' token';
            }

            // Update summary
            document.getElementById('extraPrice').textContent = fmt(extraRp);
            document.getElementById('extraRow').style.display = extraRp > 0 ? '' : 'none';

            const total = SUB_PRICE + extraRp;
            document.getElementById('totalPrice').textContent = fmt(total);

            const totalNk = SUB_NK + extraNk;
            const totalTokens = Math.floor(totalNk * TOKENS_PER_NK);
            document.getElementById('totalSummary').textContent =
                'Dapat ' + (totalNk % 1 === 0 ? totalNk : totalNk.toFixed(1))
                + ' normkredit (' + fmtToken(totalTokens) + ' token) total.';
        }

        // Init
        calc();
    </script>
@endsection
