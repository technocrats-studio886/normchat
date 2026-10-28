@extends('layouts.app', ['title' => 'Riwayat Patungan - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('settings.show', $group)" title="Riwayat patungan" subtitle="Siapa saja yang menambah Normkredit grup ini." />

        <div class="nc-list">
            @forelse ($contributions as $c)
                @php
                    $isAdmin = $c->source === 'admin_adjustment';
                    $sourceLabel = match($c->source) {
                        'patungan', 'patungan_midtrans' => 'Gabung',
                        'topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup' => 'Isi Normkredit',
                        'group_creation', 'group_creation_midtrans' => 'Buat grup',
                        'admin_adjustment' => 'Penyesuaian admin',
                        default => 'Lainnya',
                    };
                @endphp
                <div class="nc-list-row">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $isAdmin ? 'Admin Normi Chat' : ($c->user->name ?? 'Akun dihapus') }} <span class="nc-badge">{{ $sourceLabel }}</span></p>
                        <p class="mt-0.5 text-xs text-ink-soft">{{ \App\Support\UserTime::format($c->created_at, 'j M Y, H.i') }}{{ $c->price_paid > 0 ? ' · Rp' . number_format((int) $c->price_paid, 0, ',', '.') : '' }}</p>
                        @if($c->note)
                            <p class="mt-0.5 text-xs text-ink-soft">Alasan: {{ $c->note }}</p>
                        @elseif($c->payment_reference && ! $isAdmin)
                            <p class="mt-0.5 truncate font-mono text-[11px] text-ink-soft">No. transaksi {{ $c->payment_reference }}</p>
                        @endif
                    </div>
                    <p class="shrink-0 text-sm font-bold {{ $c->credit_amount < 0 ? 'text-danger-ink' : 'text-primary-ink' }}">{{ $c->credit_amount < 0 ? '−' : '+' }}{{ number_format(abs((float) $c->credit_amount), 2, ',', '.') }} NK</p>
                </div>
            @empty
                <p class="nc-list-empty">Belum ada patungan.</p>
            @endforelse
        </div>
    </section>
@endsection
