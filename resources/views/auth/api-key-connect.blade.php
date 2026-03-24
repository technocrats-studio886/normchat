@extends('layouts.app', ['title' => 'Connect ' . ucfirst($provider) . ' - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="page-shell flex min-h-screen flex-col bg-transparent px-0 pb-0 pt-0">
        {{-- Header --}}
        <header class="border-b border-[#dbe6ff] bg-white px-5 py-5">
            <div class="inline-flex items-center gap-2">
                <img src="{{ asset('normchat-logo.svg') }}" alt="Normchat Logo" class="h-9 w-9 rounded-xl" />
                <h1 class="font-display text-xl font-extrabold tracking-tight text-slate-900">Normchat</h1>
            </div>
        </header>

        <div class="flex flex-1 flex-col px-6 pb-12 pt-8">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
                <span aria-hidden="true">&larr;</span> Kembali
            </a>

            @php
                $providerLabel = $provider === 'chatgpt' ? 'ChatGPT' : 'Claude';
                $providerColor = $provider === 'chatgpt' ? 'emerald' : 'amber';
                $keyPlaceholder = $provider === 'chatgpt' ? 'sk-...' : 'sk-ant-...';
                $keyHelp = $provider === 'chatgpt'
                    ? 'Dapatkan API key dari platform.openai.com/api-keys'
                    : 'Dapatkan API key dari console.anthropic.com/settings/keys';
            @endphp

            <h2 class="mt-4 font-display text-[26px] font-extrabold leading-tight text-slate-900">
                Connect {{ $providerLabel }}
            </h2>
            <p class="mt-2 text-sm text-slate-500">
                Masukkan API key {{ $providerLabel }} kamu untuk login dan menggunakan AI di grup.
            </p>

            <form method="POST" action="{{ route('auth.connect.' . $provider . '.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-700">Nama</label>
                    <input type="text" name="name" id="name" required value="{{ old('name') }}"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                        placeholder="Nama kamu" />
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700">Email</label>
                    <input type="email" name="email" id="email" required value="{{ old('email') }}"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                        placeholder="email@contoh.com" />
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="api_key" class="block text-xs font-semibold text-slate-700">API Key {{ $providerLabel }}</label>
                    <input type="password" name="api_key" id="api_key" required
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                        placeholder="{{ $keyPlaceholder }}" />
                    <p class="mt-1 text-[11px] text-slate-400">{{ $keyHelp }}</p>
                    @error('api_key')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full rounded-2xl bg-gradient-to-r from-slate-800 to-slate-900 px-4 py-3.5 text-sm font-bold text-white shadow-lg transition hover:brightness-110">
                    Connect {{ $providerLabel }}
                </button>
            </form>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                API key dienkripsi AES-256 dan hanya dipakai untuk akses AI di grup.
            </p>
        </div>
    </section>
@endsection
