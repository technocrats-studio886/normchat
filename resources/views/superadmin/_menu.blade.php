{{-- Menu superadmin; dipakai sidebar desktop (components/admin-sidebar) dan laci HP (layouts/admin). --}}
@php
    $menu = [
        'Ikhtisar' => [
            ['superadmin.index', 'Ringkasan', 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ],
        'Kelola' => [
            ['superadmin.groups.index', 'Grup', 'M17 20h5v-1a4 4 0 0 0-4-4h-1M9 20H2v-1a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v1zM9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm9 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
            ['superadmin.users.index', 'User', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1'],
        ],
        'Uang' => [
            ['superadmin.finance', 'Keuangan & AI', 'M4 19V5m0 14h16M8 16v-5m4 5V8m4 8v-3'],
            ['superadmin.openai', 'Biaya OpenAI', 'M12 3v18m4-14H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6H8'],
            ['superadmin.promo', 'Promo', 'M20 12v8H4v-8m16 0H4m16 0V8H4v4m8-4v12m0-12c-1.5-3-5-3-5-.5S12 8 12 8zm0 0c1.5-3 5-3 5-.5S12 8 12 8z'],
        ],
        'Komunikasi' => [
            ['superadmin.announcements.index', 'Pengumuman', 'M4 10v4a1 1 0 0 0 1 1h2l5 4V5L7 9H5a1 1 0 0 0-1 1zm12-2a5 5 0 0 1 0 8'],
        ],
        'Sistem' => [
            ['superadmin.audit', 'Log audit', 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01'],
            ['superadmin.settings', 'Pengaturan', 'M10.3 4.3a2 2 0 0 1 3.4 0l.4.8a2 2 0 0 0 2.2 1l.9-.2a2 2 0 0 1 2.4 2.4l-.2.9a2 2 0 0 0 1 2.2l.8.4a2 2 0 0 1 0 3.4l-.8.4a2 2 0 0 0-1 2.2l.2.9a2 2 0 0 1-2.4 2.4l-.9-.2a2 2 0 0 0-2.2 1l-.4.8a2 2 0 0 1-3.4 0l-.4-.8a2 2 0 0 0-2.2-1l-.9.2a2 2 0 0 1-2.4-2.4l.2-.9a2 2 0 0 0-1-2.2l-.8-.4a2 2 0 0 1 0-3.4l.8-.4a2 2 0 0 0 1-2.2l-.2-.9A2 2 0 0 1 7.6 6.1l.9.2a2 2 0 0 0 2.2-1zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
        ],
    ];
@endphp
<nav class="nc-admin-nav" aria-label="Menu admin">
    @foreach($menu as $section => $items)
        <p class="nc-admin-nav-label">{{ $section }}</p>
        @foreach($items as [$route, $label, $icon])
            @php($active = request()->routeIs($route) || ($route !== 'superadmin.index' && request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.index').'.*')))
            <a href="{{ route($route) }}" @class(['is-active' => $active]) @if($active) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    @endforeach
</nav>
