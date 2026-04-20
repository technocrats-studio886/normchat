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
                {{-- payment errors shown by layout --}}

                @php
                    $selectedMethod = old('payment_method', 'du');
                    $defaultAmount = $selectedMethod === 'du'
                        ? (int) $duPatungan
                        : (int) $idrPatungan;
                    $initialAmount = (int) old('patungan_amount', $defaultAmount);
                @endphp

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-800">Bergabung ke grup ini membutuhkan patungan</p>
                    <p class="mt-1 text-xs text-amber-600">Anda akan mendapatkan <strong><span id="joinCreditsValue">{{ number_format((float) ($joinCreditsByMethod['du'] ?? 0), 1, ',', '.') }}</span> Normkredit</strong> setelah pembayaran terkonfirmasi.</p>
                    <p class="mt-1 text-[11px] text-amber-700">Rasio patungan: <strong>25 DU = 10 Normkredit</strong>, sedangkan <strong>Rp5.000 = 8 Normkredit</strong>.</p>
                    <p class="mt-1 text-[11px] font-semibold text-amber-800">Patungan ini menambah saldo AI bersama di grup. Rekomendasi: gunakan DU karena value Normkredit lebih besar.</p>
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

                    <div class="panel-card px-4 py-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Metode Pembayaran</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 px-3 py-2.5 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="du" class="mt-0.5 accent-blue-600" {{ old('payment_method', 'du') === 'du' ? 'checked' : '' }}>
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">Dots Units (DU)</span>
                                    <span class="block text-xs text-slate-500">Minimal {{ $duPatungan }} DU</span>
                                    <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Direkomendasikan</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 px-3 py-2.5 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="midtrans" class="mt-0.5 accent-blue-600" {{ old('payment_method') === 'midtrans' ? 'checked' : '' }}>
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">IDR</span>
                                    <span class="block text-xs text-slate-500">Minimal Rp{{ number_format($idrPatungan, 0, ',', '.') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="panel-card px-4 py-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400" for="joinAmountInput">Nominal Patungan</label>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500" id="joinAmountPrefix">DU</span>
                            <input id="joinAmountInput" type="number" name="patungan_amount" min="1" step="1" value="{{ $initialAmount }}"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                   placeholder="Masukkan nominal patungan" />
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500" id="joinAmountHelp"></p>
                    </div>
                    @if($errors->has('patungan_amount'))
                        <p class="text-xs text-rose-600">{{ $errors->first('patungan_amount') }}</p>
                    @endif

                    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Contoh Kalkulasi</p>
                        <p class="mt-1 text-[11px] text-blue-700">Estimasi ini mengikuti rasio patungan aktif dan dihitung proporsional.</p>
                        <div class="mt-2 space-y-1 text-[11px] text-blue-800" id="joinExamples"></div>
                    </div>

                    {{-- Summary --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Patungan</span>
                            <span class="font-bold text-slate-900" id="joinPatunganValue">{{ $duPatungan }} DU</span>
                        </div>
                        <hr class="my-2 border-slate-200" />
                        <div class="flex justify-between">
                            <span class="font-bold text-slate-800">Total bayar</span>
                            <span class="text-lg font-extrabold text-blue-600" id="joinTotalValue">{{ $duPatungan }} DU</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-cta w-full py-4" id="joinSubmitButton">
                        Bayar {{ $initialAmount }} {{ $selectedMethod === 'du' ? 'DU' : 'IDR' }} & Bergabung
                    </button>

                    <p class="text-center text-[11px] text-slate-400" id="joinPaymentHint">
                        Pembayaran menggunakan Dots Units dari akun Interdotz Anda.
                    </p>
                </form>
            @endif
        </div>
    </section>

    @if(!($alreadyMember ?? false))
    <script>
        const TOKENS_PER_CREDIT = 2500;

        const joinPricing = {
            du: {
                hint: 'Patungan DU menambah Normkredit AI bersama untuk semua member di grup.',
                minimum: {{ (int) ($joinPricingByMethod['du']['minimum'] ?? $duPatungan) }},
                baseAmount: {{ (int) ($joinPricingByMethod['du']['baseAmount'] ?? $duPatungan) }},
                tokensPerBase: {{ (int) (($joinPricingByMethod['du']['baseCredits'] ?? 10) * 2500) }},
                amountLabel: 'DU',
            },
            midtrans: {
                hint: 'Patungan IDR menambah Normkredit AI bersama untuk semua member di grup.',
                minimum: {{ (int) ($joinPricingByMethod['midtrans']['minimum'] ?? $idrPatungan) }},
                baseAmount: {{ (int) ($joinPricingByMethod['midtrans']['baseAmount'] ?? $idrPatungan) }},
                tokensPerBase: {{ (int) (($joinPricingByMethod['midtrans']['baseCredits'] ?? 8) * 2500) }},
                amountLabel: 'IDR',
            },
        };

        function fmtIdr(value) {
            return 'Rp' + Number(value || 0).toLocaleString('id-ID');
        }

        function fmtCredits(value) {
            const n = Number(value || 0);
            return n.toLocaleString('id-ID', { minimumFractionDigits: n % 1 === 0 ? 0 : 1, maximumFractionDigits: 1 });
        }

        function syncJoinPaymentUi() {
            const selected = document.querySelector('input[name="payment_method"]:checked')?.value || 'du';
            const cfg = joinPricing[selected] || joinPricing.du;
            const value = document.getElementById('joinPatunganValue');
            const total = document.getElementById('joinTotalValue');
            const button = document.getElementById('joinSubmitButton');
            const hint = document.getElementById('joinPaymentHint');
            const credits = document.getElementById('joinCreditsValue');
            const amountInput = document.getElementById('joinAmountInput');
            const amountHelp = document.getElementById('joinAmountHelp');
            const amountPrefix = document.getElementById('joinAmountPrefix');
            const examples = document.getElementById('joinExamples');

            if (!amountInput) {
                return;
            }

            amountInput.min = String(cfg.minimum);
            amountInput.step = '1';

            let amount = Number(amountInput.value || 0);
            if (!Number.isFinite(amount) || amount < cfg.minimum) {
                amount = cfg.minimum;
                amountInput.value = String(cfg.minimum);
            }

            const calculatedTokens = Math.floor((amount * cfg.tokensPerBase) / cfg.baseAmount);
            const calculatedCredits = calculatedTokens / TOKENS_PER_CREDIT;

            const displayAmount = selected === 'du' ? `${amount} DU` : fmtIdr(amount);

            if (value) value.textContent = displayAmount;
            if (total) total.textContent = displayAmount;
            if (button) button.textContent = `Bayar ${displayAmount} & Bergabung`;
            if (hint) hint.textContent = cfg.hint;
            if (credits) credits.textContent = fmtCredits(calculatedCredits);
            if (amountPrefix) amountPrefix.textContent = cfg.amountLabel;
            if (amountHelp) {
                amountHelp.textContent = selected === 'du'
                    ? `Minimal ${cfg.minimum} DU. Metode DU direkomendasikan karena memberikan value Normkredit lebih tinggi.`
                    : `Minimal ${fmtIdr(cfg.minimum)}. Nilai Normkredit IDR dibuat lebih kecil dibanding DU.`;
            }

            if (examples) {
                const sampleAmounts = [cfg.minimum, cfg.minimum * 2, cfg.minimum * 3];
                examples.innerHTML = sampleAmounts
                    .map((sampleAmount) => {
                        const sampleTokens = Math.floor((sampleAmount * cfg.tokensPerBase) / cfg.baseAmount);
                        const sampleCredits = sampleTokens / TOKENS_PER_CREDIT;
                        const sampleLabel = selected === 'du' ? `${sampleAmount} DU` : fmtIdr(sampleAmount);
                        return `<p>${sampleLabel} -> <strong>${fmtCredits(sampleCredits)} Normkredit</strong></p>`;
                    })
                    .join('');
            }
        }

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

        document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
            input.addEventListener('change', syncJoinPaymentUi);
        });
        document.getElementById('joinAmountInput')?.addEventListener('input', syncJoinPaymentUi);
        syncJoinPaymentUi();
    </script>
    @endif
@endsection
