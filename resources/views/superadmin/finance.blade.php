@extends('layouts.admin')

@section('heading', 'Keuangan & AI')

@section('actions')
    <a href="{{ route('superadmin.csv', ['transaksi', 'from' => $fromInput, 'to' => $toInput]) }}" class="btn-secondary px-3 py-2 text-sm">CSV transaksi</a>
    <a href="{{ route('superadmin.csv', ['ai', 'from' => $fromInput, 'to' => $toInput]) }}" class="btn-secondary px-3 py-2 text-sm">CSV pemakaian AI</a>
@endsection

@section('content')
    @php
        $nk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $pct = fn ($part, $total) => number_format((float) $part / max(1, (int) $total) * 100, 1, ',', '.') . '%';
        $sources = \App\Models\GroupTokenContribution::SOURCE_LABELS;
        $revenue = (float) $revenueByDay->sum('revenue');
        $charged = (float) $usageByModel->sum('charged');
        $answerCost = (float) $usageByModel->sum('cost_idr');
        $maintenanceCost = (float) collect($maintenance)->sum('cost_idr');
        $chargedValue = $charged * $rupiahPerCredit;
    @endphp

    <form method="GET" class="nc-filter-bar">
        <label class="nc-date"><span>Dari</span><input type="date" name="from" value="{{ $fromInput }}"></label>
        <label class="nc-date"><span>Sampai</span><input type="date" name="to" value="{{ $toInput }}"></label>
        <button type="submit" class="btn-secondary self-end px-4 py-2">Terapkan</button>
    </form>

    <dl class="nc-kpis">
        <div class="nc-kpi"><dt>Pendapatan</dt><dd>{{ $rp($revenue) }}<small>{{ (int) $revenueByDay->sum('payments') }} pembayaran</small></dd></div>
        <div class="nc-kpi"><dt>NK terjual</dt><dd>{{ $nk($revenueByDay->sum('credits')) }}<small>Saldo gratis {{ $nk($giftCredits) }} NK</small></dd></div>
        <div class="nc-kpi"><dt>NK terpakai</dt><dd>{{ $nk($charged) }}<small>{{ (int) $answers->total }} jawaban Normi</small></dd></div>
        <div class="nc-kpi"><dt>Biaya OpenAI</dt><dd>{{ $rp($answerCost + $maintenanceCost) }}<small>Tidak ditagih {{ $rp($maintenanceCost) }}</small></dd></div>
        <div class="nc-kpi"><dt>Margin pemakaian</dt><dd>{{ $chargedValue > 0 ? number_format(($chargedValue - $answerCost) / $chargedValue * 100, 1, ',', '.') . '%' : '–' }}<small>Sebelum QRIS, server, pajak</small></dd></div>
    </dl>

    <nav class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Bagian halaman">
        @foreach(['pendapatan' => 'Pendapatan', 'model' => 'Per model', 'konteks' => 'Konteks Normi', 'tidak-ditagih' => 'Tidak ditagih', 'ditolak' => 'Ditolak', 'grup' => 'Grup terbesar', 'transaksi' => 'Transaksi'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="font-semibold text-primary-ink hover:underline">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="nc-panel" id="pendapatan">
        <div class="nc-panel-head"><h2 class="nc-panel-title">Pendapatan per hari</h2></div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Tanggal</th><th>Pembayaran</th><th>NK</th><th>Pendapatan</th></tr></thead>
                <tbody>
                    @forelse($revenueByDay as $row)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($row->day)->translatedFormat('j M Y') }}</td><td>{{ (int) $row->payments }}</td><td>{{ $nk($row->credits) }}</td><td>{{ $rp($row->revenue) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-ink-soft">Belum ada pembayaran pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
                @if($revenueByDay->isNotEmpty())
                    <tfoot><tr><th>Total</th><th>{{ (int) $revenueByDay->sum('payments') }}</th><th>{{ $nk($revenueByDay->sum('credits')) }}</th><th>{{ $rp($revenue) }}</th></tr></tfoot>
                @endif
            </table>
        </div>
    </section>

    <section class="nc-panel" id="model">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Pemakaian per model</h2>
            <p class="nc-panel-note">Margin = (NK × Rp{{ number_format($rupiahPerCredit, 0, ',', '.') }} − biaya OpenAI) ÷ nilai NK</p>
        </div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Model</th><th>Jawaban</th><th>Token masuk rata-rata</th><th>Tercache</th><th>NK ditagih</th><th>Biaya OpenAI</th><th>Biaya per jawaban</th><th>Margin</th></tr></thead>
                <tbody>
                    @forelse($usageByModel as $row)
                        @php($value = (float) $row->charged * $rupiahPerCredit)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->model }}</td>
                            <td>{{ (int) $row->requests }}</td>
                            <td>{{ number_format((float) $row->avg_input, 0, ',', '.') }}</td>
                            <td>{{ (int) $row->input > 0 ? $pct($row->cached, $row->input) : '–' }}</td>
                            <td>{{ $nk($row->charged) }}</td>
                            <td>{{ $rp($row->cost_idr) }}</td>
                            <td>{{ (int) $row->requests > 0 ? 'Rp' . number_format((float) $row->cost_idr / (int) $row->requests, 2, ',', '.') : '–' }}</td>
                            <td>{{ $value > 0 ? number_format(($value - $row->cost_idr) / $value * 100, 1, ',', '.') . '%' : '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-ink-soft">Belum ada pemakaian pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="nc-panel" id="konteks">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Konteks jawaban Normi</h2>
            <p class="nc-panel-note">Gagal mengingat: Normi minta klarifikasi, atau pesan lama yang dirujuk tidak ketemu</p>
        </div>
        <dl class="nc-kpis">
            <div class="nc-kpi"><dt>Memakai ringkasan grup</dt><dd>{{ $pct($answers->with_summary, $answers->total) }}</dd></div>
            <div class="nc-kpi"><dt>Mencari pesan lama</dt><dd>{{ $pct($answers->searched, $answers->total) }}</dd></div>
            <div class="nc-kpi"><dt>Memanggil pemahaman</dt><dd>{{ $pct($answers->understood, $answers->total) }}</dd></div>
            <div class="nc-kpi"><dt>Gagal mengingat</dt><dd>{{ $pct($answers->recall_failed, $answers->total) }}</dd></div>
        </dl>
        @if($recallByWeek->isNotEmpty())
            <div class="nc-table-wrap mt-3">
                <table class="nc-table">
                    <thead><tr><th>Minggu mulai</th><th>Jawaban</th><th>Gagal mengingat</th><th>Persen</th></tr></thead>
                    <tbody>
                        @foreach($recallByWeek as $week)
                            <tr><td>{{ \Carbon\Carbon::parse($week->week)->translatedFormat('j M Y') }}</td><td>{{ (int) $week->total }}</td><td>{{ (int) $week->failed }}</td><td>{{ $pct($week->failed, $week->total) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="nc-panel" id="tidak-ditagih">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Biaya OpenAI tidak ditagih</h2>
            <p class="nc-panel-note">Dari saldo OpenAI, tidak memotong Normkredit grup</p>
        </div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Jenis</th><th>Panggilan</th><th>Token masuk</th><th>Biaya OpenAI</th></tr></thead>
                <tbody>
                    @foreach(['context_summary' => 'Ringkasan grup & topik', 'context_understanding' => 'Pemahaman pertanyaan', 'embedding' => 'Embedding pesan', 'refused' => 'Ditolak setelah ada biaya'] as $kind => $label)
                        @php($row = $maintenance[$kind] ?? null)
                        <tr><td>{{ $label }}</td><td>{{ (int) ($row->calls ?? 0) }}</td><td>{{ number_format((int) ($row->input ?? 0), 0, ',', '.') }}</td><td>{{ $rp($row->cost_idr ?? 0) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="nc-admin-cols lg:grid-cols-2">
        <section class="nc-panel" id="ditolak">
            <div class="nc-panel-head"><h2 class="nc-panel-title">Permintaan ditolak</h2></div>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Alasan</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @foreach(['credits' => 'Normkredit tidak cukup', 'model_locked' => 'Model terkunci', 'daily_limit' => 'Batas harian', 'spend_guard' => 'Anggaran / saldo OpenAI', 'too_long' => 'Pesan atau lampiran terlalu panjang', 'no_model' => 'Model / API key tidak tersedia'] as $reason => $label)
                            <tr><td>{{ $label }}</td><td>{{ (int) ($refusals[$reason] ?? 0) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="nc-panel" id="grup">
            <div class="nc-panel-head"><h2 class="nc-panel-title">Grup dengan biaya terbesar</h2></div>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Grup</th><th>Jawaban</th><th>NK</th><th>Biaya</th></tr></thead>
                    <tbody>
                        @forelse($usageByGroup as $row)
                            <tr><td class="max-w-[12rem] truncate"><a href="{{ route('superadmin.groups.show', $row->id) }}" class="font-medium hover:underline">{{ $row->name }}</a></td><td>{{ (int) $row->requests }}</td><td>{{ $nk($row->charged) }}</td><td>{{ $rp($row->cost_idr) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-ink-soft">Belum ada pemakaian pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="nc-panel" id="transaksi">
        <div class="nc-panel-head"><h2 class="nc-panel-title">Transaksi</h2></div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Grup</th><th class="max-sm:hidden">Jenis</th><th>NK</th><th class="max-md:hidden">Bayar</th><th>Waktu</th></tr></thead>
                <tbody>
                    @forelse($transactions as $row)
                        <tr>
                            <td class="max-w-[14rem]">
                                <span class="block truncate font-medium">{{ $row->group?->name ?? 'Grup dihapus' }}</span>
                                <span class="block truncate text-xs text-ink-soft">{{ $row->user?->name ?? 'Akun dihapus' }}{{ $row->note ? ' · ' . $row->note : '' }}</span>
                            </td>
                            <td class="max-sm:hidden">{{ $sources[$row->source] ?? $row->source }}</td>
                            <td>{{ $row->credit_amount > 0 ? '+' : '' }}{{ $nk($row->credit_amount) }}</td>
                            <td class="max-md:hidden">{{ $row->price_paid > 0 ? $rp($row->price_paid) : '–' }}</td>
                            <td>{{ \App\Support\UserTime::format($row->created_at, 'j M, H.i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-ink-soft">Tidak ada transaksi pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </section>
@endsection
