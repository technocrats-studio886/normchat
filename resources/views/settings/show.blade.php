@extends('layouts.app', ['title' => 'Settings - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        @php
            $canEditProfile = $canEditProfile ?? ($canManageSettings ?? false);
            $canManageBilling = $canManageBilling ?? false;
            $canManageAiPersona = $canManageAiPersona ?? false;
            $canExportChat = $canExportChat ?? false;
            $canCreateBackup = $canCreateBackup ?? false;
            $isReadOnly = ! $canEditProfile;
            $gt = $group->groupToken;
            $credits = $gt ? $gt->credits : 0;
            $groupInitial = strtoupper(substr($group->name, 0, 1));
        @endphp

        {{-- Header --}}
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('chat.show', $group) }}" class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm hover:bg-slate-50" aria-label="Kembali">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="text-xs font-medium text-slate-500">Group Settings</p>
                <h1 class="page-title text-xl">{{ $group->name }}</h1>
            </div>
        </div>

        {{-- Hero group card --}}
        <div class="card-glow">
            <div class="flex items-center gap-3">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-xl font-extrabold backdrop-blur">
                    {{ $groupInitial }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-display text-lg font-extrabold">{{ $group->name }}</p>
                    <p class="mt-0.5 text-[11px] text-white/80">ID: <span class="font-mono font-bold">{{ $group->share_id }}</span></p>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Normkredit</p>
                    <p class="font-display text-2xl font-extrabold">{{ number_format($credits, 1) }}</p>
                </div>
                @if($canManageBilling)
                    <a href="{{ route('subscription.tokens.buy') }}" class="rounded-2xl bg-white/15 px-4 py-2 text-xs font-bold text-white backdrop-blur hover:bg-white/25">Top-up →</a>
                @endif
            </div>
        </div>

        @if($isReadOnly)
            <div class="mt-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                <span>Kamu sedang dalam mode read-only. Hanya owner/admin dengan izin yang bisa mengubah pengaturan.</span>
            </div>
        @endif

        {{-- Profil Grup --}}
        <h2 class="section-title mt-6">Profil Grup</h2>
        <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="card-soft space-y-4">
            @csrf
            <div>
                <label for="group_name" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Nama Group</label>
                <input id="group_name" type="text" name="name" value="{{ old('name', $group->name) }}" required @if($isReadOnly) readonly @endif class="input-field mt-1.5 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" />
            </div>

            <div>
                <label for="group_description" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Deskripsi</label>
                <textarea id="group_description" name="description" rows="3" @if($isReadOnly) readonly @endif class="input-field mt-1.5 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif">{{ old('description', $group->description) }}</textarea>
            </div>

            <div>
                <label for="group_password" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Password Grup Baru</label>
                <p class="mt-1 text-[11px] text-slate-400">Kosongkan jika tidak ingin ganti password.</p>
                <div class="relative mt-1.5">
                    <input id="group_password" type="password" name="password" @if($isReadOnly) readonly @endif class="input-field pr-10 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" placeholder="Masukkan password baru" />
                    @if(! $isReadOnly)
                    <button type="button" onclick="toggleSettingsGroupPassword()" class="absolute inset-y-0 right-3 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat password">
                        <svg id="settingsPassShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="settingsPassHide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03"/></svg>
                    </button>
                    @endif
                </div>
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="approval_enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" {{ old('approval_enabled', $group->approval_enabled) ? 'checked' : '' }} @if($isReadOnly) disabled @endif>
                Wajib approval sebelum member baru masuk
            </label>

            @if($isReadOnly)
                <button type="button" disabled class="w-full cursor-not-allowed rounded-2xl bg-slate-100 py-3 text-sm font-semibold text-slate-400">
                    Read-Only
                </button>
            @else
                <button type="submit" class="btn-cta">Simpan Pengaturan</button>
            @endif
        </form>

        {{-- Quick nav cards --}}
        <h2 class="section-title mt-6">Manajemen</h2>
        <div class="space-y-2.5">
            @php
                $navItems = [
                    [
                        'canManage' => $canManageBilling,
                        'href' => route('settings.history', $group),
                        'title' => 'History & Export',
                        'subtitle' => 'Backup, export PDF/DOCX',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/>',
                        'accent' => 'indigo',
                    ],
                    [
                        'canManage' => $canManageBilling,
                        'href' => route('settings.seats', $group),
                        'title' => 'Seat Management',
                        'subtitle' => 'Kelola kapasitas anggota grup',
                        'icon' => '<circle cx="9" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 20c.9-3 3.6-5 7-5s6.1 2 7 5M17 4a3.5 3.5 0 1 1 0 7M22 20c-.5-2-1.8-3.5-3.5-4.3"/>',
                        'accent' => 'violet',
                    ],
                    [
                        'canManage' => $canManageAiPersona,
                        'href' => route('settings.ai.persona', $group),
                        'title' => 'AI Persona Editor',
                        'subtitle' => 'Atur gaya bicara NormAI',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 2 9 6H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-5l-3-4Z"/><circle cx="12" cy="13" r="3"/>',
                        'accent' => 'emerald',
                    ],
                ];
                $accentClasses = [
                    'indigo' => 'bg-indigo-50 text-indigo-600',
                    'violet' => 'bg-violet-50 text-violet-600',
                    'emerald' => 'bg-emerald-50 text-emerald-600',
                ];
            @endphp
            @foreach($navItems as $item)
                <a href="{{ $item['href'] }}" class="card-soft flex items-center gap-3 transition active:scale-[0.98]">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $accentClasses[$item['accent']] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">{!! $item['icon'] !!}</svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">{{ $item['title'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $item['subtitle'] }}</p>
                    </div>
                    @if($item['canManage'])
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                    @else
                        <span class="chip-slate">Read-only</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Backup & Export actions --}}
        <h2 class="section-title mt-6">Backup & Export</h2>
        <div class="card-soft space-y-3">
            @forelse ($group->backups as $backup)
                <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-800">Snapshot #{{ $backup->id }}</p>
                        <p class="text-[11px] text-slate-500">{{ $backup->created_at?->diffForHumans() }} · {{ $backup->creator->name ?? 'System' }}</p>
                    </div>
                </div>
            @empty
                <p class="rounded-2xl bg-slate-50 px-3 py-3 text-center text-[11px] text-slate-500">Belum ada snapshot backup.</p>
            @endforelse

            <form method="POST" action="{{ route('settings.backup', $group) }}">
                @csrf
                <button type="submit" class="w-full rounded-2xl py-3 text-sm font-semibold transition {{ $canCreateBackup ? 'bg-slate-900 text-white hover:bg-slate-800' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}" @if(! $canCreateBackup) disabled @endif>
                    + Buat Backup Snapshot
                </button>
            </form>

            <div class="grid grid-cols-2 gap-2.5">
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="pdf">
                    <button type="submit" class="w-full rounded-2xl py-3 text-xs font-bold transition {{ $canExportChat ? 'bg-rose-500 text-white hover:bg-rose-600' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}" @if(! $canExportChat) disabled @endif>Export PDF</button>
                </form>
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="docx">
                    <button type="submit" class="w-full rounded-2xl border py-3 text-xs font-bold transition {{ $canExportChat ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}" @if(! $canExportChat) disabled @endif>Export DOCX</button>
                </form>
            </div>
        </div>

        <div class="h-6"></div>
    </section>

    <script>
        function toggleSettingsGroupPassword() {
            const input = document.getElementById('group_password');
            const show = document.getElementById('settingsPassShow');
            const hide = document.getElementById('settingsPassHide');
            if (!input) return;
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            if (show && hide) {
                show.classList.toggle('hidden', makeVisible);
                hide.classList.toggle('hidden', !makeVisible);
            }
        }
    </script>
@endsection
