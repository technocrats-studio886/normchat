@extends('layouts.app', ['title' => 'Seat Added - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Payment Success</p>
            <h1 class="font-display mt-2 text-2xl font-extrabold text-[#0F172A]">Add Seat Berhasil</h1>
            <p class="mt-2 text-sm text-[#64748B]">Seat tambahan sudah aktif. Tim Anda bisa menambahkan member baru tanpa menunggu cycle berikutnya.</p>
        </div>

        <div class="panel-card mt-4 p-4">
            <p class="text-xs text-[#64748B]">Extra Seats Active</p>
            <p class="mt-1 text-3xl font-extrabold text-[#0F172A]">{{ $activeExtraSeats }}</p>
        </div>

        @if($paymentSummary)
            <div class="panel-card mt-4 p-4">
                <p class="text-xs text-[#64748B]">Payment Reference</p>
                <p class="mt-1 text-sm font-bold text-[#0F172A]">{{ $paymentSummary['reference'] ?? '-' }}</p>
                <p class="mt-2 text-xs text-[#64748B]">Seat dibeli: {{ $paymentSummary['seat_count'] ?? 0 }} x Rp{{ number_format((int) ($paymentSummary['unit_price'] ?? 0), 0, ',', '.') }}</p>
                <p class="mt-1 text-sm font-semibold text-emerald-700">Total dibayar: Rp{{ number_format((int) ($paymentSummary['amount'] ?? 0), 0, ',', '.') }}</p>
            </div>
        @endif

        <div class="mt-4 space-y-3">
            <a href="{{ route('settings.seats', $group) }}" class="btn-cta py-3 normal-case tracking-normal">
                Kembali ke Seat Management
            </a>
            <a href="{{ route('groups.index') }}" class="block w-full rounded-xl border border-[#CBD5E1] bg-white py-3 text-center text-sm font-semibold text-[#0F172A] transition hover:bg-slate-50">
                Kembali ke Dashboard
            </a>
        </div>
    </section>
@endsection
