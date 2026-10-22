@extends('layouts.app', ['title' => 'Berhasil - Normi Chat'])

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen flex-col bg-navy-50 px-5 pb-10 pt-8">
        <div class="mx-auto mt-12 flex h-20 w-20 items-center justify-center rounded-full bg-white text-navy-600 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-6 text-center font-display text-3xl font-extrabold text-navy-900">Normkredit bertambah</h1>

        @if($purchase)
            <div class="mx-auto mt-6 w-full max-w-sm rounded-2xl bg-white/80 p-5 shadow-sm">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-navy-700">Grup</span>
                        <span class="font-bold text-navy-900">{{ $purchase['group_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-navy-700">Normkredit</span>
                        <span class="font-bold text-navy-900">{{ number_format((float) $purchase['normkredits'], 0, ',', '.') }}</span>
                    </div>
                    @if(($purchase['paid_amount'] ?? 0) > 0)
                    <div class="flex justify-between">
                        <span class="text-navy-700">Dibayar</span>
                        <span class="font-bold text-navy-900">
                            Rp{{ number_format((int) $purchase['paid_amount'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        @endif

        <p class="mx-auto mt-4 max-w-[28ch] text-center text-sm text-navy-700">
            Sudah bisa langsung dipakai di grup.
        </p>

        <a href="{{ route('groups.index') }}" class="btn-cta mt-auto py-4">
            Kembali ke Beranda
        </a>
    </section>
@endsection
