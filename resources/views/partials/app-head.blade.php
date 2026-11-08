{{-- Isi <head> bersama layouts.app dan layouts.admin: tema tanpa kedip, CSP nonce, font, Vite. --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="auth-user-id" content="{{ auth()->id() }}">
        <meta name="auth-user-name" content="{{ auth()->user()->name }}">
        <meta name="auth-user-avatar" content="{{ auth()->user()->avatar_url }}">
        <meta name="auth-user-timezone" content="{{ auth()->user()->timezone }}" data-timezone-url="{{ route('profile.timezone.update') }}" data-timezone-mode="{{ auth()->user()->timezone_mode }}">
        <meta name="auth-user-notify-sound" content="{{ auth()->user()->notify_sound ? '1' : '0' }}">
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
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">try { if (localStorage.getItem('nc-sidebar') === 'collapsed') document.documentElement.classList.add('nc-sidebar-collapsed'); if (localStorage.getItem('nc-info-side') === 'open') document.documentElement.classList.add('nc-info-open'); } catch (e) {}</script>
    @auth
        {{-- Page cache: halaman aplikasi di-prefetch saat tautannya disentuh/di-hover (Chrome, Edge), jadi
             pindah halaman terasa instan. Hanya halaman GET tanpa efek samping; unduhan, lampiran, login,
             dan pembayaran dikecualikan. Halaman hasil prefetch menyusulkan pesan terbaru (chat/realtime.js). --}}
        <script type="speculationrules" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        {"prefetch": [{"where": {"and": [
            {"href_matches": ["/groups", "/groups/*/chat", "/groups/*/settings", "/groups/*/settings/ai-persona", "/groups/*/settings/history-export", "/groups/*/settings/transactions", "/profile", "/profile/*"]},
            {"not": {"href_matches": ["/profile/*/download*", "/groups/payment-callback*"]}},
            {"not": {"selector_matches": "[download], [target=_blank], [data-no-prefetch], form a[href^='#']"}}
        ]}, "eagerness": "moderate"}]}
        </script>
    @endauth
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
