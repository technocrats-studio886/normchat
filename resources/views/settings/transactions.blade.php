@extends('layouts.app', ['title' => 'Riwayat Patungan - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[var(--nc-text)]">Riwayat Patungan</h1>
        </div>

        <p class="mb-4 text-sm text-slate-500">Siapa saja yang menambah Normkredit grup ini.</p>

        <div class="space-y-3">
            @forelse ($contributions as $c)
                @php
                    $sourceLabel = match($c->source) {
                        'patungan' => 'Gabung',
                        'patungan_midtrans' => 'Gabung',
                        'topup' => 'Isi Normkredit',
                        'topup_midtrans' => 'Isi Normkredit',
                        'interdotz_topup', 'interdotz_charge_topup' => 'Isi Normkredit',
                        'group_creation' => 'Buat grup',
                        'group_creation_midtrans' => 'Buat grup',
                        default => 'Lainnya',
                    };
                    $sourceColor = match($c->source) {
                        'patungan', 'patungan_midtrans' => 'bg-navy-100 text-navy-700',
                        'topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup' => 'bg-navy-100 text-navy-700',
                        'group_creation', 'group_creation_midtrans' => 'bg-coral-100 text-coral-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <div class="rounded-xl border border-[var(--nc-line-strong)] bg-white px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[var(--nc-text)]">{{ $c->user->name ?? 'User tidak diketahui' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $c->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y, H:i') }}</p>
                            @if($c->payment_reference)
                                <p class="mt-1 truncate text-[11px] font-mono text-slate-400">No. transaksi: {{ $c->payment_reference }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $sourceColor }}">{{ $sourceLabel }}</span>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Dibayar</p>
                            <p class="text-sm font-bold text-[var(--nc-text)]">
                                                                    Rp{{ number_format((int) $c->price_paid, 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Normkredit</p>
                            <p class="text-sm font-bold text-navy-700">+{{ number_format((float) $c->credit_amount, 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--nc-line-strong)] bg-white p-5 text-center text-sm text-slate-500">
                    Belum ada patungan.
                </div>
            @endforelse
        </div>
    </section>
@endsection
