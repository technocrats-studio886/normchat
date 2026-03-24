@extends('layouts.app', ['title' => 'Buat Group - Normchat'])

@section('content')
    <section class="page-shell pt-4">
        <h1 class="mb-4 text-xl font-extrabold text-slate-900 font-display">Buat Group Chat</h1>

        <form method="POST" action="{{ route('groups.store') }}" class="space-y-3">
            @csrf

            {{-- Nama Group --}}
            <div class="panel-card px-4 py-3">
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Nama Group" required class="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400" />
            </div>

            {{-- Deskripsi --}}
            <div class="panel-card px-4 py-3">
                <textarea name="description" rows="2" placeholder="Deskripsi Group" class="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 resize-none">{{ old('description') }}</textarea>
            </div>

            {{-- Password (wajib) --}}
            <div class="panel-card px-4 py-3">
                <h3 class="text-sm font-bold text-slate-900">Password Grup</h3>
                <p class="text-xs text-slate-500">Member harus memasukkan password saat bergabung via link undangan.</p>
                <input type="password" name="password" value="{{ old('password') }}" placeholder="Buat password grup" required minlength="4" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none" />
            </div>

            {{-- Owner Control --}}
            <div class="panel-card px-4 py-3">
                <h3 class="text-sm font-bold text-slate-900">Owner Control</h3>
                <p class="text-xs text-slate-500">Member masuk hanya melalui link undangan + password</p>
                <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="approval_enabled" value="1" class="rounded border-slate-300">
                    Wajib approval sebelum join
                </label>
            </div>

            {{-- AI Provider info (auto from owner) --}}
            <div class="panel-card px-4 py-3">
                <h3 class="text-sm font-bold text-slate-900">AI Provider</h3>
                <p class="text-xs text-slate-500">Otomatis menggunakan provider yang kamu connect saat login.</p>
                <div class="mt-2 flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md
                        @if(Auth::user()->auth_provider === 'chatgpt') bg-[#10A37F]
                        @elseif(Auth::user()->auth_provider === 'claude') bg-[#D97706]
                        @else bg-[#4285F4]
                        @endif text-white">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 24C12 24 12 12 24 12C12 12 12 0 12 0C12 0 12 12 0 12C12 12 12 24 12 24Z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-semibold text-slate-700">{{ ucfirst(Auth::user()->auth_provider) }}</span>
                    <span class="ml-auto text-xs text-emerald-600 font-semibold">Connected</span>
                </div>
            </div>

            <button type="submit" class="btn-cta">Create Group</button>
        </form>
    </section>
@endsection
