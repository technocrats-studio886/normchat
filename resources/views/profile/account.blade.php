@extends('layouts.app', ['title' => 'Profil - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="route('profile.show')" title="Profil" subtitle="Nama dan foto yang dilihat anggota lain." />

        <form method="POST" action="{{ route('profile.account.update') }}" enctype="multipart/form-data" class="mt-5 space-y-3" id="accountForm">
            @csrf

            {{-- Avatar + info card (clickable avatar to change photo) --}}
            <div class="rounded-2xl border border-[var(--nc-line)] bg-white px-4 py-4 shadow-sm">
                <div class="flex items-center gap-4">
                    <label for="avatar" class="relative inline-flex h-16 w-16 items-center justify-center overflow-visible cursor-pointer group shrink-0" title="Ganti foto">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-2 ring-primary-line transition group-hover:opacity-75" referrerpolicy="no-referrer" data-avatar-preview />
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-soft text-xl font-bold text-primary-ink transition group-hover:opacity-75" data-avatar-initial>
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <img src="" alt="" class="hidden h-16 w-16 rounded-full object-cover ring-2 ring-primary-line" data-avatar-preview />
                        @endif
                        {{-- Camera badge --}}
                        <span class="absolute -bottom-0.5 -right-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white shadow ring-2 ring-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                            </svg>
                        </span>
                        <input
                            id="avatar"
                            type="file"
                            name="avatar"
                            accept="image/png,image/jpeg,image/webp"
                            class="sr-only"
                            style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"
                            data-avatar-input
                        />
                    </label>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-primary-ink">Masuk dengan {{ $user->authProviderLabel() }}</p>
                    </div>
                </div>
            </div>

            <div class="panel-card px-4 py-3">
                <label for="name" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    maxlength="120"
                    required
                    class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                />
            </div>

            <div class="panel-card px-4 py-3">
                <label for="username" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nama pengguna</label>
                <div class="mt-1 flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                    <span class="text-sm text-slate-400">@</span>
                    <input
                        id="username"
                        type="text"
                        value="{{ $username }}"
                        maxlength="40"
                        readonly
                        class="ml-2 w-full cursor-not-allowed bg-transparent text-sm text-slate-500 outline-none"
                    />
                </div>
                <p class="mt-1 text-[11px] text-slate-500">Dibuat otomatis dan tidak bisa diubah.</p>
            </div>

            <div class="panel-card px-4 py-3">
                <label for="email" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</label>
                <input
                    id="email"
                    type="email"
                    value="{{ $user->email }}"
                    readonly
                    class="mt-1 w-full cursor-not-allowed bg-transparent text-sm text-slate-500 outline-none"
                />
            </div>

            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bergabung</label>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ \App\Support\UserTime::format($user->created_at, 'j F Y') }}</p>
            </div>

            <button type="submit" class="btn-cta">Simpan</button>
        </form>
    </section>
@endsection
