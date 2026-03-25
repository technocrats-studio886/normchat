@extends('layouts.app', ['title' => 'Settings - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell pt-4">
        <h1 class="mb-5 text-xl font-extrabold text-slate-900 font-display">Owner Controls</h1>

        <div class="space-y-3">
            {{-- Edit Group --}}
            <div class="panel-card px-4 py-3.5">
                <h3 class="text-sm font-bold text-slate-900">Edit Group</h3>
                <p class="text-xs text-slate-500">Ubah nama dan deskripsi group</p>
            </div>

            {{-- Approve Join Request --}}
            <div class="panel-card flex items-center justify-between px-4 py-3.5">
                <span class="text-sm text-slate-700">Approve Join Request</span>
                <span class="text-xs font-bold {{ $group->approval_enabled ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $group->approval_enabled ? 'ON' : 'OFF' }}
                </span>
            </div>

            {{-- Transfer Ownership --}}
            <a href="#" class="panel-card flex items-center justify-between px-4 py-3.5">
                <span class="text-sm text-slate-700">Transfer Ownership</span>
                <span class="text-xs text-slate-400">›</span>
            </a>

            {{-- History & Export --}}
            <a href="{{ route('settings.history', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <span class="text-sm text-slate-700">History & Export</span>
                <span class="text-xs text-slate-400">›</span>
            </a>

            {{-- Seat Management --}}
            <a href="{{ route('settings.seats', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <span class="text-sm text-slate-700">Seat Management</span>
                <span class="text-xs text-slate-400">›</span>
            </a>

            {{-- AI Persona Editor --}}
            <a href="{{ route('settings.ai.persona', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <span class="text-sm text-slate-700">AI Persona Editor</span>
                <span class="text-xs text-slate-400">›</span>
            </a>

            {{-- Delete Group --}}
            <button class="w-full rounded-xl bg-rose-50 py-3.5 text-center text-sm font-semibold text-red-500 transition hover:bg-rose-100">
                Delete Group
            </button>
        </div>

        {{-- History & Export section --}}
        <div id="history-export" class="mt-8">
            <h2 class="mb-4 text-xl font-extrabold text-slate-900 font-display">History & Export</h2>

            <div class="space-y-3">
                @forelse ($group->backups as $backup)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <h3 class="text-sm font-bold text-slate-900">Snapshot #{{ $backup->id }}</h3>
                        <p class="text-xs text-slate-500">{{ $backup->created_at?->diffForHumans() }} • by {{ $backup->creator->name ?? 'System' }}</p>
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
    </section>
@endsection
