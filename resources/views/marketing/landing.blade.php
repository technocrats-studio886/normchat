<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c0267a">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Normchat — Workspace Chat untuk Tim yang Butuh Cepat dan Rapi</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-[#2a0e21] text-white antialiased">
    @php
        $marketingBase = rtrim((string) config('app.marketing_url', config('app.url')), '/');
        $marketingPath = '/'.trim((string) config('app.normchat_marketing_path', '/normchat'), '/');
        $landingUrl = $marketingBase.$marketingPath;

        $appBase = rtrim((string) config('app.normchat_app_url', config('app.url')), '/');
        $pricingUrl = $appBase.'/pricing';
        $loginToSubscriptionUrl = $appBase.'/login?next=subscription.payment.detail';
    @endphp

    <main class="relative overflow-hidden">
        <div class="pointer-events-none absolute -left-20 top-0 h-80 w-80 rounded-full bg-fuchsia-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute right-0 top-24 h-96 w-96 rounded-full bg-orange-300/20 blur-3xl"></div>

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-7 lg:px-10">
            <a href="{{ $landingUrl }}" class="inline-flex items-center gap-3">
                <img src="{{ asset('normchat-logo.png') }}" alt="Normchat" class="h-9 w-9 rounded-lg bg-white p-1" />
                <span class="font-display text-xl font-extrabold tracking-tight text-rose-100">Normchat</span>
            </a>
            <a href="{{ $loginToSubscriptionUrl }}" class="rounded-xl border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-100 transition hover:bg-white/10">
                Login Dulu
            </a>
        </header>

        <section class="mx-auto w-full max-w-6xl px-6 pb-20 pt-8 lg:px-10">
            <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <p class="inline-flex rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-100">
                        Chat workspace untuk tim
                    </p>

                    <h1 class="mt-5 font-display text-4xl font-extrabold leading-tight text-white md:text-5xl lg:text-6xl">
                        Bangun komunikasi tim yang
                        <span class="text-orange-300">cepat, tertata,</span>
                        dan siap dibantu AI.
                    </h1>

                    <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-200 md:text-lg">
                        Normchat membantu tim menjaga percakapan tetap fokus: chat real-time, akses berbasis Share ID,
                        kontrol penuh untuk owner, dan ekspor histori yang rapi dalam satu tempat.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3 text-sm text-slate-100">
                        <span class="rounded-full border border-white/20 bg-white/5 px-4 py-2">Realtime Group Chat</span>
                        <span class="rounded-full border border-white/20 bg-white/5 px-4 py-2">AI Assist di Percakapan</span>
                        <span class="rounded-full border border-white/20 bg-white/5 px-4 py-2">Invite-Only Access</span>
                        <span class="rounded-full border border-white/20 bg-white/5 px-4 py-2">Backup & Export</span>
                    </div>

                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a href="{{ $loginToSubscriptionUrl }}" class="inline-flex items-center justify-center rounded-2xl bg-linear-to-r from-[#c0267a] to-[#f59e0b] px-8 py-4 text-sm font-extrabold uppercase tracking-wide text-white shadow-lg shadow-rose-900/35 transition hover:brightness-105">
                            Login & Mulai
                        </a>
                        <a href="{{ $loginToSubscriptionUrl }}" class="inline-flex items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-sm font-semibold text-slate-100 transition hover:bg-white/10">
                            Login ke Subscription
                        </a>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/15 bg-white/5 p-6 backdrop-blur-sm">
                    <h2 class="font-display text-2xl font-extrabold text-white">Kenapa Normchat terasa beda?</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-200">
                        Normchat dirancang untuk tim, komunitas, dan organisasi yang butuh komunikasi cepat tanpa kehilangan kendali.
                        Bukan sekadar chat, tapi ruang kerja yang membantu percakapan tetap relevan, aman, dan mudah ditelusuri.
                    </p>

                    <div class="mt-6 space-y-3 text-sm text-slate-100">
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            Fokus di satu ruang kerja, jadi diskusi tidak tercecer ke banyak tempat.
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            Owner punya kontrol penuh atas role, akses, dan histori.
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            AI hadir saat dibutuhkan: untuk ringkasan, drafting, dan respons cepat.
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            Akses lebih aman karena member masuk lewat Share ID + password, bukan publik terbuka.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-6xl px-6 pb-12 lg:px-10">
            <div class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-white/15 bg-white/5 p-5">
                    <h3 class="font-display text-lg font-bold text-rose-100">Percakapan Tetap Fokus</h3>
                    <p class="mt-2 text-sm text-slate-200">
                        Cocok untuk diskusi proyek, operasional tim, support komunitas, dan koordinasi harian tanpa berantakan.
                    </p>
                </article>
                <article class="rounded-2xl border border-white/15 bg-white/5 p-5">
                    <h3 class="font-display text-lg font-bold text-rose-100">AI yang Membantu, Bukan Mengganggu</h3>
                    <p class="mt-2 text-sm text-slate-200">
                        AI bisa dipanggil saat dibutuhkan untuk meringkas, menyusun balasan, atau membantu klarifikasi konteks.
                    </p>
                </article>
                <article class="rounded-2xl border border-white/15 bg-white/5 p-5">
                    <h3 class="font-display text-lg font-bold text-rose-100">Siap untuk Organisasi yang Tertib</h3>
                    <p class="mt-2 text-sm text-slate-200">
                        Export PDF/DOCX, backup snapshot, dan histori percakapan membantu dokumentasi tetap rapi dan mudah dicari.
                    </p>
                </article>
            </div>
        </section>

        <section class="mx-auto w-full max-w-6xl px-6 pb-24 lg:px-10">
            <div class="rounded-3xl border border-rose-300/25 bg-linear-to-r from-[#38122a] to-[#52261a] p-8 md:p-10">
                <div class="grid gap-8 md:grid-cols-[1.1fr_0.9fr] md:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-200">Pricing Overview</p>
                        <h2 class="mt-3 font-display text-3xl font-extrabold text-white">Normchat Pro</h2>
                        <p class="mt-2 text-sm text-slate-200">
                            Satu paket untuk akses penuh fitur owner, AI, dan export histori.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-100">Mulai dari</p>
                        <p class="mt-2 text-4xl font-extrabold text-white">
                            Rp30.000<span class="text-base font-medium text-slate-300">/grup</span>
                        </p>
                        <a href="{{ $loginToSubscriptionUrl }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-extrabold uppercase tracking-wide text-[#0f1f33] transition hover:bg-slate-100">
                            Login & Lihat Subscription
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>