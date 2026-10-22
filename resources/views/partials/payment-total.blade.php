{{-- Total bayar QRIS (biaya layanan sudah termasuk). Params: $quote (PaymentQuote), $feePercent --}}
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
    <div class="flex items-center justify-between">
        <span class="font-semibold text-slate-700">Total bayar via QRIS</span>
        <span class="text-base font-extrabold text-blue-600" data-quote-total>Rp{{ number_format($quote->total(), 0, ',', '.') }}</span>
    </div>
    <p class="mt-1 text-[11px] text-slate-500">Termasuk biaya layanan QRIS {{ rtrim(rtrim(number_format($feePercent, 2, ',', '.'), '0'), ',') }}%.</p>
</div>
