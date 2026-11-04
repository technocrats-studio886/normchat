{{-- Lembar "Tautan grup" (gambar acuan WhatsApp): salin tautan, salin kode, bagikan tautan, kode QR.
     Dibuka oleh [data-open-share-sheet]; perilakunya di resources/js/ui/share-sheet.js. --}}
@php($joinUrl = route('groups.join', $group->share_id))
<div class="nc-share-overlay" hidden data-share-sheet
     data-share-url="{{ $joinUrl }}" data-share-code="{{ $group->share_id }}" data-share-name="{{ $group->name }}">
    <div class="nc-share-sheet" role="dialog" aria-modal="true" aria-labelledby="nc-share-title">
        <div class="nc-share-head">
            <p id="nc-share-title" class="text-base font-bold">Tautan grup</p>
            <button type="button" class="nc-info-icon-btn" data-share-close aria-label="Tutup"><x-info-icon name="close" /></button>
        </div>
        <div class="flex items-center gap-3 px-4 pb-3">
            <span class="nc-info-avatar h-14 w-14 text-lg"><x-group-photo :group="$group" /></span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold">{{ $group->name }}</p>
                <p class="break-all text-xs text-primary-ink">{{ $joinUrl }}</p>
            </div>
        </div>
        @if($group->needsPassword())
            <p class="mx-4 mb-2 rounded-xl bg-[var(--nc-surface-2)] px-3 py-2 text-xs text-ink-soft">Grup ini memakai kata sandi. Beri tahu sandinya ke temanmu secara langsung.</p>
        @endif
        <div class="nc-share-options" data-share-options>
            <button type="button" class="nc-share-option" data-share-action="copy-link"><x-info-icon name="copy" /><span>Salin tautan</span></button>
            <button type="button" class="nc-share-option" data-share-action="copy-code"><x-info-icon name="hash" /><span class="flex-1">Salin kode</span><span class="font-mono text-sm font-semibold text-ink-soft">{{ $group->share_id }}</span></button>
            <button type="button" class="nc-share-option" data-share-action="share"><x-info-icon name="share" /><span>Bagikan tautan</span></button>
            <button type="button" class="nc-share-option" data-share-action="qr"><x-info-icon name="qr" /><span>Kode QR</span></button>
        </div>
        <div class="nc-share-qr" hidden data-share-qr>
            <canvas class="nc-share-qr-canvas" data-share-qr-canvas aria-label="Kode QR tautan grup" role="img"></canvas>
            <p class="text-center text-xs text-ink-soft">Pindai untuk membuka undangan grup {{ $group->name }}.</p>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary flex-1 py-2" data-share-qr-back>Kembali</button>
                <button type="button" class="btn-primary flex-1 py-2" data-share-qr-save>Simpan gambar</button>
            </div>
        </div>
        <p class="nc-share-status" role="status" aria-live="polite" data-share-status></p>
    </div>
</div>
