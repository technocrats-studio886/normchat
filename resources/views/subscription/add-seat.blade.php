@extends('layouts.app', ['title' => 'Add Seat - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.seats', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">Add Seat - Payment</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Tambah kapasitas user aktif untuk tim yang tumbuh lebih cepat tanpa mengganggu percakapan berjalan.</p>

        <div class="panel-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Harga per seat</p>
            <p class="mt-2 text-3xl font-extrabold text-[#0F172A]">Rp{{ number_format($seatPrice, 0, ',', '.') }}<span class="ml-1 text-base font-normal text-[#64748B]">/bulan</span></p>

            <form method="POST" action="{{ route('subscription.add-seat.process', $group) }}" class="mt-4 space-y-3" data-seat-form="1" data-seat-price="{{ $seatPrice }}">
                @csrf
                <div>
                    <label for="seat_count" class="mb-1 block text-xs font-semibold text-[#64748B]">Jumlah Seat</label>
                    <input id="seat_count" type="number" min="1" max="20" name="seat_count" value="1" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A] outline-none" required />
                </div>

                <div class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800">
                    <p class="font-semibold">Total Payment Dummy</p>
                    <p class="mt-1 text-sm font-bold" data-total-display="1">Rp{{ number_format($seatPrice, 0, ',', '.') }}</p>
                </div>

                <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                    <p class="font-semibold text-slate-700">Metode pembayaran dummy</p>
                    <p class="mt-1">Virtual Account simulasi. Tidak ada charge nyata.</p>
                </div>

                <input type="hidden" name="payment_method" value="dummy_va" />
                <label class="flex items-center gap-2 text-xs text-[#334155]">
                    <input type="checkbox" name="payment_confirmed" value="1" class="rounded border-slate-300" required>
                    Saya konfirmasi pembayaran dummy Rp4.000 per seat
                </label>

                <button type="submit" class="btn-cta py-3 normal-case tracking-normal">
                    Bayar & Tambahkan Seat
                </button>
            </form>
        </div>

        <script>
            (() => {
                const form = document.querySelector('[data-seat-form="1"]');
                if (!form) {
                    return;
                }

                const seatPrice = Number(form.getAttribute('data-seat-price') || 0);
                const seatCount = form.querySelector('#seat_count');
                const totalDisplay = form.querySelector('[data-total-display="1"]');

                const formatIdr = (value) => new Intl.NumberFormat('id-ID').format(value);

                const syncTotal = () => {
                    const count = Math.max(1, Number(seatCount?.value || 1));
                    const total = count * seatPrice;
                    if (totalDisplay) {
                        totalDisplay.textContent = `Rp${formatIdr(total)}`;
                    }
                };

                seatCount?.addEventListener('input', syncTotal);
                syncTotal();
            })();
        </script>
    </section>
@endsection
