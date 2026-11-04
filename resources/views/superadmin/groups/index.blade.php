@extends('layouts.admin')

@section('heading', 'Grup')

@section('actions')
    <a href="{{ route('superadmin.csv', 'grup') }}" class="btn-secondary px-3 py-2 text-sm">Ekspor CSV</a>
@endsection

@section('content')
    <form method="GET" class="nc-filter-bar" role="search">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama grup, pemilik, atau email" aria-label="Cari grup">
        <select name="status" aria-label="Status" data-auto-submit>
            <option value="">Semua status</option>
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="frozen" @selected($status === 'frozen')>Dibekukan</option>
        </select>
        <button type="submit" class="btn-secondary px-4 py-2">Cari</button>
    </form>

    <div class="nc-table-wrap">
        <table class="nc-table">
            <thead>
                <tr><th>Grup</th><th class="max-md:hidden">Anggota</th><th>Saldo NK</th><th class="max-md:hidden">Aktif terakhir</th><th class="max-sm:hidden">Status</th></tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                    <tr>
                        <td class="max-w-[16rem]">
                            <a href="{{ route('superadmin.groups.show', $group) }}" class="block truncate font-semibold hover:underline">{{ $group->name }}</a>
                            <span class="block truncate text-xs text-ink-soft">{{ $group->owner?->name ?? 'Pemilik dihapus' }}</span>
                        </td>
                        <td class="max-md:hidden">{{ $group->members_count }}</td>
                        <td>{{ number_format((float) ($group->groupToken?->credits ?? 0), 2, ',', '.') }}</td>
                        <td class="max-md:hidden">{{ $group->last_activity ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($group->last_activity), 'j M Y, H.i') : '–' }}</td>
                        <td class="max-sm:hidden"><span @class(['nc-status', 'is-ok' => ! $group->isFrozen(), 'is-danger' => $group->isFrozen()])>{{ $group->isFrozen() ? 'Dibekukan' : 'Aktif' }}</span>@if($group->isFree()) <span class="nc-status">Gratis</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-ink-soft">Tidak ada grup yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $groups->links() }}</div>
@endsection
