@extends('layouts.app', ['title' => 'History & Export - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">History & Export</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Lihat riwayat snapshot dan unduh percakapan dalam format profesional untuk dokumentasi tim.</p>

        <div class="space-y-3">
            @forelse ($group->backups as $backup)
                <div class="rounded-xl border border-[#CBD5E1] bg-white px-4 py-3">
                    <p class="text-sm font-bold text-[#0F172A]">Snapshot #{{ $backup->id }}</p>
                    <p class="mt-1 text-xs text-[#64748B]">{{ $backup->created_at?->format('d M Y, H:i') }} • dibuat oleh {{ $backup->creator->name ?? 'System' }}</p>
                    <form method="POST" action="{{ route('settings.backup.restore', [$group, $backup]) }}" class="mt-3">
                        @csrf
                        <input type="text" name="reason" placeholder="Alasan restore (opsional)" class="mb-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 outline-none" />
                        <button type="submit" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                            Restore Snapshot Ini
                        </button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-white p-5 text-center text-sm text-[#64748B]">
                    Belum ada snapshot tersimpan.
                </div>
            @endforelse
        </div>

        <div class="mt-4 space-y-3">
            <form method="POST" action="{{ route('settings.backup', $group) }}">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-[#CBD5E1] bg-white py-3 text-sm font-semibold text-[#0F172A] transition hover:bg-slate-50">
                    Buat Backup Snapshot
                </button>
            </form>

            <div class="grid grid-cols-2 gap-3">
                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="pdf" />
                    <button type="submit" class="btn-cta py-3 normal-case tracking-normal">
                        Export PDF
                    </button>
                </form>

                <form method="POST" action="{{ route('settings.export', $group) }}">
                    @csrf
                    <input type="hidden" name="file_type" value="docx" />
                    <button type="submit" class="w-full rounded-xl border border-[#CBD5E1] bg-white py-3 text-sm font-bold text-[#0F172A] transition hover:bg-slate-50">
                        Export DOCX
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
