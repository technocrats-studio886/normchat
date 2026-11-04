<!DOCTYPE html>
{{--
    Layout superadmin: konsol lebar penuh. Desktop: sidebar admin di kiri. HP: bilah atas + laci menu.
    Halaman mengisi @section('heading'), opsional @section('actions') (tombol di kanan judul) dan @section('content').
--}}
@php
    $uiTheme = \App\Support\Themes::for(auth()->user());
    $uiMode = $uiTheme['mode'] === 'dark' ? 'dark' : 'light';
    $openAlerts = \App\Models\AdminAlert::query()->open()->get();
    $dangerAlert = $openAlerts->firstWhere('level', 'danger');
    $heading = trim($__env->yieldContent('heading')) ?: 'Admin';
@endphp
<html lang="id" data-theme-mode="{{ $uiTheme['mode'] }}" data-mode="{{ $uiMode }}" data-accent="{{ $uiTheme['accent'] }}">
<head>
    @include('partials.app-head', ['title' => $heading . ' · Admin Normi Chat'])
</head>
<body class="nc-admin min-h-screen antialiased">
    <x-admin-sidebar />

    <div class="nc-admin-main">
        <header class="nc-admin-top">
            <button type="button" class="nc-admin-menu-btn" data-admin-menu-open aria-label="Buka menu admin" aria-haspopup="dialog">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="min-w-0 flex-1">
                @hasSection('back')
                    <a href="@yield('back')" class="nc-admin-back">← Kembali</a>
                @endif
                <h1 class="nc-admin-title">{{ $heading }}</h1>
            </div>
            @hasSection('actions')
                <div class="nc-admin-actions">@yield('actions')</div>
            @endif
        </header>

        @if($openAlerts->isNotEmpty() && ! request()->routeIs('superadmin.openai'))
            <a href="{{ route('superadmin.openai') }}#peringatan" @class(['nc-admin-alertbar', 'is-danger' => $dangerAlert])>
                <span class="nc-admin-alertbar-dot" aria-hidden="true"></span>
                <span class="min-w-0 flex-1 truncate">{{ ($dangerAlert ?? $openAlerts->first())->title }}</span>
                @if($openAlerts->count() > 1)
                    <span class="shrink-0">+{{ $openAlerts->count() - 1 }} lainnya</span>
                @endif
                <span class="shrink-0 font-semibold">Lihat</span>
            </a>
        @endif

        @if(session('success'))
            <p class="nc-admin-flash" role="status">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <p class="nc-admin-flash is-error" role="alert">{{ $errors->first() }}</p>
        @endif

        <main class="nc-admin-content">
            @yield('content')
        </main>
    </div>

    {{-- Laci menu HP --}}
    <dialog class="nc-admin-drawer" data-admin-menu aria-label="Menu admin">
        <div class="nc-admin-drawer-head">
            <span class="font-semibold">Menu admin</span>
            <button type="button" class="nc-admin-menu-btn" data-admin-menu-close aria-label="Tutup menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        @include('superadmin._menu')
        <a href="{{ route('profile.show') }}" class="nc-admin-account">
            <span class="nc-admin-account-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                <span class="block truncate text-xs text-ink-soft">Akun & pengaturan</span>
            </span>
        </a>
    </dialog>
</body>
</html>
