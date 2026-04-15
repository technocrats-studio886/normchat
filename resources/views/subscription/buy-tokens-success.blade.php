@extends('layouts.app', ['title' => 'Normkredit Berhasil - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen flex-col bg-[linear-gradient(180deg,#f0fdf4_0%,#d1fae5_100%)] px-5 pb-10 pt-8">
        <div class="mx-auto mt-12 flex h-20 w-20 items-center justify-center rounded-full bg-white text-emerald-600 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-6 text-center font-display text-3xl font-extrabold text-emerald-900">Normkredit Ditambahkan!</h1>

        @if($purchase)
            <div class="mx-auto mt-6 w-full max-w-sm rounded-2xl bg-white/80 p-5 shadow-sm">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-emerald-700">Grup</span>
                        <span class="font-bold text-emerald-900">{{ $purchase['group_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-emerald-700">Normkredit</span>
                        <span class="font-bold text-emerald-900">{{ $purchase['normkredits'] }}</span>
                    </div>
                    @if(($purchase['paid_amount'] ?? 0) > 0)
                    <div class="flex justify-between">
                        <span class="text-emerald-700">Dibayar</span>
                        <span class="font-bold text-emerald-900">
                            @if(($purchase['payment_unit'] ?? 'DU') === 'IDR')
                                Rp{{ number_format((int) $purchase['paid_amount'], 0, ',', '.') }}
                            @else
                                {{ (int) $purchase['paid_amount'] }} DU
                            @endif
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        @endif

        <p class="mx-auto mt-4 max-w-[28ch] text-center text-sm text-emerald-700">
            Normkredit sudah masuk ke saldo grup dan bisa langsung dipakai.
        </p>

        <a href="{{ route('groups.index') }}" class="btn-cta mt-auto py-4">
            Kembali ke Home
        </a>
    </section>
@endsection
