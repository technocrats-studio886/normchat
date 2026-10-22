@extends('layouts.app', ['title' => 'Superadmin - Normchat'])

@section('content')
    @php
        $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtIdr = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
        $usageRevenue = (float) $usage->charged * $rupiahPerCredit;
        $usageMargin = $usageRevenue > 0 ? (1 - (float) $usage->cost_idr / $usageRevenue) * 100 : null;
    @endphp
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Profil
        </a>
        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Superadmin</h1>

        @if(session('success'))
            <div class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif

        {{-- Ringkasan 30 hari --}}
        <h2 class="section-title mt-6">30 hari terakhir</h2>
        <div class="grid grid-cols-2 gap-2">
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Penjualan NK</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $fmtIdr($sales->revenue) }}</p>
                <p class="text-[11px] text-slate-500">{{ $fmtNk($sales->credits) }} NK · {{ (int) $sales->payments }} bayar</p>
            </div>
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Pemakaian AI</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $fmtNk($usage->charged) }} NK</p>
                <p class="text-[11px] text-slate-500">{{ (int) $usage->requests }} request · {{ $fmtIdr($usageRevenue) }}</p>
            </div>
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Biaya OpenAI</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $fmtIdr($usage->cost_idr) }}</p>
            </div>
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-3 py-3 shadow-sm">
                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Margin atas pemakaian</p>
                <p class="mt-1 text-sm font-bold {{ $usageMargin !== null && $usageMargin < 25 ? 'text-rose-600' : 'text-emerald-700' }}">
                    {{ $usageMargin === null ? '–' : number_format($usageMargin, 1, ',', '.') . '%' }}
                </p>
            </div>
        </div>
        @if($usageByModel->isNotEmpty())
            <div class="mt-2 overflow-x-auto rounded-2xl border border-[#dbe6ff] bg-white shadow-sm">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400">
                        <tr><th class="px-3 py-2">Model</th><th class="px-3 py-2 text-right">Req</th><th class="px-3 py-2 text-right">NK</th><th class="px-3 py-2 text-right">Biaya</th></tr>
                    </thead>
                    <tbody>
                        @foreach($usageByModel as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2 font-mono">{{ $row->model }}</td>
                                <td class="px-3 py-2 text-right">{{ (int) $row->requests }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmtNk($row->charged) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmtIdr($row->cost_idr) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Katalog model --}}
        <h2 class="section-title mt-6">Model AI</h2>
        <p class="-mt-1 mb-2 text-[11px] text-slate-500">Contoh = 1 request 3.000 token input + 500 output.</p>
        <div class="space-y-2">
            @forelse($aiModels as $aiModel)
                <a href="{{ route('superadmin.models.edit', $aiModel) }}" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:bg-slate-50 {{ $aiModel->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900">
                                {{ $aiModel->label }}
                                @if($aiModel->is_default)<span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">default</span>@endif
                                @unless($aiModel->is_active)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">nonaktif</span>@endunless
                            </p>
                            <p class="truncate font-mono text-[11px] text-slate-500">{{ $aiModel->model_id }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                ${{ rtrim(rtrim(number_format($aiModel->input_usd_per_mtok, 4), '0'), '.') }} in ·
                                ${{ rtrim(rtrim(number_format($aiModel->output_usd_per_mtok, 4), '0'), '.') }} out ·
                                ×{{ rtrim(rtrim(number_format($aiModel->multiplier, 2), '0'), '.') }}
                                · gambar: {{ $aiModel->image_model }}@if($aiModel->image_model_precise) / {{ $aiModel->image_model_precise }}@endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-extrabold text-blue-600">{{ $fmtNk($sampleCredits[$aiModel->id] ?? 0) }} NK</p>
                            <p class="text-[10px] text-slate-400">contoh</p>
                        </div>
                    </div>
                </a>
            @empty
                <p class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs text-slate-500">Belum ada model. AI grup tidak bisa dipakai sampai ada model aktif.</p>
            @endforelse
        </div>

        <details class="card-soft mt-3" @if($errors->hasAny(['model_id', 'label', 'input_usd_per_mtok', 'output_usd_per_mtok', 'multiplier'])) open @endif>
            <summary class="cursor-pointer text-sm font-bold text-slate-800">+ Tambah model</summary>
            <form method="POST" action="{{ route('superadmin.models.store') }}" class="mt-4 space-y-4">
                @csrf
                @include('superadmin._model-form', ['aiModel' => null])
                <button type="submit" class="btn-cta">Tambah Model</button>
            </form>
        </details>

        {{-- Pengaturan harga --}}
        <h2 class="section-title mt-6">Harga Normkredit</h2>
        <form method="POST" action="{{ route('superadmin.settings.update') }}" class="card-soft space-y-4">
            @csrf
            @php
                $fields = [
                    'rupiah_per_credit' => ['Rupiah per 1 NK', '1'],
                    'group_creation_credits' => ['NK buat grup', '1'],
                    'package_credits' => ['NK per paket patungan/top-up', '1'],
                    'max_packages_per_payment' => ['Maks. paket per bayar', '1'],
                    'qris_fee_percent' => ['Biaya layanan QRIS %', '0.01'],
                    'media_multiplier' => ['Multiplier gambar & voice ×', '0.01'],
                    'max_credits_per_request' => ['Maks. NK / request', '0.01'],
                    'max_output_tokens' => ['Maks. output token', '1'],
                    'daily_group_credit_limit' => ['Limit harian grup (NK)', '0.01'],
                ];
            @endphp
            <div class="grid grid-cols-2 gap-3">
                @foreach($fields as $key => [$label, $step])
                    <div>
                        <label for="{{ $key }}" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</label>
                        <input id="{{ $key }}" name="{{ $key }}" type="number" step="{{ $step }}" min="0" required value="{{ old($key, $settings[$key]) }}" class="input-field mt-1.5" />
                        @error($key)
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-500">
                Potongan = biaya OpenAI × kurs Rp{{ number_format($usdToIdr, 0, ',', '.') }}/USD (dari .env) × multiplier model ÷ Rupiah per NK.
                Harga beli = NK × Rupiah per NK, ditambah biaya layanan QRIS.
                Isi 0 pada maks. NK / request atau limit harian untuk menonaktifkan.
            </p>
            <button type="submit" class="btn-cta">Simpan Harga</button>
        </form>
    </section>
@endsection
