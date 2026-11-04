@extends('layouts.admin')

@section('heading', 'Pengumuman')

@section('actions')
    <a href="{{ route('superadmin.announcements.create') }}" class="btn-primary px-3 py-2 text-sm">Buat pengumuman</a>
@endsection

@section('content')
    @php
        $states = ['draf' => ['Draf', ''], 'dijadwalkan' => ['Dijadwalkan', 'is-ok'], 'tayang' => ['Tayang', 'is-ok'], 'selesai' => ['Selesai', '']];
        $when = fn ($time) => $time ? \App\Support\UserTime::format($time, 'j M Y, H.i') : null;
    @endphp

    <p class="mb-3 text-sm text-ink-soft">Tampil ke semua user di aplikasi. Yang sudah ditutup user muncul lagi setelah ia masuk ulang, sampai masa tayang habis.</p>

    <div class="nc-table-wrap">
        <table class="nc-table">
            <thead><tr><th>Pengumuman</th><th class="max-sm:hidden">Bentuk</th><th class="max-md:hidden">Jadwal</th><th class="max-sm:hidden">Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse($announcements as $item)
                    @php([$label, $class] = $states[$item->state()])
                    <tr>
                        <td class="max-w-[18rem] max-sm:max-w-[13rem]">
                            <a href="{{ route('superadmin.announcements.edit', $item) }}" class="block truncate font-semibold hover:underline">{{ $item->title }}</a>
                            <span class="block truncate text-xs text-ink-soft">{{ \Illuminate\Support\Str::limit($item->body, 80) }}</span>
                            <span class="nc-status {{ $class }} mt-1 sm:hidden">{{ $label }}</span>
                        </td>
                        <td class="max-sm:hidden">{{ $item->display === 'banner' ? 'Banner' : 'Popup' }}{{ $item->tone === 'penting' ? ' · penting' : '' }}</td>
                        <td class="max-md:hidden">{{ $when($item->starts_at) ?? 'Segera' }} – {{ $when($item->ends_at) ?? 'tanpa batas' }}</td>
                        <td class="max-sm:hidden"><span class="nc-status {{ $class }}">{{ $label }}</span></td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('superadmin.announcements.toggle', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary px-2.5 py-1 text-xs">{{ in_array($item->state(), ['tayang', 'dijadwalkan'], true) ? 'Hentikan' : 'Tayangkan' }}</button>
                                </form>
                                <a href="{{ route('superadmin.announcements.create', ['copy' => $item->id]) }}" class="btn-secondary px-2.5 py-1 text-xs max-sm:hidden">Duplikat</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-ink-soft">Belum ada pengumuman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $announcements->links() }}</div>
@endsection
