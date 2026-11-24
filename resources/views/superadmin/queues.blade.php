@extends('layouts.admin')

@section('heading', 'Antrean')

@section('content')
    @php
        $count = fn ($value) => number_format((int) $value, 0, ',', '.');
        $duration = function ($seconds) {
            if ($seconds === null) {
                return '–';
            }
            $seconds = (int) $seconds;

            return $seconds < 60 ? $seconds . ' dtk' : (intdiv($seconds, 60) . ' mnt' . ($seconds % 60 ? ' ' . ($seconds % 60) . ' dtk' : ''));
        };
        $stateText = [
            'running' => 'Worker berjalan',
            'paused' => 'Worker sedang dijeda',
            'inactive' => 'Worker tidak berjalan. Normi tidak menjawab sampai container queue hidup lagi.',
            'unknown' => 'Status worker belum terbaca karena Redis tidak terhubung.',
        ][$state];
    @endphp

    <section class="nc-panel">
        <div class="nc-box">
            <div class="nc-box-row">
                <span @class(['nc-status', 'is-ok' => $state === 'running', 'is-danger' => $state !== 'running']) aria-hidden="true"></span>
                <span class="min-w-0 flex-1 font-medium" role="status">{{ $stateText }}</span>
            </div>
        </div>
    </section>

    <dl class="nc-kpis">
        <div class="nc-kpi"><dt>Menunggu Normi</dt><dd>{{ $count($ai['queued']) }}<small>{{ $ai['oldest_waiting'] ? 'Terlama sejak ' . \App\Support\UserTime::format($ai['oldest_waiting'], 'H.i') : 'Tidak ada yang menunggu' }}</small></dd></div>
        <div class="nc-kpi"><dt>Sedang diproses</dt><dd>{{ $count($ai['processing']) }}<small>Semua grup</small></dd></div>
        <div class="nc-kpi"><dt>Selesai 24 jam</dt><dd>{{ $count($ai['processed']) }}<small>Median {{ $duration($ai['median_seconds']) }}, p95 {{ $duration($ai['p95_seconds']) }}</small></dd></div>
        <div class="nc-kpi"><dt>Gagal 24 jam</dt><dd>{{ $count($ai['failed']) }}<small>Pengirim bisa menekan Coba lagi</small></dd></div>
    </dl>

    <section class="nc-panel">
        <div class="nc-panel-head"><h2 class="nc-panel-title">Beban tiap antrean</h2></div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Antrean</th><th class="text-right">Menunggu</th><th class="text-right">Perkiraan tunggu</th><th class="text-right">Proses</th></tr></thead>
                <tbody>
                    @foreach($queues as $queue)
                        <tr>
                            <td>{{ $queue['label'] }}<span class="block text-xs text-ink-soft">{{ $queue['queue'] }}</span></td>
                            <td class="text-right tabular-nums">{{ $count($queue['waiting']) }}</td>
                            <td class="text-right tabular-nums">{{ $queue['waiting'] > 0 ? $duration($queue['wait_seconds']) : '–' }}</td>
                            <td class="text-right tabular-nums">{{ $count($queue['processes']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="nc-panel">
        <div class="nc-panel-head"><h2 class="nc-panel-title">Job gagal terakhir</h2></div>
        <div class="nc-box">
            @forelse($failed as $job)
                <div class="nc-box-row">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $job['name'] }} <span class="text-xs font-normal text-ink-soft">{{ \App\Support\Queues::label($job['queue']) }}</span></span>
                        <span class="block truncate text-xs text-ink-soft">{{ $job['error'] }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-ink-soft">{{ $job['failed_at'] ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($job['failed_at']), 'j M, H.i') : '' }}</span>
                </div>
            @empty
                <p class="nc-box-row text-ink-soft">Belum ada job yang gagal.</p>
            @endforelse
        </div>
    </section>
@endsection
