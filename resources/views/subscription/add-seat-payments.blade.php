@extends('layouts.app', ['title' => 'Seat Payments - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.seats', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">Riwayat Aktivasi Seat</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Histori penambahan seat untuk audit internal.</p>

        <div class="space-y-3">
            @forelse($payments as $payment)
                <div class="panel-card p-4">
                    <p class="text-xs text-[#64748B]">{{ strtoupper($payment->payment_type) }} • {{ strtoupper($payment->status) }}</p>
                    <p class="mt-1 text-sm font-bold text-[#0F172A]">{{ $payment->reference }}</p>
                    <p class="mt-1 text-xs text-[#64748B]">{{ $payment->seat_count }} seat x Rp{{ number_format($payment->unit_price, 0, ',', '.') }}</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-700">{{ (int) $payment->total_amount > 0 ? 'Rp'.number_format($payment->total_amount, 0, ',', '.') : 'Simulasi (instan)' }}</p>
                    <p class="mt-1 text-xs text-[#64748B]">{{ $payment->created_at?->format('d M Y H:i') }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-white p-5 text-center text-sm text-[#64748B]">
                    Belum ada riwayat add-seat.
                </div>
            @endforelse
        </div>
    </section>
@endsection
