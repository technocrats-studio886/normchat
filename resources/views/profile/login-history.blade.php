@extends('layouts.app', ['title' => 'Riwayat Masuk - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('profile.show')" title="Riwayat Masuk" subtitle="Kapan dan di mana akunmu masuk atau keluar." />

        {{-- Stats cards --}}
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jumlah Masuk</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-slate-900">{{ number_format($totalAllTime) }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Terakhir Masuk</p>
                @if($lastLogin)
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ \App\Support\UserTime::format($lastLogin->created_at, 'j M Y') }}</p>
                    <p class="text-[11px] text-slate-500">{{ \App\Support\UserTime::format($lastLogin->created_at, 'H.i') }} WIB</p>
                @else
                    <p class="mt-1 text-sm font-bold text-slate-400">—</p>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('profile.login-history') }}" class="mt-5 flex flex-wrap items-center gap-2">
            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tampilkan</label>
            <select name="range" data-auto-submit="1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="week" {{ $loginRange === 'week' ? 'selected' : '' }}>1 Minggu</option>
                <option value="month" {{ $loginRange === 'month' ? 'selected' : '' }}>1 Bulan</option>
                <option value="3month" {{ $loginRange === '3month' ? 'selected' : '' }}>3 Bulan</option>
                <option value="all" {{ $loginRange === 'all' ? 'selected' : '' }}>Semua</option>
            </select>

            <select name="sort" data-auto-submit="1" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="desc" {{ $sortDir === 'desc' ? 'selected' : '' }}>Terbaru</option>
                <option value="asc" {{ $sortDir === 'asc' ? 'selected' : '' }}>Terlama</option>
            </select>

            <span class="ml-auto text-[11px] text-slate-400">{{ $totalCount }} aktivitas</span>
        </form>


        {{-- Log list --}}
        <div class="mt-4 space-y-2">
            @forelse($loginLogs as $log)
                @php
                    $info = \App\Support\LoginDevice::describe(is_array($log->metadata_json) ? $log->metadata_json : []);
                    $isLogin = $log->action === 'auth.connect';
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 px-3 py-2.5">
                        {{-- Device icon using SVG --}}
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $isLogin ? 'bg-primary-soft text-primary-ink' : 'bg-slate-100 text-slate-600' }}">
                            @if($info['mobile'])
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            @elseif($info['device'] !== null)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                @if($info['device'])
                                    <p class="text-xs font-semibold text-slate-800">{{ $info['device'] }}</p>
                                @endif
                                <span class="rounded px-1 py-px text-[10px] font-semibold leading-tight {{ $isLogin ? 'text-primary-ink' : 'text-slate-600' }}">
                                    {{ $isLogin ? 'Masuk' : 'Keluar' }}
                                </span>
                            </div>
                            @if($info['detail'])
                                <p class="mt-0.5 truncate text-[10px] text-slate-500">{{ $info['detail'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] text-slate-400">{{ \App\Support\UserTime::format($log->created_at, 'j M Y') }}</p>
                            <p class="text-[10px] font-semibold text-slate-500">{{ \App\Support\UserTime::format($log->created_at, 'H.i') }}</p>
                        </div>
                    </div>

                </div>
            @empty
                <p class="nc-list-empty px-0">Belum ada riwayat masuk.</p>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if(method_exists($loginLogs, 'links'))
            <div class="mt-4">
                {{ $loginLogs->links() }}
            </div>
        @endif
    </section>
@endsection
