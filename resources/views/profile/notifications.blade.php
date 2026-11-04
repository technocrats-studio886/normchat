@extends('layouts.app', ['title' => 'Notifikasi - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('profile.show')" title="Notifikasi" subtitle="Berlaku di semua perangkatmu." />

        <form method="POST" action="{{ route('profile.notifications.save') }}" class="space-y-4">
            @csrf
            <div class="nc-settings-group">
                <label class="nc-switch nc-settings-row">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Saat disebut (@nama)</span>
                        <span class="block text-xs font-normal text-ink-soft">Pemberitahuan "Kamu disebut" di aplikasi</span>
                    </span>
                    <input type="checkbox" name="notify_mentions" value="1" role="switch" @checked($user->notify_mentions)>
                </label>
                <label class="nc-switch nc-settings-row">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Bunyi pesan masuk</span>
                        <span class="block text-xs font-normal text-ink-soft">Pesan baru di grup yang terbuka saat aplikasi di latar belakang, dan saat kamu disebut</span>
                    </span>
                    <input type="checkbox" name="notify_sound" value="1" role="switch" @checked($user->notify_sound)>
                </label>
            </div>
            <p class="nc-section-note">Notifikasi ke HP saat aplikasi tertutup belum tersedia.</p>
            <button type="submit" class="btn-primary w-full py-2.5">Simpan</button>
        </form>
    </section>
@endsection
