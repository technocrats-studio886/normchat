@extends('layouts.admin')

@section('heading', $announcement->exists ? 'Ubah pengumuman' : 'Buat pengumuman')
@section('back', route('superadmin.announcements.index'))

@section('content')
    @php
        $zone = \App\Support\UserTime::zone(auth()->user());
        $local = fn ($time) => $time ? $time->copy()->setTimezone($zone)->format('Y-m-d\TH:i') : '';
        $value = fn (string $key) => old($key, $announcement->{$key});
    @endphp

    <div class="grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <form method="POST" action="{{ $announcement->exists ? route('superadmin.announcements.update', $announcement) : route('superadmin.announcements.store') }}" class="nc-box space-y-4 p-4" data-announcement-form>
            @csrf
            @if($announcement->exists) @method('PUT') @endif

            <label class="nc-field"><span>Judul</span><input name="title" maxlength="80" required value="{{ $value('title') }}" class="nc-input" data-preview="title"></label>
            <label class="nc-field"><span>Isi</span><textarea name="body" rows="5" maxlength="1000" required class="nc-input" data-preview="body">{{ $value('body') }}</textarea></label>

            <div class="nc-form-grid">
                <label class="nc-field"><span>Tampil sebagai</span>
                    <select name="display" class="nc-input" data-preview="display">
                        <option value="popup" @selected($value('display') === 'popup')>Popup (tengah layar)</option>
                        <option value="banner" @selected($value('display') === 'banner')>Banner (strip di atas)</option>
                    </select>
                </label>
                <label class="nc-field"><span>Nada</span>
                    <select name="tone" class="nc-input" data-preview="tone">
                        <option value="info" @selected($value('tone') === 'info')>Info</option>
                        <option value="penting" @selected($value('tone') === 'penting')>Penting</option>
                    </select>
                </label>
                <label class="nc-field"><span>Teks tombol (opsional)</span><input name="cta_label" maxlength="40" value="{{ $value('cta_label') }}" class="nc-input" placeholder="Lihat detail" data-preview="cta"></label>
                <label class="nc-field"><span>Tautan tombol</span><input name="cta_url" maxlength="500" value="{{ $value('cta_url') }}" class="nc-input" placeholder="https://… atau /groups"></label>
                <label class="nc-field"><span>Mulai</span><input name="starts_at" type="datetime-local" value="{{ old('starts_at', $local($announcement->starts_at)) }}" class="nc-input"></label>
                <label class="nc-field"><span>Selesai (opsional)</span><input name="ends_at" type="datetime-local" value="{{ old('ends_at', $local($announcement->ends_at)) }}" class="nc-input"></label>
            </div>
            <p class="nc-field-hint">Mulai kosong = segera. Waktu mengikuti zona {{ $zone }}. Teks ditampilkan apa adanya (tanpa HTML).</p>

            <div class="flex flex-wrap gap-2">
                <button type="submit" name="publish" value="1" class="btn-primary px-4 py-2">{{ $announcement->is_active ? 'Simpan' : 'Simpan & tayangkan' }}</button>
                <button type="submit" name="publish" value="0" class="btn-secondary px-4 py-2">{{ $announcement->is_active ? 'Simpan sebagai draf' : 'Simpan draf' }}</button>
            </div>
        </form>

        <div>
            <p class="mb-2 text-sm font-semibold">Pratinjau</p>
            <div class="nc-box overflow-hidden" data-announcement-preview>
                @include('partials.announcement', ['announcement' => $announcement->exists ? $announcement : new \App\Models\Announcement(['title' => 'Judul pengumuman', 'body' => 'Isi pengumuman.', 'display' => 'popup', 'tone' => 'info']), 'preview' => true])
            </div>

            @if($announcement->exists)
                <form method="POST" action="{{ route('superadmin.announcements.destroy', $announcement) }}" class="mt-6" data-confirm="Hapus pengumuman ini?" data-confirm-label="Hapus">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="nc-button-danger px-4 py-2">Hapus pengumuman</button>
                </form>
            @endif
        </div>
    </div>
@endsection
