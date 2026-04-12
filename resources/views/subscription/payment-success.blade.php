@extends('layouts.app', ['title' => 'Payment Success - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen flex-col bg-[linear-gradient(180deg,#f0fdf4_0%,#d1fae5_100%)] px-5 pb-10 pt-8">
        <div class="mx-auto mt-12 flex h-20 w-20 items-center justify-center rounded-full bg-white text-emerald-600 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-6 text-center font-display text-3xl font-extrabold text-emerald-900">Payment Success!</h1>
        <p class="mx-auto mt-3 max-w-xs text-center text-sm text-emerald-700">
            Langganan aktif! Sekarang buat grup pertama kamu agar normkredit bisa langsung dialokasikan.
        </p>

        <div class="mx-auto mt-6 w-full max-w-sm rounded-2xl bg-white/80 p-5 shadow-sm">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-emerald-700">Paket</span>
                    <span class="font-bold text-emerald-900">Normchat Pro</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-emerald-700">Normkredit</span>
                    <span class="font-bold text-emerald-900">12 normkredit</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-emerald-700">Status</span>
                    <span class="font-bold text-emerald-600">Aktif</span>
                </div>
            </div>
        </div>

        <a href="{{ route('groups.create') }}" class="btn-cta mt-auto py-4">
            Buat Group Sekarang
        </a>

        <p class="mt-3 text-center text-[11px] text-emerald-600/70">
            Normkredit akan dialokasikan ke grup saat grup dibuat.
        </p>

        <script>
            setTimeout(() => {
                window.location.href = @json(route('groups.create'));
            }, 3000);
        </script>
    </section>
@endsection
