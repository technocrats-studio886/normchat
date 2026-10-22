{{--
    Pemilih jumlah paket Normkredit (patungan / top-up). Data harga disuntik view composer
    (AppServiceProvider): $packageQuote (1 paket), $maxPackages, $feePercent.
    Params: $label, $submitLabel
--}}
@php
    $pickerId = 'packagePicker' . \Illuminate\Support\Str::random(6);
    $initialPackages = max(1, min($maxPackages, (int) old('packages', 1)));
    $fmtIdr = fn (int $value) => 'Rp' . number_format($value, 0, ',', '.');
@endphp
<div class="panel-card px-4 py-3" data-package-picker
     data-unit-credits="{{ $packageQuote->credits }}" data-unit-base="{{ $packageQuote->baseAmount }}"
     data-max-packages="{{ $maxPackages }}" data-fee-percent="{{ $feePercent }}" data-submit-label="{{ $submitLabel }}">
    <label for="{{ $pickerId }}Input" class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</label>
    <p class="mt-1 text-sm text-slate-700">
        1 paket = <strong>{{ number_format($packageQuote->credits, 0, ',', '.') }} Normkredit</strong> · {{ $fmtIdr($packageQuote->baseAmount) }}
    </p>
    <div class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-2 py-1.5">
        <button type="button" data-package-step="-1" class="flex h-9 w-9 items-center justify-center rounded-lg text-lg font-bold text-slate-600 hover:bg-slate-100" aria-label="Kurangi paket">−</button>
        <div class="text-center">
            <input id="{{ $pickerId }}Input" type="number" name="packages" min="1" max="{{ $maxPackages }}" step="1" required readonly
                   value="{{ $initialPackages }}" class="w-12 bg-transparent text-center text-base font-extrabold text-slate-900 outline-none" />
            <p class="text-[11px] text-slate-500"><span data-package-credits></span> NK</p>
        </div>
        <button type="button" data-package-step="1" class="flex h-9 w-9 items-center justify-center rounded-lg text-lg font-bold text-slate-600 hover:bg-slate-100" aria-label="Tambah paket">+</button>
    </div>
    @error('packages')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>

@include('partials.share-picker', ['credits' => $packageQuote->credits * $initialPackages])

@include('partials.payment-total', ['quote' => $packageQuote])

<button type="submit" class="btn-cta" data-package-submit>{{ $submitLabel }}</button>
