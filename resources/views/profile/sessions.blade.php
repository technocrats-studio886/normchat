@extends('layouts.app', ['title' => 'Perangkat & sesi - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('profile.show')" title="Perangkat & sesi" subtitle="Perangkat yang sedang masuk ke akunmu." />

        <div class="nc-settings-group">
            @foreach($sessions as $session)
                @php($current = $session->session_hash === $currentHash)
                <div class="nc-settings-row">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ $session->device ?: 'Perangkat tidak dikenal' }}</span>
                        <span class="block truncate text-xs text-ink-soft">
                            @if($current)
                                <span class="font-semibold text-primary-ink">Perangkat ini</span> · aktif sekarang
                            @else
                                Terakhir aktif {{ \App\Support\UserTime::format($session->last_active_at, 'j M Y, H.i') }}
                            @endif
                        </span>
                    </span>
                    @unless($current)
                        <form method="POST" action="{{ route('profile.sessions.revoke', $session) }}" data-confirm="Keluarkan {{ $session->device ?: 'perangkat ini' }}?" data-confirm-label="Keluarkan">
                            @csrf
                            <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">Keluarkan</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        </div>

        @if($sessions->count() > 1)
            <form method="POST" action="{{ route('profile.sessions.logout-others') }}" class="mt-4" data-confirm="Keluar dari semua perangkat lain? Perangkat ini tetap masuk." data-confirm-label="Keluarkan semua">
                @csrf
                <button type="submit" class="nc-button-danger w-full py-2.5">Keluar dari semua perangkat lain</button>
            </form>
        @endif
        <p class="nc-section-note mt-3">Sesi juga berakhir sendiri setelah {{ (int) config('normchat.session_idle_days', 5) }} hari tanpa aktivitas.</p>
    </section>
@endsection
