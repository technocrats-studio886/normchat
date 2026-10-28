@extends('layouts.app', ['title' => 'Grup - Admin Normi Chat'])

@section('content')
    <section class="page-shell">
        @include('superadmin._nav')

        <form method="GET" class="nc-filter-bar" role="search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama grup, pemilik, atau email" aria-label="Cari grup">
            <select name="status" aria-label="Status" data-auto-submit>
                <option value="">Semua status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="frozen" @selected($status === 'frozen')>Dibekukan</option>
            </select>
            <button type="submit" class="btn-secondary px-4 py-2">Cari</button>
            <a href="{{ route('superadmin.csv', 'grup') }}" class="btn-secondary px-4 py-2">Ekspor CSV</a>
        </form>

        <div class="nc-list">
            @forelse($groups as $group)
                <a href="{{ route('superadmin.groups.show', $group) }}" class="nc-list-row hover:bg-surface-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $group->name }} @if($group->isFrozen())<span class="nc-badge">Dibekukan</span>@endif</p>
                        <p class="truncate text-xs text-ink-soft">{{ $group->owner?->name ?? 'Pemilik dihapus' }} · {{ $group->members_count }} anggota · {{ number_format((float) ($group->groupToken?->credits ?? 0), 2, ',', '.') }} NK · aktif {{ $group->last_activity ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($group->last_activity)) : 'belum ada pesan' }}</p>
                    </div>
                </a>
            @empty
                <p class="nc-list-empty">Tidak ada grup yang cocok.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $groups->links() }}</div>
    </section>
@endsection
