@extends('marketing.layout', ['current' => 'terms'])

{{-- Syarat Layanan. Disusun dari cara aplikasi bekerja (4 Nov 2026); tinjau dengan ahli hukum sebelum diubah. --}}
@section('title', 'Terms of Service | Normi Chat')
@section('description', 'The terms that apply when you use Normi Chat, made by Twenti Studio.')

@section('content')
    <section class="hero">
        <div class="wrap legal">
            <h1>Terms of Service</h1>
            <p class="updated">Effective November 4, 2026</p>

            <p>These terms apply when you use Normi Chat, a group chat with an AI assistant made by Twenti Studio ("we"). By signing in, you agree to them. If you don't agree, please don't use Normi Chat.</p>

            <h2>1. Your account</h2>
            <p>You sign in with a Google account, and you are responsible for activity on your Normi Chat account. Keep your Google account secure, and tell us if you think someone else has used yours. If you are under 18, use Normi Chat with permission from a parent or guardian.</p>

            <h2>2. Groups and members</h2>
            <p>The person who creates a group owns it. The owner decides who can manage members, can set or change the group password, and can delete the group. Everything posted in a group, including Normi's answers, is visible to the group's members.</p>

            <h2>3. Normkredit and payments</h2>
            <p>Normkredit is a prepaid balance that a group uses for Normi's answers. The balance belongs to the group, so a member who leaves cannot take a contribution with them. Normkredit cannot be exchanged for cash or moved to another group.</p>
            <ul>
                <li>Prices, free groups, and the amount each new member contributes are listed on the <a href="{{ $page('/pricing') }}">Prices</a> page.</li>
                <li>Payments are made with QRIS through our payment provider.</li>
                <li>Payments are final, except where the law requires a refund or when we charged you in error.</li>
                <li>We may change prices. A change applies to payments made after it takes effect.</li>
            </ul>

            <h2>4. Normi's answers</h2>
            <p>Normi uses AI models, and its answers can be incomplete or wrong. Check important information before you rely on it, and don't treat Normi's answers as legal, medical, or financial advice.</p>

            <h2>5. Acceptable use</h2>
            <p>Don't use Normi Chat to break the law or to harm other people. That includes harassment, spam, malware, content that exploits children, and attempts to get around our security or the limits of the service. If a group or account breaks these terms, we may freeze the group, block the account, or remove content.</p>

            <h2>6. Your content</h2>
            <p>You keep ownership of the messages and files you post. You give us permission to store, process, and display them to run Normi Chat, which includes sending them to our AI provider when someone in the group asks Normi. The <a href="{{ $page('/privacy') }}">Privacy Policy</a> explains how we handle this information.</p>

            <h2>7. Availability and changes</h2>
            <p>We work to keep Normi Chat available, but we may pause it for maintenance or change and remove features. When a change affects you in a significant way, we will tell you in the app.</p>

            <h2>8. Liability</h2>
            <p>We provide Normi Chat as it is. To the extent the law allows, we are not liable for indirect losses, and our total liability for any claim is limited to the amount you paid us in the three months before the claim.</p>

            <h2>9. Ending your use</h2>
            <p>You can stop using Normi Chat at any time and ask us to delete your account. We may suspend or close an account that breaks these terms.</p>

            <h2>10. Governing law</h2>
            <p>These terms are governed by the laws of the Republic of Indonesia.</p>

            <h2>11. Changes to these terms</h2>
            <p>When we update these terms, we change the effective date above. If you keep using Normi Chat after an update, the new terms apply.</p>

            <h2>12. Contact</h2>
            @if ($supportEmail)
                <p>Send questions about these terms through the <a href="{{ $page('/contact') }}">Contact</a> page or to <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
            @else
                <p>Send questions about these terms through the <a href="{{ $page('/contact') }}">Contact</a> page.</p>
            @endif
        </div>
    </section>
@endsection
