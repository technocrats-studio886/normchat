@extends('layouts.app', ['title' => 'Riwayat Pembayaran - Normi Chat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Riwayat Pembayaran</h1>
        <p class="mt-1 text-sm text-slate-500">Semua pembayaranmu.</p>

        {{-- Summary stats --}}
        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="rounded-2xl border border-[var(--nc-line)] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Total Dibayar</p>
                <p class="mt-1 text-sm font-bold text-slate-900">Rp{{ number_format($totalSpentIdr, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--nc-line)] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Normkredit</p>
                <p class="mt-1 text-sm font-bold text-navy-700">+{{ number_format($totalCreditsEarned, 2, ',', '.') }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('profile.transactions') }}" class="mt-5 flex flex-wrap items-center gap-2">
            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tampilkan</label>
            <select name="range" data-auto-submit="1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-navy-400 focus:ring-2 focus:ring-navy-100">
                <option value="all" {{ $range === 'all' ? 'selected' : '' }}>Semua</option>
                <option value="week" {{ $range === 'week' ? 'selected' : '' }}>1 Minggu</option>
                <option value="month" {{ $range === 'month' ? 'selected' : '' }}>1 Bulan</option>
                <option value="3month" {{ $range === '3month' ? 'selected' : '' }}>3 Bulan</option>
            </select>

            <select name="source" data-auto-submit="1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-navy-400 focus:ring-2 focus:ring-navy-100">
                <option value="all" {{ $source === 'all' ? 'selected' : '' }}>Semua</option>
                <option value="group_creation" {{ $source === 'group_creation' ? 'selected' : '' }}>Buat grup</option>
                <option value="patungan" {{ $source === 'patungan' ? 'selected' : '' }}>Gabung grup</option>
                <option value="topup" {{ $source === 'topup' ? 'selected' : '' }}>Isi Normkredit</option>
            </select>

            <select name="sort" data-auto-submit="1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-navy-400 focus:ring-2 focus:ring-navy-100">
                <option value="desc" {{ $sortDir === 'desc' ? 'selected' : '' }}>Terbaru</option>
                <option value="asc" {{ $sortDir === 'asc' ? 'selected' : '' }}>Terlama</option>
            </select>

            <span class="ml-auto text-[11px] text-slate-400">{{ $totalCount }} dari {{ $totalAllTime }}</span>
        </form>

        {{-- Transaction list --}}
        <div class="mt-4 space-y-2">
            @forelse($transactions as $item)
                @php
                    $sourceLabel = match($item->source) {
                        'patungan' => 'Gabung grup',
                        'patungan_midtrans' => 'Gabung grup',
                        'topup' => 'Isi Normkredit',
                        'topup_midtrans' => 'Isi Normkredit',
                        'interdotz_topup', 'interdotz_charge_topup' => 'Isi Normkredit',
                        'group_creation' => 'Buat grup',
                        'group_creation_midtrans' => 'Buat grup',
                        default => 'Lainnya',
                    };
                    $sourceColor = match($item->source) {
                        'patungan', 'patungan_midtrans' => 'bg-navy-50 text-navy-600',
                        'topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup' => 'bg-navy-50 text-navy-600',
                        'group_creation', 'group_creation_midtrans' => 'bg-coral-50 text-coral-600',
                        default => 'bg-slate-50 text-slate-600',
                    };
                    $groupName = $item->group?->name ?? 'Tanpa grup';
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300">
                    <div class="flex items-start gap-2.5">
                        {{-- Icon --}}
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $sourceColor }}">
                            @if(str_contains($item->source, 'patungan'))
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                            @elseif(str_contains($item->source, 'topup') || str_contains($item->source, 'interdotz'))
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $sourceLabel }}</p>
                                    <p class="mt-0.5 truncate text-[10px] text-slate-500">{{ $groupName }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs font-bold text-slate-900">
                                                                                    Rp{{ number_format((int) $item->price_paid, 0, ',', '.') }}
                                    </p>
                                    <p class="text-[10px] font-semibold text-navy-600">+{{ number_format((float) $item->credit_amount, 2, ',', '.') }} Normkredit</p>
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <p class="text-[10px] text-slate-400">{{ $item->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y, H:i') }}</p>
                            </div>
                            @if($item->payment_reference)
                                <p class="mt-1 truncate font-mono text-[10px] text-slate-400">No. transaksi: {{ $item->payment_reference }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Belum ada pembayaran</p>
                    <p class="mt-1 text-xs text-slate-400">Pembayaranmu akan muncul di sini.</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if(method_exists($transactions, 'links'))
            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        @endif
    </section>
@endsection
