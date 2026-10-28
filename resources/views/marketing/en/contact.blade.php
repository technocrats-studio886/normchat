@extends('marketing.layout', ['active' => 'contact'])

@section('title', 'Contact Normi Chat — Information and Support')
@section('description', 'Contact Normi Chat with questions about pricing, AI integration, group management, and technical support.')

@section('content')
    <section class="page-hero">
        <div class="wrap">
            <p class="eyebrow">Contact us</p>
            <h1>Have a question? Let's start a conversation.</h1>
            <p class="lead">Want to know more about Normi Chat, understand the pricing, or find out which features are available?</p>
            <p class="lead">We're open to your questions and feedback. Tell us what you'd like to know so we can better understand what you need.</p>
        </div>
    </section>

    <section class="rule" id="form">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Send a message</p>
                <h2 class="h2">How can we help?</h2>
                <p class="lead" style="margin-top:20px">Share your question or tell us what you're trying to get done. What you tell us helps us understand the context.</p>
                @if ($contactEmail !== '')
                    <p class="price-note" style="margin-top:28px">Or write to us directly at</p>
                    <a class="mail-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                @endif
            </div>

            <div>
                @if (session('contact_status') === 'sent')
                    <div class="alert alert-ok" role="status" style="margin-bottom:24px">Thank you for getting in touch. Your message has been sent.</div>
                @elseif (session('contact_status') === 'failed' || $errors->any())
                    <div class="alert alert-bad" role="alert" style="margin-bottom:24px">Your message couldn't be sent. Please check the details you entered and try again.</div>
                @endif

                @if ($formEnabled)
                    <form class="form" method="POST" action="{{ route('marketing.en.contact.send') }}" novalidate>
                        @csrf
                        <div class="hp" aria-hidden="true">
                            <label for="website">Leave this empty</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="field @error('name') has-error @enderror">
                            <label for="name">Full Name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Enter your full name." autocomplete="name" maxlength="120" required>
                            @error('name') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('email') has-error @enderror">
                            <label for="email">Email Address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email address." autocomplete="email" maxlength="160" required>
                            @error('email') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('topic') has-error @enderror">
                            <label for="topic">Topic</label>
                            <select id="topic" name="topic" required>
                                <option value="" disabled @selected(old('topic') === null)>Choose a topic</option>
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                                @endforeach
                            </select>
                            @error('topic') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('message') has-error @enderror">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" placeholder="Tell us about your question, need, or the problem you're running into..." maxlength="4000" required>{{ old('message') }}</textarea>
                            @error('message') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-foot">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                            <p class="form-note">Please don't include passwords, API keys, or other sensitive information in your message.</p>
                        </div>
                    </form>
                @else
                    <div class="alert alert-bad" role="status">The contact form isn't enabled in this environment.</div>
                @endif
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Not sure yet?</p>
                <h2 class="h2" style="font-size:clamp(22px,2.8vw,30px)">Not sure where to begin?</h2>
            </div>
            <div class="stack">
                <p>That's fine. Tell us what you want to do with Normi Chat, whether it's bringing a group together, understanding the pricing, or exploring the available AI options.</p>
                <p class="emph">The clearer the details, the better we can understand your question.</p>
                <div><a href="#form" class="btn btn-ghost">Send a Question</a></div>
            </div>
        </div>
    </section>

    <section class="rule" id="faq">
        <div class="wrap faq-grid">
            <div>
                <p class="eyebrow">Common questions</p>
                <h2 class="h2" style="font-size:clamp(24px,3vw,32px)">Maybe this is what you're looking for.</h2>
            </div>
            <div class="faq-list">
                <details>
                    <summary>Where can I see Normi Chat's pricing?</summary>
                    <p>Visit the Pricing page to see what it costs to create a group, the starting Normkredit you get, and how to add more balance.</p>
                    <div class="faq-item-action"><a href="{{ $base }}/pricing" class="btn btn-ghost">See Pricing</a></div>
                </details>
                <details>
                    <summary>Which AI model does Normi Chat use?</summary>
                    <p>The group owner picks the AI model for the group from the options available in the app. What you can use depends on how AI is configured for your group.</p>
                </details>
                <details>
                    <summary>How do I get started with Normi Chat?</summary>
                    <p>Look over the pricing, log in to your account, create a group, set up its AI, then invite members to start talking.</p>
                    <div class="faq-item-action"><a href="{{ $startUrl }}" class="btn btn-ghost">Get Started</a></div>
                </details>
            </div>
        </div>
    </section>

    <section class="rule closing">
        <div class="wrap">
            <h2>Every collaboration starts with a conversation.</h2>
            <p class="lead">Tell Normi Chat what you need, what you're wondering, or what you're planning.</p>
            <p class="lead" style="margin-top:12px;color:#fff">Let's start with a conversation.</p>
            <div class="hero-actions">
                <a href="#form" class="btn btn-primary">Contact Normi Chat</a>
            </div>
        </div>
    </section>
@endsection
