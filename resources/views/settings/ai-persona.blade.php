@extends('layouts.app', ['title' => 'Normi AI - '.$group->name, 'group' => $group])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('settings.show', $group)" title="Normi AI" subtitle="Cara Normi AI menjawab di grup ini." />

        @if($canManageAiPersona ?? false)
            <form method="POST" action="{{ route('settings.ai.model.save', $group) }}" class="nc-section">
                @csrf
                <h2 class="nc-section-title">Model bawaan grup</h2>
                <p class="nc-section-note">Dipakai bila anggota tidak memilih model lain di kolom ketik.</p>
                <div class="nc-list" role="radiogroup" aria-label="Model bawaan grup">
                    @forelse($aiModels as $aiModel)
                        <label class="nc-list-row cursor-pointer hover:bg-surface-2">
                            <input type="radio" name="ai_model" value="{{ $aiModel->model_id }}" class="h-4 w-4 shrink-0 accent-primary" @checked($currentAiModel?->is($aiModel))>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">{{ $aiModel->label }}</span>
                                @if($aiModel->description)
                                    <span class="block text-xs text-ink-soft">{{ $aiModel->description }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="nc-list-empty">Belum ada model yang bisa dipakai.</p>
                    @endforelse
                </div>
                @error('ai_model')
                    <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>
                @enderror
                @if($aiModels->isNotEmpty())
                    <button type="submit" class="btn-primary mt-3 w-full py-2.5">Simpan model</button>
                @endif
            </form>

            <form method="POST" action="{{ route('settings.ai.persona.save', $group) }}" class="nc-section">
                @csrf
                <h2 class="nc-section-title">Kepribadian</h2>
                <div class="nc-list space-y-3 p-4">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Gaya bicara</span>
                        <textarea name="ai_persona_style" rows="3" class="nc-input mt-1 w-full" placeholder="Contoh: singkat, santai, langsung ke inti.">{{ old('ai_persona_style', $group->ai_persona_style) }}</textarea>
                    </label>
                    @error('ai_persona_style')
                        <p class="text-xs text-danger-ink">{{ $message }}</p>
                    @enderror
                    <label class="block">
                        <span class="text-xs font-semibold text-ink-soft">Batasan</span>
                        <textarea name="ai_persona_guardrails" rows="2" class="nc-input mt-1 w-full" placeholder="Contoh: jangan bahas di luar topik grup.">{{ old('ai_persona_guardrails', $group->ai_persona_guardrails) }}</textarea>
                    </label>
                    @error('ai_persona_guardrails')
                        <p class="text-xs text-danger-ink">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-primary w-full py-2.5">Simpan kepribadian</button>
                </div>
            </form>
        @else
            {{-- Anggota biasa: pengaturan ditampilkan apa adanya, tanpa form yang dinonaktifkan. --}}
            <div class="nc-list">
                <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Model bawaan</p><p class="text-sm font-semibold">{{ $currentAiModel?->label ?? 'Belum dipilih' }}</p>@if($currentAiModel?->description)<p class="text-xs text-ink-soft">{{ $currentAiModel->description }}</p>@endif</div></div>
                <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Gaya bicara</p><p class="whitespace-pre-line text-sm">{{ $group->ai_persona_style ?: 'Belum diatur' }}</p></div></div>
                <div class="nc-list-row"><div class="min-w-0 flex-1"><p class="text-xs text-ink-soft">Batasan</p><p class="whitespace-pre-line text-sm">{{ $group->ai_persona_guardrails ?: 'Belum diatur' }}</p></div></div>
            </div>
            <p class="nc-section-note mt-2">Pemilik atau admin grup yang bisa mengubahnya.</p>
        @endif
    </section>
@endsection
