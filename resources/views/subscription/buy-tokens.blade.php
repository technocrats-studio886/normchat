@extends('layouts.app', ['title' => 'Top-up Normkredit - Normchat'])

@section('content')
    <section class="page-shell pt-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-2xl font-extrabold text-slate-900">Top-up Normkredit</h1>
        <p class="mt-1 text-sm text-slate-500">Patungan normkredit untuk grup kamu. 1 normkredit = Rp{{ number_format($pricePerCredit, 0, ',', '.') }}</p>
        <p class="mt-0.5 text-xs text-slate-400">Rp30.000 = 12 normkredit (minimum)</p>

        <form method="POST" action="{{ route('subscription.tokens.buy.process') }}" class="mt-6 space-y-4">
            @csrf

            {{-- Select Group --}}
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pilih Grup</label>
                @if($groups->isEmpty())
                    <p class="mt-2 text-sm text-slate-500">Belum ada grup. Buat grup terlebih dahulu.</p>
                @else
                    <select name="group_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @foreach($groups as $g)
                            @php $gt = $g->groupToken; @endphp
                            <option value="{{ $g->id }}" {{ old('group_id') == $g->id ? 'selected' : '' }}>
                                {{ $g->name }} — {{ number_format($gt?->credits ?? 0, 1) }} normkredit
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Mode Toggle --}}
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mode Pembelian</label>
                <div class="mt-2 flex gap-2">
                    <button type="button"
                            class="flex-1 rounded-xl border-2 px-3 py-2.5 text-center text-sm font-bold transition"
                            data-mode-btn="by_credits"
                            onclick="setMode('by_credits')">
                        By Normkredit
                    </button>
                    <button type="button"
                            class="flex-1 rounded-xl border-2 px-3 py-2.5 text-center text-sm font-bold transition"
                            data-mode-btn="by_price"
                            onclick="setMode('by_price')">
                        By Nominal Harga
                    </button>
                </div>
                <input type="hidden" name="mode" id="modeInput" value="by_credits" />
            </div>

            {{-- By Credits Input --}}
            <div class="panel-card rounded-2xl p-4" id="byCreditsPanel">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah Normkredit</label>

                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach([12 => '12', 20 => '20', 24 => '24', 50 => '50', 100 => '100', 200 => '200'] as $opt => $label)
                        <button type="button"
                                class="rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-400 hover:bg-blue-50"
                                onclick="setCreditAmount({{ $opt }})">
                            {{ $label }} normkredit
                        </button>
                    @endforeach
                </div>

                <input type="number" name="credit_amount" id="creditAmountInput"
                       value="{{ old('credit_amount', 12) }}"
                       min="12" step="1"
                       placeholder="Min. 12 normkredit"
                       class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                       oninput="calcFromCredits()" />

                <div class="mt-2 flex items-center justify-end text-sm">
                    <span class="text-slate-600">Harga: <span class="font-bold text-slate-900" id="calcPriceFromCredits">Rp30.000</span></span>
                </div>
            </div>

            {{-- By Price Input --}}
            <div class="panel-card rounded-2xl p-4 hidden" id="byPricePanel">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal Harga (Rp)</label>

                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach([30000, 50000, 75000, 100000, 150000, 250000] as $opt)
                        <button type="button"
                                class="rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-400 hover:bg-blue-50"
                                onclick="setPriceAmount({{ $opt }})">
                            Rp{{ number_format($opt, 0, ',', '.') }}
                        </button>
                    @endforeach
                </div>

                <input type="number" name="price_amount" id="priceAmountInput"
                       value="{{ old('price_amount', 30000) }}"
                       min="30000" step="2500"
                       placeholder="Min. Rp30.000"
                       class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                       oninput="calcFromPrice()" />

                <div class="mt-2 flex items-center justify-end text-sm">
                    <span class="text-slate-600">= <span class="font-bold text-slate-900" id="calcCreditsFromPrice">12</span> normkredit</span>
                </div>
            </div>

            @if($groups->isNotEmpty())
                <button type="submit" class="btn-cta w-full py-4 text-sm font-extrabold uppercase tracking-wide">
                    Top-up Instan
                </button>
            @endif
        </form>

        <p class="mt-4 pb-4 text-center text-[11px] text-slate-400">
            Tidak ada payment gateway. Normkredit langsung masuk ke saldo grup.
        </p>
    </section>

    <script>
        const PRICE_PER_CREDIT = {{ $pricePerCredit }};
        const TOKENS_PER_CREDIT = 2500;

        function setMode(mode) {
            document.getElementById('modeInput').value = mode;
            document.getElementById('byCreditsPanel').classList.toggle('hidden', mode !== 'by_credits');
            document.getElementById('byPricePanel').classList.toggle('hidden', mode !== 'by_price');

            document.querySelectorAll('[data-mode-btn]').forEach(btn => {
                const isActive = btn.dataset.modeBtn === mode;
                btn.className = 'flex-1 rounded-xl border-2 px-3 py-2.5 text-center text-sm font-bold transition '
                    + (isActive ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500');
            });
        }

        function setCreditAmount(amount) {
            document.getElementById('creditAmountInput').value = amount;
            calcFromCredits();
        }

        function setPriceAmount(amount) {
            document.getElementById('priceAmountInput').value = amount;
            calcFromPrice();
        }

        function calcFromCredits() {
            const credits = parseFloat(document.getElementById('creditAmountInput').value) || 0;
            const price = Math.ceil(credits * PRICE_PER_CREDIT);
            document.getElementById('calcPriceFromCredits').textContent = 'Rp' + price.toLocaleString('id-ID');
        }

        function calcFromPrice() {
            const price = parseInt(document.getElementById('priceAmountInput').value) || 0;
            const credits = price / PRICE_PER_CREDIT;
            document.getElementById('calcCreditsFromPrice').textContent = credits % 1 === 0 ? credits : credits.toFixed(1);
        }

        setMode('by_credits');
        calcFromCredits();
        calcFromPrice();
    </script>
@endsection
