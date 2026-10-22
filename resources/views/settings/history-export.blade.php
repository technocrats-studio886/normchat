@extends('layouts.app', ['title' => 'Riwayat & Cadangan - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        @php
            $canExportChat = $canExportChat ?? false;
            $canCreateBackup = $canCreateBackup ?? false;
            $canRestoreBackup = $canRestoreBackup ?? false;
            $isReadOnly = ! $canExportChat && ! $canCreateBackup && ! $canRestoreBackup;
        @endphp

        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('chat.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#121C29]">Riwayat & Cadangan</h1>
        </div>

        @if($isReadOnly)
            <div class="mb-4 flex items-start gap-2 rounded-2xl border border-coral-200 bg-coral-50 px-4 py-3 text-xs text-coral-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                <span>Hanya pemilik atau admin yang bisa mengunduh, mencadangkan, dan memulihkan chat.</span>
            </div>
        @endif

        <p class="mb-4 text-sm text-[#64748B]">Unduh isi chat atau simpan cadangannya.</p>

        <h2 class="mb-2 mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Unduhan</h2>
        <div class="mb-5 space-y-3">
            @forelse ($group->exports as $export)
                <div class="flex items-center justify-between rounded-xl border border-[#C9D2DF] bg-white px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[#121C29]">{{ $export->file_name ?: ($group->name.'.'.$export->file_type) }}</p>
                        <p class="mt-1 text-xs text-[#64748B]">{{ $export->created_at?->format('d M Y, H:i') }} • {{ strtoupper($export->file_type) }} • {{ ['queued' => 'Menunggu', 'processing' => 'Diproses', 'done' => 'Siap', 'failed' => 'Gagal'][$export->status] ?? $export->status }}</p>
                    </div>
                    @if($export->status === 'done' && $export->storage_path)
                        <a href="{{ route('settings.export.download', [$group, $export]) }}" class="shrink-0 rounded-lg bg-[#C43D2E] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#A1342A]">Unduh</a>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[#C9D2DF] bg-white p-5 text-center text-sm text-[#64748B]">
                    Belum ada unduhan.
                </div>
            @endforelse
        </div>

        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Cadangan</h2>
        <div class="space-y-3">
            @forelse ($group->backups as $backup)
                <div class="rounded-xl border border-[#C9D2DF] bg-white px-4 py-3">
                    <p class="text-sm font-bold text-[#121C29]">Cadangan #{{ $backup->id }}</p>
                    <p class="mt-1 text-xs text-[#64748B]">{{ $backup->created_at?->format('d M Y, H:i') }} • oleh {{ $backup->creator->name ?? 'Sistem' }}</p>
                    <form method="POST" action="{{ route('settings.backup.restore', [$group, $backup]) }}" class="mt-3">
                        @csrf
                        <input type="text" name="reason" placeholder="Alasan (opsional)" class="mb-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 outline-none" @if(! $canRestoreBackup) readonly @endif />
                        <button type="submit" class="w-full rounded-lg border py-2 text-xs font-semibold transition {{ $canRestoreBackup ? 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}" @if(! $canRestoreBackup) disabled @endif>
                            Pulihkan chat ke cadangan ini
                        </button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[#C9D2DF] bg-white p-5 text-center text-sm text-[#64748B]">
                    Belum ada cadangan.
                </div>
            @endforelse
        </div>

        <div class="mt-4 space-y-3">
            <form method="POST" action="{{ route('settings.backup', $group) }}">
                @csrf
                <button type="submit" class="w-full rounded-xl border py-3 text-sm font-semibold transition {{ $canCreateBackup ? 'border-[#C9D2DF] bg-white text-[#121C29] hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}" @if(! $canCreateBackup) disabled @endif>
                    Buat Cadangan
                </button>
            </form>

            <div class="grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="pdf" />
                    <button type="submit" class="w-full rounded-xl py-3 text-sm font-bold normal-case tracking-normal transition {{ $canExportChat ? 'bg-[#C43D2E] text-white hover:bg-[#A1342A]' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}" @if(! $canExportChat) disabled @endif>
                        Unduh PDF
                    </button>
                </form>

                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="docx" />
                    <button type="submit" class="w-full rounded-xl border py-3 text-sm font-bold transition {{ $canExportChat ? 'border-[#C9D2DF] bg-white text-[#121C29] hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}" @if(! $canExportChat) disabled @endif>
                        Unduh Word
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
