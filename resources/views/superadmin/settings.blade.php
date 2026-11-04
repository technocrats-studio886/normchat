@extends('layouts.admin')

@section('heading', 'Pengaturan')

@section('content')
    @php
        $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $num = fn ($value, $decimals = 4) => rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
        $groups = [
            'Pembelian' => [
                'rupiah_per_credit' => ['Rupiah per 1 NK', '1'],
                'group_creation_credits' => ['NK buat grup', '1'],
                'package_credits' => ['NK per paket', '1'],
                'max_packages_per_payment' => ['Maks. paket per bayar', '1'],
                'qris_fee_percent' => ['Biaya layanan QRIS (%)', '0.01'],
            ],
            'Pemakaian Normi' => [
                'media_multiplier' => ['Multiplier gambar & voice', '0.01'],
                'max_credits_per_request' => ['Maks. NK per permintaan', '0.01'],
                'max_output_tokens' => ['Maks. token keluaran', '1'],
                'max_input_tokens' => ['Maks. token masukan', '1'],
                'daily_group_credit_limit' => ['Batas harian grup (NK)', '0.01'],
            ],
            'Kurs' => [
                'card_margin_percent' => ['Selisih kurs kartu (%)', '0.01'],
            ],
        ];
    @endphp

    <section class="nc-panel">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Model AI</h2>
            <p class="nc-panel-note">Contoh = 3.000 token masuk + 500 keluar</p>
        </div>
        <div class="nc-table-wrap">
            <table class="nc-table">
                <thead><tr><th>Model</th><th class="max-sm:hidden">Masuk / keluar (USD per 1 jt)</th><th>Multiplier</th><th class="max-md:hidden">Kunci saldo</th><th>Contoh</th></tr></thead>
                <tbody>
                    @forelse($aiModels as $aiModel)
                        <tr>
                            <td>
                                <a href="{{ route('superadmin.models.edit', $aiModel) }}" class="font-semibold hover:underline">{{ $aiModel->label }}</a>
                                @if($aiModel->is_default)<span class="nc-badge">bawaan</span>@endif
                                @unless($aiModel->is_active)<span class="nc-badge">nonaktif</span>@endunless
                                <span class="block font-mono text-xs text-ink-soft">{{ $aiModel->model_id }}</span>
                            </td>
                            <td class="max-sm:hidden">${{ $num($aiModel->input_usd_per_mtok) }} / ${{ $num($aiModel->output_usd_per_mtok) }}</td>
                            <td>×{{ $num($aiModel->multiplier, 2) }}</td>
                            <td class="max-md:hidden">{{ $aiModel->isGated() ? 'buka ' . $num($aiModel->unlock_multiple, 2) . '× · tutup ' . $num((float) $aiModel->lock_multiple, 2) . '×' : '–' }}</td>
                            <td>{{ $fmtNk($sampleCredits[$aiModel->id] ?? 0) }} NK</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-ink-soft">Belum ada model. Normi tidak bisa dipakai sampai ada model aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <details class="nc-box mt-3 p-4" @if($errors->hasAny(['model_id', 'label', 'input_usd_per_mtok', 'output_usd_per_mtok', 'multiplier'])) open @endif>
            <summary class="cursor-pointer text-sm font-semibold">Tambah model</summary>
            <form method="POST" action="{{ route('superadmin.models.store') }}" class="mt-4 space-y-4">
                @csrf
                @include('superadmin._model-form', ['aiModel' => null])
                <button type="submit" class="btn-primary px-4 py-2">Tambah model</button>
            </form>
        </details>
    </section>

    <section class="nc-panel">
        <div class="nc-panel-head">
            <h2 class="nc-panel-title">Harga Normkredit</h2>
            <p class="nc-panel-note">Setiap penyimpanan menjadi versi harga baru</p>
        </div>
        <form method="POST" action="{{ route('superadmin.settings.update') }}" class="nc-box space-y-5 p-4">
            @csrf
            @foreach($groups as $title => $fields)
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">{{ $title }}</legend>
                    <div class="nc-form-grid lg:grid-cols-3">
                        @foreach($fields as $key => [$label, $step])
                            <label class="nc-field">
                                <span>{{ $label }}</span>
                                <input name="{{ $key }}" type="number" step="{{ $step }}" min="0" required value="{{ old($key, $settings[$key]) }}" class="nc-input">
                                @error($key)<span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span>@enderror
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <p class="nc-field-hint">
                Potongan = biaya OpenAI × kurs Rp{{ number_format($usdToIdr, 2, ',', '.') }}/USD × multiplier model ÷ Rupiah per NK.
                Kurs diisi harian di <a href="{{ route('superadmin.openai') }}#kurs" class="underline">Biaya OpenAI</a>.
                Isi 0 pada maks. NK per permintaan atau batas harian untuk menonaktifkannya.
            </p>
            <button type="submit" class="btn-primary px-4 py-2">Simpan harga</button>
        </form>
    </section>
@endsection
