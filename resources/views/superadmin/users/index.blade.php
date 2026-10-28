@extends('layouts.app', ['title' => 'User - Admin Normi Chat'])

@section('content')
    <section class="page-shell">
        @include('superadmin._nav')

        <form method="GET" class="nc-filter-bar" role="search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama atau email" aria-label="Cari user">
            <button type="submit" class="btn-secondary px-4 py-2">Cari</button>
            <a href="{{ route('superadmin.csv', 'user') }}" class="btn-secondary px-4 py-2">Ekspor CSV</a>
        </form>

        <div class="nc-list">
            @forelse($users as $user)
                <a href="{{ route('superadmin.users.show', $user) }}" class="nc-list-row hover:bg-surface-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $user->name }}
                            @if($user->trashed())<span class="nc-badge">Dihapus</span>@elseif($user->blocked_at)<span class="nc-badge">Diblokir</span>@endif
                            @if($user->isSuperadmin())<span class="nc-badge">Superadmin</span>@endif
                        </p>
                        <p class="truncate text-xs text-ink-soft">{{ $user->email }} · {{ $user->owned_groups_count }} grup milik · {{ $user->memberships_count }} keanggotaan · masuk terakhir {{ $user->last_login_at ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($user->last_login_at)) : '–' }}</p>
                    </div>
                </a>
            @empty
                <p class="nc-list-empty">Tidak ada user yang cocok.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </section>
@endsection
