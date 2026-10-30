@extends('layouts.app', ['title' => 'Biaya OpenAI - Admin Normi Chat'])

@section('content')
    @php
        $usd = fn ($value, $decimals = 2) => '$' . number_format((float) $value, $decimals, ',', '.');
        $rp = fn ($value) => 'Rp' . number_format((float) $value, 2, ',', '.');
        $pct = fn ($value) => number_format((float) $value, 1, ',', '.') . '%';
        $when = fn ($time) => $time ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($time)) : '-';
    @endphp
    <section class="page-shell">
        @include('superadmin._nav')

        <p class="nc-section-note">Halaman ini menjaga saldo akun OpenAI Normi Chat. Normkredit grup tidak diubah di sini.</p>

        {{-- Kurs harian --}}
        <div class="nc-section" id="kurs">
            <h2 class="nc-section-title">Kurs USD→IDR hari ini</h2>
            <div class="nc-stats">
                <div class="nc-stat">
                    <p>Kurs dipakai menagih</p>
                    <b>{{ $rp($usdToIdr) }}</b>
                    <span>{{ $rate ? 'kurs ' . $rp($rate['market_rate']) . ' + selisih kartu ' . rtrim(rtrim(number_format($rate['card_margin_percent'], 2, ',', '.'), '0'), ',') . '%' : 'Belum pernah diisi: memakai kurs cadangan .env' }}</span>
                </div>
                <div class="nc-stat">
                    <p>Terakhir diisi</p>
                    <b>{{ $rate ? $when($rate['created_at']) : '-' }}</b>
                    <span>{{ $rates->first()?->setter?->name ?? '' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.openai.rate') }}" class="nc-list mt-3 space-y-3 p-4">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold text-ink-soft">Kurs pasar hari ini (Rp per 1 USD)</span>
                    <input name="market_rate" type="number" step="0.01" min="5000" max="50000" required value="{{ old('market_rate') }}" class="nc-input mt-1 w-full" placeholder="mis. 18158">
                </label>
                @error('market_rate')
                    <p class="text-xs text-danger-ink">{{ $message }}</p>
                    @if(str_contains($message, 'Centang'))
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_jump" value="1" class="h-4 w-4 accent-primary"> Ya, kurs ini benar</label>
                    @endif
                @enderror
                <label class="block">
                    <span class="text-xs font-semibold text-ink-soft">Catatan (opsional)</span>
                    <input name="note" type="text" maxlength="200" value="{{ old('note') }}" class="nc-input mt-1 w-full" placeholder="mis. sumber kurs">
                </label>
                <p class="text-xs text-ink-soft">Dipakai menagih: kurs × (1 + selisih kartu {{ rtrim(rtrim(number_format($cardMargin, 2, ',', '.'), '0'), ',') }}%). Selisih kartu diatur di Pengaturan › Harga Normkredit.</p>
                <button type="submit" class="btn-primary w-full py-2.5">Simpan kurs</button>
            </form>
        </div>

        {{-- Anggaran bulanan --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Anggaran bulan ini ({{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $status['period'])->translatedFormat('F Y') }})</h2>
            <div class="nc-stats">
                <div class="nc-stat">
                    <p>Terpakai</p>
                    <b>{{ $usd($status['spent'], 4) }}</b>
                    <span>{{ $status['budget'] !== null ? 'dari ' . $usd($status['budget']) . ' · ' . $pct($status['budget_percent']) : 'Anggaran belum diisi (tanpa batas)' }}</span>
                </div>
                <div class="nc-stat">
                    <p>Sedang ditahan</p>
                    <b>{{ $usd($status['reserved'], 4) }}</b>
                    <span>permintaan yang sedang berjalan</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.openai.budget') }}" class="nc-list mt-3 space-y-3 p-4">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold text-ink-soft">Anggaran per bulan (USD)</span>
                    <input name="monthly_budget_usd" type="number" step="0.01" min="1" value="{{ old('monthly_budget_usd', $status['budget']) }}" class="nc-input mt-1 w-full" placeholder="Kosong = tanpa batas">
                </label>
                @error('monthly_budget_usd')
                    <p class="text-xs text-danger-ink">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-soft">Peringatan di 50%, 70%, dan 85%. Pada 100% Normi AI berhenti menerima permintaan berbayar baru sampai anggaran dinaikkan atau bulan berganti.</p>
                <button type="submit" class="btn-primary w-full py-2.5">Simpan anggaran</button>
            </form>
        </div>

        {{-- Saldo OpenAI --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Saldo OpenAI</h2>
            <div class="nc-stats">
                <div class="nc-stat">
                    <p>Perkiraan sisa saldo</p>
                    <b>{{ $status['balance_left'] !== null ? $usd($status['balance_left'], 4) : '-' }}</b>
                    <span>{{ $status['balance'] !== null ? 'diisi ' . $usd($status['balance']) . ' pada ' . $when($status['balance_set_at']) : 'Saldo belum diisi (tanpa batas)' }}</span>
                </div>
                <div class="nc-stat">
                    <p>Cadangan yang dijaga</p>
                    <b>{{ $usd($status['reserve']) }}</b>
                    <span>{{ $status['balance_percent'] !== null ? $pct($status['balance_percent']) . ' saldo di atas cadangan terpakai' : '' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.openai.balance') }}" class="nc-list mt-3 space-y-3 p-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Saldo di billing OpenAI (USD)</span>
                        <input name="balance_usd" type="number" step="0.01" min="0" required value="{{ old('balance_usd') }}" class="nc-input mt-1 w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Cadangan (USD)</span>
                        <input name="reserve_usd" type="number" step="0.01" min="0" required value="{{ old('reserve_usd', $status['reserve']) }}" class="nc-input mt-1 w-full">
                    </label>
                </div>
                @error('balance_usd')
                    <p class="text-xs text-danger-ink">{{ $message }}</p>
                @enderror
                @error('reserve_usd')
                    <p class="text-xs text-danger-ink">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-soft">Isi setiap kali top-up di OpenAI. Pemakaian dihitung ulang dari 0 sejak saldo baru diisi; Normi AI berhenti sebelum saldo menyentuh cadangan.</p>
                <button type="submit" class="btn-primary w-full py-2.5">Simpan saldo</button>
            </form>
        </div>

        {{-- Rekonsiliasi --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Rekonsiliasi harian</h2>
            @unless($reconcileConfigured)
                <p class="nc-section-note">Rekonsiliasi nonaktif: isi OPENAI_ADMIN_KEY dan OPENAI_PROJECT_ID di .env.</p>
            @endunless
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Tanggal (UTC)</th><th>Biaya OpenAI</th><th>Hitungan sistem</th><th>Selisih</th></tr></thead>
                    <tbody>
                        @forelse($reconciliations as $row)
                            <tr>
                                <td>{{ $row->date->translatedFormat('j M Y') }}</td>
                                <td>{{ $usd($row->openai_usd, 4) }}</td>
                                <td>{{ $usd($row->computed_usd, 4) }}</td>
                                <td>{{ $usd($row->diff_usd, 4) }}{{ $row->diff_percent !== null ? ' (' . $pct($row->diff_percent) . ')' : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Belum ada rekonsiliasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Email peringatan --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Email peringatan</h2>
            <div class="nc-list">
                <div class="nc-list-row">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $alertRecipients === [] ? 'Belum ada penerima' : implode(', ', $alertRecipients) }}</p>
                        <p class="text-xs text-ink-soft">{{ $mailer === 'log' ? 'MAIL_MAILER masih "log": email hanya tercatat di log server. Atur SMTP di .env agar email terkirim.' : 'Dikirim lewat ' . ($mailer === 'smtp' ? 'SMTP' : ucfirst($mailer)) . ' (MAIL_MAILER).' }}</p>
                    </div>
                    <form method="POST" action="{{ route('superadmin.openai.test-email') }}">
                        @csrf
                        <button type="submit" class="btn-secondary px-3 py-2">Kirim email uji</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Riwayat kurs --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Riwayat kurs 30 hari</h2>
            <div class="nc-table-wrap">
                <table class="nc-table">
                    <thead><tr><th>Waktu</th><th>Kurs pasar</th><th>Selisih kartu</th><th>Dipakai</th><th>Oleh</th></tr></thead>
                    <tbody>
                        @forelse($rates as $row)
                            <tr>
                                <td>{{ \App\Support\UserTime::format($row->created_at) }}</td>
                                <td>{{ $rp($row->market_rate) }}</td>
                                <td>{{ rtrim(rtrim(number_format($row->card_margin_percent, 2, ',', '.'), '0'), ',') }}%</td>
                                <td>{{ $rp($row->applied_rate) }}</td>
                                <td>{{ $row->setter?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Belum ada kurs yang diisi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Versi harga --}}
        <div class="nc-section">
            <h2 class="nc-section-title">Riwayat versi harga</h2>
            <p class="nc-section-note">Setiap perubahan harga Normkredit atau model AI menjadi versi baru; tagihan lama tetap memakai versinya.</p>
            <div class="nc-list">
                @forelse($pricingVersions as $item)
                    <div class="nc-list-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">Versi {{ $item['version']->id }} · {{ \App\Support\UserTime::format($item['version']->effective_from) }}{{ $item['version']->creator ? ' · ' . $item['version']->creator->name : '' }}</p>
                            <p class="text-xs text-ink-soft">{{ $item['version']->note }}</p>
                            @foreach($item['changes'] as [$field, $before, $after])
                                <p class="text-xs text-ink-soft"><span class="font-mono">{{ $field }}</span>: {{ is_bool($before) ? ($before ? 'ya' : 'tidak') : ($before ?? '-') }} → {{ is_bool($after) ? ($after ? 'ya' : 'tidak') : ($after ?? '-') }}</p>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="nc-list-empty">Belum ada versi harga.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
