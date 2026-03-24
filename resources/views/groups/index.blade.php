@extends('layouts.app', ['title' => 'Dashboard - Normchat'])

@section('content')
    <section class="page-shell pt-4">
        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between">
            <h1 class="font-display text-xl font-extrabold text-slate-900">Owner Dashboard</h1>
            @if($groups->first())
                <a href="{{ route('settings.show', $groups->first()) }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-[#dbe6ff] bg-white text-slate-500 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
            @endif
        </div>

        {{-- Search bar --}}
        <div class="panel-card mb-4 px-4 py-2.5 text-sm text-slate-400">
            Cari room chat...
        </div>

        {{-- Success/info messages --}}
        @if(session('success'))
            <div class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Group list --}}
        <div class="space-y-3">
            @forelse ($groups as $group)
                <div class="panel-card px-4 py-3.5">
                    <a href="{{ route('chat.show', $group) }}" class="block transition active:scale-[0.98]">
                        <h2 class="text-base font-bold text-slate-900"># {{ $group->name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $group->members_count }} member •
                            AI {{ $group->aiConnections->where('active', true)->count() > 0 ? 'aktif' : 'nonaktif' }}
                        </p>
                    </a>
                    <div class="panel-card-muted mt-3 flex items-center justify-between px-3 py-2 text-xs text-slate-600">
                        <span>Share ID: <span class="font-bold text-slate-800">{{ $group->share_id }}</span></span>
                        <button onclick="navigator.clipboard.writeText('{{ $group->share_id }}')" class="text-blue-500 font-semibold hover:text-blue-700">Copy</button>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                    Belum ada group. Buat group pertama Anda sebagai owner.
                </div>
            @endforelse
        </div>

        {{-- Owner info --}}
        <div class="panel-card-muted mt-4 px-4 py-3 text-xs leading-relaxed text-blue-700">
            Member baru masuk dengan memasukkan Share ID + password grup.
        </div>

        {{-- Create button --}}
        <a href="{{ route('groups.create') }}" class="btn-cta mt-4 py-3">
            Tambah Group Chat Baru
        </a>
    </section>
@endsection
