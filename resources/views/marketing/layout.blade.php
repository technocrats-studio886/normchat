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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
            min-width: 320px;
        }

        a { color: inherit; }

        .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        .prose { max-width: 640px; }

        h1, h2, h3 { font-weight: 600; line-height: 1.2; letter-spacing: -0.02em; }
        h1 { font-size: clamp(2.25rem, 5vw, 3.5rem); }
        h2 { font-size: clamp(1.75rem, 3.4vw, 2.25rem); margin-bottom: 16px; }
        h3 { font-size: 1.0625rem; letter-spacing: -0.01em; margin-bottom: 6px; }
        p { color: var(--text-soft); font-size: 1.0625rem; }
        p + p { margin-top: 12px; }

        .mention { color: var(--coral); font-weight: 500; }
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

        /* CTA + footer */
        .cta { text-align: center; }
        .cta .prose { margin: 0 auto; }
        .cta .btn-row { justify-content: center; }

        footer { border-top: 1px solid var(--line); padding: 40px 0; }
        .footer-inner { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 24px; }
        .footer-inner p { font-size: 0.9375rem; }
        .footer-inner .brand { color: var(--text); font-weight: 600; }
        .footer-links { display: flex; gap: 20px; font-size: 0.9375rem; }
        .footer-links a { color: var(--text-soft); text-decoration: none; }
        .footer-links a:hover { color: var(--text); }

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
        .footer-base { width: 100%; }

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

    </style>
</head>
<body>
@include('marketing.partials.icons')

@php
    $current = $current ?? '';
    $nav = [
        'home' => ['Home', '/'],
        'about' => ['About', '/about'],
        'pricing' => ['Prices', '/pricing'],
        'contact' => ['Contact', '/contact'],
    ];
@endphp

<header class="site-header">
    <div class="wrap header-inner">
        <a href="{{ $page('/') }}" class="logo-link" aria-label="Normi Chat, home">
            <img src="{{ asset('brand/logo.svg') }}" alt="Normi Chat">
        </a>
        <nav class="nav" aria-label="Main">
            @foreach ($nav as $key => [$label, $path])
                <a href="{{ $page($path) }}" class="nav-link hide-sm{{ $current === $key ? ' is-active' : '' }}" @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ $loginUrl }}" class="btn btn-sm">Sign in</a>
        </nav>
    </div>
    <nav class="subnav wrap" aria-label="Pages">
        @foreach ($nav as $key => [$label, $path])
            <a href="{{ $page($path) }}" class="nav-link{{ $current === $key ? ' is-active' : '' }}" @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main>
    @yield('content')
</main>

<footer>
    <div class="wrap footer-inner">
        <div>
            <p class="brand">Normi Chat</p>
            <p>A group chat with AI, used together.</p>
        </div>
        <div class="footer-links">
            @foreach ($nav as $key => [$label, $path])
                <a href="{{ $page($path) }}">{{ $label }}</a>
            @endforeach
            <a href="{{ $loginUrl }}">Sign in</a>
        </div>
        <p class="footer-base">An Uplift product from Sysnavia.</p>
    </div>
</footer>

</body>
</html>
