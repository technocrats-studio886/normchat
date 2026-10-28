{{-- Harga paket. Biaya layanan QRIS tidak disebut di sini; Midtrans menampilkannya
     sebagai baris tersendiri di halaman pembayaran. Params: $quote (PaymentQuote) --}}
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
    <div class="flex items-center justify-between">
        <span class="font-semibold text-slate-700">Total</span>
        <span class="text-base font-extrabold text-primary-ink" data-quote-total>Rp{{ number_format($quote->baseAmount, 0, ',', '.') }}</span>
    </div>
</div>
