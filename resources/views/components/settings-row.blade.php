{{-- Satu baris di halaman Akun/Pengaturan: ikon, judul, keterangan, panah. Tanpa href menjadi tombol. --}}
{{-- $subtitleData: nama atribut data-* pada keterangan, untuk diperbarui JS (mis. label tema). --}}
@props(['href' => null, 'icon' => null, 'title', 'subtitle' => null, 'external' => false, 'subtitleData' => null])
@php($tag = $href ? 'a' : 'button')
<{{ $tag }} @if($href) href="{{ $href }}" @if($external) target="_blank" rel="noopener noreferrer" @endif @else type="button" @endif {{ $attributes->merge(['class' => 'nc-settings-row']) }}>
    @isset($leading)
        {{ $leading }}
    @else
        <span class="nc-settings-row-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
        </span>
    @endisset
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold">{{ $title }}</span>
        @if($subtitle !== null)
            <span class="block truncate text-xs text-ink-soft"@if($subtitleData) {{ $subtitleData }}="1"@endif>{{ $subtitle }}</span>
        @endif
    </span>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-ink-soft" aria-hidden="true">
        @if($external)
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
        @else
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        @endif
    </svg>
</{{ $tag }}>
