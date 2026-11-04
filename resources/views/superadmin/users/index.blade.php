@extends('layouts.admin')

@section('heading', 'User')

@section('actions')
    <a href="{{ route('superadmin.csv', 'user') }}" class="btn-secondary px-3 py-2 text-sm">Ekspor CSV</a>
@endsection

@section('content')
    <form method="GET" class="nc-filter-bar" role="search">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama atau email" aria-label="Cari user">
        <button type="submit" class="btn-secondary px-4 py-2">Cari</button>
    </form>

    <div class="nc-table-wrap">
        <table class="nc-table">
            <thead>
                <tr><th>User</th><th class="max-md:hidden">Grup milik</th><th class="max-md:hidden">Keanggotaan</th><th class="max-sm:hidden">Masuk terakhir</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php($state = $user->trashed() ? ['Dihapus', 'is-danger'] : ($user->blocked_at ? ['Diblokir', 'is-danger'] : ($user->isSuperadmin() ? ['Superadmin', ''] : ['Aktif', 'is-ok'])))
                    <tr>
                        <td class="max-w-[16rem]">
                            <a href="{{ route('superadmin.users.show', $user) }}" class="block truncate font-semibold hover:underline">{{ $user->name }}</a>
                            <span class="block truncate text-xs text-ink-soft">{{ $user->email }}</span>
                        </td>
                        <td class="max-md:hidden">{{ $user->owned_groups_count }}</td>
                        <td class="max-md:hidden">{{ $user->memberships_count }}</td>
                        <td class="max-sm:hidden">{{ $user->last_login_at ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($user->last_login_at), 'j M Y, H.i') : '–' }}</td>
                        <td><span class="nc-status {{ $state[1] }}">{{ $state[0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-ink-soft">Tidak ada user yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
