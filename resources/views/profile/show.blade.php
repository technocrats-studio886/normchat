@extends('layouts.app', ['title' => 'Akun - Normi Chat'])

@section('content')
    @php
        $isSuperadmin = $user->isSuperadmin();
        $accents = \App\Support\Themes::ACCENTS;
        $icons = [
            'profile' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'bell' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            'device' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
            'storage' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375',
            'login' => 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
            'payment' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            'feedback' => 'M8 9h8M8 13h5M5 4h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-5l-4 4v-4H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z',
        ];
    @endphp
    <section class="page-shell">
        <h1 class="font-display text-xl font-extrabold text-ink">Akun</h1>

        <a href="{{ route('profile.account') }}" class="nc-settings-card">
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="" class="h-14 w-14 rounded-full object-cover ring-2 ring-primary-line" referrerpolicy="no-referrer" />
            @else
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-soft text-xl font-bold text-primary-ink">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            @endif
            <span class="min-w-0 flex-1">
                <span class="block truncate text-base font-bold">{{ $user->name }}</span>
                <span class="block truncate text-xs text-ink-soft">{{ '@' . $username }} · {{ $user->email }}</span>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-ink-soft" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </a>

        <p class="nc-settings-label">Pengaturan</p>
        <div class="nc-settings-group">
            <x-settings-row data-theme-open="1" title="Tampilan" :subtitle="\App\Support\Themes::label($theme)" subtitle-data="data-theme-current-label">
                <x-slot:leading>
                    <span class="h-10 w-10 shrink-0 rounded-full" style="background: {{ $accents[$theme['accent']]['light'] }};" data-theme-current-swatch="1" aria-hidden="true"></span>
                </x-slot:leading>
            </x-settings-row>
            <x-settings-row :href="route('profile.timezone')" :icon="$icons['clock']" title="Zona waktu" :subtitle="$timezoneLabel" />
            @unless($isSuperadmin)
                <x-settings-row :href="route('profile.notifications')" :icon="$icons['bell']" title="Notifikasi" :subtitle="$notifyLabel" />
            @endunless
            <x-settings-row :href="route('profile.sessions')" :icon="$icons['device']" title="Perangkat & sesi" :subtitle="$sessionCount . ' perangkat masuk'" />
        </div>

        <p class="nc-settings-label">Data & riwayat</p>
        <div class="nc-settings-group">
            @unless($isSuperadmin)
                <x-settings-row :href="route('profile.storage')" :icon="$icons['storage']" title="Penyimpanan" :subtitle="'Terpakai ' . $storage['total_human']" />
            @endunless
            <x-settings-row :href="route('profile.login-history')" :icon="$icons['login']" title="Riwayat Masuk" :subtitle="$loginCount . ' aktivitas'" />
            @unless($isSuperadmin)
                <x-settings-row :href="route('profile.transactions')" :icon="$icons['payment']" title="Riwayat Pembayaran" :subtitle="$transactionCount . ' pembayaran'" />
            @endunless
        </div>

        <p class="nc-settings-label">Bantuan</p>
        <div class="nc-settings-group">
            {{-- Laporan bug & masukan dikumpulkan di Canny. --}}
            <x-settings-row href="https://normi.canny.io/bug-report" external :icon="$icons['feedback']" title="Laporkan masalah" subtitle="Kirim laporan bug atau masukan ke tim Normi Chat" />
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
                    <div class="nc-msg nc-msg--other is-run-start" style="--person: var(--nc-person-1)">
                        <span class="nc-avatar"><span class="nc-avatar-fallback"><svg viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"/></svg></span></span>
                        <div class="nc-msg-stack"><div class="nc-bubble bubble-other"><p class="nc-msg-name">Sari</p>Ada yang mau belajar bareng nanti malam?<span class="nc-inline-time">19.40</span></div></div>
                    </div>
                    <div class="nc-msg nc-msg--mine is-run-start">
                        <div class="nc-msg-stack"><div class="nc-bubble bubble-mine">Aku ikut!<span class="nc-inline-time">19.41</span></div></div>
                    </div>
                    <div class="nc-msg nc-msg--ai is-run-start" style="--person: var(--nc-ai-name)">
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

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl border border-danger-line bg-danger-soft px-4 py-3.5 text-left">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-danger-ink" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
                </span>
                <span class="flex-1 text-sm font-semibold text-danger-ink">Keluar</span>
            </button>
        </form>
    </section>
@endsection
