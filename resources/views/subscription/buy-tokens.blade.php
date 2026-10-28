@extends('layouts.app', ['title' => 'Isi Normkredit - Normi Chat'])

@section('content')
    <section class="page-shell">
        <x-page-header :back="url()->previous()" title="Isi Normkredit" />

        @if($errors->has('payment'))
            <div class="mt-3 rounded-xl border border-danger-line bg-danger-soft px-4 py-3 text-sm text-danger-ink">
                {{ $errors->first('payment') }}
            </div>
        @endif

        <form method="POST" action="{{ route('subscription.tokens.buy.process') }}" class="mt-6 space-y-4">
            @csrf

            @php $ctxGt = $contextGroup->groupToken; @endphp
            <div class="panel-card rounded-2xl p-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Grup</label>
                <div class="mt-2 flex items-center justify-between rounded-xl border border-primary-line bg-primary-soft px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-800">{{ $contextGroup->name }}</p>
                        <p class="text-[11px] text-slate-500">Saldo: {{ number_format($ctxGt?->credits ?? 0, 2, ',', '.') }} Normkredit</p>
                    </div>
                </div>
                <input type="hidden" name="group_id" value="{{ $contextGroup->id }}" />
            </div>

            @include('partials.package-picker', [
                'label' => 'Jumlah paket',
                'submitLabel' => 'Isi Normkredit',
            ])
        </form>

    </section>
@endsection
