@extends('layouts.admin')

@section('heading', 'Biaya OpenAI')

@section('content')
    @php
        $usd = fn ($value, $decimals = 2) => '$' . number_format((float) $value, $decimals, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 2, ',', '.');
        $pct = fn ($value) => number_format((float) $value, 1, ',', '.') . '%';
        $when = fn ($time) => $time ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($time), 'j M Y, H.i') : '–';
        $margin = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',') . '%';
        $alerts = \App\Models\AdminAlert::query()->open()->get();
    @endphp

    <p class="mb-4 text-sm text-ink-soft">Menjaga saldo akun OpenAI Normi Chat. Normkredit grup tidak diubah di sini.</p>

    @if($alerts->isNotEmpty())
        <section class="nc-panel" id="peringatan">
            <div class="nc-panel-head"><h2 class="nc-panel-title">Peringatan</h2></div>
            <div class="nc-box">
                @foreach($alerts as $alert)
                    <div class="nc-box-row">
                        <span @class(['nc-status', 'is-danger' => $alert->level === 'danger', 'is-ok' => $alert->level !== 'danger']) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ $alert->title }}</span>
                            <span class="block text-xs text-ink-soft">{{ $alert->body }}</span>
                        </span>
                        <form method="POST" action="{{ route('superadmin.alerts.resolve', $alert) }}">
                            @csrf
                            <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">Tutup</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <dl class="nc-kpis nc-panel">
        <div class="nc-kpi"><dt>Kurs dipakai menagih</dt><dd>{{ $rp($usdToIdr) }}<small>{{ $rate ? 'Diisi ' . $when($rate['created_at']) : 'Belum diisi · kurs cadangan .env' }}</small></dd></div>
        <div class="nc-kpi"><dt>Terpakai {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $status['period'])->translatedFormat('F') }}</dt><dd>{{ $usd($status['spent'], 4) }}<small>{{ $status['budget'] !== null ? 'dari ' . $usd($status['budget']) . ' · ' . $pct($status['budget_percent']) : 'Tanpa anggaran bulanan' }}</small></dd></div>
        <div class="nc-kpi"><dt>Perkiraan sisa saldo</dt><dd>{{ $status['balance_left'] !== null ? $usd($status['balance_left'], 4) : '–' }}<small>{{ $status['balance'] !== null ? 'Cadangan ' . $usd($status['reserve']) : 'Saldo belum diisi' }}</small></dd></div>
        <div class="nc-kpi"><dt>Sedang ditahan</dt><dd>{{ $usd($status['reserved'], 4) }}<small>Permintaan berjalan</small></dd></div>
    </dl>

    <div class="nc-admin-cols lg:grid-cols-2">
        <div>
            <section class="nc-panel" id="kurs">
                <div class="nc-panel-head">
                    <h2 class="nc-panel-title">Kurs USD→IDR hari ini</h2>
                    <p class="nc-panel-note">{{ $rate ? $rp($rate['market_rate']) . ' + selisih kartu ' . $margin($rate['card_margin_percent']) : '' }}</p>
                </div>
                <form method="POST" action="{{ route('superadmin.openai.rate') }}" class="nc-box space-y-3 p-4">
                    @csrf
                    <label class="nc-field"><span>Kurs pasar (Rp per 1 USD)</span>
                        <input name="market_rate" type="number" step="0.01" min="5000" max="50000" required value="{{ old('market_rate') }}" class="nc-input" placeholder="18158">
                    </label>
                    @error('market_rate')
                        <p class="text-xs text-danger-ink">{{ $message }}</p>
                        @if(str_contains($message, 'Centang'))
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_jump" value="1" class="h-4 w-4 accent-primary"> Ya, kurs ini benar</label>
                        @endif
                    @enderror
                    <label class="nc-field"><span>Catatan (opsional)</span>
                        <input name="note" type="text" maxlength="200" value="{{ old('note') }}" class="nc-input" placeholder="sumber kurs">
                    </label>
                    <p class="nc-field-hint">Dipakai menagih: kurs × (1 + {{ $margin($cardMargin) }}). Selisih kartu diatur di Pengaturan.</p>
                    <button type="submit" class="btn-primary w-full py-2">Simpan kurs</button>
                </form>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Anggaran bulanan</h2></div>
                <form method="POST" action="{{ route('superadmin.openai.budget') }}" class="nc-box space-y-3 p-4">
                    @csrf
                    <label class="nc-field"><span>Anggaran per bulan (USD)</span>
                        <input name="monthly_budget_usd" type="number" step="0.01" min="1" value="{{ old('monthly_budget_usd', $status['budget']) }}" class="nc-input" placeholder="Kosong = tanpa batas">
                    </label>
                    @error('monthly_budget_usd')
                        <p class="text-xs text-danger-ink">{{ $message }}</p>
                    @enderror
                    <p class="nc-field-hint">Peringatan di 50%, 70%, 85%. Di 100% permintaan berbayar baru berhenti sampai anggaran dinaikkan atau bulan berganti.</p>
                    <button type="submit" class="btn-primary w-full py-2">Simpan anggaran</button>
                </form>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head">
                    <h2 class="nc-panel-title">Saldo OpenAI</h2>
                    <p class="nc-panel-note">{{ $status['balance'] !== null ? 'Diisi ' . $usd($status['balance']) . ' · ' . $when($status['balance_set_at']) : '' }}</p>
                </div>
                <form method="POST" action="{{ route('superadmin.openai.balance') }}" class="nc-box space-y-3 p-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <label class="nc-field"><span>Saldo di OpenAI (USD)</span><input name="balance_usd" type="number" step="0.01" min="0" required value="{{ old('balance_usd') }}" class="nc-input"></label>
                        <label class="nc-field"><span>Cadangan (USD)</span><input name="reserve_usd" type="number" step="0.01" min="0" required value="{{ old('reserve_usd', $status['reserve']) }}" class="nc-input"></label>
                    </div>
                    @error('balance_usd')<p class="text-xs text-danger-ink">{{ $message }}</p>@enderror
                    @error('reserve_usd')<p class="text-xs text-danger-ink">{{ $message }}</p>@enderror
                    <p class="nc-field-hint">Isi setiap kali top-up di OpenAI. Normi berhenti sebelum saldo menyentuh cadangan.</p>
                    <button type="submit" class="btn-primary w-full py-2">Simpan saldo</button>
                </form>
            </section>
        </div>

        <div>
            <section class="nc-panel" id="rekonsiliasi">
                <div class="nc-panel-head">
                    <h2 class="nc-panel-title">Rekonsiliasi harian</h2>
                    @unless($reconcileConfigured)<p class="nc-panel-note">Nonaktif: isi OPENAI_ADMIN_KEY dan OPENAI_PROJECT_ID</p>@endunless
                </div>
                <div class="nc-table-wrap">
                    <table class="nc-table">
                        <thead><tr><th>Tanggal (UTC)</th><th>OpenAI</th><th>Sistem</th><th>Selisih</th></tr></thead>
                        <tbody>
                            @forelse($reconciliations as $row)
                                <tr>
                                    <td>{{ $row->date->translatedFormat('j M Y') }}</td>
                                    <td>{{ $usd($row->openai_usd, 4) }}</td>
                                    <td>{{ $usd($row->computed_usd, 4) }}</td>
                                    <td>{{ $row->diff_percent !== null ? $pct($row->diff_percent) : $usd($row->diff_usd, 4) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-ink-soft">Belum ada rekonsiliasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Email peringatan</h2></div>
                <div class="nc-box nc-box-row">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $alertRecipients === [] ? 'Belum ada penerima' : implode(', ', $alertRecipients) }}</span>
                        <span class="block text-xs text-ink-soft">{{ $mailer === 'log' ? 'MAIL_MAILER masih "log": email hanya tercatat di log server.' : 'Dikirim lewat ' . ($mailer === 'smtp' ? 'SMTP' : ucfirst($mailer)) . '.' }}</span>
                    </span>
                    <form method="POST" action="{{ route('superadmin.openai.test-email') }}">
                        @csrf
                        <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">Kirim uji</button>
                    </form>
                </div>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Riwayat kurs 30 hari</h2></div>
                <div class="nc-table-wrap">
                    <table class="nc-table">
                        <thead><tr><th>Waktu</th><th>Pasar</th><th>Kartu</th><th>Dipakai</th></tr></thead>
                        <tbody>
                            @forelse($rates as $row)
                                <tr>
                                    <td>{{ $when($row->created_at) }}<span class="block text-xs text-ink-soft">{{ $row->setter?->name ?? '' }}</span></td>
                                    <td>{{ $rp($row->market_rate) }}</td>
                                    <td>{{ $margin($row->card_margin_percent) }}</td>
                                    <td>{{ $rp($row->applied_rate) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-ink-soft">Belum ada kurs yang diisi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nc-panel">
                <div class="nc-panel-head">
                    <h2 class="nc-panel-title">Versi harga</h2>
                    <p class="nc-panel-note">Tagihan lama tetap memakai versinya</p>
                </div>
                <div class="nc-box">
                    @forelse($pricingVersions as $item)
                        <div class="nc-box-row items-start">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">Versi {{ $item['version']->id }} · {{ $when($item['version']->effective_from) }}</span>
                                <span class="block text-xs text-ink-soft">{{ $item['version']->note }}{{ $item['version']->creator ? ' · ' . $item['version']->creator->name : '' }}</span>
                                @foreach($item['changes'] as [$field, $before, $after])
                                    <span class="block text-xs text-ink-soft"><span class="font-mono">{{ $field }}</span>: {{ is_bool($before) ? ($before ? 'ya' : 'tidak') : ($before ?? '–') }} → {{ is_bool($after) ? ($after ? 'ya' : 'tidak') : ($after ?? '–') }}</span>
                                @endforeach
                            </span>
                        </div>
                    @empty
                        <p class="nc-box-empty">Belum ada versi harga.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
