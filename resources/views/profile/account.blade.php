@extends('layouts.app', ['title' => 'Akun - Normchat'])

@section('content')
    <section class="page-shell pt-5">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Profil Akun</h1>
        <p class="mt-1 text-sm text-slate-500">Perbarui data akun yang tampil di Normchat.</p>

        <form method="POST" action="{{ route('profile.account.update') }}" enctype="multipart/form-data" class="mt-5 space-y-3" id="accountForm">
            @csrf

            {{-- Avatar + info card (clickable avatar to change photo) --}}
            <div class="rounded-2xl border border-[#dbe6ff] bg-white px-4 py-4 shadow-sm">
                <div class="flex items-center gap-4">
                    <label for="avatar" class="relative cursor-pointer group shrink-0" title="Klik untuk ganti foto profil">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-2 ring-blue-100 transition group-hover:opacity-75" referrerpolicy="no-referrer" id="avatarPreview" />
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-xl font-bold text-blue-600 transition group-hover:opacity-75" id="avatarInitial">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <img src="" alt="" class="hidden h-16 w-16 rounded-full object-cover ring-2 ring-blue-100" id="avatarPreview" />
                        @endif
                        {{-- Camera badge --}}
                        <span class="absolute -bottom-0.5 -right-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white shadow ring-2 ring-white">
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
                            onchange="previewAvatar(this)"
                        />
                    </label>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-blue-600">Interdotz SSO</p>
                    </div>
                </div>
            </div>

            <div class="panel-card px-4 py-3">
                <label for="name" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Display Name</label>
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
                <label for="username" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Username</label>
                <div class="mt-1 flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100">
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
                <p class="mt-1 text-[11px] text-slate-500">Username hanya bisa diubah langsung dari akun Interdotz.</p>
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
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bergabung Sejak</label>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $user->created_at?->timezone(config('app.display_timezone', config('app.timezone')))->translatedFormat('d F Y') }}</p>
            </div>

            <button type="submit" class="btn-cta">Simpan Perubahan</button>
        </form>
    </section>

    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('avatarPreview');
                    const initial = document.getElementById('avatarInitial');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                    }
                    if (initial) {
                        initial.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
@endsection
