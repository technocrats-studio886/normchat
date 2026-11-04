@extends('layouts.admin')

@section('heading', $user->name)
@section('back', route('superadmin.users.index'))

@if(! $user->isSuperadmin() && ! $user->trashed())
    @section('actions')
        <form method="POST" action="{{ route('superadmin.users.block', $user) }}" data-confirm="{{ $user->blocked_at ? 'Buka blokir akun ini?' : 'Blokir akun ini? Semua sesinya langsung berakhir dan ia tidak bisa masuk.' }}" data-confirm-label="{{ $user->blocked_at ? 'Buka blokir' : 'Blokir' }}">
            @csrf
            <button type="submit" class="btn-secondary px-3 py-2 text-sm">{{ $user->blocked_at ? 'Buka blokir' : 'Blokir akun' }}</button>
        </form>
    @endsection
@endif

@section('content')
    @php
        $time = fn ($value) => $value ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($value), 'j M Y, H.i') : '–';
        $roles = ['owner' => 'Pemilik', 'admin' => 'Admin', 'member' => 'Anggota'];
        $state = $user->trashed() ? ['Dihapus ' . $time($user->deleted_at), 'is-danger'] : ($user->blocked_at ? ['Diblokir ' . $time($user->blocked_at), 'is-danger'] : ['Aktif', 'is-ok']);
    @endphp

    <p class="mb-3 text-sm text-ink-soft">{{ $user->email }}</p>

    <dl class="nc-kpis">
        <div class="nc-kpi"><dt>Status</dt><dd class="!text-base"><span class="nc-status {{ $state[1] }}">{{ $state[0] }}</span><small>Terdaftar {{ $time($user->created_at) }}</small></dd></div>
        <div class="nc-kpi"><dt>Masuk terakhir</dt><dd class="!text-base">{{ $time($lastLogin?->created_at) }}<small>{{ $lastLogin?->metadata_json['provider'] ?? '' }}</small></dd></div>
        <div class="nc-kpi"><dt>Grup</dt><dd class="!text-base">{{ $ownedGroups->count() }} milik · {{ $memberships->count() }} anggota</dd></div>
    </dl>

    <div class="nc-admin-cols lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div>
            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Grup</h2></div>
                <div class="nc-table-wrap">
                    <table class="nc-table">
                        <thead><tr><th>Grup</th><th>Peran</th></tr></thead>
                        <tbody>
                            @foreach($ownedGroups as $group)
                                <tr><td><a href="{{ route('superadmin.groups.show', $group) }}" class="font-medium hover:underline">{{ $group->name }}</a></td><td>Pemilik{{ $group->status === 'frozen' ? ' · Dibekukan' : '' }}</td></tr>
                            @endforeach
                            @foreach($memberships as $member)
                                <tr><td><a href="{{ route('superadmin.groups.show', $member->group) }}" class="font-medium hover:underline">{{ $member->group->name }}</a></td><td>{{ $roles[$member->role?->key] ?? 'Anggota' }}{{ $member->status !== 'active' ? ' · ' . $member->status : '' }}</td></tr>
                            @endforeach
                            @if($ownedGroups->isEmpty() && $memberships->isEmpty())
                                <tr><td colspan="2" class="text-ink-soft">Belum bergabung di grup mana pun.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div>
            <section class="nc-panel">
                <div class="nc-panel-head"><h2 class="nc-panel-title">Tindakan admin</h2></div>
                <div class="nc-box">
                    @forelse($audit as $log)
                        <div class="nc-box-row">
                            <span class="min-w-0 flex-1">
                                <span class="block">{{ \App\Support\AdminActions::label($log) }}</span>
                                <span class="block text-xs text-ink-soft">{{ $log->actor?->name ?? 'Sistem' }} · {{ $time($log->created_at) }}</span>
                            </span>
                        </div>
                    @empty
                        <p class="nc-box-empty">Belum ada tindakan admin untuk user ini.</p>
                    @endforelse
                </div>
            </section>

            @unless($user->isSuperadmin())
                <section class="nc-panel">
                    @if($user->trashed())
                        <div class="nc-panel-head"><h2 class="nc-panel-title">Pulihkan akun</h2></div>
                        <form method="POST" action="{{ route('superadmin.users.restore', $user) }}" class="nc-box space-y-3 p-4" data-confirm="Pulihkan akun {{ $user->email }}?" data-confirm-label="Pulihkan">
                            @csrf
                            <p class="text-sm text-ink-soft">Akun ini dihapus: tidak bisa masuk dan tidak tampil di aplikasi. Memulihkan juga mengembalikan grup miliknya dan keanggotaannya.</p>
                            <button type="submit" class="btn-primary w-full py-2">Pulihkan akun</button>
                        </form>
                    @else
                        <div class="nc-panel-head"><h2 class="nc-panel-title">Hapus akun</h2></div>
                        <form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" class="nc-box space-y-3 border-danger-line p-4">
                            @csrf
                            @method('DELETE')
                            <p class="text-sm text-ink-soft">Tidak bisa masuk dan hilang dari aplikasi; grup miliknya ikut nonaktif. Data tetap tersimpan dan bisa dipulihkan.</p>
                            <input type="text" name="confirm_email" required class="nc-input w-full" placeholder="{{ $user->email }}" aria-label="Ketik email untuk menghapus" autocomplete="off">
                            <button type="submit" class="nc-button-danger w-full py-2">Hapus akun</button>
                        </form>
                    @endif
                </section>
            @endunless
        </div>
    </div>
@endsection
