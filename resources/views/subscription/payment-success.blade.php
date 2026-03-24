@extends('layouts.app', ['title' => 'Payment Success - Normchat'])

@section('disableAppJs', '1')

@section('content')
    <section class="flex min-h-screen flex-col bg-[linear-gradient(180deg,#f0fdf4_0%,#d1fae5_100%)] px-5 pb-10 pt-8">
        <div class="mx-auto mt-12 flex h-20 w-20 items-center justify-center rounded-full bg-white text-emerald-600 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-6 text-center font-display text-3xl font-extrabold text-emerald-900">Payment Success</h1>
        <p class="mx-auto mt-3 max-w-[28ch] text-center text-sm text-emerald-700">
            Langganan aktif. Anda langsung masuk ke home untuk mulai membuat grup.
        </p>

        <a href="{{ route('groups.index') }}" class="btn-cta mt-auto py-4">
            Masuk ke Home
        </a>

        <script>
            setTimeout(() => {
                window.location.href = @json(route('groups.index'));
            }, 2000);
        </script>
    </section>
@endsection
