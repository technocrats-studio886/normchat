@extends('layouts.app', ['title' => $group->name.' - Normi Chat', 'group' => $group])

@section('content')
    <section class="fixed inset-y-0 left-1/2 flex w-full max-w-md -translate-x-1/2 flex-col overflow-hidden border-x border-slate-200/70 bg-[var(--nc-chat-bg)] lg:left-[var(--nc-sidebar-w)] lg:right-0 lg:w-auto lg:max-w-none lg:translate-x-0 lg:border-x-0 nc-sidebar-push" data-chat-shell="1" data-groups-url="{{ route('groups.index') }}" data-group-name="{{ $group->name }}" style="height: 100dvh; height: 100svh;">
        @php
            $gt = $group->groupToken;
            $credits = $gt ? $gt->credits : 0;
            $activeMembers = $group->members->where('status', 'active');
            $ownerInMembers = $activeMembers->contains('user_id', $group->owner_id);
            $memberCount = $activeMembers->count() + ($ownerInMembers ? 0 : 1);
            $groupInitial = strtoupper(substr($group->name, 0, 1));
            $viewerIsOwner = (int) $group->owner_id === (int) auth()->id();
            $viewerRoleKey = optional($activeMembers->firstWhere('user_id', auth()->id()))->role->key ?? null;
            $viewerIsModerator = $viewerIsOwner || in_array($viewerRoleKey, ['owner', 'admin'], true);
        @endphp

        {{-- Frozen header --}}
        <div class="nc-chat-header-card sticky top-0 z-30 mx-3 mt-3 flex items-center gap-3 rounded-3xl border px-3 py-2.5 shadow-sm backdrop-blur lg:mx-auto lg:w-[calc(100%-2rem)] lg:max-w-[var(--nc-room-max)]">
            <a href="{{ route('groups.index') }}" class="flex h-9 w-9 items-center justify-center rounded-2xl text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Kembali">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('settings.show', $group) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl transition hover:bg-slate-50" aria-label="Pengaturan grup">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-extrabold text-white" style="background: var(--nc-primary);">
                    {{ $groupInitial }}
                </div>
                <div class="min-w-0 flex-1 py-1">
                    <p class="nc-chat-header-title truncate text-sm font-semibold">{{ $group->name }}</p>
                    <p class="nc-chat-header-subtitle truncate text-[11px]">{{ $memberCount }} anggota</p>
                </div>
            </a>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                data-open-settings="1"
                aria-label="Menu grup"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5h.01M12 12h.01M12 19h.01" />
                </svg>
            </button>
        </div>

        {{-- Message search --}}
        <div class="hidden mx-3 mt-2 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm lg:mx-auto lg:w-[calc(100%-2rem)] lg:max-w-[var(--nc-room-max)]" data-message-search-bar="1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
            <input type="text" data-message-search-input="1" placeholder="Cari pesan" class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400" autocomplete="off" />
            <span class="text-[11px] font-medium text-slate-400" data-message-search-count="1"></span>
            <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100" data-close-search="1" aria-label="Tutup pencarian">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div
            class="nc-scroll mt-3 flex-1 overflow-y-auto px-4 py-3 pb-56 lg:px-[max(1.5rem,calc((100%_-_var(--nc-room-max))/2))]"
            data-chat-group-id="{{ $group->id }}"
            data-auth-user-id="{{ auth()->id() }}"
            data-chat-feed="1"
            data-message-count="{{ count($messages) }}"
            data-last-read-message-id="{{ $lastReadMessageId ?? 0 }}"
            data-latest-message-id="{{ $latestMessageId ?? 0 }}"
            data-unread-count="{{ $unreadCount ?? 0 }}"
            data-has-read-before="{{ ($lastReadMessageId ?? 0) > 0 ? '1' : '0' }}"
            data-chat-read-url="{{ route('chat.read', $group) }}"
            data-poll-stats-url="{{ route('chat.polls.stats', $group) }}"
            data-poll-vote-url-template="{{ route('chat.polls.vote', ['group' => $group, 'message' => '__MESSAGE__']) }}"
            data-viewer-is-moderator="{{ $viewerIsModerator ? '1' : '0' }}"
            data-messages-url="{{ route('chat.store', $group) }}"
            data-group-frozen="{{ $group->isFrozen() ? '1' : '0' }}"
        >
            @if(empty($messages))
                <p class="nc-feed-empty" data-feed-empty="1">Belum ada pesan. Sapa anggotamu, atau panggil <b>@normi</b> untuk bertanya.</p>
            @endif
            <div class="hidden pb-2 text-[11px] text-slate-400" data-typing-indicator="1"></div>
        </div>
        {{-- Pesan awal; dirender oleh resources/js/chat/feed.js dengan templat yang sama seperti pesan realtime. --}}
        <script type="application/json" data-forward-targets nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($forwardTargets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)</script>
        <script type="application/json" data-initial-messages nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($messages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)</script>

        {{-- Input bar --}}
        <div class="composer-shell">
            @if($group->isFrozen())
                <p class="nc-frozen-note" role="status">Grup ini dibekukan oleh admin Normi Chat. Pesan baru tidak bisa dikirim, riwayat tetap bisa dibaca.</p>
            @else
            <form method="POST" action="{{ route('chat.store', $group) }}" class="relative" enctype="multipart/form-data" data-chat-form="1">
                @csrf
                <input type="file" name="attachment" accept="{{ \App\Support\AttachmentRules::acceptAttribute() }}" class="hidden" data-chat-attachment="1" />
                <input type="hidden" name="reply_to_message_id" value="" data-reply-to-input="1" />
                <input type="hidden" name="reply_quote_text" value="" data-reply-quote-input="1" />

                <div class="hidden items-center justify-between gap-2 border-b border-primary-line bg-primary-soft px-4 py-2" data-reply-preview="1">
                    {{-- Ketuk untuk membuka pesan penuh dan memilih bagian yang dibalas (resources/js/chat/composer/quote-picker.js). --}}
                    <div class="flex min-w-0 flex-1 cursor-pointer items-center gap-2" role="button" tabindex="0" title="Pilih bagian yang dibalas" data-reply-preview-open="1">
                        <div class="h-8 w-1 shrink-0 rounded-full" style="background: var(--nc-primary);"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold text-slate-700" data-reply-preview-sender="1">Balas pesan</p>
                            <p class="truncate text-xs text-slate-600" data-reply-preview-content="1"></p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h12"/></svg>
                    </div>
                    <button type="button" class="rounded-md p-1 text-slate-500 hover:bg-primary-soft" data-reply-clear="1" aria-label="Batalkan balasan">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                @php
                    $aiModes = [
                        ['value' => 'guided_learning', 'label' => 'Guided Learning', 'hint' => 'Belajar bertahap', 'placeholder' => 'Mau belajar apa?', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.04A8.97 8.97 0 0 0 6 3.75c-1.05 0-2.06.18-3 .51v14.25A8.99 8.99 0 0 1 6 18c2.3 0 4.4.87 6 2.29m0-14.25a8.97 8.97 0 0 1 6-2.29c1.05 0 2.06.18 3 .51v14.25A8.99 8.99 0 0 0 18 18a8.97 8.97 0 0 0-6 2.29m0-14.25v14.25"/>'],
                        ['value' => 'image', 'label' => 'Buat gambar', 'hint' => 'Dari deskripsimu', 'placeholder' => 'Gambar apa yang mau dibuat?', 'icon' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 15-5-5L5 21"/>'],
                        ['value' => 'canvas', 'label' => 'Canvas', 'hint' => 'Halaman web atau dokumen', 'placeholder' => 'Mau dibuatkan apa?', 'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18M9 9v11"/>'],
                        ['value' => 'deep_research', 'label' => 'Deep Research', 'hint' => 'Riset dengan sumber', 'placeholder' => 'Mau riset tentang apa?', 'icon' => '<circle cx="11" cy="11" r="7"/><path stroke-linecap="round" stroke-linejoin="round" d="m20 20-3.5-3.5M8 11h6M11 8v6"/>'],
                    ];
                    $activeAiModel = $aiModels->firstWhere('model_id', $defaultAiModel) ?? $aiModels->first();
                @endphp
                <input type="hidden" name="ai_mode" value="" data-ai-mode-input="1" />
                <input type="hidden" name="ai_model" value="{{ $activeAiModel?->model_id }}" data-ai-model-input="1" data-default-model="{{ $activeAiModel?->model_id }}" data-group-id="{{ $group->id }}" />

                <div class="composer-pill relative" data-composer-main="1">
                    <div class="hidden px-3 pt-2.5" data-ai-mode-chip-row="1">
                        <span class="composer-mode-chip">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" data-ai-mode-chip-icon="1"></svg>
                            <span data-ai-mode-chip-label="1"></span>
                            <button type="button" class="-mr-1 rounded-full p-0.5 hover:bg-black/10" data-ai-mode-clear="1" aria-label="Hapus mode">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
                            </button>
                        </span>
                    </div>

                    <div class="composer-bar">
                        <button type="button" class="composer-iconbtn" data-open-attach-menu="1" aria-label="Tambah" aria-haspopup="menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
                        </button>

                        <textarea name="content" rows="1" placeholder="Ketik pesan" data-default-placeholder="Ketik pesan" class="composer-input" data-mention-input="1" autocomplete="off"></textarea>

                        @if($aiModels->isNotEmpty())
                            <button type="button" class="composer-model-btn" data-open-model-menu="1" aria-haspopup="menu" aria-label="Pilih model AI">
                                <span class="max-w-24 truncate" data-ai-model-label="1">{{ $activeAiModel?->label }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </button>
                        @endif

                        <button type="button" class="composer-send" data-record-voice="1" data-composer-voice-btn="1" aria-label="Rekam suara">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 0 0 3-3V7a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 11a6 6 0 1 0 12 0m-6 7v3m-3 0h6"/></svg>
                        </button>

                        <button type="submit" class="composer-send hidden" data-chat-send="1" aria-label="Kirim">
                            <svg xmlns="http://www.w3.org/2000/svg" class="nc-send-icon h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12 20 4l-4 16-4.5-6.5L4.5 12Z"/></svg>
                            {{-- Mode edit (resources/js/chat/composer/edit-mode.js): tombol kirim jadi centang. --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="nc-save-icon h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </button>
                    </div>

                    {{-- Menu plus: lampiran, polling, dan mode AI --}}
                    <div class="composer-popover left-0 w-72 hidden" data-attach-menu="1" role="menu">
                        <button type="button" class="composer-menu-item" data-attach-file role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 1 1-2.83-2.83l8.49-8.48"/></svg>
                            <span>Unggah file</span>
                        </button>
                        <button type="button" class="composer-menu-item" data-attach-poll role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4m-4 6h10M3 6h14M17 14l2 2 4-4"/></svg>
                            <span>Buat polling</span>
                        </button>
                        <div class="my-1 border-t" style="border-color: var(--nc-chat-composer-line, #E4E9F0);"></div>
                        @foreach($aiModes as $mode)
                            <button type="button" class="composer-menu-item" data-ai-mode-option="{{ $mode['value'] }}" data-ai-mode-label="{{ $mode['label'] }}" data-ai-mode-placeholder="{{ $mode['placeholder'] }}" role="menuitemradio" aria-checked="false">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" data-ai-mode-icon="1">{!! $mode['icon'] !!}</svg>
                                <span class="min-w-0 flex-1">
                                    <span class="block">{{ $mode['label'] }}</span>
                                    <span class="block text-[11px] opacity-60">{{ $mode['hint'] }}</span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 20 20" fill="currentColor" data-ai-check="1"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            </button>
                        @endforeach
                    </div>

                    {{-- Pemilih model per pesan --}}
                    @if($aiModels->isNotEmpty())
                        <div class="composer-popover right-0 w-64 hidden" data-model-menu="1" data-group-credits="{{ $groupCredits }}" role="menu">
                            @foreach($aiModels as $aiModel)
                                @php($gate = $modelGates[$aiModel->model_id] ?? null)
                                <button type="button" class="composer-menu-item" data-ai-model-option="{{ $aiModel->model_id }}" data-ai-model-name="{{ $aiModel->label }}" role="menuitemradio" aria-checked="false"
                                    @if($gate) data-ai-model-locked="{{ $gate['locked'] ? '1' : '0' }}" data-ai-model-open-at="{{ $gate['open_at'] }}" data-ai-model-floor="{{ $gate['floor'] }}" @endif>
                                    <span class="inline-flex h-5 w-5 items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 20 20" fill="currentColor" data-ai-check="1"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        @if($gate)
                                            {{-- Heroicons solid lock-closed (20) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $gate['locked'] ? '' : 'hidden' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-ai-lock-icon="1"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ $aiModel->label }}</span>
                                        @if($aiModel->description)
                                            <span class="block text-[11px] text-ink-soft {{ $gate && $gate['locked'] ? 'hidden' : '' }}" data-ai-model-description="1">{{ $aiModel->description }}</span>
                                        @endif
                                        @if($gate)
                                            {{-- Kategori dikunci saldo grup (App\Services\Credits\ModelAccess); ketuk untuk penjelasan. --}}
                                            <span class="block text-[11px] text-ink-soft {{ $gate['locked'] ? '' : 'hidden' }}" data-ai-lock-hint="1">Butuh saldo grup {{ $gate['open_at_label'] }} NK</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if($modelGates->isNotEmpty())
                        <div class="nc-popup-overlay hidden" data-model-lock-sheet="1" role="dialog" aria-modal="true" aria-labelledby="nc-model-lock-title">
                            <div class="nc-popup-card nc-lock-sheet">
                                <h2 id="nc-model-lock-title" class="text-base font-bold" data-lock-title></h2>
                                <p class="mt-1.5 text-sm text-ink-soft" data-lock-text></p>
                                <div class="nc-meter mt-3" aria-hidden="true"><span data-lock-meter></span></div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" class="btn-secondary px-4 py-2" data-lock-close="1">Tutup</button>
                                    <a href="{{ route('subscription.tokens.buy', ['group' => $group->id]) }}" class="btn-primary px-4 py-2">Isi Normkredit</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-2xl border border-slate-200 bg-white shadow-lg" data-mention-menu="1" data-mention-items='@json($mentionSuggestions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)'></div>
                </div>

                <div class="hidden items-center gap-4 rounded-2xl bg-[#121C29] px-4 py-3 text-white shadow-xl" data-voice-recorder-panel="1">
                    <button type="button" class="shrink-0 text-navy-200 transition hover:text-white" data-voice-cancel="1" aria-label="Hapus rekaman" title="Hapus rekaman">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.088-2.201a51.964 51.964 0 0 0-3.324 0c-1.178.037-2.088 1.021-2.088 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                    <span class="min-w-14 text-lg font-semibold tabular-nums" data-voice-timer="1">0:00</span>
                    <div class="flex min-w-0 flex-1 items-center gap-1 text-navy-100" aria-hidden="true">
                        @for ($i = 0; $i < 16; $i++)
                            <span class="h-6 w-1 rounded-full bg-white/70 {{ $i % 2 === 0 ? 'animate-pulse' : '' }}" style="animation-delay: {{ $i * 0.08 }}s;"></span>
                        @endfor
                    </div>
                    <button type="button" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-950" data-voice-stop-send="1" aria-label="Kirim rekaman" title="Kirim rekaman">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3.105 3.105a.75.75 0 0 1 .818-.164l12 5.25a.75.75 0 0 1 0 1.374l-12 5.25A.75.75 0 0 1 2.9 14.18l1.2-4.18a.75.75 0 0 0 0-.4l-1.2-4.18a.75.75 0 0 1 .205-.815Z" />
                        </svg>
                    </button>
                </div>

                <span class="mt-2 hidden rounded bg-slate-800 px-2 py-0.5 text-[10px] text-white" data-recording-indicator="1">Merekam suara...</span>
            </form>
            @endif
        </div>

        {{-- Scroll to bottom button --}}
        <button type="button" class="nc-scroll-bottom-btn fixed z-20 right-4 hidden flex-col lg:right-[max(1rem,calc((100%_-_var(--nc-room-max))/2))] items-center gap-1.5 rounded-full bg-transparent transition" style="bottom: calc(env(safe-area-inset-bottom) + 78px);" data-scroll-bottom="1" data-mode="latest" aria-label="Ke pesan terbaru">
            <span class="nc-scroll-bottom-count hidden min-w-10 rounded-full px-2 py-0.5 text-center text-[11px] font-semibold shadow" data-scroll-bottom-count="1"></span>
            <span class="nc-scroll-bottom-icon inline-flex h-12 w-12 items-center justify-center rounded-full border shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
            </span>
        </button>

        <div class="pointer-events-none fixed inset-0 z-40 hidden" data-settings-overlay="1"></div>
        <div class="fixed right-3 top-16 z-50 hidden w-64 rounded-2xl border border-white/20 py-1.5 text-white shadow-2xl lg:right-[max(1rem,calc((100%_-_var(--nc-room-max))/2))]" data-settings-menu="1" role="menu" aria-label="Menu grup" style="background: var(--nc-primary);">
            <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm hover:bg-white/10" data-open-search="1" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" stroke-linejoin="round" d="m20 20-3.5-3.5"/></svg>
                </span>
                <span>Cari pesan</span>
            </button>
            <a href="{{ route('settings.history', $group) }}" class="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-white/10" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                </span>
                <span>Unduh riwayat chat</span>
            </a>
            <a href="{{ route('settings.ai.persona', $group) }}" class="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-white/10" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a5 5 0 0 0-5 5v1a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 13v2a7 7 0 0 0 14 0v-2M12 20v2"/></svg>
                </span>
                <span>Kepribadian Normi AI</span>
            </a>
            <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm hover:bg-white/10" data-menu-clear-history="1" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6"/></svg>
                </span>
                <span>Bersihkan chat di perangkat ini</span>
            </button>
            <a href="{{ route('settings.show', $group) }}" class="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-white/10" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.22.7.22 1.07"/></svg>
                </span>
                <span>Pengaturan grup</span>
            </a>
            @if($viewerIsOwner)
                <div class="my-1 border-t border-white/15"></div>
                <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-[#FECCC5] hover:bg-[#FB6856]/30" data-open-delete-group-menu="1" role="menuitem">
                    <span class="inline-flex h-5 w-5 items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6m4 4v6m4-6v6"/></svg>
                    </span>
                    <span>Hapus grup</span>
                </button>
            @endif
        </div>

        @if($viewerIsOwner)
            <div class="hidden fixed inset-0 z-[90] items-center justify-center bg-navy-950/45 px-5" data-delete-group-menu-overlay="1">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <p class="text-sm font-bold text-slate-900">Hapus grup "{{ $group->name }}"?</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Semua pesan dan anggota akan hilang permanen. Ketik nama grup untuk lanjut.</p>
                    <input type="text" class="input-field mt-3" placeholder="Nama grup" data-delete-group-menu-confirm-input="1" autocomplete="off" />
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" data-close-delete-group-menu="1">Batal</button>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl bg-danger px-3 py-2 text-xs font-bold text-white hover:bg-danger-hover disabled:cursor-not-allowed disabled:opacity-50" data-delete-group-menu-submit="1" disabled>Hapus permanen</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </section>

@endsection


