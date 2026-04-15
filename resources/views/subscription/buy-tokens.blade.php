@extends('layouts.app', ['title' => 'Top-up Normkredit - Normchat'])

@section('content')
    <section class="page-shell pt-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-2xl font-extrabold text-slate-900">Top-up Normkredit</h1>
        <p class="mt-1 text-sm text-slate-500">Pilih paket lalu bayar via DU atau Midtrans tanpa mengganggu chat yang sedang berjalan.</p>

        @if($errors->has('payment'))
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('payment') }}
            </div>
        @endif

        <form method="POST" action="{{ route('subscription.tokens.buy.process') }}" class="mt-6 space-y-4">
            @csrf

            @php $ctxGt = $contextGroup->groupToken; @endphp
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Grup Tujuan</label>
                <div class="mt-2 flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-800">{{ $contextGroup->name }}</p>
                        <p class="text-[11px] text-slate-500">Saldo: {{ number_format($ctxGt?->credits ?? 0, 1) }} normkredit</p>
                    </div>
                </div>
                <input type="hidden" name="group_id" value="{{ $contextGroup->id }}" />
            </div>

            {{-- Package Selection --}}
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pilih Paket</label>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-2">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="payment_method" value="du" class="accent-blue-600" {{ old('payment_method', 'du') === 'du' ? 'checked' : '' }}>
                            DU
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="payment_method" value="midtrans" class="accent-blue-600" {{ old('payment_method') === 'midtrans' ? 'checked' : '' }}>
                            Midtrans
                        </label>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    @foreach($packageOptions as $packageId => $pkg)
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border-2 px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50" data-package-row="{{ $packageId }}" data-package-du="{{ $pkg['du'] }}" data-package-idr="{{ $pkg['idr'] }}">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="package" value="{{ $packageId }}" class="accent-blue-600"
                                       {{ old('package', 'nk_12') === $packageId ? 'checked' : '' }} />
                                <p class="text-sm font-bold text-slate-800">{{ $pkg['normkredits'] }} Normkredit</p>
                            </div>
                            <span class="text-sm font-extrabold text-blue-600" data-package-price="{{ $packageId }}">{{ $pkg['du'] }} DU</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="btn-cta w-full py-4 text-sm font-extrabold uppercase tracking-wide" id="topupSubmitBtn">
                Top-up Normkredit
            </button>
        </form>

        <p class="mt-4 pb-4 text-center text-[11px] text-slate-400" id="topupHintText">
            Pembayaran menggunakan Dots Units dari akun Interdotz Anda.
        </p>
    </section>

    <script>
        function formatIdr(value) {
            return 'Rp' + Number(value || 0).toLocaleString('id-ID');
        }

        function syncTopupPaymentUi() {
            const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'du';
            const rows = document.querySelectorAll('[data-package-row]');
            rows.forEach((row) => {
                const packageId = row.getAttribute('data-package-row');
                const priceEl = document.querySelector('[data-package-price="' + packageId + '"]');
                if (!priceEl) {
                    return;
                }

                const du = Number(row.getAttribute('data-package-du') || 0);
                const idr = Number(row.getAttribute('data-package-idr') || 0);
                priceEl.textContent = method === 'midtrans' ? formatIdr(idr) : (du + ' DU');
            });

            const submit = document.getElementById('topupSubmitBtn');
            const hint = document.getElementById('topupHintText');
            if (submit) {
                submit.textContent = method === 'midtrans' ? 'Bayar dengan Midtrans' : 'Top-up Normkredit';
            }
            if (hint) {
                hint.textContent = method === 'midtrans'
                    ? 'Pembayaran diproses melalui Midtrans dalam Rupiah (IDR).'
                    : 'Pembayaran menggunakan Dots Units dari akun Interdotz Anda.';
            }
        }

        document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
            input.addEventListener('change', syncTopupPaymentUi);
        });
        syncTopupPaymentUi();
    </script>
@endsection
