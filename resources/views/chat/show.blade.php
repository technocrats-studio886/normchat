@extends('layouts.app', ['title' => $group->name.' - Normchat', 'group' => $group])

@section('content')
    <section class="relative flex min-h-[calc(100vh-5rem)] flex-col" data-chat-shell="1">
        @php
            $gt = $group->groupToken;
            $credits = $gt ? $gt->credits : 0;
            $activeMembers = $group->members->where('status', 'active');
            $ownerInMembers = $activeMembers->contains('user_id', $group->owner_id);
            $memberCount = $activeMembers->count() + ($ownerInMembers ? 0 : 1);
            $groupInitial = strtoupper(substr($group->name, 0, 1));
        @endphp

        {{-- Sticky header --}}
        <div class="sticky top-0 z-20 mx-3 mt-3 flex items-center gap-3 rounded-3xl border border-slate-200 bg-white/85 px-3 py-2.5 shadow-sm backdrop-blur">
            <a href="{{ route('groups.index') }}" class="flex h-9 w-9 items-center justify-center rounded-2xl text-slate-500 hover:bg-slate-100" aria-label="Kembali">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
            </a>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-extrabold text-white" style="background: var(--nc-primary);">
                {{ $groupInitial }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-800">{{ $group->name }}</p>
                <p class="truncate text-[11px] text-slate-500">{{ $memberCount }} anggota</p>
            </div>
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

        {{-- Messages --}}
        <div class="nc-scroll mt-3 flex-1 space-y-4 overflow-y-auto px-4 py-3 pb-56" data-chat-group-id="{{ $group->id }}" data-auth-user-id="{{ auth()->id() }}" data-chat-feed="1">
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
                    $previewContent = $message->content
                        ? \Illuminate\Support\Str::limit((string) $message->content, 120)
                        : ($isImage ? '[Gambar]' : ($isVoice ? '[Voice note]' : '[Lampiran]'));
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
                @endphp

                @if($isMine)
                    {{-- User's own message - right aligned, blue --}}
                    <div id="message-{{ $message->id }}" class="flex justify-end" data-message-id="{{ $message->id }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}">
                        <div class="max-w-[75%]">
                            @if($replyTarget)
                                <div class="mb-1 rounded-xl bg-white/20 px-3 py-1.5 text-xs">
                                    <p class="font-semibold text-white">Reply to {{ $replySender }}</p>
                                    <p class="truncate text-white/80">{{ $replyPreview }}</p>
                                </div>
                            @endif
                            @if($isImage)
                                <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-2xl border border-blue-200 bg-blue-50">
                                    <img src="{{ $attachmentUrl }}" alt="Gambar" class="h-auto max-h-64 w-full object-cover" />
                                </a>
                            @elseif($isVoice)
                                <div class="mb-2 inline-block w-55 max-w-full rounded-2xl border border-[#0b4a3d] bg-[#0f5f4e] px-3 py-2 text-emerald-50 transition" data-voice-player="1">
                                    <audio preload="metadata" class="hidden" data-voice-audio>
                                        <source src="{{ $attachmentUrl }}" type="{{ $audioSourceMime }}">
                                    </audio>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-300 text-emerald-950" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>
                                                @foreach (['h-2','h-3','h-2','h-4','h-2','h-3','h-4','h-2','h-3','h-2','h-4','h-2'] as $barHeight)
                                                    <span class="{{ $barHeight }} w-0.5 rounded-full bg-emerald-200/85 opacity-45" data-voice-bar></span>
                                                @endforeach
                                            </div>
                                            <input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer accent-emerald-300" data-voice-progress>
                                            <div class="mt-1 flex items-center justify-between text-[11px] text-emerald-100">
                                                <span data-voice-current>0:00</span>
                                                <span data-voice-duration>0:00</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 hidden text-[11px] text-emerald-100" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
                                </div>
                            @endif
                            @if($message->content)
                                <div class="bubble-mine">
                                    {{ $message->content }}
                                </div>
                            @endif
                            <p class="mt-1 text-right meta-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                        </div>
                    </div>
                @elseif($isAi)
                    {{-- AI message - left aligned, green tinted --}}
                    <div id="message-{{ $message->id }}" class="max-w-[80%]" data-message-id="{{ $message->id }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}">
                        @if($replyTarget)
                            <div class="mb-1 rounded-xl border border-emerald-200 bg-emerald-100/70 px-3 py-1.5 text-xs text-emerald-700">
                                <p class="font-semibold">Reply to {{ $replySender }}</p>
                                <p class="truncate">{{ $replyPreview }}</p>
                            </div>
                        @endif
                        @if($isImage)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50">
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
                        @endif
                        @if($message->content)
                            <div class="bubble-ai">
                                {{ $message->content }}
                            </div>
                        @endif
                        <p class="mt-1 text-[11px] font-medium text-emerald-700" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                    </div>
                @else
                    {{-- Other user message - left aligned, white --}}
                    <div id="message-{{ $message->id }}" class="max-w-[75%]" data-message-id="{{ $message->id }}" style="touch-action: pan-y; user-select: none; -webkit-user-select: none;" data-message-sender-name="{{ $senderName }}" data-message-content="{{ $previewContent }}" data-message-type="{{ $message->message_type }}">
                        <p class="mb-1 text-[11px] text-slate-500">{{ $senderName }}</p>
                        @if($replyTarget)
                            <div class="mb-1 rounded-xl border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs text-slate-600">
                                <p class="font-semibold">Reply to {{ $replySender }}</p>
                                <p class="truncate">{{ $replyPreview }}</p>
                            </div>
                        @endif
                        @if($isImage)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-2xl border border-slate-200 bg-white">
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
                        @endif
                        @if($message->content)
                            <div class="bubble-other">
                                {{ $message->content }}
                            </div>
                        @endif
                        <p class="mt-1 meta-time" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
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
                <input type="file" name="attachment" accept="image/*,audio/*" class="hidden" data-chat-attachment="1" />
                <input type="file" accept="image/*" capture="environment" class="hidden" data-chat-camera-input="1" />
                <input type="hidden" name="reply_to_message_id" value="" data-reply-to-input="1" />

                <div class="hidden items-center justify-between border-b border-indigo-200 bg-indigo-50 px-4 py-2" data-reply-preview="1">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-indigo-700" data-reply-preview-sender="1">Reply</p>
                        <p class="truncate text-xs text-indigo-600" data-reply-preview-content="1"></p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-indigo-500 hover:bg-indigo-100" data-reply-clear="1" aria-label="Batalkan reply">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <div class="composer-bar relative" data-composer-main="1">
                    <button type="button" class="composer-iconbtn" data-open-emoji="1" aria-label="Emoji">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M9 10.5h.01v.01H9v-.01Zm6 0h.01v.01H15v-.01ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>

                    <textarea name="content" rows="1" placeholder="Ketik pesan" class="composer-input" data-mention-input="1" autocomplete="off"></textarea>

                    <button type="button" class="composer-iconbtn" data-pick-voice="1" aria-label="Lampirkan file">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 1 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>

                    <button type="button" class="composer-iconbtn" data-open-camera="1" aria-label="Kamera">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2.257a2 2 0 0 0 1.743-1.02l.5-.86A1 1 0 0 1 11.866 3h.268a1 1 0 0 1 .866.48l.5.86A2 2 0 0 0 15.243 6H17.5A2.5 2.5 0 0 1 20 8.5v8A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>

                    <button type="button" class="composer-iconbtn" data-record-voice="1" aria-label="Rekam voice">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 0 0 3-3V7a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 11a6 6 0 1 0 12 0m-6 7v3m-3 0h6"/></svg>
                    </button>

                    <button type="submit" class="composer-send" data-chat-send="1" aria-label="Kirim">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12 20 4l-4 16-4.5-6.5L4.5 12Z"/></svg>
                    </button>

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

                <span class="mt-2 hidden max-w-full truncate rounded bg-slate-800 px-2 py-0.5 text-[10px] text-white" data-attachment-label="1"></span>
                <span class="mt-2 hidden rounded bg-slate-800 px-2 py-0.5 text-[10px] text-white" data-recording-indicator="1">Merekam voice note...</span>
            </form>
        </div>

        <div class="pointer-events-none fixed inset-0 z-40 bg-slate-900/0 opacity-0 transition duration-300" data-settings-overlay="1"></div>

        <aside
            class="fixed inset-y-0 right-0 z-50 w-[88%] max-w-sm translate-x-full border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out"
            data-settings-drawer="1"
            aria-label="Pengaturan grup"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pengaturan Grup</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $group->name }}</p>
                </div>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100" data-close-settings="1" aria-label="Tutup pengaturan">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="space-y-2 px-4 py-4">
                <a href="{{ route('settings.show', $group) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <span>Ringkasan Pengaturan</span>
                    <span aria-hidden="true">></span>
                </a>
                <a href="{{ route('settings.history', $group) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <span>Backup & Export</span>
                    <span aria-hidden="true">></span>
                </a>
                <a href="{{ route('settings.ai.persona', $group) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <span>Persona AI</span>
                    <span aria-hidden="true">></span>
                </a>
                <a href="{{ route('settings.seats', $group) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <span>Manajemen Seat</span>
                    <span aria-hidden="true">></span>
                </a>
            </div>
        </aside>
    </section>
@endsection


