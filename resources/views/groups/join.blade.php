@extends('layouts.app', ['title' => 'Join Group - Normchat'])

@section('content')
    <section class="page-shell flex flex-col items-center justify-center pt-16 px-6">
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
                <a href="{{ route('chat.show', $group) }}"
                   class="btn-cta mt-4 block text-center">
                    Masuk ke Chat
                </a>
            @else
                <p class="mt-4 text-sm text-slate-500">Masukkan password grup untuk bergabung.</p>

                <form method="POST" action="{{ route('groups.join.submit', $group->share_id) }}" class="mt-4 space-y-3">
                    @csrf

                    <div class="panel-card px-4 py-3">
                        <input type="password" name="password" required
                               placeholder="Password Grup"
                               class="w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400" />
                    </div>

                    @if($errors->has('password'))
                        <p class="text-xs text-rose-600">{{ $errors->first('password') }}</p>
                    @endif

                    <button type="submit" class="btn-cta w-full">Bergabung</button>
                </form>
            @endif
        </div>
    </section>
@endsection
