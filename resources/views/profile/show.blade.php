@extends('layouts.app', ['title' => 'Akun - Normi Chat'])

@section('content')
    <section class="page-shell">
        <h1 class="font-display text-xl font-extrabold text-slate-900">Akun</h1>
        <p class="mt-1 text-sm text-slate-500">Profil, penyimpanan, dan riwayatmu.</p>

        <a href="{{ route('profile.account') }}" class="mt-4 flex items-center gap-4 rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-4 shadow-sm transition hover:bg-slate-50">
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-14 w-14 rounded-full object-cover ring-2 ring-primary-line" referrerpolicy="no-referrer" />
            @else
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-soft text-xl font-bold text-primary-ink">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Akun</p>
                <h2 class="truncate text-base font-bold text-slate-900">{{ $user->name }}</h2>
                <p class="truncate text-xs text-slate-500">{{ '@' . $username }}</p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </a>

        <div class="mt-4 overflow-hidden rounded-2xl border border-[var(--nc-line)] bg-white shadow-sm">
            <a href="{{ route('profile.account') }}" class="flex items-center gap-3 border-b border-slate-100 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Profil</span>
                    <span class="block truncate text-[11px] text-slate-500">Nama dan email</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>

            <a href="{{ route('profile.storage') }}" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5A2.25 2.25 0 0 1 5.25 5.25h13.5A2.25 2.25 0 0 1 21 7.5v9A2.25 2.25 0 0 1 18.75 18.75H5.25A2.25 2.25 0 0 1 3 16.5v-9Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75h18" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Penyimpanan</span>
                    <span class="block truncate text-[11px] text-slate-500">Terpakai {{ $storage['total_human'] }}</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-[var(--nc-line)] bg-white shadow-sm">
            <a href="{{ route('profile.login-history') }}" class="flex items-center gap-3 border-b border-slate-100 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Riwayat Masuk</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $loginCount }} aktivitas</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>

            <a href="{{ route('profile.transactions') }}" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Riwayat Pembayaran</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $transactionCount }} pembayaran</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>

        @php($accents = \App\Support\Themes::ACCENTS)
        <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
            <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50" data-theme-open="1">
                <span class="h-10 w-10 shrink-0 rounded-full" style="background: {{ $accents[$theme['accent']]['light'] }};" data-theme-current-swatch="1"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Tema</span>
                    <span class="block truncate text-[11px] text-slate-500" data-theme-current-label="1">{{ \App\Support\Themes::label($theme) }}</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>

        {{-- Pemilih tema (resources/js/ui/theme-picker.js): pilihan hanya mengubah pratinjau di panel; halaman berubah setelah Terapkan. --}}
        <div class="fixed inset-0 z-[80] hidden items-end justify-center bg-navy-950/45 lg:left-[var(--nc-sidebar-w)]" data-theme-panel="1" data-theme-url="{{ route('profile.theme.update') }}" data-theme-mode="{{ $theme['mode'] }}" data-theme-accent="{{ $theme['accent'] }}" role="dialog" aria-modal="true" aria-labelledby="nc-theme-panel-title">
            <div class="w-full max-w-md rounded-t-3xl bg-white px-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] pt-3 shadow-[0_-12px_40px_-12px_rgba(0,0,0,0.35)]">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100" data-theme-cancel="1">Batal</button>
                    <p id="nc-theme-panel-title" class="text-sm font-bold text-slate-900">Tema</p>
                    <button type="button" class="btn-primary px-4 py-2 disabled:opacity-50" data-theme-apply="1">Terapkan</button>
                </div>

                <div class="nc-theme-preview mt-3" data-theme-preview="1" data-mode="light" data-accent="{{ $theme['accent'] }}" aria-hidden="true">
                    <div class="nc-msg nc-msg--other is-run-start is-run-end" style="--person: var(--nc-person-1)">
                        <span class="nc-avatar"><span class="nc-avatar-fallback"><svg viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"/></svg></span></span>
                        <div class="nc-msg-stack"><div class="nc-bubble bubble-other"><p class="nc-msg-name">Sari</p>Ada yang mau belajar bareng nanti malam?<span class="nc-inline-time">19.40</span></div></div>
                    </div>
                    <div class="nc-msg nc-msg--mine is-run-start is-run-end">
                        <div class="nc-msg-stack"><div class="nc-bubble bubble-mine">Aku ikut!<span class="nc-inline-time">19.41</span></div></div>
                    </div>
                    <div class="nc-msg nc-msg--ai is-run-start is-run-end" style="--person: var(--nc-ai-name)">
                        <span class="nc-avatar nc-avatar--ai"><img src="/icons/icon-192.png" alt="" class="nc-avatar-img"></span>
                        <div class="nc-msg-stack"><div class="nc-bubble bubble-ai"><p class="nc-msg-name">Normi AI</p>Boleh. Mulai dari normalisasi 1NF dulu ya.<span class="nc-inline-time">19.41</span></div></div>
                    </div>
                </div>

                <p class="mt-4 text-xs font-semibold text-slate-500">Mode</p>
                <div class="nc-segment mt-1.5" role="radiogroup" aria-label="Mode tampilan">
                    @foreach (\App\Support\Themes::MODES as $modeId => $modeLabel)
                        <button type="button" role="radio" aria-checked="{{ $modeId === $theme['mode'] ? 'true' : 'false' }}" data-theme-mode-option="{{ $modeId }}">{{ $modeLabel }}</button>
                    @endforeach
                </div>

                <p class="mt-4 text-xs font-semibold text-slate-500">Warna aksen</p>
                <div class="mt-1.5 flex flex-wrap gap-3" role="radiogroup" aria-label="Warna aksen">
                    @foreach ($accents as $accentId => $accent)
                        <button type="button" role="radio" class="nc-accent-option" aria-checked="{{ $accentId === $theme['accent'] ? 'true' : 'false' }}" aria-label="{{ $accent['label'] }}" title="{{ $accent['label'] }}" data-theme-accent-option="{{ $accentId }}" style="--swatch: {{ $accent['light'] }}"></button>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] text-slate-500">Berlaku di seluruh aplikasi, di semua perangkatmu.</p>
            </div>
        </div>

        {{-- Laporan bug & masukan dari anggota dikumpulkan di Canny. --}}
        <a href="https://normi.canny.io/bug-report" target="_blank" rel="noopener noreferrer" class="mt-4 flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3.5 shadow-sm transition hover:bg-slate-50">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0 1 12 12.75Zm0 0c2.883 0 5.647.508 8.207 1.44a23.91 23.91 0 0 1-1.152 6.06M12 12.75c-2.883 0-5.647.508-8.208 1.44.125 2.104.52 4.136 1.153 6.06M12 12.75a2.25 2.25 0 0 0 2.248-2.354M12 12.75a2.25 2.25 0 0 1-2.248-2.354M12 8.25c.995 0 1.971-.08 2.922-.236.403-.066.74-.358.795-.762a3.778 3.778 0 0 0-.399-2.25M12 8.25c-.995 0-1.97-.08-2.922-.236-.402-.066-.74-.358-.795-.762a3.734 3.734 0 0 1 .4-2.253M12 8.25a2.25 2.25 0 0 0-2.248 2.146M12 8.25a2.25 2.25 0 0 1 2.248 2.146M8.683 5a6.032 6.032 0 0 1-1.155-1.002c.07-.63.27-1.222.574-1.747m.581 2.749A3.75 3.75 0 0 1 15.318 5m0 0c.427-.283.815-.62 1.155-.999a4.471 4.471 0 0 0-.575-1.752M4.921 6a24.048 24.048 0 0 0-.392 3.314c1.668.546 3.416.914 5.223 1.082M19.08 6c.205 1.08.337 2.187.392 3.314a23.882 23.882 0 0 1-5.223 1.082"/></svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-900">Laporkan masalah</span>
                <span class="block truncate text-[11px] text-slate-500">Kirim laporan bug atau masukan ke tim Normi Chat</span>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
        </a>

        @if(auth()->user()?->isSuperadmin())
            <a href="{{ route('superadmin.index') }}" class="mt-4 flex items-center gap-3 rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-3.5 shadow-sm transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Admin Normi Chat</span>
                    <span class="block truncate text-[11px] text-slate-500">Model AI dan harga</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl border border-danger-line bg-danger-soft px-4 py-3.5 text-left transition hover:bg-danger-line/40">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-danger-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                </span>
                <span class="flex-1 text-sm font-semibold text-danger-ink">Keluar</span>
            </button>
        </form>
    </section>
@endsection
