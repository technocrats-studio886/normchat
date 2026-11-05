{{-- Kolom kiri desktop (≥1024px). Bisa ditutup (tombol atau digeser ke kiri) menjadi kolom ramping
     berisi logo grup saja. Hanya daftar grup yang bisa di-scroll; kepala dan kaki (profil +
     pengaturan) tidak ikut bergulir sehingga daftar panjang tidak pernah menutupinya. --}}
<aside class="nc-sidebar hidden lg:flex" data-group-search-scope="1" data-sidebar="1" aria-label="Daftar grup">
    <div class="nc-sidebar-head">
        <a href="{{ route('groups.index') }}" class="flex min-w-0 items-center gap-2" title="Beranda">
            <x-brand-mark alt="Normi Chat" class="h-8 w-8 shrink-0" />
            <span class="nc-sidebar-label truncate font-display text-lg font-extrabold text-slate-900">Normi Chat</span>
        </a>
        <div class="nc-sidebar-actions">
            <button type="button" class="nc-sidebar-icon nc-sidebar-label" data-sidebar-join-toggle="1" title="Gabung dengan kode grup" aria-label="Gabung dengan kode grup" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7h3a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7a3 3 0 0 1 3-3h3m3-4v10m0 0-3-3m3 3 3-3"/></svg>
            </button>
            <a href="{{ route('groups.create') }}" class="nc-sidebar-icon" title="Buat grup" aria-label="Buat grup">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
            </a>
            <button type="button" class="nc-sidebar-icon" data-sidebar-toggle="1" title="Tutup/buka panel" aria-label="Tutup panel" aria-expanded="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path stroke-linecap="round" d="M9 4v16"/></svg>
            </button>
        </div>
    </div>

    <form method="GET" action="" class="hidden items-center gap-2 px-3 pb-2" data-join-by-id-form="{{ url('/join') }}" data-sidebar-join-form="1">
        <input type="text" name="share_id" placeholder="Kode grup" required autocomplete="off" class="input-field min-w-0 flex-1 py-2" data-join-by-id-input="1" />
        <button type="submit" class="btn-primary shrink-0 px-3 py-2 text-xs">Gabung</button>
    </form>

    <div class="nc-sidebar-label px-3 pb-2" data-group-search-row="1">
        <div class="flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
            <input type="text" placeholder="Cari grup" class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400" data-group-search-input="1" autocomplete="off" />
        </div>
    </div>

    {{-- Hasil cari pesan di grup yang sedang dibuka; menggantikan daftar grup selama pencarian (resources/js/chat/message-search.js). --}}
    <section class="nc-search-panel hidden" data-search-panel="1" aria-label="Hasil pencarian pesan">
        <div class="nc-search-panel-head">
            <p class="min-w-0 flex-1 truncate text-sm font-semibold">Cari di grup ini</p>
            <button type="button" class="nc-sidebar-icon" data-close-search="1" aria-label="Tutup pencarian" title="Tutup pencarian">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
            </button>
        </div>
        <p class="nc-search-panel-status" data-search-panel-status="1" aria-live="polite">Ketik minimal 2 huruf.</p>
        <div class="nc-search-panel-list nc-scroll" data-search-panel-list="1"></div>
    </section>

    <nav class="nc-sidebar-list nc-scroll" data-group-list data-viewer-id="{{ $user->id }}">
        @forelse($rows as $row)
            @php($isActive = $row['group']->id === $activeGroupId)
            @php($unreadLabel = $row['unread'] > 99 ? '99+' : $row['unread'])
            <a href="{{ route('chat.show', $row['group']) }}"
               class="nc-sidebar-item {{ $isActive ? 'is-active' : '' }}"
               title="{{ $row['group']->name }}"
               data-group-card="1" data-group-search="{{ $row['group']->name }} {{ $row['group']->share_id }}"
               data-group-row="{{ $row['group']->id }}" data-activity-at="{{ $row['activity_at'] }}" data-draft-at="{{ $row['draft_at'] }}" data-unread="{{ $isActive ? 0 : $row['unread'] }}" @if($isActive) data-row-active @endif
               @if($isActive) aria-current="page" @endif>
                <span class="nc-sidebar-avatar">
                    <x-group-photo :group="$row['group']" />
                    <span class="nc-sidebar-dot" data-row-dot @if(! ($row['unread'] > 0 && ! $isActive)) hidden @endif>{{ $unreadLabel }}</span>
                </span>
                <span class="nc-sidebar-label min-w-0 flex-1">
                    <span class="flex items-baseline gap-2">
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $row['group']->name }}</span>
                        <span class="nc-sidebar-time shrink-0 text-[11px]" data-row-time>{{ $row['time'] }}</span>
                    </span>
                    <span class="mt-0.5 flex items-center gap-2">
                        @include('groups.partials.row-preview', ['row' => $row, 'class' => 'nc-sidebar-preview min-w-0 flex-1 truncate text-[13px]'])
                        <span class="nc-sidebar-badge" data-row-badge @if(! ($row['unread'] > 0 && ! $isActive)) hidden @endif>{{ $unreadLabel }}</span>
                    </span>
                </span>
            </a>
        @empty
            <div class="nc-sidebar-label px-6 py-10 text-center text-sm text-slate-500">
                <p class="font-semibold text-slate-700">Belum ada grup</p>
                <p class="mt-1 text-xs">Buat grup atau gabung dengan kode grup.</p>
                <a href="{{ route('groups.create') }}" class="btn-primary mt-4 inline-flex text-xs">+ Buat grup</a>
                {{-- Kebanyakan orang baru datang karena diundang: kolom kode grup langsung terlihat. --}}
                <form method="GET" action="" class="mt-4 flex items-center gap-2 text-left" data-join-by-id-form="{{ url('/join') }}">
                    <input type="text" name="share_id" placeholder="Kode grup" required autocomplete="off" aria-label="Kode grup" class="input-field min-w-0 flex-1 py-2" data-join-by-id-input="1" />
                    <button type="submit" class="btn-secondary shrink-0 px-3 py-2 text-xs">Gabung</button>
                </form>
            </div>
        @endforelse
        <p class="hidden px-6 py-6 text-center text-xs text-slate-500" data-group-search-empty="1">Grup tidak ditemukan.</p>
    </nav>

    <div class="nc-sidebar-foot">
        <a href="{{ route('profile.account') }}" class="nc-sidebar-profile {{ request()->routeIs('profile.account') ? 'bg-slate-100' : '' }}" title="Profil">
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-slate-200" referrerpolicy="no-referrer" draggable="false" />
            @else
                <span class="nc-sidebar-avatar h-9 w-9 text-xs">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
            @endif
            <span class="nc-sidebar-label min-w-0">
                <span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                <span class="block truncate text-[11px] text-slate-500">{{ $user->email }}</span>
            </span>
        </a>
        <a href="{{ route('profile.show') }}" class="nc-sidebar-icon {{ request()->routeIs('profile.show') ? 'bg-slate-100 text-slate-900' : '' }}" title="Pengaturan akun" aria-label="Pengaturan akun">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.22.7.22 1.07"/></svg>
        </a>
    </div>

    {{-- Tepi kanan: geser ke kiri untuk menutup, ke kanan untuk membuka. --}}
    <div class="nc-sidebar-resizer" data-sidebar-resizer="1" role="separator" aria-orientation="vertical" aria-label="Geser untuk menutup atau membuka panel" title="Geser untuk menutup/membuka"></div>
</aside>
