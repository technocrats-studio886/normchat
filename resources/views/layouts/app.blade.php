<!DOCTYPE html>
@php
    // Tema tampilan user (App\Support\Themes); dipasang server-side agar tidak berkedip.
    $uiTheme = \App\Support\Themes::for(auth()->user());
    $uiMode = $uiTheme['mode'] === 'dark' ? 'dark' : 'light';
@endphp
<html lang="id" data-theme-mode="{{ $uiTheme['mode'] }}" data-mode="{{ $uiMode }}" data-accent="{{ $uiTheme['accent'] }}">
<head>
    @include('partials.app-head')
</head>
<body class="normchat-bg min-h-screen antialiased">
    @php
        $isSuperadmin = auth()->user()?->isSuperadmin() ?? false;
    @endphp
    @auth
        {{-- Superadmin tidak memakai grup: sidebar admin, bukan daftar grup. --}}
        @if($isSuperadmin)
            <x-admin-sidebar />
        @else
            <x-desktop-sidebar />
        @endif
    @endauth
    {{-- Mobile: satu kolom max-w-md. Desktop (≥1024px): konten di panel kanan sidebar. --}}
    <div class="@auth {{ $isSuperadmin ? 'lg:pl-[232px]' : 'nc-sidebar-push lg:pl-[var(--nc-sidebar-w)]' }} @endauth">
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
            @unless($isSuperadmin)
                @include('partials.announcements')
            @endunless
        @endauth

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
{{-- Service worker khusus notifikasi push didaftarkan resources/js/push.js; SW cache lama ikut tergantikan. --}}
</body>
</html>
