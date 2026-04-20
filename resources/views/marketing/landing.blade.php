<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c0267a">
    <meta name="description" content="Normchat — platform group chat murah untuk komunitas, tim, dan organisasi. Chatting ramean jadi lebih mudah dan lebih murah.">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Normchat — Chatting Ramean Lebih Mudah, Lebih Murah</title>
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
            width: 500px;
            height: 500px;
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
        @media (max-width: 640px) {
            .steps { grid-template-columns: 1fr; }
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
            align-items: center;
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
                    AI group chat platform
                </div>

                <h1 class="fade-up fade-up-d1">
                    AI bareng satu grup<br>
                    <span class="grad">lebih mudah, lebih murah.</span>
                </h1>

                <p class="hero-sub fade-up fade-up-d2">
                    Normchat adalah platform chat grup dengan AI di dalamnya untuk komunitas, tim, dan organisasi.
                    Member bisa patungan Normkredit, lalu AI dipakai bareng di percakapan yang sama.
                </p>

                <div class="hero-actions fade-up fade-up-d3">
                    <a href="{{ $loginToSubscriptionUrl }}" class="btn-main">
                        Mulai Sekarang
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#cara-kerja" class="btn-outline">Lihat Cara Kerja</a>
                </div>

                <p class="hero-price-note fade-up fade-up-d4">Detail paket ditampilkan setelah login, saat kamu siap membuat grup.</p>
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
                        <h3>AI Patungan untuk Semua Member</h3>
                        <p>Setiap member bisa patungan. Normkredit terkumpul di grup dan dipakai bersama untuk kebutuhan AI harian.</p>
                    </div>

                    <div class="feature-card fade-up fade-up-d1">
                        <div class="feature-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h3>Chat Real-time + AI Context</h3>
                        <p>Diskusi jalan cepat tanpa pindah aplikasi. AI membaca konteks obrolan grup agar respons tetap nyambung.</p>
                    </div>

                    <div class="feature-card fade-up fade-up-d2">
                        <div class="feature-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        </div>
                        <h3>Satu Ruang untuk Tim + AI</h3>
                        <p>Dari ide, keputusan, sampai tindak lanjut, semua tetap di satu ruang obrolan bersama AI sebagai copilot tim.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="how-section" id="cara-kerja">
            <div class="container">
                <p class="section-label">Cara Kerja</p>
                <h2 class="section-title">Tiga langkah, langsung pakai AI bareng.</h2>

                <div class="steps">
                    <div class="step fade-up">
                        <div class="step-num">1</div>
                        <h4>Buat Grup AI</h4>
                        <p>Daftar akun, lalu buat grup baru. AI langsung aktif di dalam grup dengan kontrol owner tetap penuh.</p>
                    </div>
                    <div class="step fade-up fade-up-d1">
                        <div class="step-num">2</div>
                        <h4>Undang dan Patungan</h4>
                        <p>Bagikan Share ID ke tim atau komunitas. Member join lalu patungan untuk menambah Normkredit AI grup.</p>
                    </div>
                    <div class="step fade-up fade-up-d2">
                        <div class="step-num">3</div>
                        <h4>Eksekusi Bareng AI</h4>
                        <p>Tanya, rangkum, dan susun respons langsung dari chat grup. Semua histori tetap rapi dan mudah ditelusuri.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Value Spotlight -->
        <section class="pricing" id="kenapa-normchat">
            <div class="container">
                <p class="section-label">Kenapa Pilih Normchat</p>
                <h2 class="section-title">Bukan cuma grup chat, ini AI bersama satu tim.</h2>

                <div class="pricing-card fade-up">
                    <p class="pricing-label">AI-first Group Experience</p>
                    <p class="pricing-name">Satu ruang chat + satu asisten AI untuk seluruh member.</p>
                    <p class="pricing-desc">Fokus tim tetap di percakapan. AI membantu meringkas, menyusun jawaban, dan menjaga ritme kerja bersama.</p>

                    <ul class="pricing-features">
                        <li>Onboarding cepat lewat Share ID + password</li>
                        <li>Patungan Normkredit untuk kebutuhan AI grup</li>
                        <li>AI assist langsung di thread percakapan</li>
                        <li>Chat real-time dan histori terarsip rapi</li>
                        <li>Kontrol owner untuk role, akses, dan keamanan</li>
                        <li>Siap dipakai komunitas, tim kecil, hingga organisasi</li>
                    </ul>

                    <a href="{{ $loginToSubscriptionUrl }}" class="pricing-btn">Buat Akun dan Mulai AI Bareng</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p class="footer-text">&copy; {{ date('Y') }} Normchat. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>