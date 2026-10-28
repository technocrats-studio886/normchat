@extends('layouts.app', ['title' => 'Ringkasan - Admin Normi Chat'])

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $margin = fn ($usage) => (float) $usage->charged * $rupiahPerCredit > 0 ? (1 - (float) $usage->cost_idr / ((float) $usage->charged * $rupiahPerCredit)) * 100 : null;
    @endphp
    <section class="page-shell">
        @include('superadmin._nav')

        <div class="nc-stats">
            <div class="nc-stat"><p>User</p><b>{{ number_format($users['total'], 0, ',', '.') }}</b><span>{{ $users['new'] }} baru 30 hari · {{ $users['blocked'] }} diblokir</span></div>
            <div class="nc-stat"><p>Grup aktif</p><b>{{ number_format($groups['active'], 0, ',', '.') }}</b><span>{{ $groups['new'] }} baru 30 hari · {{ $groups['frozen'] }} dibekukan</span></div>
            <div class="nc-stat"><p>Pendapatan hari ini</p><b>{{ $rp($salesToday->revenue) }}</b><span>{{ (int) $salesToday->payments }} pembayaran</span></div>
            <div class="nc-stat"><p>Pendapatan 30 hari</p><b>{{ $rp($salesMonth->revenue) }}</b><span>{{ (int) $salesMonth->payments }} pembayaran</span></div>
            <div class="nc-stat"><p>Pemakaian AI hari ini</p><b>{{ $nk($usageToday->charged) }} NK</b><span>{{ (int) $usageToday->requests }} permintaan · biaya {{ $rp($usageToday->cost_idr) }}</span></div>
            <div class="nc-stat"><p>Pemakaian AI 30 hari</p><b>{{ $nk($usageMonth->charged) }} NK</b><span>{{ (int) $usageMonth->requests }} permintaan · biaya {{ $rp($usageMonth->cost_idr) }}</span></div>
            <div class="nc-stat"><p>Margin pemakaian 30 hari</p><b>{{ $margin($usageMonth) === null ? '–' : number_format($margin($usageMonth), 1, ',', '.') . '%' }}</b><span>Pendapatan NK terpakai dikurangi biaya OpenAI</span></div>
        </div>
    </section>
@endsection
