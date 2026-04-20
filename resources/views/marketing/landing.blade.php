<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c0267a">
    <meta name="description" content="Normchat — chatting ramean dengan AI, lebih mudah lebih murah. Satu grup, satu saldo, satu asisten AI yang dipakai bareng seluruh tim dan komunitas.">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Normchat — Chatting Ramean dengan AI, Lebih Mudah Lebih Murah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --nc-pink: #c0267a;
            --nc-magenta: #d63384;
            --nc-orange: #f59e0b;
            --nc-peach: #f97316;
            --nc-dark: #1a1a2e;
            --nc-dark-2: #16213e;
            --nc-text: #f1f1f1;
            --nc-text-soft: #b8b8cc;
            --nc-grad: linear-gradient(135deg, #c0267a, #e8456b, #f97316, #f59e0b);
            --nc-grad-btn: linear-gradient(135deg, #c0267a 0%, #f97316 100%);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--nc-dark);
            color: var(--nc-text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
            overflow-x: hidden;
            min-width: 320px;
        }

        img, svg {
            max-width: 100%;
            height: auto;
        }

        .font-display { font-family: 'Poppins', sans-serif; }

        /* ── Layout ─── */
        .container {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── Header ─── */
        .site-header {
            padding: 20px 0;
            position: relative;
            z-index: 10;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .logo-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .logo-img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #fff;
            padding: 3px;
        }
        .logo-text {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .header-cta {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }
        .header-cta:hover {
            background: rgba(255,255,255,0.12);
        }

        /* ── Hero ─── */
        .hero {
            padding: 60px 0 80px;
            text-align: center;
            position: relative;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            width: min(500px, 92vw);
            height: min(500px, 92vw);
            border-radius: 50%;
            background: radial-gradient(circle, rgba(192,38,122,0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 100px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--nc-text-soft);
            margin-bottom: 28px;
        }
        .hero-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--nc-orange);
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .hero h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: clamp(32px, 6vw, 52px);
            line-height: 1.15;
            color: #fff;
            max-width: 700px;
            margin: 0 auto;
            letter-spacing: -0.02em;
            overflow-wrap: anywhere;
        }
        .hero h1 .grad {
            background: var(--nc-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            margin-top: 20px;
            font-size: 16px;
            color: var(--nc-text-soft);
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }

        .hero-actions {
            margin-top: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-main {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 14px;
            background: var(--nc-grad-btn);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 24px -6px rgba(192,38,122,0.35);
        }
        .btn-main:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 32px -6px rgba(192,38,122,0.45);
        }
        .btn-main:active { transform: scale(0.98); }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 14px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.06);
        }

        .hero-price-note {
            margin-top: 20px;
            font-size: 13px;
            color: var(--nc-text-soft);
            opacity: 0.7;
        }

        /* ── Features ─── */
        .features {
            padding: 60px 0;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        @media (max-width: 980px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
        .feature-card {
            padding: 28px 24px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
            transition: border-color 0.25s, background 0.25s;
        }
        .feature-card:hover {
            border-color: rgba(192,38,122,0.25);
            background: rgba(255,255,255,0.05);
        }
        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            background: rgba(192,38,122,0.12);
            color: #e8456b;
            font-size: 18px;
        }
        .feature-card h3 {
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.45;
        }
        .feature-card p {
            font-size: 13px;
            color: var(--nc-text-soft);
            line-height: 1.65;
        }

        /* ── How it works ─── */
        .how-section {
            padding: 60px 0;
        }
        .section-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--nc-text-soft);
            text-align: center;
            margin-bottom: 10px;
        }
        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: clamp(24px, 4vw, 32px);
            text-align: center;
            color: #fff;
            margin-bottom: 40px;
            letter-spacing: -0.01em;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        @media (max-width: 980px) {
            .steps { grid-template-columns: repeat(2, 1fr); }
            .step:last-child {
                grid-column: span 2;
                max-width: 380px;
                margin: 0 auto;
            }
        }
        @media (max-width: 640px) {
            .steps { grid-template-columns: 1fr; }
            .step:last-child {
                grid-column: auto;
                max-width: none;
            }
        }
        .step {
            text-align: center;
            padding: 8px;
        }
        .step-num {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--nc-grad-btn);
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .step h4 {
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .step p {
            font-size: 13px;
            color: var(--nc-text-soft);
            line-height: 1.6;
        }

        /* ── Pricing ─── */
        .pricing {
            padding: 60px 0;
        }
        .pricing-card {
            max-width: 480px;
            margin: 0 auto;
            border-radius: 20px;
            border: 1px solid rgba(192,38,122,0.2);
            background: linear-gradient(160deg, rgba(192,38,122,0.08) 0%, rgba(249,115,22,0.06) 100%);
            padding: 36px 32px;
            text-align: center;
        }
        .pricing-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--nc-text-soft);
            margin-bottom: 6px;
        }
        .pricing-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 24px;
            color: #fff;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .pricing-price {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 40px;
            color: #fff;
        }
        .pricing-price span {
            font-size: 16px;
            font-weight: 500;
            color: var(--nc-text-soft);
        }
        .pricing-desc {
            margin-top: 8px;
            font-size: 14px;
            color: var(--nc-text-soft);
            margin-bottom: 28px;
        }
        .pricing-features {
            list-style: none;
            text-align: left;
            margin-bottom: 28px;
        }
        .pricing-features li {
            padding: 8px 0;
            font-size: 13px;
            color: var(--nc-text-soft);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .pricing-features li::before {
            content: '✓';
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: rgba(192,38,122,0.12);
            color: #e8456b;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .pricing-btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            background: var(--nc-grad-btn);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 24px -6px rgba(192,38,122,0.3);
        }
        .pricing-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 32px -6px rgba(192,38,122,0.4);
        }

        /* ── Footer ─── */
        .site-footer {
            padding: 40px 0;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .footer-text {
            font-size: 12px;
            color: var(--nc-text-soft);
            opacity: 0.6;
        }

        /* ── Animations ─── */
        .fade-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s ease forwards;
        }
        .fade-up-d1 { animation-delay: 0.1s; }
        .fade-up-d2 { animation-delay: 0.2s; }
        .fade-up-d3 { animation-delay: 0.3s; }
        .fade-up-d4 { animation-delay: 0.4s; }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 18px;
            }

            .site-header {
                padding: 16px 0;
            }

            .logo-text {
                font-size: 18px;
            }

            .header-cta {
                padding: 8px 14px;
                font-size: 12px;
            }

            .hero {
                padding: 40px 0 56px;
            }

            .hero-badge {
                font-size: 10px;
                letter-spacing: 0.1em;
                margin-bottom: 20px;
            }

            .hero-sub {
                margin-top: 16px;
                font-size: 15px;
                line-height: 1.65;
            }

            .hero-actions {
                margin-top: 28px;
                width: 100%;
            }

            .btn-main,
            .btn-outline {
                width: 100%;
                justify-content: center;
                padding: 13px 18px;
            }

            .features,
            .how-section,
            .pricing {
                padding: 44px 0;
            }

            .section-title {
                margin-bottom: 28px;
                line-height: 1.3;
            }

            .pricing-card {
                max-width: 100%;
                padding: 26px 20px;
                border-radius: 16px;
            }

            .pricing-name {
                font-size: 20px;
            }

            .pricing-desc {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 14px;
            }

            .logo-link {
                gap: 8px;
            }

            .logo-img {
                width: 32px;
                height: 32px;
            }

            .logo-text {
                font-size: 17px;
            }

            .header-inner {
                flex-wrap: wrap;
            }

            .header-cta {
                width: 100%;
                justify-content: center;
            }

            .hero h1 {
                font-size: 29px;
                line-height: 1.22;
            }

            .hero h1 br {
                display: none;
            }

            .hero-price-note {
                font-size: 12px;
            }

            .feature-card {
                padding: 20px 16px;
            }

            .pricing-btn {
                font-size: 13px;
            }

            .step {
                padding: 2px;
            }

            .site-footer {
                padding: 30px 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .fade-up,
            .fade-up-d1,
            .fade-up-d2,
            .fade-up-d3,
            .fade-up-d4 {
                opacity: 1;
                transform: none;
                animation: none;
            }

            .btn-main,
            .pricing-btn {
                transition: none;
            }
        }
    </style>
