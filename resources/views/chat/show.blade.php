@extends('layouts.app', ['title' => $group->name.' - Normchat', 'group' => $group])

@section('content')
    <section class="flex min-h-[calc(100vh-5rem)] flex-col">
        {{-- Group name row --}}
        <div class="flex items-center justify-between px-5 pb-2">
            <a href="{{ route('groups.index') }}" class="text-sm text-slate-500"># {{ $group->name }}</a>
            <a href="{{ route('settings.show', $group) }}" class="text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
            </a>
        </div>

        {{-- Group info card --}}
        <div class="mx-5 mb-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <h2 class="text-lg font-bold text-slate-900"># {{ $group->name }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @php
                    $hasActiveAi = isset($activeAi) && (is_countable($activeAi) ? count($activeAi) > 0 : !empty($activeAi));
                @endphp
                <span class="rounded-full {{ $hasActiveAi ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} px-2.5 py-1 text-[11px] font-semibold">
                    AI {{ $hasActiveAi ? 'online' : 'offline' }}
                </span>
                @if(isset($activeGroupAi) && $activeGroupAi)
                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">
                        Provider: {{ strtoupper($activeGroupAi->provider) }}
                    </span>
                @endif
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-semibold text-blue-700">
                    {{ $group->members->count() }} members
                </span>
                <span class="text-[11px] text-slate-400">Realtime: connected</span>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-3" data-chat-group-id="{{ $group->id }}" data-auth-user-id="{{ auth()->id() }}" data-chat-feed="1">
            @forelse ($messages as $message)
                @php
                    $isMine = $message->sender_type === 'user' && $message->sender_id === auth()->id();
                    $isAi = $message->sender_type === 'ai';
                    $senderName = $isAi ? ($message->sender_name ?? 'NormAI') : ($isMine ? 'You' : ($message->sender->name ?? 'User'));
                @endphp

                @if($isMine)
                    {{-- User's own message - right aligned, blue --}}
                    <div class="flex justify-end" data-message-id="{{ $message->id }}">
                        <div class="max-w-[75%]">
                            <div class="rounded-2xl rounded-tr-sm bg-[#2563EB] px-4 py-2.5 text-sm text-white">
                                {{ $message->content }}
                            </div>
                            <p class="mt-1 text-right text-[11px] text-slate-400">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                        </div>
                    </div>
                @elseif($isAi)
                    {{-- AI message - left aligned, green tinted --}}
                    <div class="max-w-[80%]" data-message-id="{{ $message->id }}">
                        <div class="rounded-2xl rounded-tl-sm border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-sm text-slate-800">
                            {{ $message->content }}
                        </div>
                        <p class="mt-1 text-[11px] font-medium text-emerald-700">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                    </div>
                @else
                    {{-- Other user message - left aligned, white --}}
                    <div class="max-w-[75%]" data-message-id="{{ $message->id }}">
                        <p class="mb-1 text-[11px] text-slate-500">{{ $senderName }}</p>
                        <div class="rounded-2xl rounded-tl-sm border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800">
                            {{ $message->content }}
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $senderName }} • {{ $message->created_at?->format('H:i') }}</p>
                    </div>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">
                    Belum ada pesan. Mulai percakapan sekarang.
                </div>
            @endforelse
        </div>

        {{-- Input bar --}}
        <div class="sticky bottom-20 z-20 border-t border-slate-200 bg-white px-5 py-3">
            <form method="POST" action="{{ route('chat.store', $group) }}" class="flex items-center gap-3">
                @csrf
                <input type="text" name="content" placeholder="Tulis pesan, gunakan @ai untuk masuk antrean AI..." required class="flex-1 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400" />
                <button type="submit" class="text-sm font-bold text-blue-600">Kirim</button>
            </form>
        </div>
    </section>
@endsection
