@props(['back', 'title', 'subtitle' => null])
{{-- Kepala halaman dengan satu gaya tombol kembali untuk semua halaman. --}}
<div class="nc-page-header">
    <a href="{{ $back }}" class="nc-icon-button -ml-2" aria-label="Kembali">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
    </a>
    <div class="min-w-0">
        <h1 class="truncate text-xl font-bold">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-ink-soft">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
