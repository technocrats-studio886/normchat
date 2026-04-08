@extends('layouts.app', ['title' => 'Account Security - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Account Security</h1>
        <p class="mt-1 text-sm text-slate-500">Pastikan akses akun Anda tetap aman dengan kontrol sesi dan notifikasi aktivitas.</p>

        @if(session('success'))
            <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="mt-6 space-y-3">
            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-slate-900">Login via Interdotz SSO</h2>
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
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mt-3 w-full rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-50">
                        Logout & Akhiri Sesi
                    </button>
                </form>
            </div>

            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-slate-900">Informasi Keamanan</h2>
                <p class="mt-1 text-xs text-slate-500">Akun Anda dilindungi oleh Interdotz SSO. Semua autentikasi dikelola melalui Interdotz.</p>
                <div class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">
                    Untuk mengubah password atau mengaktifkan 2FA, kelola langsung di akun Interdotz Anda.
                </div>
            </div>
        </div>
    </section>
@endsection
