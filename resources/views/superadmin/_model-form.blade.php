{{-- Field form katalog model. Param: $aiModel (nullable) --}}
@php $m = $aiModel ?? null; @endphp
<div class="grid grid-cols-2 gap-3">
    <div class="col-span-2">
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="model_id">Model ID OpenAI</label>
        <input id="model_id" name="model_id" value="{{ old('model_id', $m?->model_id) }}" required placeholder="gpt-6-luna" class="input-field mt-1.5 font-mono" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="label">Label</label>
        <input id="label" name="label" value="{{ old('label', $m?->label) }}" required placeholder="Fast" class="input-field mt-1.5" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="sort_order">Urutan</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $m?->sort_order ?? 0) }}" class="input-field mt-1.5" />
    </div>
    <div class="col-span-2">
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="description">Deskripsi untuk user</label>
        <input id="description" name="description" value="{{ old('description', $m?->description) }}" class="input-field mt-1.5" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="input_usd_per_mtok">Input $/1M</label>
        <input id="input_usd_per_mtok" name="input_usd_per_mtok" type="number" step="0.000001" min="0" required value="{{ old('input_usd_per_mtok', $m?->input_usd_per_mtok) }}" class="input-field mt-1.5" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="cached_input_usd_per_mtok">Cached $/1M</label>
        <input id="cached_input_usd_per_mtok" name="cached_input_usd_per_mtok" type="number" step="0.000001" min="0" value="{{ old('cached_input_usd_per_mtok', $m?->cached_input_usd_per_mtok) }}" placeholder="= input" class="input-field mt-1.5" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="output_usd_per_mtok">Output $/1M</label>
        <input id="output_usd_per_mtok" name="output_usd_per_mtok" type="number" step="0.000001" min="0" required value="{{ old('output_usd_per_mtok', $m?->output_usd_per_mtok) }}" class="input-field mt-1.5" />
    </div>
    <div>
        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="multiplier">Multiplier ×</label>
        <input id="multiplier" name="multiplier" type="number" step="0.01" min="1" max="20" required value="{{ old('multiplier', $m?->multiplier ?? 2) }}" class="input-field mt-1.5" />
    </div>
    {{-- Kunci saldo grup (App\Services\Credits\ModelAccess): kelipatan modal pembuatan grup. --}}
    @php($groupCreationCredits = app(\App\Services\NormkreditPricing::class)->groupCreationCredits())
    @foreach(['unlock_multiple' => 'Terbuka saat saldo grup ≥ … × modal', 'lock_multiple' => 'Terkunci lagi saat saldo < … × modal'] as $field => $fieldLabel)
        <div>
            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="{{ $field }}">{{ $fieldLabel }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="number" step="0.1" min="0" max="20" value="{{ old($field, $m?->{$field}) }}" placeholder="Kosong = tanpa kunci" class="input-field mt-1.5" />
        </div>
    @endforeach
    <p class="col-span-2 text-[11px] text-slate-500">Modal grup sekarang {{ $groupCreationCredits }} NK. Contoh 2 dan 1: terbuka di {{ 2 * $groupCreationCredits }} NK, terkunci lagi di bawah {{ $groupCreationCredits }} NK, dan model ini tidak boleh memakai {{ $groupCreationCredits }} NK terakhir.</p>
    @foreach(['image_model' => 'Mesin gambar', 'image_model_precise' => 'Mesin gambar presisi (opsional)'] as $field => $fieldLabel)
        <div>
            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500" for="{{ $field }}">{{ $fieldLabel }}</label>
            <select id="{{ $field }}" name="{{ $field }}" class="input-field mt-1.5">
                @if($field === 'image_model_precise')
                    <option value="">— tidak ada —</option>
                @endif
                @foreach(config('normchat.image_models') as $engineId => $engine)
                    <option value="{{ $engineId }}" @selected(old($field, $m?->{$field} ?? ($field === 'image_model' ? 'gpt-image-2.5-flare' : null)) === $engineId)>{{ $engine['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
    <label class="flex items-center gap-2 text-xs text-slate-600">
        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300" {{ old('is_active', $m?->is_active ?? true) ? 'checked' : '' }}>
        Aktif
    </label>
    <label class="flex items-center gap-2 text-xs text-slate-600">
        <input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-slate-300" {{ old('is_default', $m?->is_default ?? false) ? 'checked' : '' }}>
        Default grup baru
    </label>
</div>
@if($errors->any())
    <div class="mt-3 rounded-xl bg-coral-50 px-3 py-2 text-xs text-coral-700">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
