@extends('layouts.admin')

@section('heading', 'Promo')

@section('content')
    @php
        $nk = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $local = fn ($time) => $time ? $time->setTimezone($zone)->format('Y-m-d\TH:i') : '';
        $when = fn ($time) => $time ? \App\Support\UserTime::format($time, 'j M Y, H.i') : null;
        $labels = ['mati' => ['Tidak aktif', ''], 'dijadwalkan' => ['Dijadwalkan', 'is-ok'], 'berjalan' => ['Berjalan', 'is-ok'], 'selesai' => ['Selesai', '']];
    @endphp

    <section class="nc-panel">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Bonus buat grup</h2>
            <span class="nc-status {{ $labels[$state][1] }}">{{ $labels[$state][0] }}</span>
        </div>
        <dl class="nc-kpis">
            <div class="nc-kpi"><dt>Bonus per grup baru</dt><dd>{{ $nk($settings['group_bonus_credits'] ?? 0) }} NK<small>{{ $starts || $ends ? trim(($when($starts) ?? 'Sejak awal') . ' – ' . ($when($ends) ?? 'tanpa batas')) : 'Tanpa batas waktu' }}</small></dd></div>
            <div class="nc-kpi"><dt>Grup dapat bonus (periode ini)</dt><dd>{{ number_format($periodGroups, 0, ',', '.') }}<small>{{ $nk($periodCredits) }} NK diberikan</small></dd></div>
            <div class="nc-kpi"><dt>Total bonus sepanjang waktu</dt><dd>{{ $nk($allCredits) }} NK<small>Bukan pendapatan</small></dd></div>
        </dl>
    </section>

    <section class="nc-panel">
        <form method="POST" action="{{ route('superadmin.promo.update') }}" class="nc-box max-w-2xl space-y-4 p-4">
            @csrf
            <label class="nc-switch">
                <span>Promo aktif</span>
                <input type="checkbox" name="group_bonus_enabled" value="1" role="switch" @checked(old('group_bonus_enabled', $settings['group_bonus_enabled'] ?? false))>
            </label>
            <label class="nc-field"><span>Bonus per grup baru (NK)</span>
                <input name="group_bonus_credits" type="number" step="0.01" min="0" max="10000" required value="{{ old('group_bonus_credits', $settings['group_bonus_credits'] ?? 20) }}" class="nc-input">
            </label>
            <div class="nc-form-grid">
                <label class="nc-field"><span>Mulai (opsional)</span><input name="group_bonus_starts_at" type="datetime-local" value="{{ old('group_bonus_starts_at', $local($starts)) }}" class="nc-input"></label>
                <label class="nc-field"><span>Selesai (opsional)</span><input name="group_bonus_ends_at" type="datetime-local" value="{{ old('group_bonus_ends_at', $local($ends)) }}" class="nc-input"></label>
            </div>
            <p class="nc-field-hint">
                Setiap grup yang checkout-nya dimulai selama periode ini mendapat bonus di atas {{ $nk(app(\App\Services\NormkreditPricing::class)->groupCreationCredits()) }} NK modal grup.
                Nilai bonus dikunci saat checkout. Waktu mengikuti zona {{ $zone }}. Tersimpan sebagai versi harga baru.
            </p>
            <button type="submit" class="btn-primary px-4 py-2">Simpan promo</button>
        </form>
    </section>
@endsection
