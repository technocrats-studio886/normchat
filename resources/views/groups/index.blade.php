@extends('layouts.app', ['title' => 'Dashboard - Normchat'])

@section('content')
    <section class="page-shell">
        {{-- Header --}}
        <div class="flex items-start justify-between">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-full transition active:opacity-70" aria-label="Buka profil">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="" class="h-11 w-11 rounded-full object-cover ring-1 ring-slate-200" referrerpolicy="no-referrer" />
                @else
                    <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white" style="background: var(--nc-primary);">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <p class="text-xs text-slate-500">Selamat datang</p>
                    <h1 class="font-display text-lg font-extrabold text-slate-900">{{ Str::words(Auth::user()->name, 1, '') }}</h1>
                </div>
            </a>
            <a href="{{ route('profile.security') }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 transition hover:bg-slate-50" aria-label="Settings profil">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </a>
        </div>

        {{-- Gabung Grup (paling atas) --}}
        <div class="mt-5">
            <div class="card-soft">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8h2a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h2m8-4H8l-1 4h10l-1-4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v4m-2-2h4"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Gabung Grup</p>
                        <p class="text-[11px] text-slate-500">Masukkan ID grup untuk bergabung</p>
                    </div>
                </div>
                <form method="GET" action="" class="mt-3 flex items-center gap-2" data-join-by-id-form="1">
                    <input
                        type="text"
                        name="share_id"
                        placeholder="Masukkan ID grup..."
                        required
                        autocomplete="off"
                        class="input-field flex-1"
                        data-join-by-id-input="1"
                    />
                    <button type="submit" class="btn-primary">Gabung</button>
                </form>
            </div>
        </div>

        {{-- Section title --}}
        <div class="mt-6 flex items-center justify-between">
            <h2 class="section-title">Grup Kamu</h2>
            <a href="{{ route('groups.create') }}" class="text-[11px] font-bold text-rose-600 hover:text-rose-700">+ Buat baru</a>
        </div>

        {{-- Group search --}}
        @if(count($groups) > 0)
            <div class="mt-2 flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                <input type="text" placeholder="Cari grup..." class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400" data-group-search-input="1" autocomplete="off" />
            </div>
        @endif

        {{-- Group list --}}
        <div class="mt-2 space-y-2.5">
            @forelse ($groups as $group)
                @php
                    $activeMembers = $group->members->where('status', 'active');
                    $ownerInMembers = $activeMembers->contains('user_id', $group->owner_id);
                    $memberCount = $activeMembers->count() + ($ownerInMembers ? 0 : 1);
                    $initial = strtoupper(substr($group->name, 0, 1));
                @endphp
                <div class="card-soft overflow-hidden p-0" data-group-card="1" data-group-search="{{ $group->name }} {{ $group->share_id }}">
                    <a href="{{ route('chat.show', $group) }}" class="flex items-center gap-3 px-4 py-3 transition active:bg-slate-50">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white" style="background: var(--nc-primary);">
                            {{ $initial }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="truncate text-sm font-bold text-slate-900">{{ $group->name }}</h3>
                            </div>
                            <p class="mt-0.5 flex items-center gap-1.5 text-[11px] text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c1.2-3.2 4-5 8-5s6.8 1.8 8 5"/></svg>
                                {{ $memberCount }} member
                            </p>
                        </div>
                    </a>
                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-[11px] text-slate-500">
                        <span class="truncate">ID: <span class="font-mono font-semibold text-slate-600">{{ $group->share_id }}</span></span>
                        <div class="flex items-center gap-3">
                            <button type="button" class="font-semibold text-rose-600 hover:text-rose-700" data-copy-share-id="{{ $group->share_id }}">Copy</button>
                            <button
                                type="button"
                                class="font-semibold text-emerald-600 hover:text-emerald-700"
                                data-share-group-url="{{ route('groups.join', $group->share_id) }}"
                                data-share-group-name="{{ $group->name }}"
                            >
                                Share
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card-soft p-6 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 text-rose-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h7m-7 4h5M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-6l-4 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Belum ada grup</p>
                    <p class="mt-1 text-xs text-slate-500">Buat grup pertamamu untuk memulai.</p>
                </div>
            @endforelse
            <div class="hidden rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-center text-xs text-slate-500" data-group-search-empty="1">
                Tidak ada grup yang cocok dengan pencarian.
            </div>
        </div>

        {{-- Create CTA --}}
        <a href="{{ route('groups.create') }}" class="btn-cta mt-6">
            + Buat Group Chat Baru
        </a>

        <script>
            (function () {
                const form = document.querySelector('[data-join-by-id-form]');
                const input = document.querySelector('[data-join-by-id-input]');
                if (!form || !input) return;
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const id = String(input.value || '').trim();
                    if (!id) return;
                    window.location.href = '{{ url('/join') }}/' + encodeURIComponent(id);
                });
            })();
        </script>
    </section>
@endsection
