@extends('layouts.app', ['title' => 'Gabung '.$group->name.' - Normi Chat'])

{{-- Alur gabung (docs/rancangan-info-grup.md): profil grup → Bergabung → kata sandi → tagihan patungan.
     $step: profile | password | pay | free | member. Keanggotaan aktif hanya setelah bayar (atau slot gratis). --}}
@section('content')
    @php
        $fmtNk = fn ($value) => number_format((float) $value, 2, ',', '.');
        $balance = (float) ($group->groupToken?->credits ?? 0);
    @endphp
    <section class="page-shell flex flex-col items-center px-6 pt-10" data-join-step="{{ $step }}">
        <div class="w-full max-w-sm">
            {{-- Profil grup (tampil di setiap langkah, ringkas setelah langkah pertama) --}}
            <div class="flex flex-col items-center text-center">
                <span class="nc-info-avatar {{ $step === 'profile' || $step === 'member' ? 'h-24 w-24 text-3xl' : 'h-16 w-16 text-xl' }}"><x-group-photo :group="$group" :join="true" /></span>
                <h1 class="mt-3 font-display text-xl font-extrabold">{{ $group->name }}</h1>
                <p class="mt-0.5 text-sm text-ink-soft">Grup · {{ $memberCount }} anggota</p>
                @if($group->description && in_array($step, ['profile', 'member'], true))
                    <p class="mt-3 whitespace-pre-line text-sm">{{ $group->description }}</p>
                @endif
            </div>

            @if($step === 'member')
                <div class="mt-6 rounded-xl border border-primary-line bg-primary-soft px-4 py-3 text-sm text-primary-ink">Kamu sudah bergabung di grup ini.</div>
                <a href="{{ route('chat.show', $group) }}" class="btn-cta mt-4 block text-center">Buka chat</a>

            @elseif($step === 'profile')
                <p class="mt-6 text-center text-sm text-ink-soft">Kamu diundang ke grup ini. Anggotanya mengobrol dan bertanya ke @normi bersama, memakai satu saldo Normkredit.</p>
                <a href="{{ route('groups.join.continue', $group->share_id) }}" class="btn-cta mt-5 block text-center" data-join-start>Bergabung</a>
                <p class="mt-3 text-center text-xs text-ink-soft">Kode grup {{ $group->share_id }}</p>

            @elseif($step === 'password')
                <form method="POST" action="{{ route('groups.join.password', $group->share_id) }}" class="mt-6 space-y-3">
                    @csrf
                    <div class="panel-card px-4 py-3">
                        <label for="joinGroupPasswordInput" class="text-sm font-semibold">Masukkan kata sandi grup</label>
                        <p class="mt-0.5 text-xs text-ink-soft">Minta kata sandinya ke pemilik atau admin grup.</p>
                        <div class="relative mt-2">
                            <input id="joinGroupPasswordInput" type="password" name="password" required autofocus autocomplete="off"
                                   placeholder="Kata sandi" class="nc-input w-full pr-10" />
                            <button type="button" data-password-toggle="joinGroupPasswordInput" class="absolute inset-y-0 right-3 inline-flex items-center text-ink-soft" aria-label="Lihat kata sandi">
                                <svg data-eye-icon="off" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                <svg data-eye-icon="on" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-2 text-xs font-semibold text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-cta w-full">Lanjut</button>
                    <a href="{{ route('groups.join', $group->share_id) }}" class="block text-center text-sm font-semibold text-ink-soft hover:underline">Kembali</a>
                </form>

            @elseif($step === 'free')
                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-6 space-y-3" data-join-free>
                    @csrf
                    <div class="rounded-2xl border border-primary-line bg-primary-soft px-4 py-3">
                        <p class="text-sm font-semibold text-primary-ink">Gabung gratis</p>
                        <p class="mt-1 text-xs text-primary-ink">Masih ada {{ $group->freeSlotsLeft() }} slot gratis di grup ini. Kamu langsung ikut memakai saldo grup sebesar <strong>{{ $fmtNk($balance) }} Normkredit</strong> bersama anggota lain.</p>
                        @if($group->member_daily_credit_limit)
                            <p class="mt-1 text-xs text-primary-ink">Setiap anggota bisa memakai paling banyak {{ $fmtNk($group->member_daily_credit_limit) }} Normkredit per hari.</p>
                        @endif
                    </div>
                    <button type="submit" class="btn-cta w-full">Masuk grup</button>
                </form>

            @else
                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-6 space-y-3" data-join-bill>
                    @csrf
                    <div class="rounded-2xl border border-primary-line bg-primary-soft px-4 py-3">
                        <p class="text-sm font-semibold text-primary-ink">Tagihan patungan</p>
                        <p class="mt-1 text-xs text-primary-ink">Bayar patungan dulu untuk masuk ke grup. Patunganmu menambah saldo grup yang sekarang <strong>{{ $fmtNk($balance) }} Normkredit</strong> dan dipakai bersama.</p>
                        @if($group->member_daily_credit_limit)
                            <p class="mt-1 text-xs text-primary-ink">Setiap anggota bisa memakai paling banyak {{ $fmtNk($group->member_daily_credit_limit) }} Normkredit per hari.</p>
                        @endif
                    </div>
                    @include('partials.package-picker', ['label' => 'Patungan', 'submitLabel' => 'Bayar dan gabung'])
                    @error('payment')<p class="text-xs text-danger-ink">{{ $message }}</p>@enderror
                    <p class="text-center text-xs text-ink-soft">Kamu masuk ke grup setelah pembayaran QRIS berhasil.</p>
                </form>
            @endif
        </div>
    </section>
@endsection
