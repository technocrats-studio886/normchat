@extends('layouts.app', ['title' => 'Account Security - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Account Security</h1>
        <p class="mt-1 text-sm text-slate-500">Pastikan akses akun Anda tetap aman dengan kontrol sesi dan notifikasi aktivitas.</p>

        <div class="mt-6 space-y-3">
            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-slate-900">Login via Google SSO</h2>
                <div class="mt-2 flex items-center gap-3">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover" referrerpolicy="no-referrer" />
                    @endif
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                </div>
            </div>

            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-slate-900">Session Controls</h2>
                <p class="mt-1 text-xs text-slate-500">Akhiri sesi lama jika Anda login di perangkat publik.</p>
                <button type="button" class="mt-3 w-full rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-50">
                    Akhiri Semua Sesi Lain
                </button>
            </div>

            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-slate-900">Activity Alerts</h2>
                <p class="mt-1 text-xs text-slate-500">Dapatkan ringkasan aktivitas login yang mencurigakan.</p>
                <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked class="rounded border-slate-300" />
                    Kirim notifikasi email keamanan
                </label>
            </div>
        </div>
    </section>
@endsection
