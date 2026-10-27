@extends('layouts.app', ['title' => 'Akun - Normi Chat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <h1 class="font-display text-xl font-extrabold text-slate-900">Akun</h1>
        <p class="mt-1 text-sm text-slate-500">Profil, penyimpanan, dan riwayatmu.</p>

        <a href="{{ route('profile.account') }}" class="mt-4 flex items-center gap-4 rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-4 shadow-sm transition hover:bg-slate-50">
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-14 w-14 rounded-full object-cover ring-2 ring-navy-100" referrerpolicy="no-referrer" />
            @else
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-navy-100 text-xl font-bold text-navy-600">
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
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Profil</span>
                    <span class="block truncate text-[11px] text-slate-500">Nama dan email</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>

            <a href="{{ route('profile.storage') }}" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-coral-50 text-coral-600">
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
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Riwayat Masuk</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $loginCount }} aktivitas</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>

            <a href="{{ route('profile.transactions') }}" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Riwayat Pembayaran</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $transactionCount }} pembayaran</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>

        @php($themes = \App\Support\Themes::OPTIONS)
        <div class="mt-4 overflow-hidden rounded-2xl border border-[var(--nc-line)] bg-white shadow-sm">
            <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50" data-theme-open="1">
                <span class="h-10 w-10 shrink-0 rounded-xl border border-[var(--nc-line)]" style="background: {{ $themes[$theme]['swatch'] }};" data-theme-current-swatch="1"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">Tema</span>
                    <span class="block truncate text-[11px] text-slate-500" data-theme-current-label="1">{{ $themes[$theme]['label'] }}</span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>

        {{-- Pemilih tema (resources/js/ui/theme-picker.js): halaman di belakang panel ikut berubah sebagai pratinjau. --}}
        <div class="fixed inset-x-0 bottom-0 z-[80] hidden lg:left-[var(--nc-sidebar-w)]" data-theme-panel="1" data-theme-url="{{ route('profile.theme.update') }}" role="dialog" aria-modal="true" aria-labelledby="nc-theme-panel-title">
            <div class="mx-auto w-full max-w-md rounded-t-3xl border border-b-0 border-[var(--nc-line)] bg-white px-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] pt-3 shadow-[0_-12px_40px_-12px_rgba(0,0,0,0.35)]">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100" data-theme-cancel="1">Batal</button>
                    <p id="nc-theme-panel-title" class="text-sm font-bold text-slate-900">Tema</p>
                    <button type="button" class="btn-primary px-4 py-2 disabled:opacity-50" data-theme-apply="1">Terapkan</button>
                </div>

                <div class="mt-3 space-y-2 rounded-2xl p-3 nc-theme-sample" aria-hidden="true">
                    <div class="max-w-[80%]">
                        <p class="nc-chat-sender mb-0.5 text-[10px]">Teman</p>
                        <div class="bubble-other text-xs">Ada yang mau belajar bareng?</div>
                    </div>
                    <div class="max-w-[80%]">
                        <p class="nc-chat-sender nc-chat-sender--ai mb-0.5 text-[10px] font-semibold">Normi AI</p>
                        <div class="bubble-ai text-xs">Boleh! Mulai dari bab mana?</div>
                    </div>
                    <div class="flex justify-end">
                        <div class="bubble-mine max-w-[80%] text-xs">Aku ikut!</div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2.5" role="radiogroup" aria-label="Pilihan tema">
                    @foreach ($themes as $themeId => $option)
                        <button
                            type="button"
                            role="radio"
                            aria-checked="{{ $themeId === $theme ? 'true' : 'false' }}"
                            class="nc-theme-option overflow-hidden rounded-xl border border-[var(--nc-line)] text-left text-xs font-semibold text-slate-700 transition"
                            data-theme-option="{{ $themeId }}"
                            data-theme-label="{{ $option['label'] }}"
                            data-theme-swatch="{{ $option['swatch'] }}"
                            data-theme-browser="{{ $option['browser'] }}"
                        >
                            <span class="block h-11 w-full" style="background: {{ $option['swatch'] }};"></span>
                            <span class="block px-2 py-1.5">{{ $option['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-slate-500">Berlaku di seluruh aplikasi, di semua perangkatmu.</p>
            </div>
        </div>

        @if(auth()->user()?->isSuperadmin())
            <a href="{{ route('superadmin.index') }}" class="mt-4 flex items-center gap-3 rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-3.5 shadow-sm transition hover:bg-slate-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-coral-50 text-coral-600">
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
            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl border border-coral-100 bg-coral-50 px-4 py-3.5 text-left transition hover:bg-coral-100">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-coral-100 text-coral-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                </span>
                <span class="flex-1 text-sm font-semibold text-coral-500">Keluar</span>
            </button>
        </form>
    </section>
@endsection
