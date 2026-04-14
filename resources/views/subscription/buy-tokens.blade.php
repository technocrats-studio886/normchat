@extends('layouts.app', ['title' => 'Top-up Normkredit - Normchat'])

@section('content')
    <section class="page-shell pt-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-2xl font-extrabold text-slate-900">Top-up Normkredit</h1>
        <p class="mt-1 text-sm text-slate-500">Tambah normkredit untuk grup kamu menggunakan Dots Units (DU).</p>

        @if($errors->has('payment'))
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('payment') }}
            </div>
        @endif

        <form method="POST" action="{{ route('subscription.tokens.buy.process') }}" class="mt-6 space-y-4">
            @csrf

            {{-- Select Group --}}
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pilih Grup</label>
                @if($groups->isEmpty())
                    <p class="mt-2 text-sm text-slate-500">Belum ada grup. Buat grup terlebih dahulu.</p>
                @else
                    <select name="group_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @foreach($groups as $g)
                            @php $gt = $g->groupToken; @endphp
                            <option value="{{ $g->id }}" {{ old('group_id') == $g->id ? 'selected' : '' }}>
                                {{ $g->name }} — {{ number_format($gt?->credits ?? 0, 1) }} normkredit
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Package Selection --}}
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pilih Paket</label>

                <div class="mt-3 space-y-2">
                    @php
                        $packages = [
                            ['id' => 'nk_12', 'nk' => 12, 'du' => $duPer12Nk],
                            ['id' => 'nk_24', 'nk' => 24, 'du' => $duPer12Nk * 2],
                            ['id' => 'nk_48', 'nk' => 48, 'du' => $duPer12Nk * 4],
                            ['id' => 'nk_100', 'nk' => 100, 'du' => (int) ceil(($duPer12Nk / 12) * 100)],
                        ];
                    @endphp
                    @foreach($packages as $pkg)
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border-2 px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="package" value="{{ $pkg['id'] }}" class="accent-blue-600"
                                       {{ old('package', 'nk_12') === $pkg['id'] ? 'checked' : '' }} />
                                <p class="text-sm font-bold text-slate-800">{{ $pkg['nk'] }} Normkredit</p>
                            </div>
                            <span class="text-sm font-extrabold text-blue-600">{{ $pkg['du'] }} DU</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if($groups->isNotEmpty())
                <button type="submit" class="btn-cta w-full py-4 text-sm font-extrabold uppercase tracking-wide">
                    Top-up Normkredit
                </button>
            @endif
        </form>

        <p class="mt-4 pb-4 text-center text-[11px] text-slate-400">
            Pembayaran menggunakan Dots Units dari akun Interdotz Anda.
        </p>
    </section>
@endsection
