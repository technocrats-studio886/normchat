@extends('layouts.app', ['title' => 'Settings - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell pt-4">
        <h1 class="mb-5 text-xl font-extrabold text-slate-900 font-display">Group Settings</h1>

        <div class="space-y-3">
            {{-- Group Info --}}
            <div class="panel-card px-4 py-3.5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $group->name }}</h3>
                        <p class="text-xs text-slate-500">{{ $group->description ?: 'Tidak ada deskripsi' }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Active</span>
                </div>
            </div>

            {{-- Approve Join Request --}}
            <div class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">Approve Join Request</span>
                    <p class="text-[11px] text-slate-400">Wajib approve sebelum member baru masuk</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $group->approval_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $group->approval_enabled ? 'ON' : 'OFF' }}
                </span>
            </div>

            {{-- History & Export --}}
            <a href="{{ route('settings.history', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">History & Export</span>
                    <p class="text-[11px] text-slate-400">Backup, export PDF/DOCX</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>

            {{-- Seat Management --}}
            <a href="{{ route('settings.seats', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">Seat Management</span>
                    <p class="text-[11px] text-slate-400">Kelola kapasitas anggota grup</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>

            {{-- AI Persona Editor --}}
            <a href="{{ route('settings.ai.persona', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">AI Persona Editor</span>
                    <p class="text-[11px] text-slate-400">Atur gaya bicara AI untuk grup ini</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>

        {{-- History & Export section --}}
        <div id="history-export" class="mt-8">
            <h2 class="mb-4 text-xl font-extrabold text-slate-900 font-display">History & Export</h2>

            <div class="space-y-3">
                @forelse ($group->backups as $backup)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <h3 class="text-sm font-bold text-slate-900">Snapshot #{{ $backup->id }}</h3>
                        <p class="text-xs text-slate-500">{{ $backup->created_at?->diffForHumans() }} &middot; by {{ $backup->creator->name ?? 'System' }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                        Belum ada snapshot backup.
                    </div>
                @endforelse

                <form method="POST" action="{{ route('settings.backup', $group) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-slate-100 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">
                        Buat Backup Snapshot
                    </button>
                </form>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="pdf">
                    <button type="submit" class="w-full rounded-xl bg-red-500 py-3 text-sm font-bold text-white transition hover:bg-red-600">Export PDF</button>
                </form>
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="docx">
                    <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Export DOCX</button>
                </form>
            </div>
        </div>

        {{-- AI & Normkredit Info --}}
        <div id="ai-provider" class="mt-8">
            <h2 class="mb-4 text-xl font-extrabold text-slate-900 font-display">AI & Normkredit</h2>

            @php
                $providerLabel = config("ai_models.providers.{$group->ai_provider}.label", 'Belum dipilih');
                $modelLabel = config("ai_models.providers.{$group->ai_provider}.models.{$group->ai_model}.label", $group->ai_model ?? '-');
                $multiplier = $group->getModelMultiplier();
                $gt = $group->groupToken;
                $credits = $gt ? $gt->credits : 0;
            @endphp

            <div class="space-y-3">
                <div class="panel-card px-4 py-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-700">Provider</span>
                        <span class="text-sm font-bold text-slate-900">{{ $providerLabel }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-sm text-slate-700">Model</span>
                        <span class="text-sm font-bold text-slate-900">{{ $modelLabel }} <span class="text-xs font-semibold text-blue-500">({{ $multiplier }}x)</span></span>
                    </div>
                </div>

                <div class="panel-card px-4 py-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-700">Saldo Normkredit</span>
                        <span class="text-sm font-bold {{ $credits > 0 ? 'text-emerald-600' : 'text-rose-500' }}">{{ number_format($credits, 1) }} normkredit</span>
                    </div>
                    <p class="mt-0.5 text-[11px] text-slate-400">1 normkredit = 1.000 token = Rp1.000</p>
                    <a href="{{ route('subscription.tokens.buy') }}" class="mt-2 block text-xs font-semibold text-blue-500 hover:text-blue-700">
                        Top-up Normkredit &rarr;
                    </a>
                </div>

                <div class="rounded-xl bg-blue-50 px-4 py-3 text-xs text-blue-600">
                    Normkredit milik grup. Semua member bisa patungan top-up.
                </div>
            </div>
        </div>

        <div class="h-6"></div>
    </section>
@endsection
