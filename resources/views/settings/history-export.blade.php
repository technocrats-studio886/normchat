@extends('layouts.app', ['title' => 'Riwayat & Cadangan - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell" data-history-page="1"
        data-export-url="{{ route('settings.export', $group) }}"
        data-exports-url="{{ route('settings.exports', $group) }}"
        data-export-count-url="{{ route('settings.export.count', $group) }}"
        data-backup-url="{{ route('settings.backup', $group) }}"
        data-backups-url="{{ route('settings.backups', $group) }}">
        <x-page-header :back="route('settings.show', $group)" title="Riwayat & Cadangan" />

        @if($canExportChat)
            <div class="nc-section">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="nc-section-title">Unduh riwayat chat</h2>
                        <p class="nc-section-note">PDF atau DOCX, disimpan 7 hari.</p>
                    </div>
                    <button type="button" class="btn-primary shrink-0 px-4 py-2" data-open-export="1">Unduh riwayat</button>
                </div>
                <div class="nc-list" data-export-list="1" aria-live="polite"></div>
            </div>
        @endif

        @if($canBackup)
            <div class="nc-section">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="nc-section-title">Cadangan</h2>
                        <p class="nc-section-note">Dibuat otomatis tiap malam pukul 03.00 WIB bila ada pesan baru, disimpan 30 hari. Memulihkan cadangan mengembalikan pesan yang terhapus sejak saat itu, tanpa mengubah pesan lain.</p>
                    </div>
                </div>
                <div class="nc-list" data-backup-list="1" aria-live="polite"></div>
                <button type="button" class="btn-secondary mt-3 w-full py-2.5" data-create-backup="1">Buat cadangan sekarang</button>
            </div>
        @endif

        @if(! $canExportChat && ! $canBackup)
            <p class="text-sm text-ink-soft">Unduh riwayat dan cadangan hanya tersedia untuk pemilik atau admin grup.</p>
        @endif

        <script type="application/json" data-history-data nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json(['exports' => $exports, 'backups' => $backups], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)</script>

        @if($canExportChat)
            {{-- Lembar filter unduhan (resources/js/settings/history.js) --}}
            <div class="nc-dialog-overlay hidden" data-export-sheet="1">
                <form class="nc-dialog nc-export-sheet" role="dialog" aria-modal="true" aria-labelledby="nc-export-title" data-export-form="1">
                    <div class="flex items-center justify-between gap-2">
                        <h2 id="nc-export-title" class="nc-dialog-title">Unduh riwayat chat</h2>
                        <button type="button" class="nc-icon-button" data-close-export="1" aria-label="Tutup">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <fieldset>
                        <legend class="nc-field-label">Format</legend>
                        <div class="nc-segment nc-segment--2">
                            <label><input type="radio" name="file_type" value="pdf" checked><span>PDF</span></label>
                            <label><input type="radio" name="file_type" value="docx"><span>DOCX</span></label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="nc-field-label">Rentang waktu</legend>
                        <div class="nc-chips">
                            <label><input type="radio" name="range" value="7d"><span>7 hari</span></label>
                            <label><input type="radio" name="range" value="30d" checked><span>30 hari</span></label>
                            <label><input type="radio" name="range" value="all"><span>Semua</span></label>
                            <label><input type="radio" name="range" value="custom"><span>Pilih tanggal</span></label>
                        </div>
                        <div class="mt-2 hidden grid-cols-2 gap-2" data-custom-range="1">
                            <label class="nc-date"><span>Dari</span><input type="date" name="from"></label>
                            <label class="nc-date"><span>Sampai</span><input type="date" name="to"></label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="nc-field-label">Pengirim</legend>
                        <div class="nc-chips">
                            <label><input type="checkbox" value="" data-sender-all checked><span>Semua</span></label>
                            <label><input type="checkbox" name="senders[]" value="ai"><span>Normi AI</span></label>
                            @foreach($senders as $sender)
                                <label><input type="checkbox" name="senders[]" value="{{ $sender['id'] }}"><span>{{ $sender['name'] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid gap-2">
                        <label class="nc-switch"><span>Gambar ditampilkan di dalam file</span><input type="checkbox" name="images" value="1" checked role="switch"></label>
                        <label class="nc-switch"><span>Sertakan polling</span><input type="checkbox" name="polls" value="1" checked role="switch"></label>
                    </div>

                    <p class="text-center text-sm text-ink-soft" data-export-count="1" aria-live="polite"></p>
                    <button type="submit" class="btn-primary w-full py-2.5 disabled:opacity-50" data-export-submit="1">Buat PDF</button>
                </form>
            </div>
        @endif
    </section>
@endsection
