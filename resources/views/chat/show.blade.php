@extends('layouts.app', ['title' => $group->name.' - Normchat', 'group' => $group])

@section('content')
    <section class="relative flex min-h-[calc(100vh-5rem)] flex-col" data-chat-shell="1">
        {{-- Group name row --}}
        <div class="relative z-20 flex items-center justify-between px-5 pb-2">
            <a href="{{ route('groups.index') }}" class="text-sm text-slate-500"># {{ $group->name }}</a>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100"
                    data-share-group-url="{{ route('groups.join', $group->share_id) }}"
                    data-share-group-name="{{ $group->name }}"
                    aria-label="Share group"
                    title="Share group"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12a4.5 4.5 0 0 1 4.5-4.5h5.25" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.25 4.5 3.75 3.75-3.75 3.75" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 13.5V16a2.5 2.5 0 0 1-2.5 2.5h-8A2.5 2.5 0 0 1 5 16v-8A2.5 2.5 0 0 1 7.5 5.5h2.5" />
                    </svg>
                </button>
                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100" data-open-settings="1" aria-label="Buka settings group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
            </div>
        </div>

        {{-- Group info card --}}
        <div class="mx-5 mb-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <h2 class="text-lg font-bold text-slate-900"># {{ $group->name }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @php
                    $gt = $group->groupToken;
                    $credits = $gt ? $gt->credits : 0;
                    $modelLabel = config("ai_models.providers.{$group->ai_provider}.models.{$group->ai_model}.label", $group->ai_model ?? 'N/A');
                    $multiplier = $group->getModelMultiplier();
                @endphp
                @if($group->ai_provider)
                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">
                        {{ $modelLabel }} ({{ $multiplier }}x)
                    </span>
                @endif
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-semibold text-blue-700">
                    {{ $group->members->count() }} members
                </span>
                <span class="rounded-full {{ $credits > 0 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-600' }} px-2.5 py-1 text-[11px] font-semibold">
                    {{ number_format($credits, 1) }} normkredit
                </span>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-3 pb-56" data-chat-group-id="{{ $group->id }}" data-auth-user-id="{{ auth()->id() }}" data-chat-feed="1">
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
                    $audioSourceMime = $attachmentMime === 'video/webm' ? 'audio/webm' : ($message->attachment_mime ?? 'audio/webm');
                @endphp

                @if($isMine)
                    {{-- User's own message - right aligned, blue --}}
                    <div class="flex justify-end" data-message-id="{{ $message->id }}">
                        <div class="max-w-[75%]">
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
                                <div class="rounded-2xl rounded-tr-sm bg-[#2563EB] px-4 py-2.5 text-sm text-white">
                                    {{ $message->content }}
                                </div>
                            @endif
                            <p class="mt-1 text-right text-[11px] text-slate-400" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                        </div>
                    </div>
                @elseif($isAi)
                    {{-- AI message - left aligned, green tinted --}}
                    <div class="max-w-[80%]" data-message-id="{{ $message->id }}">
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
                            <div class="rounded-2xl rounded-tl-sm border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-sm text-slate-800">
                                {{ $message->content }}
                            </div>
                        @endif
                        <p class="mt-1 text-[11px] font-medium text-emerald-700" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                    </div>
                @else
                    {{-- Other user message - left aligned, white --}}
                    <div class="max-w-[75%]" data-message-id="{{ $message->id }}">
                        <p class="mb-1 text-[11px] text-slate-500">{{ $senderName }}</p>
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
                            <div class="rounded-2xl rounded-tl-sm border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800">
                                {{ $message->content }}
                            </div>
                        @endif
                        <p class="mt-1 text-[11px] text-slate-400" data-message-time="{{ optional($message->created_at)->toIso8601String() }}" data-time-label="{{ $senderName }}">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                    </div>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">
                    Belum ada pesan. Mulai percakapan sekarang.
                </div>
            @endforelse
        </div>

        {{-- Input bar --}}
        <div class="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+4.8rem)] z-30 mx-auto w-full max-w-md border-t border-slate-200 bg-white px-4 py-3">
            <form method="POST" action="{{ route('chat.store', $group) }}" class="relative" enctype="multipart/form-data" data-chat-form="1">
                @csrf
                <input type="file" name="attachment" accept="image/*,audio/*" class="hidden" data-chat-attachment="1" />
                <input type="file" accept="image/*" capture="environment" class="hidden" data-chat-camera-input="1" />

                <div class="relative flex items-end gap-2" data-composer-main="1">
                    <button type="button" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100" data-open-emoji="1" title="Emoji" aria-label="Emoji">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M9 10.5h.008v.008H9V10.5Zm6 0h.008v.008H15V10.5ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </button>

                    <div class="relative flex min-w-0 flex-1 items-center gap-1 rounded-full bg-slate-100 px-3 py-1.5">
                        <textarea name="content" rows="1" placeholder="Ketik pesan" class="min-h-6 max-h-24 min-w-0 flex-1 resize-none bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400" data-mention-input="1" autocomplete="off"></textarea>

                        <button type="button" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-200" data-pick-voice="1" title="Ambil file dari perangkat" aria-label="Ambil file dari perangkat">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4.172a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 13.328 7H18.5A2.5 2.5 0 0 1 21 9.5v9A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-11Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8M8 15h5" />
                            </svg>
                        </button>

                        <button type="button" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-200" data-open-camera="1" title="Buka kamera" aria-label="Buka kamera">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2.257a2 2 0 0 0 1.743-1.02l.5-.86A1 1 0 0 1 11.866 3h.268a1 1 0 0 1 .866.48l.5.86A2 2 0 0 0 15.243 6H17.5A2.5 2.5 0 0 1 20 8.5v8A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-8Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>

                        <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-xl border border-slate-200 bg-white shadow-lg" data-mention-menu="1" data-mention-items='@json($mentionSuggestions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)'></div>
                        <div class="absolute bottom-full left-0 right-0 z-30 mb-2 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-lg" data-emoji-panel="1">
                            <div class="grid grid-cols-8 gap-1 text-xl">
                                @foreach (['😀','😁','😂','🤣','😊','😍','👍','🙏','🎉','🔥','❤️','😅','🤔','👏','😎','😢'] as $emoji)
                                    <button type="button" class="rounded-lg px-1 py-1 hover:bg-slate-100" data-emoji-item="{{ $emoji }}">{{ $emoji }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-md transition hover:brightness-110" data-record-voice="1" title="Rekam voice note" aria-label="Rekam voice note">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 0 0 3-3V7a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 11a6 6 0 1 0 12 0m-6 7v3m-3 0h6" />
                        </svg>
                    </button>

                    <button type="submit" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#22c55e] text-white shadow-md transition hover:brightness-110" data-chat-send="1" title="Kirim" aria-label="Kirim">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3.105 3.105a.75.75 0 0 1 .818-.164l12 5.25a.75.75 0 0 1 0 1.374l-12 5.25A.75.75 0 0 1 2.9 14.18l1.2-4.18a.75.75 0 0 0 0-.4l-1.2-4.18a.75.75 0 0 1 .205-.815Z" />
                        </svg>
                    </button>
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

        {{-- Slide-in settings drawer --}}
        <div class="pointer-events-none fixed inset-0 z-50 bg-slate-900/0 opacity-0 transition" data-settings-overlay="1"></div>
        <aside class="fixed bottom-0 right-0 top-0 z-50 w-[92%] max-w-md translate-x-full border-l border-slate-200 bg-[#f7f7f7] shadow-2xl transition-transform duration-300" data-settings-drawer="1">
            <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                <h3 class="text-sm font-bold text-slate-900">Settings Group</h3>
                <button type="button" class="rounded-md p-1 text-slate-500 hover:bg-slate-100" data-close-settings="1" aria-label="Tutup settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <iframe src="{{ route('settings.show', $group) }}" class="h-[calc(100%-52px)] w-full border-0" loading="lazy" title="Settings {{ $group->name }}"></iframe>
        </aside>
    </section>
@endsection
