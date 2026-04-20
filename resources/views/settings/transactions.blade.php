@extends('layouts.app', ['title' => 'Riwayat Transaksi - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">Riwayat Transaksi</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Siapa saja yang telah berkontribusi ke grup ini — patungan, top-up normkredit, maupun pembuatan grup.</p>

        <div class="space-y-3">
            @forelse ($contributions as $c)
                @php
                    $sourceLabel = match($c->source) {
                        'patungan' => 'Patungan gabung (DU)',
                        'patungan_midtrans' => 'Patungan gabung (IDR)',
                        'topup' => 'Top-up normkredit (DU)',
                        'topup_midtrans' => 'Top-up normkredit (IDR)',
                        'interdotz_topup', 'interdotz_charge_topup' => 'Top-up normkredit (DU)',
                        'group_creation' => 'Pembuatan grup (DU)',
                        'group_creation_midtrans' => 'Pembuatan grup (IDR)',
                        default => ucfirst(str_replace('_', ' ', (string) $c->source)),
                    };
                    $sourceColor = match($c->source) {
                        'patungan', 'patungan_midtrans' => 'bg-indigo-100 text-indigo-700',
                        'topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup' => 'bg-emerald-100 text-emerald-700',
                        'group_creation', 'group_creation_midtrans' => 'bg-amber-100 text-amber-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                    $isIdr = in_array($c->source, ['patungan_midtrans', 'topup_midtrans', 'group_creation_midtrans'], true);
                @endphp
                <div class="rounded-xl border border-[#CBD5E1] bg-white px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[#0F172A]">{{ $c->user->name ?? 'User tidak diketahui' }}</p>
                            <p class="mt-0.5 text-xs text-[#64748B]">{{ $c->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y, H:i') }}</p>
                            @if($c->payment_reference)
                                <p class="mt-1 truncate text-[11px] font-mono text-slate-400">Ref: {{ $c->payment_reference }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $sourceColor }}">{{ $sourceLabel }}</span>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Dibayar</p>
                            <p class="text-sm font-bold text-[#0F172A]">
                                @if($isIdr)
                                    Rp{{ number_format((int) $c->price_paid, 0, ',', '.') }}
                                @else
                                    {{ number_format((int) $c->price_paid) }} DU
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Normkredit</p>
                            <p class="text-sm font-bold text-emerald-700">+{{ number_format(round(((int) $c->token_amount) / 2500, 1), 1, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-white p-5 text-center text-sm text-[#64748B]">
                    Belum ada transaksi pada grup ini.
                </div>
            @endforelse
        </div>
    </section>
@endsection
