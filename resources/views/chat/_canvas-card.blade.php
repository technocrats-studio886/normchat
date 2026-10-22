{{-- Kartu Canvas di feed; tombol Open membuka panel Code/Preview (resources/js/chat/canvas.js).
     Markup yang sama dibangun ulang di JS (attachments.js) untuk pesan realtime. Params: $message, $url --}}
@php
    $canvasIsWeb = $message->attachment_mime === \App\Services\Ai\Responders\CanvasResponder::MIME_WEB;
    $canvasTitle = \Illuminate\Support\Str::of((string) $message->attachment_original_name)->beforeLast('.')->replace('-', ' ')->title()->toString() ?: 'Canvas';
@endphp
<div class="nc-canvas-card mb-2" data-message-body="1">
    <div class="flex items-start gap-3">
        <span class="nc-canvas-card-icon">
            @if($canvasIsWeb)
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8 9-3 3 3 3m8-6 3 3-3 3M13.5 6l-3 12"/></svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6m-6 4h4"/></svg>
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">{{ $canvasTitle }}</p>
            <p class="text-[11px] opacity-70">{{ $canvasIsWeb ? 'Halaman web' : 'Dokumen' }} · {{ $message->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M, H:i') }}</p>
        </div>
    </div>
    <div class="mt-3 flex justify-end">
        <button type="button" class="nc-canvas-open" data-canvas-open="1" data-canvas-url="{{ $url }}" data-canvas-kind="{{ $canvasIsWeb ? 'web' : 'document' }}" data-canvas-title="{{ $canvasTitle }}" data-canvas-filename="{{ $message->attachment_original_name }}">Buka</button>
    </div>
</div>
