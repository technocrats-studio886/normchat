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

        {{-- AI Management section --}}
        <div id="ai-provider" class="mt-8">
            <h2 class="mb-4 text-xl font-extrabold text-slate-900 font-display">Add AI Provider</h2>

            @php
                $activeGroupAi = $group->aiConnections->first();
            @endphp

            @if ((int) $group->owner_id === (int) auth()->id())
                <form method="POST" action="{{ route('settings.ai', $group) }}" class="panel-card mb-4 space-y-3 px-4 py-4">
                    @csrf
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konfigurasi provider utama grup</p>
                    <select name="provider_name" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none">
                        <option value="">Pilih Provider</option>
                        <option value="openai" @selected(old('provider_name', $activeGroupAi?->provider) === 'openai')>OpenAI</option>
                        <option value="claude" @selected(old('provider_name', $activeGroupAi?->provider) === 'claude')>Claude</option>
                        <option value="gemini" @selected(old('provider_name', $activeGroupAi?->provider) === 'gemini')>Gemini</option>
                    </select>
                    <input type="password" name="access_token" placeholder="Masukkan token API owner" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none" />
                    <button type="submit" class="btn-cta py-3 normal-case tracking-normal">Simpan Provider Grup</button>
                </form>
            @endif

            <div class="space-y-3">
                @php
                    $providers = [
                        ['key' => 'openai', 'label' => 'OpenAI'],
                        ['key' => 'claude', 'label' => 'Claude'],
                        ['key' => 'gemini', 'label' => 'Gemini'],
                    ];
                @endphp
                @foreach($providers as $provider)
                    @php
                        $connected = $group->aiConnections->first(fn ($ai) => $ai->provider === $provider['key']);
                    @endphp
                    <div class="panel-card flex items-center justify-between gap-3 px-4 py-3.5">
                        <div>
                            <span class="text-sm font-semibold text-slate-900">{{ $provider['label'] }}</span>
                            <p class="mt-0.5 text-xs {{ $connected ? 'text-emerald-600' : 'text-slate-500' }}">
                                {{ $connected ? 'Connected' : 'Belum terhubung' }}
                            </p>
                        </div>

                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                            {{ $connected ? 'Aktif dipakai grup' : 'Tidak aktif' }}
                        </span>
                    </div>
                @endforeach

                <div class="rounded-xl bg-blue-50 px-4 py-3 text-xs text-blue-600">
                    Semua member grup wajib login dengan provider yang aktif dipakai owner pada grup ini.
                </div>
            </div>

            {{-- Connected AIs --}}
            @if($group->aiConnections && $group->aiConnections->count() > 0)
                <div class="mt-3 space-y-2">
                    @foreach ($group->aiConnections as $ai)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2.5 text-sm">
                            <span class="font-semibold">{{ strtoupper($ai->provider) }}</span>
                            <span class="text-xs text-emerald-600">Active</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
