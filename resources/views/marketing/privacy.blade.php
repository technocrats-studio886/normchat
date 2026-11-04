@extends('marketing.layout', ['current' => 'privacy'])

{{-- Kebijakan Privasi. Disusun dari cara aplikasi memperlakukan data (4 Nov 2026): Google, OpenAI, Midtrans, penyimpanan
     berkas, cadangan 30 hari (GroupBackups::KEEP_DAYS), ekspor 7 hari, sesi 5 hari. Perbarui bila hal itu berubah. --}}
@section('title', 'Privacy Policy | Normi Chat')
@section('description', 'How Normi Chat collects, uses, and protects your information.')

@section('content')
    <section class="hero">
        <div class="wrap legal">
            <h1>Privacy Policy</h1>
            <p class="updated">Effective November 4, 2026</p>

            <p>This policy explains what information Normi Chat collects, how we use it, and the choices you have. Normi Chat is made by Twenti Studio ("we").</p>

            <h2>1. Information we collect</h2>
            <ul>
                <li>Your name, email address, and profile photo from Google when you sign in.</li>
                <li>What you post in your groups, such as messages, files, images, voice notes, polls, and edits, plus group names and descriptions.</li>
                <li>Your IP address and the type of device and browser you sign in with, which we use for your sign-in history and active sessions.</li>
                <li>Records of Normi's answers and the Normkredit each one used.</li>
                <li>Payment records from our payment provider, such as the order number, amount, and status. We don't receive your banking or e-wallet login details.</li>
                <li>Messages you send through the contact form.</li>
            </ul>

            <h2>2. How we use it</h2>
            <p>We use this information to run Normi Chat. That means showing your groups and messages, generating Normi's answers, processing payments and keeping group balances, protecting accounts, and answering your support requests. We also look at overall usage to improve the product.</p>
            <p>We don't sell your personal information, and we don't show ads.</p>

            <h2>3. How Normi processes your messages</h2>
            <p>When someone in a group mentions @normi, we send the relevant messages and files from that group to OpenAI to produce the answer. We also keep a searchable memory of the group's messages so Normi can recall earlier discussion. OpenAI processes this data under its API terms.</p>

            <h2>4. Who we share it with</h2>
            <ul>
                <li>Google, to sign you in.</li>
                <li>OpenAI, to generate Normi's answers.</li>
                <li>Midtrans, to process QRIS payments.</li>
                <li>Our hosting and storage providers, which run our servers and keep files and backups.</li>
            </ul>
            <p>Members of a group can see what you post in that group. We may also disclose information when Indonesian law requires it.</p>

            <h2>5. How long we keep it</h2>
            <p>We keep your account information and group content while your account and groups exist. Group backups are kept for 30 days, and exported files for 7 days. A session ends after 5 days without activity. When an owner deletes a group, its messages and Normi's memory of that group are removed from the app. We keep payment records for as long as accounting rules require.</p>

            <h2>6. Your choices</h2>
            <ul>
                <li>Change your name, theme, time zone, and notifications in your account settings.</li>
                <li>See the devices signed in to your account and sign out of the ones you don't recognize.</li>
                <li>Leave a group at any time.</li>
                <li>Ask us to delete your account through the <a href="{{ $page('/contact') }}">Contact</a> page, and we will confirm once it's done.</li>
            </ul>

            <h2>7. Cookies</h2>
            <p>We use cookies to keep you signed in and to protect forms against misuse. We don't use advertising or third-party tracking cookies.</p>

            <h2>8. Security</h2>
            <p>We use HTTPS and limit who can access our systems. No system is perfectly secure, so please tell us if you notice anything unusual with your account.</p>

            <h2>9. Children</h2>
            <p>If you are under 18, use Normi Chat with permission from a parent or guardian.</p>

            <h2>10. Changes to this policy</h2>
            <p>When we update this policy, we change the effective date above. If a change affects you in a significant way, we will tell you in the app.</p>

            <h2>11. Contact</h2>
            @if ($supportEmail)
                <p>Send questions about your privacy through the <a href="{{ $page('/contact') }}">Contact</a> page or to <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
            @else
                <p>Send questions about your privacy through the <a href="{{ $page('/contact') }}">Contact</a> page.</p>
            @endif
        </div>
    </section>
@endsection
