{{--
    normi.chat dan /login: satu kolom di tengah. Judul dan penjelasan singkat, kartu masuk, lalu contoh obrolan hidup
    di bawahnya. Copy: docs/rancangan-grup-gratis-login.md bagian 5.
--}}
@section('disableAppJs', '1')
@php
    $uiTheme = \App\Support\Themes::for(null);
    $uiMode = $uiTheme['mode'] === 'dark' ? 'dark' : 'light';
    $title = 'Normi Chat | Grup chat dengan AI';
    $credits = $freeCredits ? number_format($freeCredits, 0, ',', '.') : null;
    // Sama dengan /home dan /pricing: dua grup pertama gratis (70 lalu 50 NK), grup ke-3 dan seterusnya berbayar.
    $second = ($freeSecondCredits ?? null) ? number_format($freeSecondCredits, 0, ',', '.') : null;
    $offer = $credits
        ? ($second
            ? 'Dua grup pertamamu gratis, dengan ' . $credits . ' dan ' . $second . ' Normkredit.'
            : 'Grup pertamamu gratis dengan ' . $credits . ' Normkredit.')
          . ($freeSlots > 0 ? ' Di tiap grup gratis, ' . $freeSlots . ' teman pertama ikut tanpa biaya.' : '')
        : null;
@endphp
<!DOCTYPE html>
<html lang="id" data-theme-mode="{{ $uiTheme['mode'] }}" data-mode="{{ $uiMode }}" data-accent="{{ $uiTheme['accent'] }}">
<head>
    @include('partials.app-head')
    {{-- Font pemasaran (sama dengan situs marketing): judul Bricolage Grotesque, isi Hanken Grotesk. --}}
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..700&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <meta name="description" content="Ajak teman ke satu grup dan tanya @normi bareng.{{ $credits ? ' Mulai gratis dengan ' . $credits . ' Normkredit.' : '' }}">
</head>
<body class="nc-login antialiased">
    <main class="nc-login-single">
        <a href="{{ route('home') }}" class="nc-login-brand">
            <x-brand-mark width="32" height="32" />
            <span>Normi Chat</span>
        </a>

        <h1 class="nc-login-title">Dari obrolan jadi rencana.</h1>
        <p class="nc-login-lead">Satu AI untuk seluruh grupmu. Cukup sebut <span class="nc-login-mention">@normi</span>.</p>

        <div class="nc-login-card">
            <h2 class="nc-login-card-title">Masuk ke Normi Chat</h2>
            <p class="nc-login-card-sub">Pakai akun Google-mu.</p>

            @if(session('info'))
                <p class="nc-login-alert" role="alert">{{ session('info') }}</p>
            @endif
            @if($errors->any())
                <p class="nc-login-alert" role="alert">{{ $errors->first() }}</p>
            @endif

            <div class="mt-6 space-y-3">
                <a href="{{ route('auth.google.redirect') }}" class="nc-login-google" data-cta>
                    <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#C43D2E" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                        <path fill="#C43D2E" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                        <path fill="#7186A3" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                        <path fill="#526884" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                    </svg>
                    Masuk dengan Google
                </a>

                @if($interdotzEnabled)
                    <a href="{{ route('auth.interdotz.login') }}" class="nc-login-google is-plain" data-cta>Masuk dengan Interdotz</a>
                @endif
            </div>

            @if($offer)
                <p class="nc-login-note">{{ $offer }}</p>
            @endif
            <p class="nc-login-legal">Dengan masuk, kamu menyetujui <a href="{{ route('terms') }}">Syarat Layanan</a> dan <a href="{{ route('privacy') }}">Kebijakan Privasi</a> Normi Chat.</p>
        </div>


        {{-- Contoh obrolan yang diputar langsung (partials/live-demo-script); hiasan, tidak dibacakan pembaca layar. --}}
        <section class="nc-login-preview" aria-label="Contoh obrolan">
            <p class="nc-login-preview-title">Begini Normi bekerja di grup</p>
            <p class="nc-login-preview-sub">Teman bertanya, kamu panggil <span class="nc-login-mention">@normi</span>, dan semua membaca jawaban yang sama.</p>
            <div class="nc-login-demo" aria-hidden="true" data-live-demo>
                <p class="nc-login-bubble" data-demo-step><b>Sari</b>Jadi fix berangkat Sabtu? Budget per orang berapa?</p>
                <p class="nc-login-bubble is-mine" data-demo-step data-demo-pause="1300"><span class="nc-login-mention" data-demo-mention>@normi</span> rangkum dong</p>
                <p class="nc-login-bubble is-normi nc-login-typing" data-demo-typing><b>Normi</b><span class="nc-login-dots"><i></i><i></i><i></i></span> sedang berpikir</p>
                <p class="nc-login-bubble is-normi" data-demo-step><b>Normi</b><span data-demo-answer>Berangkat Sabtu 07.00 dari kampus, patungan Rp150.000 per orang untuk 4 orang. Tinggal siapa yang bawa mobil yang belum diputuskan.</span></p>
            </div>
        </section>

        <nav class="nc-login-foot" aria-label="Tentang Normi Chat">
            <a href="{{ route('home') }}">Beranda</a>
            <a href="{{ route('about') }}">Tentang</a>
            <a href="{{ route('pricing') }}">Harga</a>
            <a href="{{ route('contact') }}">Kontak</a>
            <a href="{{ route('terms') }}">Syarat</a>
            <a href="{{ route('privacy') }}">Privasi</a>
        </nav>
    </main>
    @include('partials.live-demo-script')
</body>
</html>
