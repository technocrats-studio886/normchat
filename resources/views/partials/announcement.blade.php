{{-- Satu pengumuman (popup atau banner). Param: $announcement, $preview (bool, pratinjau di admin). --}}
@php($preview = $preview ?? false)
<div @class([
        'nc-announce',
        'nc-announce--' . $announcement->display,
        'is-important' => $announcement->tone === 'penting',
        'is-preview' => $preview,
    ])
    @unless($preview) data-announcement="{{ $announcement->id }}" @endunless
    data-announcement-card
    role="{{ $announcement->tone === 'penting' ? 'alert' : 'status' }}">
    <div class="min-w-0 flex-1">
        <p class="nc-announce-title" data-preview-target="title">{{ $announcement->title }}</p>
        <p class="nc-announce-text" data-preview-target="body">{{ $announcement->bodyHtml() }}</p>
    </div>
    <div class="nc-announce-actions">
        <a @if($preview) href="#" @else href="{{ $announcement->cta_url }}" @endif
           @if($announcement->ctaIsExternal() && ! $preview) target="_blank" rel="noopener noreferrer" @endif
           @class(['btn-primary px-3 py-1.5 text-sm', 'hidden' => blank($announcement->cta_label)])
           data-preview-target="cta">{{ $announcement->cta_label }}</a>
        <button type="button" class="btn-secondary px-3 py-1.5 text-sm" @unless($preview) data-announcement-dismiss @endunless>Tutup</button>
    </div>
</div>
