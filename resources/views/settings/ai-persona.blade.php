@extends('layouts.app', ['title' => 'AI Persona Editor - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#0F172A]">AI Persona Editor</h1>
        </div>

        <p class="mb-4 text-sm text-[#64748B]">Atur gaya bicara AI agar jawaban lebih relevan dengan budaya tim dan konteks proyek.</p>

        @if(!$group->ai_provider)
            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-white p-5 text-sm text-[#64748B]">
                Belum ada AI aktif. Provider dipilih saat membuat grup.
            </div>
        @else
            @php
                $providerLabel = config("ai_models.providers.{$group->ai_provider}.label", strtoupper($group->ai_provider));
                $modelLabel = config("ai_models.providers.{$group->ai_provider}.models.{$group->ai_model}.label", $group->ai_model ?? '-');
            @endphp
            <div class="space-y-3">
                <div class="panel-card p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-bold text-[#0F172A]">{{ $providerLabel }} — {{ $modelLabel }}</p>
                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                            Active
                        </span>
                    </div>

                    <label class="mb-1 block text-xs font-semibold text-[#64748B]">Persona Style</label>
                    <textarea rows="3" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none" placeholder="Contoh: ringkas, to-the-point, fokus solusi praktis."></textarea>

                    <label class="mb-1 mt-3 block text-xs font-semibold text-[#64748B]">Guardrails</label>
                    <textarea rows="2" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none" placeholder="Contoh: hindari jawaban di luar scope grup."></textarea>

                    <button type="button" class="btn-cta mt-3 py-2.5 normal-case tracking-normal">
                        Simpan Persona
                    </button>
                </div>
            </div>
        @endif
    </section>
@endsection
