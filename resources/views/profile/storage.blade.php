@extends('layouts.app', ['title' => 'Kelola Penyimpanan - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Kelola Penyimpanan</h1>
        <p class="mt-1 text-sm text-slate-500">Ringkasan penyimpanan dari konten yang kamu kirim.</p>

        {{-- Total --}}
        <div class="mt-5 rounded-2xl border border-[#dbe6ff] bg-white px-5 py-5 text-center shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total Penyimpanan</p>
            <p class="mt-1 font-display text-3xl font-extrabold text-blue-600">{{ $storage['total_human'] }}</p>
            <p class="mt-1 text-[11px] text-slate-500">Gabungan file, gambar, dan video di semua grup.</p>
        </div>

        {{-- Distribusi file --}}
        <div class="mt-4 rounded-2xl border border-[#dbe6ff] bg-white px-4 py-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">File tersebar</h2>
            <p class="mt-0.5 text-[11px] text-slate-500">Jenis konten yang paling banyak kamu unggah.</p>

            @php
                $total = max(1, (int) $storage['total_bytes']);
                $palette = [
                    'Gambar' => ['bar' => 'bg-blue-500', 'dot' => 'bg-blue-500'],
                    'Video' => ['bar' => 'bg-amber-500', 'dot' => 'bg-amber-500'],
                    'File' => ['bar' => 'bg-emerald-500', 'dot' => 'bg-emerald-500'],
                ];
            @endphp

            @if($storage['total_bytes'] > 0)
                <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                    @foreach($storage['breakdown'] as $row)
                        @php $pct = (int) round(($row['bytes'] / $total) * 100); @endphp
                        @if($pct > 0)
                            <div class="{{ $palette[$row['label']]['bar'] ?? 'bg-slate-400' }}" style="width: {{ $pct }}%"></div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="mt-4 space-y-2">
                @foreach($storage['breakdown'] as $row)
                    @php $pct = $storage['total_bytes'] > 0 ? (int) round(($row['bytes'] / $total) * 100) : 0; @endphp
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $palette[$row['label']]['dot'] ?? 'bg-slate-400' }}"></span>
                            <span class="text-sm text-slate-800">{{ $row['label'] }}</span>
                            <span class="text-[11px] text-slate-400">({{ $row['count'] }} item)</span>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-900">{{ $row['human'] }}</p>
                            <p class="text-[10px] text-slate-400">{{ $pct }}%</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Detail per grup --}}
        <div class="mt-4 rounded-2xl border border-[#dbe6ff] bg-white px-4 py-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">Detail penyimpanan tiap grup</h2>
            <p class="mt-0.5 text-[11px] text-slate-500">Urut dari grup dengan kontribusi penyimpanan terbesar.</p>

            <div class="mt-3 space-y-2">
                @forelse($perGroup as $row)
                    @php $pct = $storage['total_bytes'] > 0 ? (int) round(($row['total_bytes'] / $total) * 100) : 0; @endphp
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $row['name'] }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">{{ $row['total_count'] }} item</p>
                            </div>
                            <p class="shrink-0 text-sm font-bold text-blue-600">{{ $row['total_human'] }}</p>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-blue-500" style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Gambar</p>
                                <p class="text-[11px] font-semibold text-slate-700">{{ $row['image_human'] }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Video</p>
                                <p class="text-[11px] font-semibold text-slate-700">{{ $row['video_human'] }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">File</p>
                                <p class="text-[11px] font-semibold text-slate-700">{{ $row['file_human'] }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-5 text-center text-xs text-slate-500">
                        Belum ada penyimpanan. Kirim file, gambar, atau video di grup untuk mulai mengisinya.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
