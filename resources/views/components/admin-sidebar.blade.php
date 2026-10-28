{{-- Kolom kiri desktop untuk superadmin: menu admin, bukan daftar grup (superadmin tidak bergabung di grup). --}}
@php
    $links = [
        'superadmin.index' => 'Ringkasan',
        'superadmin.groups.index' => 'Grup',
        'superadmin.users.index' => 'User',
        'superadmin.finance' => 'Keuangan & AI',
        'superadmin.audit' => 'Log audit',
        'superadmin.settings' => 'Pengaturan',
    ];
@endphp
<aside class="nc-sidebar hidden lg:flex" data-sidebar="1" aria-label="Menu admin">
    <div class="nc-sidebar-head">
        <a href="{{ route('superadmin.index') }}" class="flex min-w-0 items-center gap-2">
            <img src="{{ asset('brand/logo-mark.svg') }}" alt="Normi Chat" class="h-8 w-8 shrink-0" draggable="false" />
            <span class="nc-sidebar-label truncate font-display text-lg font-extrabold text-ink">Admin</span>
        </a>
        <div class="nc-sidebar-actions">
            <button type="button" class="nc-sidebar-icon" data-sidebar-toggle="1" title="Tutup/buka panel" aria-label="Tutup panel" aria-expanded="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M9 4v16"/></svg>
            </button>
        </div>
    </div>
    <nav class="nc-sidebar-list nc-scroll">
        @foreach($links as $route => $label)
            @php($active = request()->routeIs($route) || ($route !== 'superadmin.index' && request()->routeIs(str_replace('.index', '.*', $route))))
            <a href="{{ route($route) }}" class="nc-sidebar-item {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>
                <span class="nc-sidebar-avatar text-xs">{{ mb_substr($label, 0, 1) }}</span>
                <span class="nc-sidebar-label truncate text-sm font-semibold">{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <div class="nc-sidebar-foot">
        <a href="{{ route('profile.show') }}" class="nc-sidebar-profile" title="Akun">
            <span class="nc-sidebar-avatar h-9 w-9 text-xs">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
            <span class="nc-sidebar-label min-w-0">
                <span class="block truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
                <span class="block truncate text-[11px] text-ink-soft">{{ $user->email }}</span>
            </span>
        </a>
    </div>
    <div class="nc-sidebar-resizer" data-sidebar-resizer="1" role="separator" aria-orientation="vertical" aria-label="Geser untuk menutup atau membuka panel"></div>
</aside>
