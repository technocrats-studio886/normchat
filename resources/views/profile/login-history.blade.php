@extends('layouts.app', ['title' => 'Login History - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Login History</h1>
        <p class="mt-1 text-sm text-slate-500">Riwayat aktivitas login dan logout akun kamu.</p>

        {{-- Stats cards --}}
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total Login</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-slate-900">{{ number_format($totalAllTime) }}</p>
            </div>
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Terakhir Login</p>
                @if($lastLogin)
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $lastLogin->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y') }}</p>
                    <p class="text-[11px] text-slate-500">{{ $lastLogin->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('H:i') }} WIB</p>
                @else
                    <p class="mt-1 text-sm font-bold text-slate-400">—</p>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('profile.login-history') }}" class="mt-5 flex flex-wrap items-center gap-2">
            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Filter</label>
            <select name="range" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                <option value="week" {{ $loginRange === 'week' ? 'selected' : '' }}>1 Minggu</option>
                <option value="month" {{ $loginRange === 'month' ? 'selected' : '' }}>1 Bulan</option>
                <option value="3month" {{ $loginRange === '3month' ? 'selected' : '' }}>3 Bulan</option>
                <option value="all" {{ $loginRange === 'all' ? 'selected' : '' }}>Semua</option>
            </select>

            <select name="sort" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                <option value="desc" {{ $sortDir === 'desc' ? 'selected' : '' }}>Terbaru</option>
                <option value="asc" {{ $sortDir === 'asc' ? 'selected' : '' }}>Terlama</option>
            </select>

            <span class="ml-auto text-[11px] text-slate-400">{{ $totalCount }} hasil</span>
        </form>

        @php
            $detectDevice = static function (string $ua): string {
                $uaLower = strtolower($ua);
                if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) return 'iPhone / iPad';
                if (str_contains($uaLower, 'android')) return 'Android';
                if (str_contains($uaLower, 'windows')) return 'Windows';
                if (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) return 'macOS';
                if (str_contains($uaLower, 'linux')) return 'Linux';
                return $ua !== '' ? 'Desktop Browser' : 'Perangkat tidak diketahui';
            };
        @endphp

        {{-- Log list --}}
        <div class="mt-4 space-y-2">
            @forelse($loginLogs as $log)
                @php
                    $meta = is_array($log->metadata_json) ? $log->metadata_json : [];
                    $device = $detectDevice((string) ($meta['user_agent'] ?? ''));
                    $city = trim((string) ($meta['city'] ?? ''));
                    $country = trim((string) ($meta['country'] ?? ''));
                    $location = trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country);
                    if ($location === '') {
                        $locField = trim((string) ($meta['location'] ?? ''));
                        if ($locField !== '') {
                            $location = $locField;
                        } else {
                            $location = 'Tidak diketahui';
                        }
                    }
                    $isLogin = $log->action === 'auth.connect';
                @endphp

                <details class="group rounded-2xl border border-slate-200 bg-white transition hover:border-slate-300">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-3 py-2.5">
                        {{-- Device icon using SVG --}}
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $isLogin ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500' }}">
                            @if(str_contains($device, 'iPhone') || str_contains($device, 'iPad') || str_contains($device, 'Android'))
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            @elseif(str_contains($device, 'macOS') || str_contains($device, 'Windows') || str_contains($device, 'Linux'))
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs font-semibold text-slate-800">{{ $device }}</p>
                                <span class="rounded px-1 py-px text-[10px] font-semibold leading-tight {{ $isLogin ? 'text-emerald-600' : 'text-rose-500' }}">
                                    {{ $isLogin ? 'login' : 'logout' }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-[10px] text-slate-500">Lokasi: {{ $location }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] text-slate-400">{{ $log->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('d M Y') }}</p>
                            <p class="text-[10px] font-semibold text-slate-500">{{ $log->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('H:i') }}</p>
                        </div>
                    </summary>

                    <div class="border-t border-slate-100 px-3 py-2.5 text-[11px] text-slate-500">
                        @if(! empty($meta['ip']))
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-600">IP Address:</span>
                                <span class="font-mono text-[10px]">{{ $meta['ip'] }}</span>
                            </div>
                        @endif
                        @if(! empty($meta['user_agent']))
                            <div class="mt-1.5">
                                <span class="font-semibold text-slate-600">User Agent:</span>
                                <p class="mt-0.5 break-all text-[10px] leading-relaxed text-slate-400">{{ $meta['user_agent'] }}</p>
                            </div>
                        @endif
                        @if(! empty($meta['city']) || ! empty($meta['country']))
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="font-semibold text-slate-600">Lokasi:</span>
                                <span>{{ trim(($meta['city'] ?? '') . ', ' . ($meta['country'] ?? ''), ', ') }}</span>
                            </div>
                        @endif
                    </div>
                </details>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Belum ada login history</p>
                    <p class="mt-1 text-xs text-slate-400">Aktivitas login dan logout kamu akan muncul di sini.</p>
                </div>
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
