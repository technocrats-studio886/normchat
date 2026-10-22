@extends('layouts.app', ['title' => 'Join Group - Normchat'])

@section('content')
    <section class="page-shell flex flex-col items-center justify-center px-6 pt-10">
        <div class="w-full max-w-sm">
            <h1 class="font-display text-xl font-extrabold text-slate-900">{{ $group->name }}</h1>
            <p class="mt-1 text-xs text-slate-400">ID: {{ $group->share_id }}</p>

            @if($group->description)
                <p class="mt-1 text-sm text-slate-500">{{ $group->description }}</p>
            @endif

            @if($alreadyMember)
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Anda sudah menjadi member group ini.
                </div>
                <a href="{{ route('chat.show', $group) }}" class="btn-cta mt-4 block text-center">
                    Masuk ke Chat
                </a>
            @else
                {{-- payment errors shown by layout --}}

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-800">Gabung dengan ikut patungan dana AI grup</p>
                    <p class="mt-1 text-xs text-amber-700">Kredit bersama grup saat ini: <strong>{{ number_format((float) ($group->groupToken?->shared_credits ?? 0), 2, ',', '.') }} NK</strong>.</p>
                    @if($group->member_daily_shared_limit)
                        <p class="mt-1 text-xs text-amber-700">Owner membatasi pemakaian kredit bersama {{ number_format($group->member_daily_shared_limit, 2, ',', '.') }} NK per anggota per hari.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-5 space-y-3">
                    @csrf

                    {{-- Password --}}
                    <div class="panel-card px-4 py-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Password Grup</label>
                        <div class="relative mt-1">
                            <input id="joinGroupPasswordInput" type="password" name="password" required
                                   placeholder="Masukkan password grup"
                                   class="w-full bg-transparent pr-10 text-sm text-slate-900 outline-none placeholder:text-slate-400" />
                            <button type="button" data-password-toggle="joinGroupPasswordInput" class="absolute inset-y-0 right-0 inline-flex items-center text-slate-400 hover:text-slate-600" aria-label="Lihat password" title="Lihat password">
                                <svg data-eye-icon="off" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg data-eye-icon="on" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 1 0 2.83 2.83" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.48 0 8.27 2.94 9.54 7a11.05 11.05 0 0 1-4.2 5.17" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A11.05 11.05 0 0 0 2.46 12c1.27 4.06 5.06 7 9.54 7 1.58 0 3.09-.37 4.42-1.03" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    @if($errors->has('password'))
                        <p class="text-xs text-rose-600">{{ $errors->first('password') }}</p>
                    @endif

                    @include('partials.package-picker', [
                        'label' => 'Patungan',
                        'submitLabel' => 'Bergabung',
                    ])
                    @error('payment')
                        <p class="text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </form>
            @endif
        </div>
    </section>
@endsection
