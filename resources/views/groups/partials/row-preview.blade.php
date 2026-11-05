{{-- Pratinjau baris daftar grup: draf (berlabel, warna aksen) atau pesan terakhir. resources/js/ui/group-list-live.js
     memperbarui keduanya tanpa memuat ulang. --}}
<span class="{{ $class }}" data-row-preview>
    <span data-row-draft @unless($row['draft']) hidden @endunless><span class="nc-draft-label">Draf</span> <span data-row-draft-text>{{ $row['draft'] }}</span></span>
    <span data-row-last @if($row['draft']) hidden @endif>{{ $row['preview'] }}</span>
</span>
