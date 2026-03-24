@extends('layouts.app', ['title' => 'Account Security - Normchat'])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('profile.show') }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">Account Security</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Pastikan akses akun Anda tetap aman dengan kontrol sesi dan notifikasi aktivitas.</p>

        <div class="space-y-3">
            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-[#0F172A]">SSO Provider</h2>
                <p class="mt-1 text-xs text-[#64748B]">Akun Anda terhubung melalui {{ strtoupper((string) $user->auth_provider) }}.</p>
                <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $user->email }}</p>
            </div>

            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-[#0F172A]">Session Controls</h2>
                <p class="mt-1 text-xs text-[#64748B]">Akhiri sesi lama jika Anda login di perangkat publik.</p>
                <button type="button" class="mt-3 w-full rounded-lg border border-[#CBD5E1] bg-white py-2.5 text-sm font-semibold text-[#0F172A] transition hover:bg-slate-50">
                    Akhiri Semua Sesi Lain
                </button>
            </div>

            <div class="panel-card p-4">
                <h2 class="text-sm font-bold text-[#0F172A]">Activity Alerts</h2>
                <p class="mt-1 text-xs text-[#64748B]">Dapatkan ringkasan aktivitas login yang mencurigakan.</p>
                <label class="mt-3 flex items-center gap-2 text-sm text-[#0F172A]">
                    <input type="checkbox" checked class="rounded border-slate-300" />
                    Kirim notifikasi email keamanan
                </label>
            </div>
        </div>
    </section>
@endsection
