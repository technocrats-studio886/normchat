@extends('layouts.app', ['title' => 'Join Group - Normchat'])

@section('content')
    <section class="page-shell flex flex-col items-center justify-center px-6 pt-10">
        <div class="w-full max-w-sm">
            <h1 class="font-display text-xl font-extrabold text-slate-900">{{ $group->name }}</h1>
            <p class="mt-1 text-xs text-slate-400">ID: {{ $group->share_id }}</p>

            @if($group->description)
                <p class="mt-1 text-sm text-slate-500">{{ $group->description }}</p>
            @endif

            @if($alreadyMember)
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Anda sudah menjadi member group ini.
                </div>
                <a href="{{ route('chat.show', $group) }}" class="btn-cta mt-4 block text-center">
                    Masuk ke Chat
                </a>
            @else
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-800">Untuk masuk grup ini kamu harus patungan buat buka slot kamu</p>
                    <p class="mt-1 text-xs text-amber-600">Patungan normkredit untuk grup + biaya buka seat.</p>
                </div>

                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-5 space-y-3">
                    @csrf

                    {{-- Password --}}
                    <div class="panel-card px-4 py-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Password Grup</label>
                        <input type="password" name="password" required
                               placeholder="Masukkan password grup"
                               class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400" />
                    </div>
                    @if($errors->has('password'))
                        <p class="text-xs text-rose-600">{{ $errors->first('password') }}</p>
                    @endif

                    {{-- Patungan Amount --}}
                    <div class="panel-card px-4 py-4">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Patungan Normkredit</label>
                        <p class="mt-1 text-xs text-slate-500">Min. Rp{{ number_format($minPatungan, 0, ',', '.') }} ({{ $minPatungan / $pricePerNormkredit }} normkredit)</p>

                        <input type="number" name="patungan_amount" id="patunganInput"
                               value="{{ old('patungan_amount', $minPatungan) }}"
                               min="{{ $minPatungan }}" step="1000"
                               class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-lg font-bold text-slate-900 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                               oninput="calcPatungan()" />

                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-slate-600">= <span class="font-bold text-blue-600" id="calcNormkredit">{{ $minPatungan / $pricePerNormkredit }}</span> normkredit</span>
                            <span class="text-slate-600">(<span class="font-bold text-slate-800" id="calcTokens">{{ number_format($minPatungan / $pricePerNormkredit * 1000) }}</span> token)</span>
                        </div>
                    </div>
                    @if($errors->has('patungan_amount'))
                        <p class="text-xs text-rose-600">{{ $errors->first('patungan_amount') }}</p>
                    @endif

                    {{-- Summary --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Patungan normkredit</span>
                            <span class="font-bold text-slate-900" id="summaryPatungan">Rp{{ number_format($minPatungan, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span class="text-slate-600">Biaya seat</span>
                            <span class="font-bold text-slate-900">Rp{{ number_format($seatPrice, 0, ',', '.') }}</span>
                        </div>
                        <hr class="my-2 border-slate-200" />
                        <div class="flex justify-between">
                            <span class="font-bold text-slate-800">Total bayar</span>
                            <span class="text-lg font-extrabold text-blue-600" id="summaryTotal">Rp{{ number_format($minPatungan + $seatPrice, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-cta w-full py-4">
                        Bayar & Bergabung
                    </button>

                    <p class="text-center text-[11px] text-slate-400">
                        Normkredit langsung masuk ke saldo grup. 1 normkredit = 1.000 token.
                    </p>
                </form>
            @endif
        </div>
    </section>

    @if(!($alreadyMember ?? false))
    <script>
        const PRICE_PER_NK = {{ $pricePerNormkredit }};
        const SEAT_PRICE = {{ $seatPrice }};
        const TOKENS_PER_NK = 1000;

        function calcPatungan() {
            const amount = parseInt(document.getElementById('patunganInput').value) || 0;
            const nk = amount / PRICE_PER_NK;
            const tokens = Math.floor(nk * TOKENS_PER_NK);
            const total = amount + SEAT_PRICE;

            document.getElementById('calcNormkredit').textContent = nk % 1 === 0 ? nk : nk.toFixed(1);
            document.getElementById('calcTokens').textContent = tokens.toLocaleString('id-ID');
            document.getElementById('summaryPatungan').textContent = 'Rp' + amount.toLocaleString('id-ID');
            document.getElementById('summaryTotal').textContent = 'Rp' + total.toLocaleString('id-ID');
        }
    </script>
    @endif
@endsection
