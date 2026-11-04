<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="description" content="@yield('description')">
    @include('partials.brand-head')
    <title>@yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..700&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #FFFFFF;
            --bg-alt: #F7F8FA;
            --text: #121C29;
            --text-soft: #5B6778;
            --navy: #1E2E43;
            --coral: #FB6856;
            --line: #E6E9EE;
            --radius: 12px;
        }

        html { scroll-behavior: smooth; }

        body {
            /* Judul Bricolage Grotesque, isi Hanken Grotesk (bukan font bawaan AI seperti Inter/Poppins; keputusan user 4 Nov 2026). */
            font-family: 'Hanken Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
            min-width: 320px;
        }

        a { color: inherit; }

        .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        .prose { max-width: 640px; }

        h1, h2, h3 { font-family: 'Bricolage Grotesque', 'Hanken Grotesk', sans-serif; font-weight: 600; line-height: 1.15; letter-spacing: -0.02em; }
        h1 { font-size: clamp(2.25rem, 5vw, 3.5rem); }
        h2 { font-size: clamp(1.75rem, 3.4vw, 2.25rem); margin-bottom: 16px; }
        h3 { font-size: 1.0625rem; letter-spacing: -0.01em; margin-bottom: 6px; }
        p { color: var(--text-soft); font-size: 1.0625rem; }
        p + p { margin-top: 12px; }

        /* Koral gelap untuk teks: #FB6856 di atas putih hanya 2,9:1. */
        .mention { color: #C43D2E; font-weight: 500; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.8125rem; font-weight: 500; color: var(--text-soft);
            margin-bottom: 20px;
        }
        .eyebrow::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--coral);
        }

        /* Header */
        .site-header { border-bottom: 1px solid var(--line); background: var(--bg); position: sticky; top: 0; z-index: 10; }
        .header-inner { display: flex; align-items: center; justify-content: space-between; height: 64px; gap: 16px; }
        .logo-link img { height: 30px; width: auto; display: block; }
        .nav { display: flex; align-items: center; gap: 28px; }
        .nav a { text-decoration: none; font-size: 0.9375rem; color: var(--text-soft); }
        .nav a:hover { color: var(--text); }

        /* Buttons: text only */
        .btn {
            display: inline-block; text-decoration: none; font: inherit; font-size: 0.9375rem; font-weight: 500;
            padding: 11px 20px; border-radius: var(--radius); border: 1px solid var(--navy);
            background: var(--navy); color: #fff; cursor: pointer;
        }
        .btn:hover { background: var(--text); border-color: var(--text); }
        .btn-ghost { background: transparent; color: var(--navy); border-color: var(--line); }
        .btn-ghost:hover { background: var(--bg-alt); border-color: var(--line); color: var(--navy); }
        .btn-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }

        /* Sections */
        section { padding: 96px 0; }
        section.alt { background: var(--bg-alt); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .hero { padding: 96px 0 80px; }
        .hero p { font-size: 1.1875rem; margin-top: 20px; }

        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: start; }

        /* Facts */
        .facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 48px; }
        .fact { border: 1px solid var(--line); border-radius: var(--radius); padding: 20px; background: var(--bg); }
        .fact .k { font-size: 0.8125rem; color: var(--text-soft); }
        .fact .v { font-size: 1.125rem; font-weight: 600; margin-top: 4px; color: var(--text); }

        /* Cards */
        .grid { display: grid; gap: 16px; margin-top: 40px; }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .card { border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; background: var(--bg); }
        .card p { font-size: 0.9688rem; }
        .icon { width: 24px; height: 24px; color: var(--navy); fill: currentColor; display: block; margin-bottom: 16px; }
        .num { font-size: 0.8125rem; font-weight: 600; color: var(--text-soft); margin-bottom: 12px; display: block; }

        /* Chat mockup */
        .chat { border: 1px solid var(--line); border-radius: var(--radius); background: var(--bg); padding: 20px; }
        .msg { display: flex; flex-direction: column; gap: 2px; padding: 10px 0; }
        .msg + .msg { border-top: 1px solid var(--line); }
        .msg b { font-size: 0.8125rem; font-weight: 600; color: var(--text); }
        .msg span { font-size: 0.9688rem; color: var(--text-soft); }
        .msg.normi b { color: var(--navy); }
        .msg.normi span { color: var(--text); }
        .chat-note { font-size: 0.8125rem; margin-top: 12px; }

        /* Pricing table */
        .plans { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 40px; max-width: 760px; }
        .plan .price { font-size: 1.75rem; font-weight: 600; color: var(--text); letter-spacing: -0.02em; margin: 8px 0 4px; }
        .plan .credit { font-size: 0.9375rem; color: var(--text-soft); }
        .plan p.desc { margin-top: 12px; font-size: 0.9375rem; }

        .list { list-style: none; margin-top: 20px; display: grid; gap: 10px; }
        .list li { padding-left: 20px; position: relative; color: var(--text-soft); font-size: 1.0625rem; }
        .list li::before { content: ''; position: absolute; left: 0; top: 0.7em; width: 6px; height: 6px; border-radius: 50%; background: var(--navy); }
        .list li strong { color: var(--text); font-weight: 600; }

        /* Manfaat utama (kartu besar berlatar navy) dan baris manfaat di bawah hero */
        .card.lead { grid-column: 1 / -1; background: var(--navy); border-color: var(--navy); color: #FFFFFF; }
        .card.lead h3 { color: #FFFFFF; font-size: 1.375rem; }
        .card.lead p { color: rgba(255, 255, 255, 0.82); font-size: 1.0625rem; max-width: 560px; }
        .card.lead .icon { color: #FFFFFF; }
        .facts.benefits .v { font-size: 1rem; }
        .facts.benefits .k { font-size: 0.875rem; margin-top: 6px; color: var(--text-soft); }

        /* Mode Normi di /home */
        .section-intro { margin-top: 16px; max-width: 640px; font-size: 1.0625rem; }
        .mode-tag { display: inline-block; margin-bottom: 10px; padding: 2px 10px; border: 1px solid var(--line); border-radius: 999px; background: var(--bg-alt); font-size: 0.8125rem; font-weight: 600; color: var(--navy); }
        .mode-card p + p { margin-top: 10px; }
        .mode-card .how { color: var(--text-soft); }
        .modes-note { margin-top: 20px; font-size: 0.9375rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

        /* Tabel perbandingan: digulir sendiri di HP, halaman tidak ikut melebar */
        .table-wrap { margin-top: 32px; overflow-x: auto; border: 1px solid var(--line); border-radius: var(--radius); background: var(--bg); }
        .table { width: 100%; border-collapse: collapse; font-size: 0.9375rem; }
        @media (max-width: 600px) { .table th, .table td { padding: 12px 10px; font-size: 0.875rem; } }
        .table th, .table td { padding: 14px 16px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--line); color: var(--text-soft); }
        .table thead th { color: var(--text); font-weight: 600; background: var(--bg-alt); }
        .table tbody th { color: var(--text); font-weight: 500; }
        .table tr:last-child th, .table tr:last-child td { border-bottom: 0; }
        .table-note { margin-top: 12px; font-size: 0.9375rem; }
        /* Level AI: tabel di layar lebar, kartu di HP supaya kolom Advanced tidak tersembunyi. */
        .levels { display: none; }
        @media (max-width: 600px) {
            .table-wrap.wide-only { display: none; }
            .levels { display: grid; gap: 12px; margin-top: 24px; }
            .levels dl { display: grid; gap: 8px; margin-top: 10px; }
            .levels dt { font-size: 0.8125rem; color: var(--text-soft); }
            .levels dd { font-size: 0.9375rem; color: var(--text); }
        }

        /* CTA + footer */
        .cta { text-align: center; }
        .cta .prose { margin: 0 auto; }
        .cta .btn-row { justify-content: center; }

        /* Halaman legal (terms, privacy) */
        .legal { max-width: 720px; }
        .legal h2 { margin-top: 40px; margin-bottom: 10px; font-size: 1.375rem; }
        .legal p, .legal li { font-size: 1rem; }
        .legal ul { margin: 10px 0 0 20px; display: grid; gap: 6px; color: var(--text-soft); }
        .legal .updated { font-size: 0.9375rem; }

        /* Footer: merek + tiga kolom tautan, lalu hak cipta dan tautan legal. */
        .site-footer { border-top: 1px solid var(--line); background: var(--bg-alt); padding: 56px 0 28px; }
        .footer-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 2fr); gap: 40px; }
        .footer-logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font-family: 'Bricolage Grotesque', 'Hanken Grotesk', sans-serif; font-size: 1.125rem; font-weight: 600; color: var(--text); }
        .footer-logo img { width: 28px; height: 28px; display: block; }
        .footer-brand p { margin-top: 12px; font-size: 0.9375rem; }
        .footer-cols { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
        .footer-cols div { display: grid; align-content: start; gap: 10px; }
        .footer-title { font-size: 0.8125rem; font-weight: 600; color: var(--text); }
        .footer-cols a, .footer-legal a { font-size: 0.9375rem; color: var(--text-soft); text-decoration: none; overflow-wrap: anywhere; }
        .footer-cols a:hover, .footer-legal a:hover { color: var(--text); text-decoration: underline; text-underline-offset: 3px; }
        .footer-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-top: 48px; padding-top: 20px; border-top: 1px solid var(--line); }
        .footer-bottom p { font-size: 0.875rem; }
        .footer-legal { display: flex; gap: 20px; }
        @media (max-width: 720px) {
            .footer-grid { grid-template-columns: 1fr; }
            .footer-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 880px) {
            section { padding: 72px 0; }
            .hero { padding: 64px 0 56px; }
            .split { grid-template-columns: 1fr; gap: 40px; }
            .facts { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: 1fr; }
            .nav a.hide-sm { display: none; }
        }
        @media (max-width: 600px) {
            .grid-2, .plans { grid-template-columns: 1fr; }
            .nav { gap: 16px; }
            .wrap { padding: 0 20px; }
        }
    

        /* Nav states, mobile subnav, small button */
        .nav-link.is-active { color: var(--text); font-weight: 500; }
        .btn-sm { padding: 8px 16px; }
        .nav a.btn { color: #fff; }
        .nav a.btn:hover { color: #fff; }
        .nav a.nav-link.is-active { color: var(--text); }
        .subnav { display: none; gap: 20px; padding-top: 0; padding-bottom: 12px; overflow-x: auto; }
        .subnav .nav-link { text-decoration: none; font-size: 0.9375rem; color: var(--text-soft); white-space: nowrap; }
        .subnav .nav-link.is-active { color: var(--text); font-weight: 500; }

        /* Form */
        .form { display: grid; gap: 18px; max-width: 560px; margin-top: 32px; }
        .field label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text); margin-bottom: 6px; }
        .field input, .field select, .field textarea {
            width: 100%; font: inherit; font-size: 1rem; color: var(--text); background: var(--bg);
            border: 1px solid var(--line); border-radius: var(--radius); padding: 11px 14px;
        }
        .field textarea { min-height: 140px; resize: vertical; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid var(--navy); outline-offset: 1px; }
        .field .err { color: #B42318; font-size: 0.875rem; margin-top: 6px; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .form-note { font-size: 0.875rem; }
        .alert { border: 1px solid var(--line); border-radius: var(--radius); padding: 14px 18px; max-width: 560px; margin-top: 24px; background: var(--bg-alt); color: var(--text); }
        .alert.error { border-color: #F2B8B5; }

        /* FAQ */
        .faq { margin-top: 32px; max-width: 760px; border-top: 1px solid var(--line); }
        .faq details { border-bottom: 1px solid var(--line); padding: 18px 0; }
        .faq summary { cursor: pointer; font-weight: 500; color: var(--text); list-style: none; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq details p { margin-top: 10px; font-size: 1rem; }

        @media (max-width: 880px) {
            .subnav { display: flex; }
        }

        /* ── Gerak (partials/live-demo-script): demo chat hidup, scroll reveal, latar ambient, micro-interaction.
           Tanpa JS atau dengan prefers-reduced-motion, semua konten tampil statis. ── */
        .hero { position: relative; overflow: hidden; }
        .hero > .wrap { position: relative; z-index: 1; }
        .hero::before {
            content: ''; position: absolute; inset: -30% -15% -10%; z-index: 0; pointer-events: none;
            background:
                radial-gradient(38% 46% at 78% 32%, rgba(59, 111, 212, 0.10), transparent 70%),
                radial-gradient(30% 36% at 12% 8%, rgba(30, 46, 67, 0.05), transparent 70%);
            animation: nc-ambient 18s ease-in-out infinite alternate;
        }
        @keyframes nc-ambient { from { transform: translate3d(0, 0, 0) scale(1); } to { transform: translate3d(-3%, 2%, 0) scale(1.06); } }
        .demo-wrap { position: relative; }
        .demo-wrap::before {
            content: ''; position: absolute; inset: 8% -6% -6%; z-index: -1; pointer-events: none;
            background: radial-gradient(closest-side, rgba(59, 111, 212, 0.16), transparent); filter: blur(28px);
        }

        /* Demo chat */
        .chat { box-shadow: 0 24px 48px -32px rgba(18, 28, 41, 0.35); }
        .chat-head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid var(--line); font-size: 0.875rem; font-weight: 600; color: var(--text); }
        .live-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: #EAF5EE; color: #1E6B3A; font-size: 0.75rem; font-weight: 600; }
        .live-badge i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: nc-pulse 1.6s ease-in-out infinite; }
        @keyframes nc-pulse { 50% { opacity: 0.3; } }
        /* Pesan menumpuk dari bawah seperti aplikasi chat; tinggi kotak dikunci skrip agar halaman tidak bergeser. */
        [data-demo-body] { display: flex; flex-direction: column; justify-content: flex-end; }
        .msg.mine b { color: var(--text); }
        .msg.typing { display: none; }
        .dots { display: inline-flex; align-items: center; gap: 4px; }
        .dots i { width: 6px; height: 6px; border-radius: 50%; background: var(--text-soft); animation: nc-dot 1.2s ease-in-out infinite; }
        .dots i:nth-child(2) { animation-delay: 0.15s; }
        .dots i:nth-child(3) { animation-delay: 0.3s; }
        .dots em { margin-left: 6px; font-style: normal; font-size: 0.875rem; color: var(--text-soft); }
        @keyframes nc-dot { 0%, 60%, 100% { transform: translateY(0); opacity: 0.45; } 30% { transform: translateY(-3px); opacity: 1; } }
        .chat.is-live [data-demo-step], .chat.is-live .msg.typing { display: none; }
        .chat.is-live .is-shown { display: flex; animation: nc-msg-in 0.35s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
        @keyframes nc-msg-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .chat.is-live .is-streaming [data-demo-answer]::after { content: ''; display: inline-block; width: 2px; height: 1em; margin-left: 2px; vertical-align: -2px; background: currentColor; animation: nc-caret 0.9s steps(1) infinite; }
        @keyframes nc-caret { 50% { opacity: 0; } }
        .mention.is-flash { border-radius: 4px; animation: nc-mention 1.2s ease-out; }
        @keyframes nc-mention { 0% { background: rgba(196, 61, 46, 0.18); } 100% { background: transparent; } }

        /* Scroll reveal: hanya aktif bila JS memasang .reveal-on di <html>. */
        .reveal-on .reveal { opacity: 0; transform: translateY(16px); transition: opacity 0.5s cubic-bezier(0.2, 0.7, 0.2, 1) var(--reveal-delay, 0ms), transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1) var(--reveal-delay, 0ms); }
        .reveal-on .reveal.reveal-scale { transform: translateY(12px) scale(0.98); }
        .reveal-on .reveal.is-visible { opacity: 1; transform: none; }

        /* Micro-interaction kartu dan tombol */
        .card { transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-3px); border-color: #CDD3DC; box-shadow: 0 12px 28px -18px rgba(18, 28, 41, 0.3); }
        .card.lead:hover { border-color: var(--navy); }
        .card .icon { transition: transform 0.2s ease; }
        .card:hover .icon { transform: translateY(-2px) rotate(-6deg); }
        .btn { transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.15s ease; }
        .btn:hover { transform: scale(1.02); }
        .btn:active { transform: scale(0.99); }
        .btn-arrow { display: inline-flex; align-items: center; gap: 8px; }
        .btn-arrow svg { width: 16px; height: 16px; transition: transform 0.2s ease; }
        .btn-arrow:hover svg { transform: translateX(3px); }
        .btn.is-loading { pointer-events: none; opacity: 0.88; }
        .btn.is-loading svg { display: none; }
        .btn.is-loading::after { content: ''; width: 14px; height: 14px; margin-left: 8px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: nc-spin 0.7s linear infinite; display: inline-block; vertical-align: -2px; }
        @keyframes nc-spin { to { transform: rotate(360deg); } }

        /* Hero /home: judul, CTA, dan penjelasan rata tengah memenuhi lebar; di bawahnya demo (kiri) dan manfaat (kanan). */
        .hero-head { max-width: 920px; text-align: center; }
        .hero-head h1 { font-size: clamp(2.75rem, 7.2vw, 5.25rem); line-height: 1.04; letter-spacing: -0.025em; }
        .hero-head .btn-row { justify-content: center; margin-top: 32px; }
        .hero-head .hero-lead { max-width: 680px; margin: 28px auto 0; font-size: 1.1875rem; }
        .hero-head .chat-note { margin-top: 12px; }
        .hero-body { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 56px; align-items: center; margin-top: 72px; }
        .benefit-list { list-style: none; border-top: 1px solid var(--line); }
        .benefit-list li { padding: 18px 0; border-bottom: 1px solid var(--line); }
        .benefit-list h3 { margin-bottom: 4px; font-size: 1.125rem; }
        .hero .benefit-list p { margin-top: 0; font-size: 1rem; }
        @media (max-width: 880px) {
            .hero-body { grid-template-columns: 1fr; gap: 40px; margin-top: 48px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .card:hover, .btn:hover, .btn:active, .card:hover .icon { transform: none; }
        }

    </style>
</head>
<body>
@include('marketing.partials.icons')

@php
    $current = $current ?? '';
    $nav = [
        'home' => ['Home', '/home'],
        'about' => ['About', '/about'],
        'pricing' => ['Prices', '/pricing'],
        'contact' => ['Contact', '/contact'],
    ];
@endphp

<header class="site-header">
    <div class="wrap header-inner">
        <a href="{{ $page('/home') }}" class="logo-link" aria-label="Normi Chat, home">
            <img src="{{ asset('brand/logo.svg') }}" alt="Normi Chat">
        </a>
        <nav class="nav" aria-label="Main">
            @foreach ($nav as $key => [$label, $path])
                <a href="{{ $page($path) }}" class="nav-link hide-sm{{ $current === $key ? ' is-active' : '' }}" @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ $loginUrl }}" class="btn btn-sm" data-cta>{{ $n['free'] ? 'Start free' : 'Sign in' }}</a>
        </nav>
    </div>
    <nav class="subnav wrap" aria-label="Pages">
        @foreach ($nav as $key => [$label, $path])
            <a href="{{ $page($path) }}" class="nav-link{{ $current === $key ? ' is-active' : '' }}" @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main data-reveal-scope>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap footer-grid">
        <div class="footer-brand">
            <a href="{{ $page('/home') }}" class="footer-logo">
                <img src="{{ asset('brand/logo-mark.svg') }}" alt="" width="28" height="28">
                <span>Normi Chat</span>
            </a>
            <p>One AI for the whole group.</p>
        </div>
        <nav class="footer-cols" aria-label="Footer">
            <div>
                <p class="footer-title">Product</p>
                <a href="{{ $page('/home') }}">Home</a>
                <a href="{{ $page('/pricing') }}">Prices</a>
                <a href="{{ $loginUrl }}">Sign in</a>
            </div>
            <div>
                <p class="footer-title">Company</p>
                <a href="{{ $page('/about') }}">About</a>
                <a href="{{ $page('/contact') }}">Contact</a>
                @if ($supportEmail)
                    <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                @endif
                @if ($businessEmail)
                    <a href="mailto:{{ $businessEmail }}">{{ $businessEmail }}</a>
                @endif
            </div>
            <div>
                <p class="footer-title">Legal</p>
                <a href="{{ $page('/terms') }}">Terms of Service</a>
                <a href="{{ $page('/privacy') }}">Privacy Policy</a>
            </div>
        </nav>
    </div>
    <div class="wrap footer-bottom">
        <p>&copy; {{ now()->year }} Twenti Studio. All rights reserved.</p>
        <p class="footer-legal"><a href="{{ $page('/terms') }}">Terms</a><a href="{{ $page('/privacy') }}">Privacy</a></p>
    </div>
</footer>

@include('partials.live-demo-script')
</body>
</html>
