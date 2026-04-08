@extends('layouts.app', ['title' => 'Dashboard - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        {{-- Header --}}
        <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover ring-2 ring-blue-100" referrerpolicy="no-referrer" />
                @endif
                <div>
                    <h1 class="font-display text-lg font-extrabold text-slate-900">Hi, {{ Str::words(Auth::user()->name, 1, '') }}!</h1>
                    <p class="text-xs text-slate-500">Dashboard grup kamu</p>
                </div>
            </div>
            <a href="{{ route('profile.security') }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-[#dbe6ff] bg-white text-slate-500 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </a>
        </div>

        {{-- Group list --}}
        <div class="space-y-3">
            @forelse ($groups as $group)
                @php
                    $gt = $group->groupToken;
                    $credits = $gt ? $gt->credits : 0;
                    $remaining = $gt ? $gt->remaining_tokens : 0;
                    $activeMembers = $group->members->where('status', 'active');
                    $ownerInMembers = $activeMembers->contains('user_id', $group->owner_id);
                    $memberCount = $activeMembers->count() + ($ownerInMembers ? 0 : 1);
                @endphp
                <div class="panel-card overflow-hidden">
                    <a href="{{ route('chat.show', $group) }}" class="block px-4 py-3.5 transition active:scale-[0.98]">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900"># {{ $group->name }}</h2>
                            <span class="rounded-full {{ $credits > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500' }} px-2 py-0.5 text-[10px] font-bold">
                                {{ number_format($credits, 1) }} normkredit
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $memberCount }} member &middot; NormAI aktif
                        </p>
                    </a>
                    <div class="border-t border-slate-100 bg-slate-50/50 flex items-center justify-between px-4 py-2 text-xs text-slate-600">
                        <span>Share ID: <span class="font-bold text-slate-800">{{ $group->share_id }}</span></span>
                        <div class="flex items-center gap-3">
                            <button type="button" class="font-semibold text-blue-500 hover:text-blue-700" data-copy-share-id="{{ $group->share_id }}">Copy</button>
                            <button
                                type="button"
                                class="font-semibold text-emerald-600 hover:text-emerald-700"
                                data-share-group-url="{{ route('groups.join', $group->share_id) }}"
                                data-share-group-name="{{ $group->name }}"
                            >
                                Share
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                    Belum ada group. Buat group pertama kamu.
                </div>
            @endforelse
        </div>

        {{-- Info --}}
        <div class="panel-card-muted mt-4 px-4 py-3 text-xs leading-relaxed text-blue-700">
            Normkredit milik grup (1 normkredit = 1.000 token = Rp1.000). Semua member bisa patungan top-up.
        </div>

        {{-- Create button --}}
        <a href="{{ route('groups.create') }}" class="btn-cta mt-4 py-3">
            Tambah Group Chat Baru
        </a>
    </section>
@endsection
