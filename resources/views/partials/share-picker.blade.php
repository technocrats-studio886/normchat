{{--
    Pilihan pembagian Normkredit yang dibeli: berapa persen masuk ke kredit bersama.
    Params: $credits (int, NK awal). Ikut berubah bila package-picker mengirim event `credits-change`.
--}}
@php
    $initialPercent = (int) old('share_percent', \App\Services\Credits\Funding::DEFAULT_SHARE_PERCENT);
    $sharing = $initialPercent > 0;
@endphp
<div class="panel-card px-4 py-3" data-share-picker data-credits="{{ $credits }}">
    <label class="flex items-center justify-between gap-3">
        <span class="text-sm font-bold text-slate-800">Bagikan ke kredit bersama</span>
        <input type="checkbox" data-share-toggle class="h-5 w-5 accent-blue-600" @checked($sharing)>
    </label>
    <input type="hidden" name="share_percent" value="{{ $initialPercent }}" data-share-value>

    <div class="mt-3" data-share-slider-wrap @if(! $sharing) hidden @endif>
        <input type="range" min="0" max="100" step="5" value="{{ $sharing ? $initialPercent : 50 }}" data-share-slider class="w-full accent-blue-600" aria-label="Persentase kredit bersama">
    </div>
    <p class="mt-2 text-xs text-slate-700">
        <strong data-share-shared></strong> NK bersama · <strong data-share-protected></strong> NK terlindungi
    </p>
    <p class="mt-1 text-[11px] text-slate-500">
        Kredit bersama bisa dipakai semua anggota. Kredit terlindungi hanya bisa dipakai kamu, dan bisa kamu pindahkan ke bersama kapan saja (tidak bisa sebaliknya).
    </p>
    @error('share_percent')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
