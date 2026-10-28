@extends('layouts.app', ['title' => $user->name . ' - Admin Normi Chat'])

@section('content')
    @php
        $time = fn ($value) => $value ? \App\Support\UserTime::format(\Illuminate\Support\Carbon::parse($value)) : '–';
        $roles = ['owner' => 'Pemilik', 'admin' => 'Admin', 'member' => 'Anggota'];
        $state = $user->trashed() ? 'Dihapus ' . $time($user->deleted_at) : ($user->blocked_at ? 'Diblokir ' . $time($user->blocked_at) : 'Aktif');
    @endphp
    <section class="page-shell">
        @include('superadmin._nav')

        <x-page-header :back="route('superadmin.users.index')" :title="$user->name" :subtitle="$user->email" />

        <div class="nc-stats">
            <div class="nc-stat"><p>Status</p><b>{{ $state }}</b><span>Terdaftar {{ $time($user->created_at) }}</span></div>
            <div class="nc-stat"><p>Masuk terakhir</p><b>{{ $time($lastLogin?->created_at) }}</b><span>{{ $lastLogin?->metadata_json['provider'] ?? '' }}</span></div>
        </div>

        <div class="nc-section mt-6">
            <h2 class="nc-section-title">Grup</h2>
            <div class="nc-list">
                @foreach($ownedGroups as $group)
                    <a href="{{ route('superadmin.groups.show', $group) }}" class="nc-list-row hover:bg-surface-2"><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $group->name }}</p><p class="text-xs text-ink-soft">Pemilik{{ $group->status === 'frozen' ? ' · Dibekukan' : '' }}</p></div></a>
                @endforeach
                @foreach($memberships as $member)
                    <a href="{{ route('superadmin.groups.show', $member->group) }}" class="nc-list-row hover:bg-surface-2"><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $member->group->name }}</p><p class="text-xs text-ink-soft">{{ $roles[$member->role?->key] ?? 'Anggota' }}{{ $member->status !== 'active' ? ' · ' . $member->status : '' }}</p></div></a>
                @endforeach
                @if($ownedGroups->isEmpty() && $memberships->isEmpty())
                    <p class="nc-list-empty">Belum bergabung di grup mana pun.</p>
                @endif
            </div>
        </div>

        <div class="nc-section">
            <h2 class="nc-section-title">Tindakan admin</h2>
            <div class="nc-list">
                @forelse($audit as $log)
                    <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-sm">{{ \App\Support\AdminActions::label($log) }}</p><p class="text-xs text-ink-soft">{{ $time($log->created_at) }} · {{ $log->actor?->name ?? 'Sistem' }}</p></div></div>
                @empty
                    <p class="nc-list-empty">Belum ada tindakan admin untuk user ini.</p>
                @endforelse
            </div>
        </div>

        @unless($user->isSuperadmin())
            <div class="nc-section">
                <h2 class="nc-section-title">Blokir, hapus, atau pulihkan</h2>
                @if($user->trashed())
                    <p class="nc-section-note">Akun ini dihapus: tidak bisa masuk dan tidak tampil di aplikasi. Memulihkan akun juga mengembalikan grup miliknya dan keanggotaannya.</p>
                    <form method="POST" action="{{ route('superadmin.users.restore', $user) }}" class="mt-3" data-confirm="Pulihkan akun {{ $user->email }}?" data-confirm-label="Pulihkan">
                        @csrf
                        <button type="submit" class="btn-primary w-full py-2.5">Pulihkan akun</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('superadmin.users.block', $user) }}" class="mt-2" data-confirm="{{ $user->blocked_at ? 'Buka blokir akun ini?' : 'Blokir akun ini? Semua sesinya langsung berakhir dan ia tidak bisa masuk.' }}" data-confirm-label="{{ $user->blocked_at ? 'Buka blokir' : 'Blokir' }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full py-2.5">{{ $user->blocked_at ? 'Buka blokir' : 'Blokir akun' }}</button>
                    </form>
                    <p class="nc-section-note mt-4">Menghapus akun: tidak bisa masuk dan hilang dari aplikasi, grup miliknya ikut dinonaktifkan. Data tetap tersimpan dan bisa dipulihkan dari halaman ini.</p>
                    <form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" class="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]">
                        @csrf
                        @method('DELETE')
                        <input type="text" name="confirm_email" required class="nc-input" placeholder="Ketik {{ $user->email }} untuk menghapus" aria-label="Ketik email untuk menghapus" autocomplete="off">
                        <button type="submit" class="nc-button-danger px-4 py-2">Hapus akun</button>
                    </form>
                @endif
            </div>
        @endunless
    </section>
@endsection
