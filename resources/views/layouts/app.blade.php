<!DOCTYPE html>
@php
    // Tema tampilan user (App\Support\Themes); dipasang server-side agar tidak berkedip.
    $uiTheme = \App\Support\Themes::resolve(auth()->user()?->theme);
@endphp
<html lang="id" data-theme="{{ $uiTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="auth-user-id" content="{{ auth()->id() }}">
        <meta name="auth-user-name" content="{{ auth()->user()->name }}">
    @endauth
    <meta name="theme-color" content="{{ \App\Support\Themes::OPTIONS[$uiTheme]['browser'] }}">
    @include('partials.brand-head')
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <title>{{ $title ?? 'Normi Chat' }}</title>
    {{-- Status sidebar desktop dipasang sebelum render agar tidak berkedip (resources/js/ui/sidebar.js). --}}
    {{-- Semua <script> butuh nonce dari Content-Security-Policy (App\Http\Middleware\AddContentSecurityPolicy). --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">try { if (localStorage.getItem('nc-sidebar') === 'collapsed') document.documentElement.classList.add('nc-sidebar-collapsed'); } catch (e) {}</script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">mermaid.initialize({startOnLoad:false,theme:'neutral',securityLevel:'strict'});</script>
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
                    <div class="rounded-2xl border border-navy-200 bg-navy-50 px-4 py-3 text-sm font-medium text-navy-700 shadow-sm">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('info'))
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-coral-200 bg-coral-50 px-4 py-3 text-sm font-medium text-coral-700 shadow-sm">
                        {{ session('info') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-coral-200 bg-coral-50 px-4 py-3 text-sm text-coral-700 shadow-sm">
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
                $isCreate = request()->routeIs('groups.create');
                $isProfile = request()->routeIs('profile.*');
                $hideBottomNav = request()->routeIs('chat.*')
                    || request()->routeIs('groups.create')
                    || request()->routeIs('settings.*')
                    || request()->routeIs('mailbox.*');
            @endphp
            @unless($hideBottomNav)
                <nav class="nc-bottom-nav lg:hidden">
                    <div class="nc-bottom-nav-inner">
                        <a href="{{ route('groups.index') }}" class="{{ $isHome ? 'nc-nav-item-active' : 'nc-nav-item' }}" aria-label="Beranda">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
                            Beranda
                        </a>
                        <a href="{{ route('groups.create') }}" class="{{ $isCreate ? 'nc-nav-item-active' : 'nc-nav-item' }}" aria-label="Buat Grup">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
                            Buat
                        </a>
                        <a href="{{ route('profile.show') }}" class="{{ $isProfile ? 'nc-nav-item-active' : 'nc-nav-item' }}" aria-label="Pengaturan">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
                            Akun
                        </a>
                    </div>
                </nav>
            @endunless
        @endauth
    </div>
    </div>
</body>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            registrations.forEach((registration) => registration.unregister());
        }).catch(() => {});
    });
}

window.onerror = function(message) {
    if (typeof message === 'string' && message.indexOf('removeChild') !== -1) {
        return true;
    }
};

window.addEventListener('unhandledrejection', function(event) {
    var reason = event.reason;
    if (reason && typeof reason.message === 'string' && reason.message.indexOf('removeChild') !== -1) {
        event.preventDefault();
    }
});
</script>
</html>
