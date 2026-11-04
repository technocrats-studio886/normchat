@extends('layouts.app', ['title' => 'Zona waktu - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('profile.show')" title="Zona waktu" subtitle="Dipakai untuk jam pesan, riwayat, ekspor, dan cadangan." />

        <form method="POST" action="{{ route('profile.timezone.save') }}" class="space-y-4" data-timezone-form>
            @csrf
            <div class="nc-settings-group">
                <label class="nc-settings-row cursor-pointer">
                    <input type="radio" name="timezone_mode" value="auto" class="h-4 w-4 accent-primary" @checked(old('timezone_mode', $user->timezone_mode) !== 'manual')>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Otomatis</span>
                        <span class="block text-xs text-ink-soft">Ikut zona waktu perangkat yang sedang dipakai</span>
                    </span>
                </label>
                <label class="nc-settings-row cursor-pointer">
                    <input type="radio" name="timezone_mode" value="manual" class="h-4 w-4 accent-primary" @checked(old('timezone_mode', $user->timezone_mode) === 'manual')>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Pilih sendiri</span>
                        <span class="block text-xs text-ink-soft">Tetap walau perangkat memakai zona lain</span>
                    </span>
                </label>
            </div>

            <label class="nc-field">
                <span>Zona waktu</span>
                <select name="timezone" class="nc-input w-full">
                    <optgroup label="Indonesia">
                        @foreach(\App\Http\Controllers\ProfileSettingsController::INDONESIA as $id => $label)
                            <option value="{{ $id }}" @selected(old('timezone', $zone) === $id)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Zona lain">
                        @foreach($others as $id)
                            <option value="{{ $id }}" @selected(old('timezone', $zone) === $id)>{{ str_replace('_', ' ', $id) }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <span class="nc-field-hint">Sekarang: {{ \App\Support\UserTime::format(now(), 'l, j F Y H.i', $user) }}</span>
            </label>
            @error('timezone')<p class="text-xs text-danger-ink">{{ $message }}</p>@enderror

            <button type="submit" class="btn-primary w-full py-2.5">Simpan</button>
        </form>
    </section>
@endsection