</head>
<body>
    @php
        $marketingBase = rtrim((string) config('app.marketing_url', config('app.url')), '/');
        $marketingPath = '/'.trim((string) config('app.normchat_marketing_path', '/normchat'), '/');
        $landingUrl = $marketingBase.$marketingPath;

        $appBase = rtrim((string) config('app.normchat_app_url', config('app.url')), '/');
        $loginToSubscriptionUrl = $appBase.'/login?next=subscription.payment.detail';
    @endphp

    <!-- Header -->
    <header class="site-header">
        <div class="container header-inner">
            <a href="{{ $landingUrl }}" class="logo-link">
                <img src="{{ asset('normchat-logo.png') }}" alt="Normchat" class="logo-img" />
                <span class="logo-text">Normchat</span>
            </a>
            <a href="{{ $loginToSubscriptionUrl }}" class="header-cta">Masuk</a>
        </div>
    </header>

    <main>
        <!-- Hero -->
        <section class="hero">
            <div class="container">
                <div class="hero-badge fade-up">
                    <span class="hero-badge-dot"></span>
                    Grup chat + AI, satu aplikasi
                </div>

                <h1 class="fade-up fade-up-d1">
                    Chatting ramean dengan AI<br>
                    <span class="grad">lebih mudah, lebih murah.</span>
                </h1>

                <p class="hero-sub fade-up fade-up-d2">
                    Normchat menyatukan grup chat dan asisten AI dalam satu ruang.
                    Kumpulkan tim atau komunitasmu, patungan Normkredit, lalu pakai AI bareng —
                    tanpa ribet langganan sendiri-sendiri.
                </p>

                <div class="hero-actions fade-up fade-up-d3">
                    <a href="{{ $loginToSubscriptionUrl }}" class="btn-main">
                        Mulai Sekarang
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#cara-kerja" class="btn-outline">Lihat Cara Kerja</a>
                </div>

                <p class="hero-price-note fade-up fade-up-d4">Detail paket muncul setelah login, pas kamu siap bikin grup pertama.</p>
            </div>
        </section>

        <!-- Features -->
        <section class="features">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-card fade-up">
                        <div class="feature-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h3>Patungan sekali, pakai bareng</h3>
                        <p>Member grup menyetor Normkredit ke saldo bersama. Satu dompet, satu AI, dipakai siapa pun yang butuh — jauh lebih hemat daripada langganan per orang.</p>
                    </div>

                    <div class="feature-card fade-up fade-up-d1">
                        <div class="feature-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h3>Chat cepat, AI yang paham konteks</h3>
                        <p>Obrolan grup berjalan real-time. AI ikut membaca alur percakapan, jadi setiap jawabannya nyambung tanpa perlu kamu jelaskan ulang.</p>
                    </div>

                    <div class="feature-card fade-up fade-up-d2">
                        <div class="feature-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        </div>
                        <h3>Satu ruang untuk ide, keputusan, dan AI</h3>
                        <p>Brainstorming, diskusi, sampai tindak lanjut tetap di satu tempat. AI duduk di meja yang sama — bukan di tab sebelah yang harus dibuka-tutup.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="how-section" id="cara-kerja">
            <div class="container">
                <p class="section-label">Cara Kerja</p>
                <h2 class="section-title">Tiga langkah, AI langsung aktif di grupmu.</h2>

                <div class="steps">
                    <div class="step fade-up">
                        <div class="step-num">1</div>
                        <h4>Bikin grup</h4>
                        <p>Daftar akun dan buka grup baru. AI hadir di dalamnya sejak menit pertama, kendali penuh tetap di tangan owner.</p>
                    </div>
                    <div class="step fade-up fade-up-d1">
                        <div class="step-num">2</div>
                        <h4>Undang dan patungan</h4>
                        <p>Bagikan Share ID ke tim atau komunitas. Member gabung, lalu patungan Normkredit untuk saldo AI bersama.</p>
                    </div>
                    <div class="step fade-up fade-up-d2">
                        <div class="step-num">3</div>
                        <h4>Ngobrol bareng AI</h4>
                        <p>Tanya, rangkum, dan susun jawaban langsung di chat. Seluruh histori tersimpan rapi dan gampang ditelusuri kapan pun.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Value Spotlight -->
        <section class="pricing" id="kenapa-normchat">
            <div class="container">
                <p class="section-label">Kenapa Normchat</p>
                <h2 class="section-title">Bukan sekadar grup chat — ini AI yang dipakai bareng.</h2>

                <div class="pricing-card fade-up">
                    <p class="pricing-label">AI-first Group Experience</p>
                    <p class="pricing-name">Satu ruang chat, satu saldo AI, untuk seluruh anggota.</p>
                    <p class="pricing-desc">Fokus tim tetap di percakapan. Normchat yang mengurus AI, saldo, dan konteksnya — biar kamu cukup berpikir dan mengerjakan.</p>

                    <ul class="pricing-features">
                        <li>Onboarding singkat via Share ID + password</li>
                        <li>Patungan Normkredit, hemat bersama satu grup</li>
                        <li>Bantuan AI langsung di thread obrolan</li>
                        <li>Chat real-time dengan arsip tertata rapi</li>
                        <li>Kendali owner untuk peran, akses, dan keamanan</li>
                        <li>Cocok untuk komunitas, tim kecil, sampai organisasi</li>
                    </ul>

                    <a href="{{ $loginToSubscriptionUrl }}" class="pricing-btn">Mulai Chatting Ramean dengan AI</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p class="footer-text">&copy; {{ date('Y') }} Normchat — chatting ramean dengan AI, lebih mudah lebih murah.</p>
        </div>
    </footer>
</body>
</html>