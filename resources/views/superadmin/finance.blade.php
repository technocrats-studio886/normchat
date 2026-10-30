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
                    <thead><tr><th>Model</th><th>Permintaan</th><th>Token masuk rata-rata</th><th>Tercache</th><th>NK ditagih</th><th>Biaya OpenAI</th><th>Biaya per jawaban</th><th>Margin kotor</th></tr></thead>
                    <tbody>
                        @forelse($usageByModel as $row)
                            @php($revenue = (float) $row->charged * $rupiahPerCredit)
                            <tr>
                                <td class="font-mono">{{ $row->model }}</td>
                                <td>{{ (int) $row->requests }}</td>
                                <td>{{ number_format((float) $row->avg_input, 0, ',', '.') }}</td>
                                <td>{{ (int) $row->input > 0 ? number_format($row->cached / $row->input * 100, 1, ',', '.') . '%' : '-' }}</td>
                                <td>{{ $nk($row->charged) }}</td>
                                <td>{{ $rp($row->cost_idr) }}</td>
                                <td>{{ (int) $row->requests > 0 ? 'Rp' . number_format((float) $row->cost_idr / (int) $row->requests, 2, ',', '.') : '-' }}</td>
                                <td>{{ $revenue > 0 ? number_format(($revenue - $row->cost_idr) / $revenue * 100, 1, ',', '.') . '%' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Belum ada pemakaian AI pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="nc-section-note mt-2">Margin kotor = (NK ditagih × Rp{{ number_format($rupiahPerCredit, 0, ',', '.') }} − biaya OpenAI) ÷ NK ditagih, sebelum biaya QRIS, server, dan pajak.</p>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Konteks jawaban Normi</h2>
            @php($totalAnswers = max(1, (int) $answers->total))
            <div class="nc-stats">
                <div class="nc-stat"><p>Jawaban</p><b>{{ (int) $answers->total }}</b></div>
                <div class="nc-stat"><p>Memakai ringkasan grup</p><b>{{ number_format($answers->with_summary / $totalAnswers * 100, 1, ',', '.') }}%</b></div>
                <div class="nc-stat"><p>Mencari pesan lama</p><b>{{ number_format($answers->searched / $totalAnswers * 100, 1, ',', '.') }}%</b></div>
                <div class="nc-stat"><p>Memanggil pemahaman (Luna)</p><b>{{ number_format($answers->understood / $totalAnswers * 100, 1, ',', '.') }}%</b></div>
                <div class="nc-stat"><p>Gagal mengingat</p><b>{{ number_format($answers->recall_failed / $totalAnswers * 100, 1, ',', '.') }}%</b></div>
            </div>
            <p class="nc-section-note mt-2">Gagal mengingat = Normi harus minta klarifikasi karena rujukannya tidak ketemu, atau pertanyaan merujuk pembicaraan sebelumnya ("kemarin", "yang tadi", membalas pesan lama) tapi tidak ada pesan lama yang cocok. Pertanyaan umum tidak dihitung.</p>
            @if($recallByWeek->isNotEmpty())
                <div class="nc-table-wrap mt-3">
                    <table class="nc-table">
                        <thead><tr><th>Minggu mulai</th><th>Jawaban</th><th>Gagal mengingat</th><th>Persen</th></tr></thead>
                        <tbody>
                            @foreach($recallByWeek as $week)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($week->week)->translatedFormat('j M Y') }}</td>
                                    <td>{{ (int) $week->total }}</td>
                                    <td>{{ (int) $week->failed }}</td>
                                    <td>{{ number_format((int) $week->failed / max(1, (int) $week->total) * 100, 1, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Biaya OpenAI yang tidak ditagih ke grup</h2>
            <p class="nc-section-note">Pemeliharaan ingatan Normi dan permintaan yang ditolak setelah ada biaya. Dibayar dari saldo OpenAI, tidak memotong Normkredit grup.</p>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Jenis</th><th>Panggilan</th><th>Token masuk</th><th>Biaya OpenAI</th></tr></thead>
                    <tbody>
                        @foreach(['context_summary' => 'Ringkasan grup & topik', 'context_understanding' => 'Pemahaman pertanyaan', 'embedding' => 'Embedding pesan', 'refused' => 'Permintaan ditolak setelah ada biaya (mis. transkripsi)'] as $kind => $label)
                            @php($row = $maintenance[$kind] ?? null)
                            <tr><td>{{ $label }}</td><td>{{ (int) ($row->calls ?? 0) }}</td><td>{{ number_format((int) ($row->input ?? 0), 0, ',', '.') }}</td><td>{{ $rp($row->cost_idr ?? 0) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Permintaan ditolak</h2>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Alasan</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @foreach(['credits' => 'Normkredit grup tidak cukup', 'model_locked' => 'Model terkunci (saldo < ambang)', 'daily_limit' => 'Batas harian grup', 'spend_guard' => 'Anggaran / saldo OpenAI', 'too_long' => 'Pesan atau lampiran terlalu panjang', 'no_model' => 'Model atau API key tidak tersedia'] as $reason => $label)
                            <tr><td>{{ $label }}</td><td>{{ (int) ($refusals[$reason] ?? 0) }}</td></tr>
                        @endforeach
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
