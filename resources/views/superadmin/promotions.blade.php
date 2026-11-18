@extends('layouts.admin')
@section('title', 'Promo | Normi Chat')
@section('heading', 'Promo')
@section('content')
    @php
        $local = fn ($value) => $value ? \Carbon\CarbonImmutable::parse($value)->setTimezone(\App\Services\GroupPromotion::TIMEZONE)->format('Y-m-d\TH:i') : '';
    @endphp
    <section class="nc-panel nc-box p-4">
        <h2 class="text-lg font-semibold">Promo pembuatan grup</h2>
        <p class="mt-2 text-sm text-slate-600">{{ $sentence }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ $offer['active'] ? 'Sedang berlaku' : 'Tidak sedang berlaku' }} · {{ $bonusCount }} bonus diberikan · {{ number_format($bonusGiven, 2, ',', '.') }} NC</p>
        <form action="{{ route('superadmin.promotions.update') }}" method="post" class="mt-6 space-y-4">
            @csrf
            <label class="flex items-center gap-2"><input type="checkbox" name="group_promo_enabled" value="1" @checked(old('group_promo_enabled', $settings['group_promo_enabled'] ?? false))> Aktifkan promo pada periode di bawah</label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Jenis bonus
                    <select name="group_promo_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3">
                        <option value="fixed" @selected(old('group_promo_type', $settings['group_promo_type'] ?? 'fixed') === 'fixed')>Nominal NC tetap</option>
                        <option value="percent" @selected(old('group_promo_type', $settings['group_promo_type'] ?? 'fixed') === 'percent')>Persentase dari NC pembelian</option>
                    </select>
                </label>
                <label class="block text-sm">Nilai bonus (NC atau persen)
                    <input type="number" name="group_promo_value" min="0" max="10000" step="0.01" required value="{{ old('group_promo_value', $settings['group_promo_value'] ?? 50) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3">
                </label>
                <label class="block text-sm">Mulai (WITA)
                    <input type="datetime-local" name="group_promo_starts_at" value="{{ old('group_promo_starts_at', $local($settings['group_promo_starts_at'] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3">
                </label>
                <label class="block text-sm">Berakhir (WITA)
                    <input type="datetime-local" name="group_promo_ends_at" value="{{ old('group_promo_ends_at', $local($settings['group_promo_ends_at'] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3">
                </label>
            </div>
            <p class="text-xs text-slate-500">Contoh: bonus 50 NC atau 20% dari 250 NC sama-sama memberikan total 300 NC setelah pembayaran. Berlaku untuk setiap pembuatan grup; top-up dan patungan tidak mendapat bonus ini.</p>
            <label class="block text-sm">Teman pertama yang bergabung gratis di setiap grup baru
                <input type="number" name="paid_group_member_slots" min="0" max="50" required value="{{ old('paid_group_member_slots', $settings['paid_group_member_slots'] ?? 2) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3">
            </label>
            <p class="text-xs text-slate-500">Perubahan slot hanya untuk grup berikutnya. Slot tidak kembali setelah anggota keluar. Grup dan saldo lama tetap tersedia.</p>
            @if($errors->any())<p class="text-sm text-danger-ink" role="alert">{{ $errors->first() }}</p>@endif
            <button class="btn-primary" type="submit">Simpan promo</button>
        </form>
    </section>
@endsection
