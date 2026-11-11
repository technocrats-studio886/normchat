@extends('layouts.admin')

@section('heading', 'Grup gratis')

@section('content')
    @php
        $nk = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $on = (bool) ($settings['free_groups_enabled'] ?? false);
        // Saldo grup gratis ke-1, ke-2, … (NormkreditPricing::freeGroupCredits); dua baris kosong untuk menambah grup.
        $list = old('free_group_credits', \App\Services\NormkreditPricing::parseCreditList($settings['free_group_credits'] ?? ''));
        $rows = min(\App\Services\NormkreditPricing::MAX_FREE_GROUPS, max(2, count(array_filter((array) $list)) + 2));
    @endphp

    <section class="nc-panel">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Ringkasan</h2>
            <span class="nc-status {{ $on ? 'is-ok' : '' }}">{{ $on ? 'Aktif' : 'Tidak aktif' }}</span>
        </div>
        <dl class="nc-kpis">
            <div class="nc-kpi"><dt>Grup gratis dibuat</dt><dd>{{ number_format($groupCount, 0, ',', '.') }}<small>Termasuk yang sudah dihapus</small></dd></div>
            <div class="nc-kpi"><dt>Anggota gabung gratis</dt><dd>{{ number_format($freeJoins, 0, ',', '.') }}<small>Tanpa patungan</small></dd></div>
            <div class="nc-kpi"><dt>Normkredit diberikan</dt><dd>{{ $nk($givenCredits) }} NK<small>Bukan pendapatan</small></dd></div>
        </dl>
    </section>

    <section class="nc-panel">
        <form method="POST" action="{{ route('superadmin.free-groups.update') }}" class="nc-box max-w-2xl space-y-4 p-4">
            @csrf
            <label class="nc-switch">
                <span>Grup gratis aktif</span>
                <input type="checkbox" name="free_groups_enabled" value="1" role="switch" @checked(old('free_groups_enabled', $on))>
            </label>
            <fieldset class="space-y-2">
                <legend class="text-sm font-semibold">Grup gratis per user dan saldo awalnya</legend>
                <div class="nc-form-grid">
                    @for ($i = 0; $i < $rows; $i++)
                        <label class="nc-field"><span>Saldo grup gratis ke-{{ $i + 1 }} (NK)</span>
                            <input name="free_group_credits[]" type="number" step="1" min="0" max="10000" value="{{ isset($list[$i]) && (float) $list[$i] > 0 ? (int) $list[$i] : '' }}" placeholder="Tidak gratis" class="nc-input">
                        </label>
                    @endfor
                </div>
                @error('free_group_credits.*')<p class="text-xs font-semibold text-danger-ink">{{ $message }}</p>@enderror
            </fieldset>
            <label class="nc-field"><span>Anggota gratis per grup</span>
                <input name="free_group_member_slots" type="number" step="1" min="0" max="50" required value="{{ old('free_group_member_slots', (int) ($settings['free_group_member_slots'] ?? 2)) }}" class="nc-input">
            </label>
            <p class="nc-field-hint">
                Jumlah grup gratis per user mengikuti baris yang diisi, berurutan. Kosongkan atau isi 0 untuk berhenti; baris sesudahnya diabaikan.
                Grup gratis ke-1 dibuat otomatis saat user pertama kali masuk, berikutnya dibuat sendiri oleh user. Jatah dihitung dari semua grup
                yang pernah dibuat user, termasuk yang dibayar dan yang dihapus. Grup sesudah jatah habis dibuat dengan {{ $nk($paidCredits) }} NK berbayar.
                Anggota di luar slot gratis ikut patungan, dan slot tidak kembali bila anggota gratis keluar.
                Perubahan berlaku untuk grup yang dibuat sesudahnya, langsung tampil di halaman masuk, /home, dan /pricing, dan tersimpan sebagai versi harga baru.
            </p>
            <div class="nc-box p-3 text-sm">
                <p class="font-semibold">Tampil di halaman masuk normi.chat sekarang</p>
                <p class="mt-1 text-ink-soft">{{ $offerSentence ?? 'Tidak ada tawaran grup gratis (halaman masuk hanya menampilkan tombol masuk).' }}</p>
            </div>
            <button type="submit" class="btn-primary px-4 py-2">Simpan</button>
        </form>
    </section>
@endsection
