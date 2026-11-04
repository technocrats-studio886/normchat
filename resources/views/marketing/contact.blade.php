@extends('marketing.layout', ['current' => 'contact'])

@section('title', 'Contact | Normi Chat')
@section('description', 'Send us your questions about prices or Normi, and we will reply by email.')

@section('content')

    {{-- Hero + form --}}
    <section class="hero">
        <div class="wrap">
            <h1>Talk to us.</h1>
            <div class="prose">
                <p>Send us your questions about prices or Normi, and we'll reply by email.</p>
            </div>

            @if (session('contact_status') === 'sent')
                <div class="alert" role="status">Thank you. Your message has been sent, and we will reply by email.</div>
            @elseif (session('contact_status') === 'failed')
                <div class="alert error" role="alert">The message was not sent. Check the fields and try again.</div>
            @endif

            <form method="POST" action="{{ route('contact.send') }}" class="form" novalidate>
                @csrf
                <div class="hp" aria-hidden="true">
                    <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Your name" autocomplete="name" required>
                    @error('name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@email.com" autocomplete="email" required>
                    @error('email') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="topic">Topic</label>
                    <select id="topic" name="topic" required>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                        @endforeach
                    </select>
                    @error('topic') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" placeholder="Tell us what you need" required>{{ old('message') }}</textarea>
                    @error('message') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div>
                    <button type="submit" class="btn">Send message</button>
                    <p class="form-note" style="margin-top:12px">Do not send passwords or payment details through this form.</p>
                </div>
            </form>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="alt">
        <div class="wrap">
            <h2>Quick answers</h2>
            <div class="faq">
                <details>
                    <summary>Where can I see the prices?</summary>
                    <p>You can find them on the <a href="{{ $page('/pricing') }}">Prices</a> page.</p>
                </details>
                <details>
                    <summary>How do I start?</summary>
                    <p>Sign in with Google to get your first group, then share its group code with your friends.</p>
                </details>
                <details>
                    <summary>Which AI models are used?</summary>
                    <p>Normi uses OpenAI models, and you choose the level each time you ask Normi.</p>
                </details>
            </div>
        </div>
    </section>

    {{-- Closing --}}
    <section class="cta">
        <div class="wrap">
            <div class="prose">
                <h2>You can also write to us by email.</h2>
                @if ($supportEmail)
                    <p>Write to <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> for questions about Normi Chat.</p>
                @endif
                @if ($businessEmail)
                    <p>For partnerships and other business inquiries, write to <a href="mailto:{{ $businessEmail }}">{{ $businessEmail }}</a>.</p>
                @endif
                <div class="btn-row">
                    <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
                </div>
            </div>
        </div>
    </section>

@endsection
