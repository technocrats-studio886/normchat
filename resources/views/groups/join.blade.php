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
                    <p class="text-sm font-semibold text-amber-800">Lengkapi data join, lalu kamu langsung masuk ke grup</p>
                    <p class="mt-1 text-xs text-amber-600">UI patungan tetap ada, aktivasi seat dan token diproses instan.</p>
                </div>

                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-5 space-y-3">
                    @csrf

                    {{-- Password --}}
                    <div class="panel-card px-4 py-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Password Grup</label>
                        <div class="relative mt-1">
                            <input id="joinGroupPasswordInput" type="password" name="password" required
                                   placeholder="Masukkan password grup"
                                   class="w-full bg-transparent pr-10 text-sm text-slate-900 outline-none placeholder:text-slate-400" />
                            <button type="button" onclick="toggleJoinPassword()" class="absolute inset-y-0 right-0 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat password" title="Lihat password">
                                <svg id="joinPassShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg id="joinPassHide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03" />
                                </svg>
                            </button>
                        </div>
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
                            <span class="text-slate-600">(<span class="font-bold text-slate-800" id="calcTokens">{{ number_format($minPatungan / $pricePerNormkredit * 2500) }}</span> token)</span>
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
                        Masuk & Aktifkan
                    </button>

                    <p class="text-center text-[11px] text-slate-400">
                        Normkredit langsung masuk ke saldo grup. Tidak ada langkah pembayaran tambahan.
                    </p>
                </form>
            @endif
        </div>
    </section>

    @if(!($alreadyMember ?? false))
    <script>
        const PRICE_PER_NK = {{ $pricePerNormkredit }};
        const SEAT_PRICE = {{ $seatPrice }};
        const TOKENS_PER_NK = 2500;

        function toggleJoinPassword() {
            const input = document.getElementById('joinGroupPasswordInput');
            const show = document.getElementById('joinPassShow');
            const hide = document.getElementById('joinPassHide');

            if (!input) {
                return;
            }

            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';

            if (show && hide) {
                show.classList.toggle('hidden', makeVisible);
                hide.classList.toggle('hidden', !makeVisible);
            }
        }

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
