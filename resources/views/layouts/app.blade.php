<!DOCTYPE html>
@php
    // Tema tampilan user (App\Support\Themes); dipasang server-side agar tidak berkedip.
    $uiTheme = \App\Support\Themes::for(auth()->user());
    $uiMode = $uiTheme['mode'] === 'dark' ? 'dark' : 'light';
@endphp
<html lang="id" data-theme-mode="{{ $uiTheme['mode'] }}" data-mode="{{ $uiMode }}" data-accent="{{ $uiTheme['accent'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="auth-user-id" content="{{ auth()->id() }}">
        <meta name="auth-user-name" content="{{ auth()->user()->name }}">
        <meta name="auth-user-timezone" content="{{ auth()->user()->timezone }}" data-timezone-url="{{ route('profile.timezone.update') }}">
    @endauth
    <meta name="theme-color" content="{{ \App\Support\Themes::BROWSER[$uiMode] }}">
    @include('partials.brand-head')
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <title>{{ $title ?? 'Normi Chat' }}</title>
    {{-- Status sidebar desktop dipasang sebelum render agar tidak berkedip (resources/js/ui/sidebar.js). --}}
    {{-- Semua <script> butuh nonce dari Content-Security-Policy (App\Http\Middleware\AddContentSecurityPolicy). --}}
    {{-- Mode "Ikuti sistem": pilih terang/gelap dari setelan perangkat sebelum render, dan ikuti perubahannya. --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">(() => { const html = document.documentElement; const query = matchMedia('(prefers-color-scheme: dark)'); const apply = () => { if (html.dataset.themeMode !== 'system') return; html.dataset.mode = query.matches ? 'dark' : 'light'; document.querySelector('meta[name="theme-color"]')?.setAttribute('content', query.matches ? '{{ \App\Support\Themes::BROWSER['dark'] }}' : '{{ \App\Support\Themes::BROWSER['light'] }}'); }; apply(); query.addEventListener('change', apply); })();</script>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">try { if (localStorage.getItem('nc-sidebar') === 'collapsed') document.documentElement.classList.add('nc-sidebar-collapsed'); } catch (e) {}</script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @php
            $viteEntries = ['resources/css/app.css'];
            if (trim($__env->yieldContent('disableAppJs')) !== '1') {
                $viteEntries[] = 'resources/js/app.js';
            }
        @endphp
        @vite($viteEntries)
    @else
        <style>
            body { margin: 0; font-family: Inter, sans-serif; }
            .hidden { display: none; }
            .google-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                width: 100%;
                border-radius: 9999px;
                background: #1E2E43;
                color: #fff;
                font-weight: 700;
                text-decoration: none;
            }
            .google-mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                border-radius: 9999px;
                background: #fff;
            }
        </style>
    @endif
</head>
<body class="normchat-bg min-h-screen antialiased">
    @auth
        <x-desktop-sidebar />
    @endauth
    {{-- Mobile: satu kolom max-w-md. Desktop (≥1024px): konten di panel kanan sidebar. --}}
    <div class="@auth nc-sidebar-push lg:pl-[var(--nc-sidebar-w)] @endauth">
    <div class="mx-auto min-h-screen w-full max-w-md lg:max-w-2xl">
        <main class="{{ auth()->check() ? 'pb-32 lg:pb-8' : 'pb-0' }}">
            @if (session('success'))
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-primary-line bg-primary-soft px-4 py-3 text-sm font-medium text-primary-ink shadow-sm">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('info'))
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-danger-line bg-danger-soft px-4 py-3 text-sm font-medium text-danger-ink shadow-sm">
                        {{ session('info') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-danger-line bg-danger-soft px-4 py-3 text-sm text-danger-ink shadow-sm">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        @auth
            @php
                $isHome = request()->routeIs('groups.index');
                $isProfile = request()->routeIs('profile.*');
                $hideBottomNav = request()->routeIs('chat.*')
                    || request()->routeIs('groups.create')
                    || request()->routeIs('settings.*')
                    || request()->routeIs('mailbox.*');
            @endphp
            @unless($hideBottomNav)
                <nav class="nc-bottom-nav lg:hidden">
                    <div class="nc-bottom-nav-inner">
                        @if(auth()->user()->isSuperadmin())
                            <a href="{{ route('superadmin.index') }}" class="{{ request()->routeIs('superadmin.*') ? 'nc-nav-item-active' : 'nc-nav-item' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h12a2.25 2.25 0 0 0 2.25-2.25V3M3.75 3h16.5M8.25 21l3.75-4.5L15.75 21"/></svg>
                                Admin
                            </a>
                        @else
                            <a href="{{ route('groups.index') }}" class="{{ $isHome ? 'nc-nav-item-active' : 'nc-nav-item' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
                                Beranda
                            </a>
                        @endif
                        <a href="{{ route('profile.show') }}" class="{{ $isProfile ? 'nc-nav-item-active' : 'nc-nav-item' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
                            Akun
                        </a>
                    </div>
                </nav>
            @endunless
        @endauth
    </div>
    </div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            registrations.forEach((registration) => registration.unregister());
        }).catch(() => {});
    });
}

</script>
</body>
</html>
