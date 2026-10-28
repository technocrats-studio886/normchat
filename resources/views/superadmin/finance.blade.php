@extends('layouts.app', ['title' => 'Keuangan & AI - Admin Normi Chat'])

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $range = ['from' => $fromInput, 'to' => $toInput];
        $sources = ['admin_adjustment' => 'Penyesuaian admin', 'topup' => 'Isi Normkredit', 'topup_midtrans' => 'Isi Normkredit', 'patungan' => 'Patungan', 'patungan_midtrans' => 'Patungan', 'group_creation' => 'Buat grup', 'group_creation_midtrans' => 'Buat grup'];
    @endphp
    <section class="page-shell">
        @include('superadmin._nav')

        <form method="GET" class="nc-filter-bar">
            <label class="nc-date"><span>Dari</span><input type="date" name="from" value="{{ $fromInput }}"></label>
            <label class="nc-date"><span>Sampai</span><input type="date" name="to" value="{{ $toInput }}"></label>
            <button type="submit" class="btn-secondary self-end px-4 py-2">Terapkan</button>
            <a href="{{ route('superadmin.csv', ['transaksi'] + $range) }}" class="btn-secondary self-end px-4 py-2">CSV transaksi</a>
            <a href="{{ route('superadmin.csv', ['ai'] + $range) }}" class="btn-secondary self-end px-4 py-2">CSV pemakaian AI</a>
        </form>

        <div class="nc-section">
            <h2 class="nc-section-title">Pendapatan per hari</h2>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Tanggal</th><th>Pembayaran</th><th>NK</th><th>Pendapatan</th></tr></thead>
                    <tbody>
                        @forelse($revenueByDay as $row)
                            <tr><td>{{ \Illuminate\Support\Carbon::parse($row->day)->translatedFormat('j M Y') }}</td><td>{{ (int) $row->payments }}</td><td>{{ $nk($row->credits) }}</td><td>{{ $rp($row->revenue) }}</td></tr>
                        @empty
                            <tr><td colspan="4">Belum ada pembayaran pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if($revenueByDay->isNotEmpty())
                        <tfoot><tr><th>Total</th><th>{{ (int) $revenueByDay->sum('payments') }}</th><th>{{ $nk($revenueByDay->sum('credits')) }}</th><th>{{ $rp($revenueByDay->sum('revenue')) }}</th></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Pemakaian & biaya AI per model</h2>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Model</th><th>Permintaan</th><th>NK ditagih</th><th>Biaya OpenAI</th></tr></thead>
                    <tbody>
                        @forelse($usageByModel as $row)
                            <tr><td class="font-mono">{{ $row->model }}</td><td>{{ (int) $row->requests }}</td><td>{{ $nk($row->charged) }}</td><td>{{ $rp($row->cost_idr) }}</td></tr>
                        @empty
                            <tr><td colspan="4">Belum ada pemakaian AI pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Grup dengan biaya AI terbesar</h2>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Grup</th><th>Permintaan</th><th>NK ditagih</th><th>Biaya OpenAI</th></tr></thead>
                    <tbody>
                        @forelse($usageByGroup as $row)
                            <tr><td><a href="{{ route('superadmin.groups.show', $row->id) }}" class="underline">{{ $row->name }}</a></td><td>{{ (int) $row->requests }}</td><td>{{ $nk($row->charged) }}</td><td>{{ $rp($row->cost_idr) }}</td></tr>
                        @empty
                            <tr><td colspan="4">Belum ada pemakaian AI pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Transaksi</h2>
            <div class="nc-list">
                @forelse($transactions as $row)
                    <div class="nc-list-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $row->group?->name ?? 'Grup dihapus' }} · {{ $sources[$row->source] ?? $row->source }} · {{ $row->credit_amount > 0 ? '+' : '' }}{{ $nk($row->credit_amount) }} NK</p>
                            <p class="text-xs text-ink-soft">{{ \App\Support\UserTime::format($row->created_at) }} · {{ $row->user?->name ?? 'Akun dihapus' }}{{ $row->price_paid > 0 ? ' · ' . $rp($row->price_paid) : '' }}{{ $row->note ? ' · ' . $row->note : '' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="nc-list-empty">Tidak ada transaksi pada rentang ini.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $transactions->links() }}</div>
        </div>
    </section>
@endsection
