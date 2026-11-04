{{-- Kolom kiri desktop superadmin: menu admin, bukan daftar grup (superadmin tidak memakai grup). --}}
@php($admin = auth()->user())
<aside class="nc-admin-side" aria-label="Navigasi admin">
    <a href="{{ route('superadmin.index') }}" class="nc-admin-brand">
        <img src="{{ asset('brand/logo-mark.svg') }}" alt="" class="h-7 w-7 shrink-0" draggable="false" />
        <span>Normi Chat <small>Admin</small></span>
    </a>
    <div class="nc-admin-side-scroll nc-scroll">
        @include('superadmin._menu')
    </div>
    <a href="{{ route('profile.show') }}" @class(['nc-admin-account', 'is-active' => request()->routeIs('profile.*')])>
        <span class="nc-admin-account-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($admin->name, 0, 1)) }}</span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold">{{ $admin->name }}</span>
            <span class="block truncate text-xs text-ink-soft">{{ $admin->email }}</span>
        </span>
    </a>
</aside>
