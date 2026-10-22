@extends('layouts.app', ['title' => 'Normi AI - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        @php
            $canManageAiPersona = $canManageAiPersona ?? false;
        @endphp

        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('chat.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="font-display text-xl font-extrabold text-[#121C29]">Normi AI</h1>
        </div>

        @if(! $canManageAiPersona)
            <div class="mb-4 flex items-start gap-2 rounded-2xl border border-coral-200 bg-coral-50 px-4 py-3 text-xs text-coral-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                <span>Hanya pemilik atau admin yang bisa mengubah ini.</span>
            </div>
        @endif

        <p class="mb-4 text-sm text-[#64748B]">Atur cara Normi AI menjawab di grup ini.</p>

        @if(session('success'))
            <div class="mb-4 rounded-xl bg-navy-50 px-4 py-3 text-sm font-semibold text-navy-700">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.ai.model.save', $group) }}" class="panel-card mb-3 p-4">
            @csrf
            <p class="text-sm font-bold text-[#121C29]">Model bawaan grup</p>
            <div class="mt-3 space-y-2">
                @forelse($aiModels as $aiModel)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 px-3 py-2.5 transition has-[:checked]:border-navy-500 has-[:checked]:bg-navy-50 @if(! $canManageAiPersona) cursor-not-allowed opacity-70 @endif">
                        <input type="radio" name="ai_model" value="{{ $aiModel->model_id }}" class="mt-0.5 accent-navy-600"
                               {{ $currentAiModel?->is($aiModel) ? 'checked' : '' }} @if(! $canManageAiPersona) disabled @endif>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-800">{{ $aiModel->label }}</span>
                            @if($aiModel->description)
                                <span class="block text-xs text-slate-500">{{ $aiModel->description }}</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs text-slate-500">Belum ada model yang bisa dipakai.</p>
                @endforelse
            </div>
            @error('ai_model')
                <p class="mt-1 text-xs text-coral-500">{{ $message }}</p>
            @enderror
            @if($canManageAiPersona && $aiModels->isNotEmpty())
                <button type="submit" class="btn-cta mt-3 py-2.5 normal-case tracking-normal">Simpan</button>
            @endif
        </form>

        <form method="POST" action="{{ route('settings.ai.persona.save', $group) }}" class="space-y-3">
            @csrf
            <div class="panel-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-bold text-[#121C29]">Kepribadian</p>
                </div>

                    <label for="ai_persona_style" class="mb-1 block text-xs font-semibold text-[#64748B]">Gaya bicara</label>
                    <textarea id="ai_persona_style" name="ai_persona_style" rows="3"
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-navy-400 focus:ring-2 focus:ring-navy-100 @if(! $canManageAiPersona) cursor-not-allowed text-slate-500 @endif"
                              placeholder="Contoh: singkat, santai, langsung ke inti." @if(! $canManageAiPersona) readonly @endif>{{ old('ai_persona_style', $group->ai_persona_style) }}</textarea>

                    <label for="ai_persona_guardrails" class="mb-1 mt-3 block text-xs font-semibold text-[#64748B]">Batasan</label>
                    <textarea id="ai_persona_guardrails" name="ai_persona_guardrails" rows="2"
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-navy-400 focus:ring-2 focus:ring-navy-100 @if(! $canManageAiPersona) cursor-not-allowed text-slate-500 @endif"
                              placeholder="Contoh: jangan bahas di luar topik grup." @if(! $canManageAiPersona) readonly @endif>{{ old('ai_persona_guardrails', $group->ai_persona_guardrails) }}</textarea>

                    @error('ai_persona_style')
                        <p class="mt-1 text-xs text-coral-500">{{ $message }}</p>
                    @enderror
                    @error('ai_persona_guardrails')
                        <p class="mt-1 text-xs text-coral-500">{{ $message }}</p>
                    @enderror

                @if($canManageAiPersona)
                    <button type="submit" class="btn-cta mt-3 py-2.5 normal-case tracking-normal">
                        Simpan
                    </button>
                @else
                    <button type="button" disabled class="mt-3 w-full cursor-not-allowed rounded-xl bg-slate-100 py-2.5 text-sm font-semibold text-slate-400">
                        Hanya lihat
                    </button>
                @endif
            </div>
        </form>
    </section>
@endsection
