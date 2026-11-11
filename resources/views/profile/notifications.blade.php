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
            {{-- Notifikasi push perangkat ini (resources/js/settings/push-settings.js). --}}
            <div class="nc-settings-group" data-push-settings>
                <div class="nc-settings-row">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Notifikasi di perangkat ini</span>
                        <span class="block text-xs font-normal text-ink-soft" data-push-status>Memeriksa…</span>
                    </span>
                    <button type="button" class="btn-primary hidden shrink-0 px-3 py-1.5 text-xs" data-push-on>Nyalakan</button>
                    <button type="button" class="btn-secondary hidden shrink-0 px-3 py-1.5 text-xs" data-push-off>Matikan</button>
                </div>
                <div class="nc-settings-row hidden" data-push-test-row>
                    <span class="min-w-0 flex-1 text-xs text-ink-soft" data-push-test-status>Kirim notifikasi percobaan ke perangkat ini.</span>
                    <button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" data-push-test>Kirim uji</button>
                </div>
            </div>
            <p class="nc-section-note">Notifikasi hanya muncul untuk pesan yang belum kamu baca di perangkat mana pun, dan tidak muncul selama Normi Chat sedang terbuka di salah satu perangkatmu. Grup bisa dibisukan dari Info grup.</p>
            <button type="submit" class="btn-primary w-full py-2.5">Simpan</button>
        </form>
    </section>
@endsection
