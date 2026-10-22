@extends('layouts.app', ['title' => 'Top-up Normkredit - Normchat'])

@section('content')
    <section class="page-shell pt-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-2xl font-extrabold text-slate-900">Top-up Normkredit</h1>

        @if($errors->has('payment'))
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('payment') }}
            </div>
        @endif

        <form method="POST" action="{{ route('subscription.tokens.buy.process') }}" class="mt-6 space-y-4">
            @csrf

            @php $ctxGt = $contextGroup->groupToken; @endphp
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Grup Tujuan</label>
                <div class="mt-2 flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-800">{{ $contextGroup->name }}</p>
                        <p class="text-[11px] text-slate-500">Saldo: {{ number_format($ctxGt?->credits ?? 0, 2, ',', '.') }} NK</p>
                    </div>
                </div>
                <input type="hidden" name="group_id" value="{{ $contextGroup->id }}" />
            </div>

            @include('partials.package-picker', [
                'label' => 'Jumlah paket',
                'submitLabel' => 'Top-up',
            ])
        </form>

    </section>
@endsection
