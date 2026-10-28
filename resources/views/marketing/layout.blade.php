<!DOCTYPE html>
<html lang="{{ $lang ?? 'id' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#121C29">
    <meta name="description" content="@yield('description')">
    @include('partials.brand-head')
    <title>@yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --coral: #FB6856;
            --coral-deep: #E94E3C;
            --navy: #121C29;
            --navy-2: #1E2E43;
            --paper: #F3F5F8;
            --ink: #F3F5F8;
            --ink-soft: #A1B0C5;
            --ink-faint: #6F8099;
            --line: rgba(255, 255, 255, 0.1);
            --wrap: 1120px;
        }

        html { scroll-behavior: smooth; scroll-padding-top: 72px; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--navy);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            line-height: 1.65;
            overflow-x: hidden;
            min-width: 320px;
        }

        img, svg { max-width: 100%; display: block; }
        a { color: inherit; }

        h1, h2, h3, .display {
            font-family: 'Poppins', sans-serif;
            letter-spacing: -0.02em;
            color: #fff;
            overflow-wrap: break-word;
        }

        .wrap { width: 100%; max-width: var(--wrap); margin: 0 auto; padding: 0 28px; }

        .eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--coral);
            margin-bottom: 18px;
        }

        .h2 {
            font-size: clamp(26px, 3.6vw, 40px);
            line-height: 1.18;
            font-weight: 700;
            max-width: 18em;
        }

        .lead { color: var(--ink-soft); font-size: 16px; max-width: 34em; }

        section { padding: 96px 0; }
        .rule { border-top: 1px solid var(--line); }

        /* ── Tombol ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 26px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color 0.15s, border-color 0.15s;
        }
        .btn-primary { background: var(--coral); color: #fff; }
        .btn-primary:hover { background: var(--coral-deep); }
        .btn-ghost { border-color: rgba(255, 255, 255, 0.22); color: #fff; }
        .btn-ghost:hover { border-color: rgba(255, 255, 255, 0.5); }
        .btn-dark { background: var(--navy); color: #fff; }
        .btn-dark:hover { background: #000; }
        .btn:focus-visible, a:focus-visible, summary:focus-visible {
            outline: 2px solid var(--coral);
            outline-offset: 3px;
        }

        /* ── Header ─── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(18, 28, 41, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            height: 64px;
        }
        .brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand img { width: 30px; height: 32px; }
        .brand span { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 18px; color: #fff; letter-spacing: -0.02em; }

        .nav { display: flex; align-items: center; gap: 28px; }
        .nav a { font-size: 14px; color: var(--ink-soft); text-decoration: none; transition: color 0.15s; }
        .nav a:hover { color: #fff; }
        .nav .btn { color: #fff; padding: 9px 18px; font-size: 13px; }

        .menu { display: none; position: relative; }
        .menu summary {
            list-style: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 10px;
        }
        .menu summary::-webkit-details-marker { display: none; }
        .menu-panel {
            position: absolute;
            right: 0;
            top: 48px;
            width: 220px;
            background: var(--navy-2);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            display: grid;
        }
        .menu-panel a { padding: 11px 12px; font-size: 14px; color: var(--ink); text-decoration: none; border-radius: 8px; }
        .menu-panel a:hover { background: rgba(255, 255, 255, 0.06); }

        /* ── Hero ─── */
        .hero { padding: 72px 0 96px; }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            gap: 64px;
            align-items: center;
        }
        .hero h1 {
            font-size: clamp(34px, 5.2vw, 56px);
            line-height: 1.08;
            font-weight: 800;
        }
        .hero h1 em { font-style: normal; color: var(--coral); }
        .hero .lead { margin-top: 24px; font-size: 17px; }
        .hero-actions { margin-top: 36px; display: flex; flex-wrap: wrap; gap: 12px; }
        .hero-note { margin-top: 22px; font-size: 13px; color: var(--ink-faint); }

        /* Contoh percakapan: dibuat dengan HTML supaya ikut tema, bukan gambar. */
        .chat {
            background: var(--navy-2);
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
        }
        .chat-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
        }
        .chat-bar strong { color: #fff; font-weight: 600; }
        .chat-bar span { color: var(--ink-faint); }
        .chat-body { padding: 20px 18px 22px; display: grid; gap: 14px; }
        .msg { display: grid; gap: 4px; max-width: 88%; }
        .msg small { font-size: 11px; color: var(--ink-faint); padding-left: 2px; }
        .msg p { font-size: 14px; line-height: 1.55; padding: 10px 14px; border-radius: 4px 14px 14px 14px; background: rgba(255, 255, 255, 0.07); color: var(--ink); }
        .msg.me { justify-self: end; }
        .msg.me small { text-align: right; padding-right: 2px; }
        .msg.me p { border-radius: 14px 4px 14px 14px; background: #2B4160; }
        .msg.ai p { background: rgba(251, 104, 86, 0.12); border: 1px solid rgba(251, 104, 86, 0.35); }
        .msg.ai small { color: var(--coral); font-weight: 600; }
        .tag { color: var(--coral); font-weight: 600; }
        .msg ul { margin: 6px 0 0 18px; }
        .chat-foot { margin: 0 18px 18px; padding: 11px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 13px; color: var(--ink-faint); }
        .chat-caption { margin-top: 12px; font-size: 12px; color: var(--ink-faint); }

        /* ── Pengenalan ─── */
        .intro-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 64px;
            align-items: start;
        }
        .intro-copy { display: grid; gap: 18px; color: var(--ink-soft); }
        .intro-copy .emph { color: #fff; font-weight: 500; }

        /* ── Fitur: baris bernomor ─── */
        .features-head { display: grid; gap: 16px; margin-bottom: 48px; }
        .feature {
            display: grid;
            grid-template-columns: 64px minmax(0, 5fr) minmax(0, 6fr);
            gap: 32px;
            padding: 32px 0;
            border-top: 1px solid var(--line);
        }
        .feature:last-child { border-bottom: 1px solid var(--line); }
        .feature .n { font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 600; color: var(--coral); padding-top: 5px; }
        .feature h3 { font-size: 21px; line-height: 1.3; font-weight: 600; }
        .feature .kicker { display: block; margin-top: 8px; font-size: 13px; color: var(--ink-faint); font-family: 'Inter', sans-serif; letter-spacing: 0; font-weight: 400; }
        .feature p { color: var(--ink-soft); font-size: 15px; }
        .feature p + p { margin-top: 12px; }

        /* ── Cara kerja ─── */
        .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 40px; margin-top: 56px; counter-reset: s; }
        .step { position: relative; padding-top: 28px; border-top: 2px solid var(--line); }
        .step::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 0;
            width: 48px;
            border-top: 2px solid var(--coral);
        }
        .step .n { font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; color: var(--coral); }
        .step h3 { font-size: 20px; font-weight: 600; margin: 8px 0 10px; }
        .step p { color: var(--ink-soft); font-size: 15px; }
        .section-cta { margin-top: 48px; }

        /* ── Nilai: satu blok warna ─── */
        .value { background: var(--coral); color: var(--navy); padding: 96px 0; }
        .value-grid { display: grid; grid-template-columns: minmax(0, 6fr) minmax(0, 5fr); gap: 64px; align-items: end; }
        .value h2 { color: var(--navy); font-size: clamp(28px, 4.2vw, 46px); line-height: 1.12; font-weight: 800; }
        .value-copy { display: grid; gap: 16px; font-size: 16px; }
        .value-copy .strong { font-weight: 700; font-size: 18px; }

        /* ── Tentang ─── */
        .about-head { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 64px; align-items: start; }
        .about-head .lead { max-width: none; }
        .about-note { margin-top: 18px; font-size: 14px; color: var(--ink-faint); }
        .principles {
            margin-top: 64px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-top: 1px solid var(--line);
            border-left: 1px solid var(--line);
        }
        .principle { padding: 32px 32px 36px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .principle .n { font-size: 12px; font-weight: 600; letter-spacing: 0.12em; color: var(--coral); text-transform: uppercase; }
        .principle h3 { font-size: 19px; font-weight: 600; margin: 10px 0 10px; line-height: 1.35; }
        .principle p { color: var(--ink-soft); font-size: 14.5px; }
        .principle p + p { margin-top: 10px; }

        .audience { margin-top: 80px; display: grid; grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); gap: 64px; }
        .audience-list { display: grid; }
        .audience-item { padding: 22px 0; border-top: 1px solid var(--line); display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 24px; }
        .audience-item:last-child { border-bottom: 1px solid var(--line); }
        .audience-item h3 { font-size: 17px; font-weight: 600; line-height: 1.4; }
        .audience-item p { color: var(--ink-soft); font-size: 15px; }

        /* ── Harga ─── */

        /* ── FAQ ─── */
        .faq-grid { display: grid; grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); gap: 64px; }
        .faq-list { border-bottom: 1px solid var(--line); }
        .faq-list details { border-top: 1px solid var(--line); }
        .faq-list summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 22px 0;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
        }
        .faq-list summary::-webkit-details-marker { display: none; }
        .faq-list summary::after { content: '+'; flex-shrink: 0; font-size: 22px; font-weight: 400; color: var(--coral); line-height: 1; }
        .faq-list details[open] summary::after { content: '−'; }
        .faq-list details p { padding: 0 40px 24px 0; color: var(--ink-soft); font-size: 15px; }

        /* ── Kontak ─── */

        /* ── CTA penutup ─── */
        .closing { padding: 112px 0; text-align: left; }
        .closing h2 { font-size: clamp(30px, 5vw, 54px); line-height: 1.1; font-weight: 800; max-width: 14em; }
        .closing .lead { margin-top: 22px; }
        .closing .hero-actions { margin-top: 36px; }

        /* ── Footer ─── */
        .site-footer { border-top: 1px solid var(--line); padding: 56px 0 40px; }
        .footer-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 3fr) minmax(0, 3fr); gap: 48px; }
        .footer-grid p { font-size: 14px; color: var(--ink-soft); max-width: 28em; margin-top: 16px; }
        .footer-grid h4 { font-size: 12px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-faint); margin-bottom: 14px; }
        .footer-grid ul { list-style: none; display: grid; gap: 10px; }
        .footer-grid li a { font-size: 14px; color: var(--ink-soft); text-decoration: none; }
        .footer-grid li a:hover { color: #fff; }
        .footer-bottom { margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--line); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 24px; font-size: 12.5px; color: var(--ink-faint); }

        /* ── Responsif ─── */
        @media (max-width: 960px) {
            section { padding: 72px 0; }
            .hero { padding: 48px 0 72px; }
            .hero-grid, .intro-grid, .about-head, .faq-grid, .audience, .value-grid { grid-template-columns: minmax(0, 1fr); gap: 40px; }
            .feature { grid-template-columns: 40px minmax(0, 1fr); gap: 8px 16px; padding: 28px 0; }
            .feature .n { grid-row: span 2; }
            .feature > div:last-child { grid-column: 2; margin-top: 8px; }
            .steps { grid-template-columns: minmax(0, 1fr); gap: 32px; margin-top: 40px; }
            .footer-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .footer-grid > div:first-child { grid-column: 1 / -1; }
            .value { padding: 72px 0; }
            .closing { padding: 80px 0; }
        }

        @media (max-width: 720px) {
            .nav { display: none; }
            .menu { display: block; }
            .principles { grid-template-columns: minmax(0, 1fr); }
            .principle { padding: 26px 22px 28px; }
            .audience-item { grid-template-columns: minmax(0, 1fr); gap: 6px; }
        }

        @media (max-width: 480px) {
            .wrap { padding: 0 18px; }
            .btn { width: 100%; }
            .msg { max-width: 94%; }
            .faq-list details p { padding-right: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .btn, .nav a { transition: none; }
        }
    
        /* ── Halaman dalam ─── */
        .page-hero { padding: 88px 0 72px; }
        .page-hero h1 { font-size: clamp(34px, 5vw, 54px); line-height: 1.1; font-weight: 800; max-width: 15em; }
        .page-hero .lead { margin-top: 24px; font-size: 17px; max-width: 38em; }
        .page-hero .lead + .lead { margin-top: 14px; }
        .page-hero .hero-note { margin-top: 24px; }
        .split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 64px; align-items: start; }
        .split-wide { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); }
        .section-title-sm { font-size: clamp(22px, 2.8vw, 30px); line-height: 1.2; font-weight: 700; }
        .stack { display: grid; gap: 16px; color: var(--ink-soft); }
        .stack .emph { color: #fff; font-weight: 500; }

        /* Harga */
        .price-main { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); border: 1px solid var(--line); border-radius: 20px; overflow: hidden; background: rgba(255, 255, 255, 0.025); }
        .price-side { padding: 40px; background: var(--navy-2); display: flex; flex-direction: column; gap: 6px; }
        .price-side .tier { font-size: 12px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--coral); }
        .price-figure { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: clamp(40px, 6vw, 60px); line-height: 1.05; color: #fff; letter-spacing: -0.03em; margin-top: 10px; }
        .price-unit { font-size: 14px; color: var(--ink-soft); }
        .price-side p { margin-top: 18px; font-size: 14.5px; color: var(--ink-soft); }
        .price-side .btn { margin-top: 28px; align-self: flex-start; }
        .price-list { padding: 40px; }
        .price-list h3 { font-size: 13px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-faint); margin-bottom: 18px; font-family: 'Inter', sans-serif; }
        .checklist { list-style: none; display: grid; gap: 0; }
        .checklist li { display: flex; gap: 14px; padding: 13px 0; border-top: 1px solid var(--line); font-size: 15px; color: var(--ink-soft); }
        .checklist li:first-child { border-top: 0; padding-top: 0; }
        .checklist li::before { content: ''; flex-shrink: 0; width: 8px; height: 8px; margin-top: 8px; background: var(--coral); border-radius: 2px; }
        .price-note { margin-top: 18px; font-size: 13.5px; color: var(--ink-faint); max-width: 44em; }

        .buy-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 48px; border-top: 1px solid var(--line); }
        .buy { padding: 36px 40px 40px 0; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
        .buy + .buy { padding: 36px 0 40px 40px; border-left: 1px solid var(--line); }
        .buy h3 { font-size: 20px; font-weight: 600; }
        .buy .amount { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 30px; color: #fff; letter-spacing: -0.02em; }
        .buy .amount small { font-family: 'Inter', sans-serif; font-weight: 400; font-size: 14px; color: var(--ink-soft); letter-spacing: 0; }
        .buy p { color: var(--ink-soft); font-size: 15px; }
        .buy .btn { margin-top: 12px; }

        /* Kontak */
        .form { display: grid; gap: 20px; }
        .field { display: grid; gap: 8px; }
        .field label { font-size: 13px; font-weight: 600; color: #fff; }
        .field input, .field select, .field textarea {
            width: 100%;
            font: inherit;
            font-size: 15px;
            color: #fff;
            background: var(--navy-2);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 10px;
            padding: 13px 14px;
            transition: border-color 0.15s;
        }
        .field textarea { min-height: 150px; resize: vertical; }
        .field select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none' stroke='%23A1B0C5' stroke-width='2' stroke-linecap='round' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 38px; }
        .field input::placeholder, .field textarea::placeholder { color: var(--ink-faint); }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--coral); }
        .field .err { font-size: 13px; color: #FF9A8D; }
        .field.has-error input, .field.has-error select, .field.has-error textarea { border-color: #FF9A8D; }
        .form-foot { display: grid; gap: 14px; }
        .form-foot .btn { justify-self: start; }
        .form-note { font-size: 13px; color: var(--ink-faint); }
        .alert { padding: 16px 18px; border-radius: 12px; font-size: 14.5px; border: 1px solid; }
        .alert-ok { border-color: rgba(120, 214, 160, 0.4); background: rgba(120, 214, 160, 0.08); color: #B8EBCB; }
        .alert-bad { border-color: rgba(255, 154, 141, 0.45); background: rgba(255, 154, 141, 0.08); color: #FFC3BA; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .mail-link { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 20px; color: #fff; text-decoration: none; overflow-wrap: anywhere; }
        .mail-link:hover { color: var(--coral); }
        .faq-item-action { padding: 0 0 24px; }

        .nav a[aria-current="page"] { color: #fff; }
        .nav .lang-switch { font-size: 12px; font-weight: 600; letter-spacing: 0.08em; padding: 5px 9px; border: 1px solid var(--line); border-radius: 6px; }
        .nav .lang-switch:hover { border-color: rgba(255, 255, 255, 0.4); }
        .menu-panel a[aria-current="page"] { background: rgba(255, 255, 255, 0.06); }

        @media (max-width: 960px) {
            .page-hero { padding: 56px 0 48px; }
            .split, .split-wide { grid-template-columns: minmax(0, 1fr); gap: 40px; }
            .price-main { grid-template-columns: minmax(0, 1fr); }
            .price-side, .price-list { padding: 28px 24px; }
        }
        @media (max-width: 720px) {
            .buy-grid { grid-template-columns: minmax(0, 1fr); }
            .buy, .buy + .buy { padding: 28px 0; border-left: 0; }
            .buy + .buy { border-top: 1px solid var(--line); }
        }
</style>
</head>
<body>
    @php
        $lang = $lang ?? 'id';
        $active = $active ?? 'home';
        $copy = [
            'id' => [
                'nav' => ['home' => 'Home', 'about' => 'About', 'pricing' => 'Pricing', 'contact' => 'Contact'],
                'cta' => 'Mulai Sekarang',
                'menu' => 'Buka menu',
                'main' => 'Navigasi utama',
                'brand' => 'Normi Chat menyatukan manusia dan AI dalam satu ruang percakapan untuk mendukung komunikasi real-time, pertukaran ide, dan kolaborasi yang lebih terarah.',
                'navTitle' => 'Navigasi',
                'accountTitle' => 'Akun',
                'login' => 'Masuk',
                'create' => 'Buat grup',
                'tagline' => 'Percakapan Lebih Baik, Bersama.',
                'product' => 'Produk Uplift, Sysnavia',
            ],
            'en' => [
                'nav' => ['home' => 'Home', 'about' => 'About', 'pricing' => 'Pricing', 'contact' => 'Contact'],
                'cta' => 'Get Started',
                'menu' => 'Open menu',
                'main' => 'Main navigation',
                'brand' => 'Normi Chat brings people and AI together in one conversation space, for real-time communication, shared ideas, and more focused collaboration.',
                'navTitle' => 'Navigate',
                'accountTitle' => 'Account',
                'login' => 'Log in',
                'create' => 'Create a group',
                'tagline' => 'Better Conversations, Together.',
                'product' => 'An Uplift product by Sysnavia',
            ],
        ][$lang];

        $paths = ['home' => '', 'about' => '/about', 'pricing' => '/pricing', 'contact' => '/contact'];
        $navItems = [];
        foreach ($copy['nav'] as $key => $label) {
            $navItems[$key] = [$label, $base.$paths[$key]];
        }
        $otherLang = $lang === 'en' ? 'id' : 'en';
        $otherUrl = ($otherLang === 'en' ? $landingUrl.'/en' : $landingUrl).$paths[$active];
    @endphp

    <header class="site-header">
        <div class="wrap header-inner">
            <a href="{{ $base }}" class="brand">
                <img src="{{ asset('brand/logo-mark-white.svg') }}" alt="" width="30" height="32">
                <span>Normi Chat</span>
            </a>

            <nav class="nav" aria-label="{{ $copy['main'] }}">
                @foreach ($navItems as $key => [$label, $url])
                    <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="{{ $otherUrl }}" class="lang-switch" hreflang="{{ $otherLang }}" lang="{{ $otherLang }}" aria-label="{{ $otherLang === 'en' ? 'Read in English' : 'Baca dalam Bahasa Indonesia' }}">{{ strtoupper($otherLang) }}</a>
                <a href="{{ $startUrl }}" class="btn btn-primary">{{ $copy['cta'] }}</a>
            </nav>

            <details class="menu">
                <summary aria-label="{{ $copy['menu'] }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </summary>
                <div class="menu-panel">
                    @foreach ($navItems as $key => [$label, $url])
                        <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ $otherUrl }}" hreflang="{{ $otherLang }}" lang="{{ $otherLang }}">{{ $otherLang === 'en' ? 'English' : 'Bahasa Indonesia' }}</a>
                    <a href="{{ $startUrl }}">{{ $copy['cta'] }}</a>
                </div>
            </details>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <a href="{{ $base }}" class="brand">
                        <img src="{{ asset('brand/logo-mark-white.svg') }}" alt="" width="30" height="32">
                        <span>Normi Chat</span>
                    </a>
                    <p>{{ $copy['brand'] }}</p>
                </div>
                <div>
                    <h4>{{ $copy['navTitle'] }}</h4>
                    <ul>
                        @foreach ($navItems as [$label, $url])
                            <li><a href="{{ $url }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h4>{{ $copy['accountTitle'] }}</h4>
                    <ul>
                        <li><a href="{{ $startUrl }}">{{ $copy['login'] }}</a></li>
                        <li><a href="{{ $startUrl }}">{{ $copy['create'] }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} Normi Chat · {{ $copy['product'] }}</span>
                <span>{{ $copy['tagline'] }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
