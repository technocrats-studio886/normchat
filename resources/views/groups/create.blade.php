@extends('layouts.app', ['title' => 'Buat Group - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('groups.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Buat Group Chat</h1>
        <p class="mt-1 text-sm text-slate-500">Pilih AI provider, buat grup, dan mulai chatting.</p>

        <form method="POST" action="{{ route('groups.store') }}" class="mt-6 space-y-3" id="createGroupForm">
            @csrf

            {{-- Nama Group --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Group</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Tim Marketing" required class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400" />
            </div>

            {{-- Deskripsi --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Deskripsi</label>
                <textarea name="description" rows="2" placeholder="Deskripsi singkat grup ini" class="mt-1 w-full resize-none bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400">{{ old('description') }}</textarea>
            </div>

            {{-- Password --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Password Grup</label>
                <p class="text-xs text-slate-500">Member harus memasukkan password saat join via Share ID.</p>
                <input type="password" name="password" value="{{ old('password') }}" placeholder="Buat password grup" required minlength="4" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
            </div>

            {{-- AI Provider Selection --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pilih AI Provider</label>
                <p class="text-xs text-slate-500">Provider dan model akan digunakan oleh semua member di grup ini.</p>

                <input type="hidden" name="ai_provider" id="aiProviderInput" value="{{ old('ai_provider') }}" />
                <input type="hidden" name="ai_model" id="aiModelInput" value="{{ old('ai_model') }}" />

                <div class="mt-3 space-y-2" id="providerList">
                    @foreach(config('ai_models.providers') as $providerKey => $provider)
                        <div>
                            <button type="button"
                                    class="flex w-full items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition"
                                    data-provider-btn="{{ $providerKey }}"
                                    onclick="selectProvider('{{ $providerKey }}')">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white text-xs font-bold"
                                      style="background: {{ $provider['color'] }}">
                                    {{ strtoupper(substr($providerKey, 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-slate-900">{{ $provider['label'] }}</p>
                                    <p class="text-xs text-slate-500">{{ count($provider['models']) }} model tersedia</p>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition" data-provider-arrow="{{ $providerKey }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            {{-- Model dropdown --}}
                            <div class="mt-1 hidden space-y-1 pl-11" data-model-list="{{ $providerKey }}">
                                @foreach($provider['models'] as $modelKey => $model)
                                    <button type="button"
                                            class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition"
                                            data-model-btn="{{ $providerKey }}:{{ $modelKey }}"
                                            onclick="selectModel('{{ $providerKey }}', '{{ $modelKey }}')">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">{{ $model['label'] }}</p>
                                            <p class="text-[11px] text-slate-500">{{ $model['description'] }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold
                                                {{ $model['multiplier'] <= 1 ? 'bg-emerald-100 text-emerald-700' : ($model['multiplier'] <= 3 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                                {{ $model['multiplier'] }}x
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Selected model display --}}
                <div class="mt-3 hidden rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5" id="selectedModelDisplay">
                    <p class="text-xs font-semibold text-blue-600">Model dipilih:</p>
                    <p class="mt-0.5 text-sm font-bold text-blue-800" id="selectedModelLabel"></p>
                    <p class="text-[11px] text-blue-600" id="selectedModelMultiplier"></p>
                </div>
            </div>

            {{-- Owner Control --}}
            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Owner Control</label>
                <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="approval_enabled" value="1" class="rounded border-slate-300">
                    Wajib approval sebelum join
                </label>
            </div>

            {{-- Credit Info --}}
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs font-semibold text-emerald-700">10 normkredit dari subscription kamu akan dialokasikan ke grup ini.</p>
                <p class="mt-1 text-xs text-emerald-600">1 normkredit = 1.000 token = Rp1.000. Multiplier model mempengaruhi penggunaan token.</p>
            </div>

            <button type="submit" class="btn-cta" id="submitBtn" disabled>Pilih Provider Dulu</button>
        </form>
    </section>

    <script>
        let selectedProvider = '{{ old('ai_provider', '') }}';
        let selectedModel = '{{ old('ai_model', '') }}';

        function selectProvider(provider) {
            // Toggle model list visibility
            document.querySelectorAll('[data-model-list]').forEach(el => {
                el.classList.toggle('hidden', el.dataset.modelList !== provider);
            });

            // Style provider buttons
            document.querySelectorAll('[data-provider-btn]').forEach(btn => {
                const isActive = btn.dataset.providerBtn === provider;
                btn.className = 'flex w-full items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition '
                    + (isActive ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white');
            });

            // Rotate arrow
            document.querySelectorAll('[data-provider-arrow]').forEach(arrow => {
                arrow.style.transform = arrow.dataset.providerArrow === provider ? 'rotate(180deg)' : '';
            });

            selectedProvider = provider;

            // If switching provider, reset model
            if (selectedModel && !selectedModel.startsWith(provider)) {
                selectedModel = '';
                document.getElementById('aiModelInput').value = '';
                document.getElementById('selectedModelDisplay').classList.add('hidden');
                updateSubmitBtn();
            }

            document.getElementById('aiProviderInput').value = provider;
        }

        function selectModel(provider, model) {
            selectedProvider = provider;
            selectedModel = model;

            document.getElementById('aiProviderInput').value = provider;
            document.getElementById('aiModelInput').value = model;

            // Style model buttons
            document.querySelectorAll('[data-model-btn]').forEach(btn => {
                const isActive = btn.dataset.modelBtn === provider + ':' + model;
                btn.className = 'flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition '
                    + (isActive ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300');
            });

            // Show selected model display
            const models = @json(config('ai_models.providers'));
            const providerData = models[provider];
            const modelData = providerData?.models?.[model];

            if (modelData) {
                document.getElementById('selectedModelLabel').textContent = providerData.label + ' - ' + modelData.label;
                document.getElementById('selectedModelMultiplier').textContent =
                    'Multiplier: ' + modelData.multiplier + 'x — ' + modelData.description;
                document.getElementById('selectedModelDisplay').classList.remove('hidden');
            }

            updateSubmitBtn();
        }

        function updateSubmitBtn() {
            const btn = document.getElementById('submitBtn');
            if (selectedProvider && selectedModel) {
                btn.disabled = false;
                btn.textContent = 'Create Group';
            } else {
                btn.disabled = true;
                btn.textContent = 'Pilih Provider & Model Dulu';
            }
        }

        // Restore old values on validation error
        if (selectedProvider) {
            selectProvider(selectedProvider);
            if (selectedModel) {
                selectModel(selectedProvider, selectedModel);
            }
        }
    </script>
@endsection
