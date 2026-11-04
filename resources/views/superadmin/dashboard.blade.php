@extends('layouts.admin')

@section('heading', 'Ringkasan')

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $count = fn ($value) => number_format((int) $value, 0, ',', '.');
        $charged = (float) $usageMonth->charged * $rupiahPerCredit;
        $margin = $charged > 0 ? (1 - (float) $usageMonth->cost_idr / $charged) * 100 : null;
    @endphp

    <dl class="nc-kpis">
        <div class="nc-kpi"><dt>User</dt><dd>{{ $count($users['total']) }}<small>+{{ $count($users['new']) }} dalam 30 hari</small></dd></div>
        <div class="nc-kpi"><dt>Grup aktif</dt><dd>{{ $count($groups['active']) }}<small>+{{ $count($groups['new']) }} dalam 30 hari</small></dd></div>
        <div class="nc-kpi"><dt>Pendapatan 30 hari</dt><dd>{{ $rp($salesMonth->revenue) }}<small>Hari ini {{ $rp($salesToday->revenue) }}</small></dd></div>
        <div class="nc-kpi"><dt>Biaya OpenAI 30 hari</dt><dd>{{ $rp($usageMonth->cost_idr) }}<small>{{ $count($usageMonth->requests) }} jawaban Normi</small></dd></div>
        <div class="nc-kpi"><dt>Margin pemakaian</dt><dd>{{ $margin === null ? '–' : number_format($margin, 1, ',', '.') . '%' }}<small>{{ $nk($usageMonth->charged) }} NK terpakai</small></dd></div>
    </dl>

    @if($attention !== [])
        <section class="nc-panel">
            <div class="nc-panel-head"><h2 class="nc-panel-title">Perlu perhatian</h2></div>
            <div class="nc-box">
                @foreach($attention as $item)
                    <a href="{{ $item['url'] }}" class="nc-box-row hover:bg-surface-2">
                        <span @class(['nc-status', 'is-danger' => $item['danger'], 'is-ok' => ! $item['danger']]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">{{ $item['text'] }}</span>
                        <span class="text-ink-soft" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="nc-admin-cols lg:grid-cols-2">
        <section class="nc-panel">
            <div class="nc-panel-head">
                <h2 class="nc-panel-title">Pembayaran terbaru</h2>
                <a href="{{ route('superadmin.finance') }}">Keuangan</a>
            </div>
            <div class="nc-box">
                @forelse($recentPayments as $payment)
                    <div class="nc-box-row">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $payment->group?->name ?? 'Grup dihapus' }}</span>
                            <span class="block truncate text-xs text-ink-soft">{{ $payment->user?->name ?? 'Akun dihapus' }} · {{ \App\Support\UserTime::format($payment->created_at, 'j M, H.i') }}</span>
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums">{{ $rp($payment->price_paid) }}</span>
                    </div>
                @empty
                    <p class="nc-box-empty">Belum ada pembayaran.</p>
                @endforelse
            </div>
        </section>

        <section class="nc-panel">
            <div class="nc-panel-head">
                <h2 class="nc-panel-title">Tindakan admin terbaru</h2>
                <a href="{{ route('superadmin.audit') }}">Log audit</a>
            </div>
            <div class="nc-box">
                @forelse($recentActions as $log)
                    <div class="nc-box-row">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ \App\Support\AdminActions::label($log) }}</span>
                            <span class="block truncate text-xs text-ink-soft">{{ $log->actor?->name ?? 'Sistem' }} · {{ \App\Support\UserTime::format($log->created_at, 'j M, H.i') }}</span>
                        </span>
                    </div>
                @empty
                    <p class="nc-box-empty">Belum ada tindakan admin.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
