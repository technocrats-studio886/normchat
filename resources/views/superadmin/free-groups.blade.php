@extends('layouts.admin')

@section('heading', 'Grup gratis')

@section('content')
    @php
        $nk = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $on = (bool) ($settings['free_groups_enabled'] ?? false);
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
            <div class="nc-form-grid">
                <label class="nc-field"><span>Saldo grup gratis ke-1 (NK)</span>
                    <input name="free_first_group_credits" type="number" step="1" min="0" max="10000" required value="{{ old('free_first_group_credits', (int) ($settings['free_first_group_credits'] ?? 70)) }}" class="nc-input">
                </label>
                <label class="nc-field"><span>Saldo grup gratis ke-2 (NK)</span>
                    <input name="free_second_group_credits" type="number" step="1" min="0" max="10000" required value="{{ old('free_second_group_credits', (int) ($settings['free_second_group_credits'] ?? 50)) }}" class="nc-input">
                </label>
            </div>
            <label class="nc-field"><span>Anggota gratis per grup</span>
                <input name="free_group_member_slots" type="number" step="1" min="0" max="50" required value="{{ old('free_group_member_slots', (int) ($settings['free_group_member_slots'] ?? 2)) }}" class="nc-input">
            </label>
            <p class="nc-field-hint">
                Grup ke-1 dibuat otomatis saat user pertama kali masuk; grup ke-2 dibuat sendiri oleh user. Jatah dihitung dari semua grup
                yang pernah dibuat user, termasuk yang dibayar dan yang dihapus. Grup ke-3 dst dibuat dengan {{ $nk($paidCredits) }} NK berbayar.
                Anggota di luar slot gratis ikut patungan, dan slot tidak kembali bila anggota gratis keluar. Isi saldo 0 bila grup ke-1 atau ke-2 tidak perlu gratis.
                Perubahan berlaku untuk grup yang dibuat sesudahnya dan tersimpan sebagai versi harga baru.
            </p>
            <button type="submit" class="btn-primary px-4 py-2">Simpan</button>
        </form>
    </section>
@endsection
