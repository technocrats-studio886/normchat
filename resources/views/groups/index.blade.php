@extends('layouts.app', ['title' => 'Beranda - Normi Chat'])

@section('content')
    {{-- Desktop: daftar grup ada di sidebar; panel kanan kosong sampai sebuah grup dipilih. --}}
    <div class="hidden min-h-screen items-center justify-center lg:flex">
        <span class="nc-empty-pill">Pilih grup untuk mulai chat</span>
    </div>

    <section class="page-shell pb-44 lg:hidden" data-group-search-scope="1">
        {{-- Header --}}
        <div class="flex items-start justify-between">
            <a href="{{ route('profile.account') }}" class="flex items-center gap-3 rounded-full transition active:opacity-70" aria-label="Profil">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="" class="h-11 w-11 rounded-full object-cover ring-1 ring-slate-200" referrerpolicy="no-referrer" />
                @else
                    <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white" style="background: var(--nc-primary);">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <p class="text-xs text-slate-500">Halo,</p>
                    <h1 class="font-display text-lg font-extrabold text-slate-900">{{ Str::words(Auth::user()->name, 1, '') }}</h1>
                </div>
            </a>
            @if(config('services.interdotz.enabled'))
            <a href="{{ route('mailbox.inbox') }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 transition hover:bg-slate-50" aria-label="Buka mailbox">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
            </a>
            @endif
        </div>

        {{-- Ajakan menyalakan notifikasi (resources/js/push.js). --}}
        <div data-push-prompt-slot></div>

        <h2 class="nc-section-title mt-6">Grup</h2>

        <form method="GET" action="" class="mt-3 flex items-center gap-2" data-join-by-id-form="{{ url('/join') }}">
            <input type="text" name="share_id" placeholder="Punya kode grup? Masukkan di sini" required autocomplete="off" class="nc-input min-w-0 flex-1" aria-label="Kode grup" data-join-by-id-input="1">
            <button type="submit" class="btn-secondary shrink-0 px-4 py-2">Gabung</button>
        </form>

        @if(count($rows) > 3)
            <input type="search" placeholder="Cari grup" class="nc-input mt-3 w-full" aria-label="Cari grup" data-group-search-input="1" autocomplete="off">
        @endif

        <div class="nc-list" data-group-list data-viewer-id="{{ auth()->id() }}">
            @forelse ($rows as $row)
                @php($group = $row['group'])
                @php($unreadLabel = $row['unread'] > 99 ? '99+' : $row['unread'])
                <a href="{{ route('chat.show', $group) }}" class="nc-list-row hover:bg-surface-2" data-group-card="1" data-group-search="{{ $group->name }} {{ $group->share_id }}"
                   data-group-row="{{ $group->id }}" data-activity-at="{{ $row['activity_at'] }}" data-draft-at="{{ $row['draft_at'] }}" data-unread="{{ $row['unread'] }}">
                    <span class="nc-sidebar-avatar h-12 w-12 text-sm"><x-group-photo :group="$group" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline gap-2">
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $group->name }}</span>
                            <span class="nc-sidebar-time shrink-0 text-[11px]" data-row-time>{{ $row['time'] }}</span>
                        </span>
                        <span class="mt-0.5 flex items-center gap-2">
                            @include('groups.partials.row-preview', ['row' => $row, 'class' => 'nc-sidebar-preview min-w-0 flex-1 truncate text-[13px]'])
                            <span class="nc-sidebar-badge" data-row-badge @unless($row['unread'] > 0) hidden @endunless>{{ $unreadLabel }}</span>
                        </span>
                        @if($group->isFrozen())<span class="block text-xs text-ink-soft">Dibekukan</span>@endif
                    </span>
                </a>
            @empty
                <p class="nc-list-empty">Belum ada grup. Ketuk tombol + untuk membuat grup, atau gabung dengan kode dari temanmu.</p>
            @endforelse
            <p class="nc-list-empty hidden" data-group-search-empty="1">Grup tidak ditemukan.</p>
        </div>

        <a href="{{ route('groups.create') }}" class="nc-fab" aria-label="Buat grup" title="Buat grup">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        </a>
    </section>
@endsection
