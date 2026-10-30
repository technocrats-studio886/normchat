@extends('layouts.app', ['title' => 'Buat Grup - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('groups.index')" title="Buat Grup" subtitle="Ngobrol bareng teman dan pakai Normi AI bersama." />

        <form method="POST" action="{{ route('groups.store') }}" class="mt-6 space-y-3" id="createGroupForm">
            @csrf

            {{-- Nama grup --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Grup</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Tim Marketing" required class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400" />
            </div>

            {{-- Deskripsi --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Deskripsi</label>
                <textarea name="description" rows="2" placeholder="Opsional" class="mt-1 w-full resize-none bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400">{{ old('description') }}</textarea>
            </div>

            {{-- Password --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kata Sandi Grup</label>
                <p class="text-xs text-slate-500">Dipakai teman saat bergabung.</p>
                <div class="relative mt-2">
                    <input id="groupPasswordInput" type="password" name="password" value="{{ old('password') }}" placeholder="Buat kata sandi" required minlength="4" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    <button type="button" data-password-toggle="groupPasswordInput" class="absolute inset-y-0 right-2 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat kata sandi" title="Lihat kata sandi">
                        <svg data-eye-icon="off" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg data-eye-icon="on" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="panel-card px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Normkredit Awal</p>
                <p class="mt-1 text-sm text-slate-700"><strong>{{ number_format($quote->credits, 0, ',', '.') }} Normkredit</strong> · Rp{{ number_format($quote->baseAmount, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Dipakai semua anggota untuk Normi AI.</p>
            </div>

            @include('partials.payment-total', ['quote' => $quote])

            <button type="submit" class="btn-cta">Buat Grup · Rp{{ number_format($quote->baseAmount, 0, ',', '.') }}</button>
            @error('payment')
                <p class="px-1 text-xs text-danger-ink">{{ $message }}</p>
            @enderror
        </form>
    </section>
@endsection
