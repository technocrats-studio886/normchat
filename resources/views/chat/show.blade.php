@extends('layouts.app', ['title' => $group->name.' - Normchat', 'group' => $group])

@section('content')
    <section class="fixed inset-y-0 left-1/2 flex w-full max-w-md -translate-x-1/2 flex-col overflow-hidden border-x border-slate-200/70 bg-[#f5f8ff]" data-chat-shell="1" data-group-name="{{ $group->name }}" style="height: 100dvh; height: 100svh;">
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
        <div class="nc-chat-header-card sticky top-0 z-30 mx-3 mt-3 flex items-center gap-3 rounded-3xl border px-3 py-2.5 shadow-sm backdrop-blur">
            <a href="{{ route('groups.index') }}" class="flex h-9 w-9 items-center justify-center rounded-2xl text-slate-500 hover:bg-slate-100" aria-label="Kembali">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('settings.show', $group) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl transition hover:bg-slate-50" aria-label="Buka ringkasan pengaturan grup">
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
                aria-label="Buka pengaturan grup"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5h.01M12 12h.01M12 19h.01" />
                </svg>
            </button>
        </div>

        {{-- Message search --}}
        <div class="hidden mx-3 mt-2 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm" data-message-search-bar="1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
            <input type="text" data-message-search-input="1" placeholder="Cari pesan di grup ini..." class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400" autocomplete="off" />
            <span class="text-[11px] font-medium text-slate-400" data-message-search-count="1"></span>
            <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100" data-close-search="1" aria-label="Tutup pencarian">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div
            class="nc-scroll mt-3 flex-1 space-y-4 overflow-y-auto px-4 py-3 pb-56"
            data-chat-group-id="{{ $group->id }}"
            data-auth-user-id="{{ auth()->id() }}"
            data-chat-feed="1"
            data-message-count="{{ $messages->count() }}"
            data-last-read-message-id="{{ $lastReadMessageId ?? 0 }}"
            data-latest-message-id="{{ $latestMessageId ?? 0 }}"
            data-unread-count="{{ $unreadCount ?? 0 }}"
            data-has-read-before="{{ ($lastReadMessageId ?? 0) > 0 ? '1' : '0' }}"
            data-chat-read-url="{{ route('chat.read', $group) }}"
            data-poll-stats-url="{{ route('chat.polls.stats', $group) }}"
            data-poll-vote-url-template="{{ route('chat.polls.vote', ['group' => $group, 'message' => '__MESSAGE__']) }}"
            data-viewer-is-moderator="{{ $viewerIsModerator ? '1' : '0' }}"
        >
            @forelse ($messages as $message)
                @php
                    $isMine = $message->sender_type === 'user' && $message->sender_id === auth()->id();
                    $isAi = $message->sender_type === 'ai';
                    $senderName = $isAi ? ($message->sender_name ?? 'NormAI') : ($isMine ? 'You' : ($message->sender->name ?? 'User'));
                    $attachmentUrl = $message->attachment_path ? route('chat.attachment', ['group' => $group->id, 'message' => $message->id]) : null;
                    $attachmentMime = strtolower((string) $message->attachment_mime);
                    $isImage = $message->message_type === 'image' && $attachmentUrl;
                    $isVoice = $attachmentUrl && (
                        $message->message_type === 'voice'
                        || str_starts_with($attachmentMime, 'audio/')
                        || $attachmentMime === 'video/webm'
                    );
                    $isVideo = $attachmentUrl && ! $isVoice && (
                        $message->message_type === 'video'
                        || str_starts_with($attachmentMime, 'video/')
                    );
                    $isFileAttachment = $attachmentUrl && ! $isImage && ! $isVoice && ! $isVideo;
                    $attachmentName = (string) ($message->attachment_original_name ?: ($message->attachment_path ? basename($message->attachment_path) : 'Lampiran'));
                    $attachmentBytes = (int) ($message->attachment_size ?? 0);
                    if ($attachmentBytes > 0 && $attachmentBytes >= 1024 * 1024 * 1024) {
                        $attachmentSizeLabel = number_format($attachmentBytes / (1024 * 1024 * 1024), 1) . ' GB';
                    } elseif ($attachmentBytes > 0 && $attachmentBytes >= 1024 * 1024) {
                        $attachmentSizeLabel = number_format($attachmentBytes / (1024 * 1024), 1) . ' MB';
                    } elseif ($attachmentBytes > 0 && $attachmentBytes >= 1024) {
                        $attachmentSizeLabel = number_format($attachmentBytes / 1024, 1) . ' KB';
                    } elseif ($attachmentBytes > 0) {
                        $attachmentSizeLabel = $attachmentBytes . ' B';
                    } else {
                        $attachmentSizeLabel = 'Dokumen';
                    }
                    $previewContent = $message->content
                        ? (string) $message->content
                        : ($isImage ? '[Gambar]' : ($isVideo ? '[Video]' : ($isVoice ? '[Voice note]' : ($isFileAttachment ? '[File]' : '[Lampiran]'))));
                    $replyTarget = $message->replyToMessage;
                    $replySender = $replyTarget
                        ? ($replyTarget->sender_type === 'ai' ? 'NormAI' : ($replyTarget->sender->name ?? 'User'))
                        : null;
                    $replyPreview = $replyTarget
                        ? ($replyTarget->content
                            ? \Illuminate\Support\Str::limit((string) $replyTarget->content, 100)
                            : (($replyTarget->message_type === 'image') ? '[Gambar]' : (($replyTarget->message_type === 'voice') ? '[Voice note]' : '[Lampiran]')))
                        : null;
                    $audioSourceMime = $attachmentMime === 'video/webm' ? 'audio/webm' : ($message->attachment_mime ?? 'audio/webm');
                    $isEdited = ((int) ($message->versions_count ?? 0)) > 0;
                        $editedAtIso = $isEdited ? optional($message->updated_at)->toIso8601String() : '';
                        $editedTitle = $isEdited && $message->updated_at
                            ? 'Diedit ' . $message->updated_at->timezone(config('app.display_timezone', config('app.timezone')))->format('d M H:i')
                            : 'Pesan telah diedit';
                @endphp

                @if($isMine)
                    {{-- User's own message - right aligned, blue --}}
                    <div id="message-{{ $message->id }}" class="flex justify-end" data-message-id="{{ $message->id }}" data-message-sender-id="{{ (int) ($message->sender_id ?? 0) }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}" data-message-attachment-mime="{{ $message->attachment_mime }}" data-message-attachment-name="{{ $message->attachment_original_name }}">
                        <div class="max-w-[75%]">
                            @if($replyTarget)
                                <a href="#message-{{ $replyTarget->id }}" class="nc-reply-chip nc-reply-chip--mine mb-1 block rounded-xl px-3 py-1.5 text-xs">
                                    <p class="nc-reply-chip-sender font-semibold">Membalas {{ $replySender }}</p>
                                    <p class="truncate">{{ $replyPreview }}</p>
                                </a>
                            @endif
                            @if($message->content && $isImage)
                                <div class="nc-media-caption-card nc-media-caption-card--mine mb-2" data-message-body="1">
                                    <a href="{{ $attachmentUrl }}" class="block overflow-hidden rounded-t-[20px]" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="{{ $attachmentName }}" data-attachment-frame="1">
                                        <img src="{{ $attachmentUrl }}" alt="Gambar" class="h-auto max-h-72 w-full object-cover" />
                                    </a>
                                    <div class="px-3 pb-2 pt-2 text-sm text-white/95">
                                        <span class="whitespace-pre-wrap">{{ $message->content }}</span><span class="nc-inline-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="mine">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--mine" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</span>
                                    </div>
                                </div>
                            @elseif($isImage)
                                <a href="{{ $attachmentUrl }}" class="mb-2 block overflow-hidden rounded-2xl border border-blue-200 bg-blue-50" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="{{ $attachmentName }}">
                                    <img src="{{ $attachmentUrl }}" alt="Gambar" class="h-auto max-h-64 w-full object-cover" />
                                </a>
                            @elseif($isVoice)
                                <div class="mb-2 inline-block w-55 max-w-full rounded-2xl border border-white/15 px-3 py-2 text-white transition" style="background: var(--nc-mine);" data-voice-player="1">
                                    <audio preload="metadata" class="hidden" data-voice-audio>
                                        <source src="{{ $attachmentUrl }}" type="{{ $audioSourceMime }}">
                                    </audio>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>
                                                @foreach (['h-2','h-3','h-2','h-4','h-2','h-3','h-4','h-2','h-3','h-2','h-4','h-2'] as $barHeight)
                                                    <span class="{{ $barHeight }} w-0.5 rounded-full bg-white/70 opacity-45" data-voice-bar></span>
                                                @endforeach
                                            </div>
                                            <input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer" style="accent-color: rgba(255,255,255,0.85);" data-voice-progress>
                                            <div class="mt-1 flex items-center justify-between text-[11px] text-white/80">
                                                <span data-voice-current>0:00</span>
                                                <span data-voice-duration>0:00</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 hidden text-[11px] text-white/80" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
                                </div>
                            @elseif($message->content && $isVideo)
                                <div class="nc-media-caption-card nc-media-caption-card--mine mb-2" data-message-body="1">
                                    <button type="button" class="block w-full overflow-hidden rounded-t-[20px] bg-black" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}" data-attachment-frame="1">
                                        <div class="relative">
                                            <video src="{{ $attachmentUrl }}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
                                            <span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
                                        </div>
                                    </button>
                                    <div class="px-3 pb-2 pt-2 text-sm text-white/95">
                                        <span class="whitespace-pre-wrap">{{ $message->content }}</span><span class="nc-inline-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="mine">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--mine" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</span>
                                    </div>
                                </div>
                            @elseif($isVideo)
                                <button type="button" class="mb-2 block w-full overflow-hidden rounded-2xl border border-blue-200 bg-blue-50" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                    <div class="relative">
                                        <video src="{{ $attachmentUrl }}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
                                        <span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
                                    </div>
                                    <p class="px-3 py-2 text-left text-xs font-medium text-slate-700">{{ $attachmentName }}</p>
                                </button>
                            @elseif($isFileAttachment)
                                <button type="button" class="mb-2 block w-full rounded-2xl border border-blue-200 bg-blue-50 p-3 text-left text-blue-800" data-message-body="1" data-attachment-download="1" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-black/10 text-base">📄</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold">{{ $attachmentName }}</p>
                                            <p class="text-[11px] opacity-75">{{ $attachmentSizeLabel }}</p>
                                        </div>
                                        <span class="text-[11px] font-semibold opacity-80">Buka</span>
                                    </div>
                                </button>
                            @endif
                            @if($message->content && ($isImage || $isVideo))
                            @elseif($message->content)
                                <div class="bubble-mine">
                                    <span class="whitespace-pre-wrap">{{ $message->content }}</span><span class="nc-inline-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="mine">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--mine" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</span>
                                </div>
                            @else
                                <p class="mt-1 text-right meta-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="mine">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--mine" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</p>
                            @endif
                        </div>
                    </div>
                @elseif($isAi)
                    {{-- AI message - left aligned, green tinted --}}
                    @php $hasRich = str_contains($message->content ?? '', '|') || str_contains($message->content ?? '', '```mermaid'); @endphp
                    <div id="message-{{ $message->id }}" class="{{ $hasRich ? 'max-w-[95%]' : 'max-w-[80%]' }}" data-message-id="{{ $message->id }}" data-message-sender-id="{{ (int) ($message->sender_id ?? 0) }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}" data-message-attachment-mime="{{ $message->attachment_mime }}" data-message-attachment-name="{{ $message->attachment_original_name }}">
                        @if($replyTarget)
                            <div class="nc-reply-chip nc-reply-chip--ai mb-1 rounded-xl px-3 py-1.5 text-xs">
                                <p class="nc-reply-chip-sender font-semibold">Membalas {{ $replySender }}</p>
                                <p class="truncate">{{ $replyPreview }}</p>
                            </div>
                        @endif
                        @if($isImage)
                            <a href="{{ $attachmentUrl }}" class="mb-2 block overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="{{ $attachmentName }}">
                                <img src="{{ $attachmentUrl }}" alt="Gambar AI" class="h-auto max-h-64 w-full object-cover" />
                            </a>
                        @elseif($isVoice)
                            <div class="mb-2 inline-block w-55 max-w-full rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-900 transition" data-voice-player="1">
                                <audio preload="metadata" class="hidden" data-voice-audio>
                                    <source src="{{ $attachmentUrl }}" type="{{ $audioSourceMime }}">
                                </audio>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>
                                            @foreach (['h-2','h-3','h-2','h-4','h-2','h-3','h-4','h-2','h-3','h-2','h-4','h-2'] as $barHeight)
                                                <span class="{{ $barHeight }} w-0.5 rounded-full bg-emerald-500/70 opacity-45" data-voice-bar></span>
                                            @endforeach
                                        </div>
                                        <input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer accent-emerald-500" data-voice-progress>
                                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                                            <span data-voice-current>0:00</span>
                                            <span data-voice-duration>0:00</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-1 hidden text-[11px] text-slate-500" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
                            </div>
                        @elseif($isVideo)
                            <button type="button" class="mb-2 block w-full overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                <div class="relative">
                                    <video src="{{ $attachmentUrl }}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
                                    <span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
                                </div>
                                <p class="px-3 py-2 text-left text-xs font-medium text-slate-700">{{ $attachmentName }}</p>
                            </button>
                        @elseif($isFileAttachment)
                            <button type="button" class="mb-2 block w-full rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-left text-emerald-800" data-message-body="1" data-attachment-download="1" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-black/10 text-base">📄</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $attachmentName }}</p>
                                        <p class="text-[11px] opacity-75">{{ $attachmentSizeLabel }}</p>
                                    </div>
                                    <span class="text-[11px] font-semibold opacity-80">Buka</span>
                                </div>
                            </button>
                        @endif
                        <p class="nc-chat-sender nc-chat-sender--ai mb-1 text-[11px] font-semibold">{{ $senderName }}</p>
                        @if($message->content)
                            <div class="bubble-ai ai-markdown overflow-hidden" data-ai-raw>{{ $message->content }}</div>
                            <p class="nc-chat-meta nc-chat-meta--ai mt-1 text-right text-[11px] font-medium" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="ai">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--ai" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</p>
                        @else
                            <p class="nc-chat-meta nc-chat-meta--ai mt-1 text-right text-[11px] font-medium" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="ai">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--ai" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</p>
                        @endif
                    </div>
                @else
                    {{-- Other user message - left aligned, white --}}
                    <div id="message-{{ $message->id }}" class="max-w-[75%]" data-message-id="{{ $message->id }}" data-message-sender-id="{{ (int) ($message->sender_id ?? 0) }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}" data-message-attachment-mime="{{ $message->attachment_mime }}" data-message-attachment-name="{{ $message->attachment_original_name }}">
                        <p class="nc-chat-sender mb-1 text-[11px]">{{ $senderName }}</p>
                        @if($replyTarget)
                            <div class="nc-reply-chip nc-reply-chip--other mb-1 rounded-xl px-3 py-1.5 text-xs">
                                <p class="nc-reply-chip-sender font-semibold">Membalas {{ $replySender }}</p>
                                <p class="truncate">{{ $replyPreview }}</p>
                            </div>
                        @endif
                        @if($isImage)
                            <a href="{{ $attachmentUrl }}" class="mb-2 block overflow-hidden rounded-2xl border border-slate-200 bg-white" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="{{ $attachmentName }}">
                                <img src="{{ $attachmentUrl }}" alt="Gambar" class="h-auto max-h-64 w-full object-cover" />
                            </a>
                        @elseif($isVoice)
                            <div class="mb-2 inline-block w-55 max-w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-800 transition" data-voice-player="1">
                                <audio preload="metadata" class="hidden" data-voice-audio>
                                    <source src="{{ $attachmentUrl }}" type="{{ $audioSourceMime }}">
                                </audio>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>
                                            @foreach (['h-2','h-3','h-2','h-4','h-2','h-3','h-4','h-2','h-3','h-2','h-4','h-2'] as $barHeight)
                                                <span class="{{ $barHeight }} w-0.5 rounded-full bg-emerald-500/70 opacity-45" data-voice-bar></span>
                                            @endforeach
                                        </div>
                                        <input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer accent-emerald-500" data-voice-progress>
                                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                                            <span data-voice-current>0:00</span>
                                            <span data-voice-duration>0:00</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-1 hidden text-[11px] text-slate-500" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
                            </div>
                        @elseif($isVideo)
                            <button type="button" class="mb-2 block w-full overflow-hidden rounded-2xl border border-slate-200 bg-white" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                <div class="relative">
                                    <video src="{{ $attachmentUrl }}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
                                    <span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
                                </div>
                                <p class="px-3 py-2 text-left text-xs font-medium text-slate-700">{{ $attachmentName }}</p>
                            </button>
                        @elseif($isFileAttachment)
                            <button type="button" class="mb-2 block w-full rounded-2xl border border-slate-200 bg-white p-3 text-left text-slate-700" data-message-body="1" data-attachment-download="1" data-attachment-url="{{ $attachmentUrl }}" data-attachment-name="{{ $attachmentName }}">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-base">📄</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $attachmentName }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $attachmentSizeLabel }}</p>
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-500">Buka</span>
                                </div>
                            </button>
                        @endif
                        @if($message->content)
                            <div class="bubble-other">
                                <span class="whitespace-pre-wrap">{{ $message->content }}</span><span class="nc-inline-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="other">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--other" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</span>
                            </div>
                        @else
                            <p class="mt-1 meta-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="" data-time-edited="{{ $isEdited ? '1' : '0' }}" data-time-edited-at="{{ $editedAtIso }}" data-time-tone="other">{{ $message->created_at?->format('H:i') }}@if($isEdited) <span class="nc-edited-mark nc-edited-mark--other" title="{{ $editedTitle }}" aria-label="{{ $editedTitle }}">diedit</span>@endif</p>
                        @endif
                    </div>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">
                    Belum ada pesan. Mulai percakapan sekarang.
                </div>
            @endforelse
            <div class="hidden pb-2 text-[11px] text-slate-400" data-typing-indicator="1"></div>
        </div>

        {{-- Input bar --}}
        <div class="composer-shell">
            <form method="POST" action="{{ route('chat.store', $group) }}" class="relative" enctype="multipart/form-data" data-chat-form="1">
                @csrf
                <input type="file" name="attachment" accept="image/*,audio/*,video/*,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z" class="hidden" data-chat-attachment="1" />
                <input type="file" accept="image/*" capture="environment" class="hidden" data-chat-camera-input="1" />
                <input type="hidden" name="reply_to_message_id" value="" data-reply-to-input="1" />

                <div class="hidden items-center justify-between border-b border-indigo-100 bg-indigo-50/70 px-4 py-2" data-reply-preview="1">
                    <div class="flex min-w-0 items-center gap-2">
                        <div class="h-8 w-1 shrink-0 rounded-full" style="background: var(--nc-primary);"></div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold text-slate-700" data-reply-preview-sender="1">Balas pesan</p>
                            <p class="truncate text-xs text-slate-600" data-reply-preview-content="1"></p>
                        </div>
                    </div>
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-indigo-100" data-reply-clear="1" aria-label="Batalkan balasan">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <div class="composer-bar relative" data-composer-main="1">
                    <textarea name="content" rows="1" placeholder="Ketik pesan" class="composer-input" data-mention-input="1" autocomplete="off"></textarea>

                    <button type="button" class="composer-iconbtn" data-open-attach-menu="1" aria-label="Lampiran">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 1 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>

                    <button type="button" class="composer-send" data-record-voice="1" data-composer-voice-btn="1" aria-label="Rekam voice" style="background: var(--nc-primary);">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 0 0 3-3V7a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 11a6 6 0 1 0 12 0m-6 7v3m-3 0h6"/></svg>
                    </button>

                    <button type="submit" class="composer-send hidden" data-chat-send="1" aria-label="Kirim">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12 20 4l-4 16-4.5-6.5L4.5 12Z"/></svg>
                    </button>

                    {{-- Attachment sub-menu (Telegram style) --}}
                    <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-2xl border border-slate-200 bg-white shadow-lg" data-attach-menu="1">
                        <div class="p-2 space-y-0.5">
                            <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition" data-attach-photo>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                </span>
                                Foto/Galeri
                            </button>
                            <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition" data-attach-camera>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2.257a2 2 0 0 0 1.743-1.02l.5-.86A1 1 0 0 1 11.866 3h.268a1 1 0 0 1 .866.48l.5.86A2 2 0 0 0 15.243 6H17.5A2.5 2.5 0 0 1 20 8.5v8A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </span>
                                Kamera
                            </button>
                            <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition" data-attach-file>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                                </span>
                                Berkas
                            </button>
                            <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition" data-attach-poll>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-violet-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4m-4 6h10M3 6h14M17 14l2 2 4-4"/></svg>
                                </span>
                                Buat Polling
                            </button>
                        </div>
                    </div>

                    <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-2xl border border-slate-200 bg-white shadow-lg" data-mention-menu="1" data-mention-items='@json($mentionSuggestions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)'></div>
                    <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-lg" data-emoji-panel="1">
                        <div class="grid grid-cols-8 gap-1 text-xl">
                            @foreach (['😀','😁','😂','🤣','😊','😍','👍','🙏','🎉','🔥','❤️','😅','🤔','👏','😎','😢'] as $emoji)
                                <button type="button" class="rounded-lg px-1 py-1 hover:bg-slate-100" data-emoji-item="{{ $emoji }}">{{ $emoji }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="hidden items-center gap-4 rounded-2xl bg-[#091420] px-4 py-3 text-white shadow-xl" data-voice-recorder-panel="1">
                    <button type="button" class="shrink-0 text-slate-300 transition hover:text-white" data-voice-cancel="1" aria-label="Hapus rekaman" title="Hapus rekaman">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.088-2.201a51.964 51.964 0 0 0-3.324 0c-1.178.037-2.088 1.021-2.088 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                    <span class="min-w-14 text-lg font-semibold tabular-nums" data-voice-timer="1">0:00</span>
                    <div class="flex min-w-0 flex-1 items-center gap-1 text-cyan-100" aria-hidden="true">
                        @for ($i = 0; $i < 16; $i++)
                            <span class="h-6 w-1 rounded-full bg-cyan-100/80 {{ $i % 2 === 0 ? 'animate-pulse' : '' }}" style="animation-delay: {{ $i * 0.08 }}s;"></span>
                        @endfor
                    </div>
                    <button type="button" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#22c55e] text-[#04200f]" data-voice-stop-send="1" aria-label="Kirim voice note" title="Kirim voice note">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3.105 3.105a.75.75 0 0 1 .818-.164l12 5.25a.75.75 0 0 1 0 1.374l-12 5.25A.75.75 0 0 1 2.9 14.18l1.2-4.18a.75.75 0 0 0 0-.4l-1.2-4.18a.75.75 0 0 1 .205-.815Z" />
                        </svg>
                    </button>
                </div>

                <span class="mt-2 hidden rounded bg-slate-800 px-2 py-0.5 text-[10px] text-white" data-recording-indicator="1">Merekam voice note...</span>
            </form>
        </div>

        {{-- Scroll to bottom button --}}
        <button type="button" class="fixed z-20 right-4 hidden flex-col items-center gap-1.5 rounded-full bg-transparent text-slate-500 transition" style="bottom: calc(env(safe-area-inset-bottom) + 78px);" data-scroll-bottom="1" aria-label="Lewati ke pesan terbaru">
            <span class="hidden min-w-10 rounded-full bg-[#2b6cb0] px-2 py-0.5 text-center text-[11px] font-semibold text-white shadow" data-scroll-bottom-count="1"></span>
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white shadow-md hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
            </span>
        </button>

        <div class="pointer-events-none fixed inset-0 z-40 hidden" data-settings-overlay="1"></div>
        <div class="fixed right-3 top-16 z-50 hidden w-64 rounded-2xl border border-white/20 py-1.5 text-white shadow-2xl" data-settings-menu="1" role="menu" aria-label="Menu chat grup" style="background: var(--nc-primary);">
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
                <span>Export chat history</span>
            </a>
            <a href="{{ route('settings.ai.persona', $group) }}" class="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-white/10" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a5 5 0 0 0-5 5v1a5 5 0 0 0 10 0V7a5 5 0 0 0-5-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 13v2a7 7 0 0 0 14 0v-2M12 20v2"/></svg>
                </span>
                <span>AI Persona</span>
            </a>
            <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm hover:bg-white/10" data-menu-clear-history="1" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6"/></svg>
                </span>
                <span>Clear history (lokal)</span>
            </button>
            <a href="{{ route('settings.show', $group) }}" class="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-white/10" role="menuitem">
                <span class="inline-flex h-5 w-5 items-center justify-center text-white/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.22.7.22 1.07"/></svg>
                </span>
                <span>Group setting</span>
            </a>
            @if($viewerIsOwner)
                <div class="my-1 border-t border-white/15"></div>
                <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-rose-200 hover:bg-rose-500/30" data-open-delete-group-menu="1" role="menuitem">
                    <span class="inline-flex h-5 w-5 items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6m4 4v6m4-6v6"/></svg>
                    </span>
                    <span>Delete chat</span>
                </button>
            @endif
        </div>

        @if($viewerIsOwner)
            <div class="hidden fixed inset-0 z-[90] items-center justify-center bg-slate-900/45 px-5" data-delete-group-menu-overlay="1">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <p class="text-sm font-bold text-slate-900">Hapus grup "{{ $group->name }}"?</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Semua pesan, anggota, dan backup akan dihapus. Ketik nama grup untuk konfirmasi.</p>
                    <input type="text" class="input-field mt-3" placeholder="Ketik nama grup persis" data-delete-group-menu-confirm-input="1" autocomplete="off" />
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" data-close-delete-group-menu="1">Batal</button>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl bg-rose-500 px-3 py-2 text-xs font-bold text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50" data-delete-group-menu-submit="1" disabled>Hapus permanen</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <script>
        // Device back button routes to groups index (not browser history)
        (function () {
            const groupsUrl = @json(route('groups.index'));
            try { history.pushState({ nc: 'chat' }, '', location.href); } catch (_) {}
            window.addEventListener('popstate', function () {
                window.location.replace(groupsUrl);
            });
        })();

        // Keep chat shell stable to visual viewport when keyboard opens.
        (function () {
            const shell = document.querySelector('[data-chat-shell]');
            if (!shell || !window.visualViewport) return;

            document.documentElement.style.overscrollBehavior = 'none';
            document.body.style.overscrollBehavior = 'none';
            document.body.style.overflow = 'hidden';

            let rafId = null;
            const applyHeight = () => {
                const vv = window.visualViewport;
                const h = Math.max(320, Math.round(vv?.height || window.innerHeight));
                shell.style.height = h + 'px';
                shell.style.top = '0px';
                shell.style.bottom = 'auto';
                // Keep composer anchored to bottom to avoid double keyboard compensation.
                document.documentElement.style.setProperty('--nc-keyboard-offset', '0px');
            };

            const schedule = () => {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                }
                rafId = requestAnimationFrame(applyHeight);
            };

            applyHeight();
            window.visualViewport.addEventListener('resize', schedule);
            window.addEventListener('orientationchange', schedule);
        })();
    </script>
@endsection


