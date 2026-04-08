<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="auth-user-id" content="{{ auth()->id() }}">
        <meta name="auth-user-name" content="{{ auth()->user()->name }}">
    @endauth
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <title>{{ $title ?? 'Normchat' }}</title>
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
                background: #2563eb;
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
<body class="normchat-bg min-h-screen text-slate-900 antialiased">
    <div class="mx-auto min-h-screen w-full max-w-md bg-[#F7F7F7] shadow-xl shadow-slate-900/10">
        <main class="pb-24">
            @if (session('success'))
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('info'))
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        {{ session('info') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mx-4 pt-4">
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
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
                $currentGroup = $group ?? null;
                $chatTargetUrl = $currentGroup
                    ? route('chat.show', $currentGroup)
                    : route('chat.last');
                $isHome = request()->routeIs('groups.index');
                $isChat = request()->routeIs('chat.*');
                $isProfile = request()->routeIs('profile.*');
                $hideBottomNav = request()->routeIs('groups.create')
                    || request()->routeIs('subscription.pricing')
                    || request()->routeIs('subscription.payment.*')
                    || request()->routeIs('subscription.checkout')
                    || request()->routeIs('subscription.success')
                    || request()->routeIs('settings.*');
            @endphp
            @unless($hideBottomNav)
                <nav class="fixed inset-x-0 bottom-0 z-40 mx-auto w-full max-w-md bg-white px-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)] pt-3">
                    <ul class="flex items-center justify-center gap-2 text-xs font-semibold">
                        <li>
                            <a href="{{ route('groups.index') }}" class="{{ $isHome ? 'nav-pill-active-outline' : 'nav-pill' }}">HOME</a>
                        </li>
                        <li>
                            <a href="{{ $chatTargetUrl }}" class="{{ $isChat ? 'nav-pill-chat-active' : 'nav-pill-chat' }}">CHAT</a>
                        </li>
                        <li>
                            <a href="{{ route('profile.show') }}" class="{{ $isProfile ? 'nav-pill-active-outline' : 'nav-pill' }}">PROFILE</a>
                        </li>
                    </ul>
                </nav>
            @endunless
        @endauth
    </div>
</body>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            registrations.forEach((registration) => registration.unregister());
        }).catch(() => {});
    });
}
</script>
</html>
