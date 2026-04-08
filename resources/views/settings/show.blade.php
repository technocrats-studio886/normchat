@extends('layouts.app', ['title' => 'Settings - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell pt-4">
        @php
            $canEditProfile = $canEditProfile ?? ($canManageSettings ?? false);
            $canManageBilling = $canManageBilling ?? false;
            $canManageAiPersona = $canManageAiPersona ?? false;
            $canExportChat = $canExportChat ?? false;
            $canCreateBackup = $canCreateBackup ?? false;
            $isReadOnly = ! $canEditProfile;
        @endphp

        <h1 class="mb-5 text-xl font-extrabold text-slate-900 font-display">Group Settings</h1>

        @if(session('success'))
            <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if($isReadOnly)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                Kamu sedang membuka mode read-only. Hanya owner/admin dengan izin yang dapat mengubah pengaturan grup.
            </div>
        @endif

        <div class="space-y-3">
            {{-- Group Profile Editor --}}
            <form method="POST" action="{{ route('settings.profile.update', $group) }}" class="panel-card px-4 py-3.5 space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Profil Grup</h3>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Active</span>
                </div>

                <div>
                    <label for="group_name" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Group</label>
                    <input id="group_name" type="text" name="name" value="{{ old('name', $group->name) }}" required @if($isReadOnly) readonly @endif class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" />
                </div>

                <div>
                    <label for="group_description" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Deskripsi</label>
                    <textarea id="group_description" name="description" rows="2" @if($isReadOnly) readonly @endif class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif">{{ old('description', $group->description) }}</textarea>
                </div>

                <div>
                    <label for="group_password" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Password Grup Baru</label>
                    <p class="text-[11px] text-slate-500">Kosongkan jika tidak ingin ganti password.</p>
                    <div class="relative mt-1">
                        <input id="group_password" type="password" name="password" @if($isReadOnly) readonly @endif class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 @if($isReadOnly) cursor-not-allowed bg-slate-50 text-slate-500 @endif" placeholder="Masukkan password baru" />
                        @if(! $isReadOnly)
                        <button type="button" onclick="toggleSettingsGroupPassword()" class="absolute inset-y-0 right-2 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat password" title="Lihat password">
                            <svg id="settingsPassShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg id="settingsPassHide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="approval_enabled" value="1" class="rounded border-slate-300" {{ old('approval_enabled', $group->approval_enabled) ? 'checked' : '' }} @if($isReadOnly) disabled @endif>
                    Wajib approval sebelum member baru masuk
                </label>

                @if($isReadOnly)
                    <button type="button" disabled class="w-full cursor-not-allowed rounded-xl bg-slate-200 py-2.5 text-sm font-semibold text-slate-500">
                        Read-Only (Tidak bisa mengubah)
                    </button>
                @else
                    <button type="submit" class="w-full rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Simpan Pengaturan Grup
                    </button>
                @endif
            </form>

            {{-- History & Export --}}
            @if($canManageBilling)
            <a href="{{ route('settings.history', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">History & Export</span>
                    <p class="text-[11px] text-slate-400">Backup, export PDF/DOCX</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>
            @else
            <div class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">History & Export</span>
                    <p class="text-[11px] text-slate-400">Hanya owner/admin yang bisa mengakses halaman ini</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Locked</span>
            </div>
            @endif

            {{-- Seat Management --}}
            @if($canManageBilling)
            <a href="{{ route('settings.seats', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">Seat Management</span>
                    <p class="text-[11px] text-slate-400">Kelola kapasitas anggota grup</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>
            @else
            <div class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">Seat Management</span>
                    <p class="text-[11px] text-slate-400">Hanya owner/admin yang bisa mengelola seat</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Locked</span>
            </div>
            @endif

            {{-- AI Persona Editor --}}
            @if($canManageAiPersona)
            <a href="{{ route('settings.ai.persona', $group) }}" class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">AI Persona Editor</span>
                    <p class="text-[11px] text-slate-400">Atur gaya bicara AI untuk grup ini</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </a>
            @else
            <div class="panel-card flex items-center justify-between px-4 py-3.5">
                <div>
                    <span class="text-sm text-slate-700">AI Persona Editor</span>
                    <p class="text-[11px] text-slate-400">Hanya owner/admin yang bisa ubah persona AI</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Locked</span>
            </div>
            @endif
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
                    <button type="submit" class="w-full rounded-xl py-3 text-sm font-semibold transition {{ $canCreateBackup ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'cursor-not-allowed bg-slate-200 text-slate-500' }}" @if(! $canCreateBackup) disabled @endif>
                        Buat Backup Snapshot
                    </button>
                </form>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="pdf">
                    <button type="submit" class="w-full rounded-xl py-3 text-sm font-bold transition {{ $canExportChat ? 'bg-red-500 text-white hover:bg-red-600' : 'cursor-not-allowed bg-slate-200 text-slate-500' }}" @if(! $canExportChat) disabled @endif>Export PDF</button>
                </form>
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="docx">
                    <button type="submit" class="w-full rounded-xl border py-3 text-sm font-bold transition {{ $canExportChat ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500' }}" @if(! $canExportChat) disabled @endif>Export DOCX</button>
                </form>
            </div>
        </div>

        {{-- AI & Normkredit Info --}}
        <div id="ai-provider" class="mt-8">
            <h2 class="mb-4 text-xl font-extrabold text-slate-900 font-display">AI & Normkredit</h2>

            @php
                $gt = $group->groupToken;
                $credits = $gt ? $gt->credits : 0;
            @endphp

            <div class="space-y-3">
                <div class="panel-card px-4 py-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-700">AI Assistant</span>
                        <span class="text-sm font-bold text-slate-900">NormAI Aktif</span>
                    </div>
                </div>

                <div class="panel-card px-4 py-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-700">Saldo Normkredit</span>
                        <span class="text-sm font-bold {{ $credits > 0 ? 'text-emerald-600' : 'text-rose-500' }}">{{ number_format($credits, 1) }} normkredit</span>
                    </div>
                    <p class="mt-0.5 text-[11px] text-slate-400">1 normkredit = 1.000 token = Rp1.000</p>
                    @if($canManageBilling)
                        <a href="{{ route('subscription.tokens.buy') }}" class="mt-2 block text-xs font-semibold text-blue-500 hover:text-blue-700">
                            Top-up Normkredit &rarr;
                        </a>
                    @endif
                </div>

                <div class="rounded-xl bg-blue-50 px-4 py-3 text-xs text-blue-600">
                    Normkredit milik grup. Top-up diproses instan tanpa payment gateway.
                </div>
            </div>
        </div>

        <div class="h-6"></div>
    </section>

    <script>
        function toggleSettingsGroupPassword() {
            const input = document.getElementById('group_password');
            const show = document.getElementById('settingsPassShow');
            const hide = document.getElementById('settingsPassHide');

            if (!input) {
                return;
            }

            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';

            if (show && hide) {
                show.classList.toggle('hidden', makeVisible);
                hide.classList.toggle('hidden', !makeVisible);
            }
        }
    </script>
@endsection
