@extends('marketing.layout', ['current' => 'about'])

{{-- Copy: docs/rancangan-copy-marketing.md (revisi 3, anti-slop). --}}
@section('title', 'About | Normi Chat')
@section('description', 'We build AI that a whole group can use. Normi Chat is made by Twenti Studio.')

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap">
            <h1>We build AI that a whole group can use.</h1>
            <div class="prose">
                <p>Most people use AI alone, and the answer stays on one screen. We think answers belong in the conversation, where the group can read them and build on them.</p>
            </div>
            <div class="facts">
                <div class="fact"><div class="k">Made by</div><div class="v">Twenti Studio</div></div>
                <div class="fact"><div class="k">Product</div><div class="v">Normi Chat</div></div>
            </div>
        </div>
    </section>

    {{-- Principle --}}
    <section class="alt">
        <div class="wrap split">
            <div>
                <h2>Anyone in the group can ask.</h2>
                <p>Some members know how to prompt an AI and others don't. With Normi, one person asks and the whole group benefits from the answer.</p>
            </div>
            <div>
                <h2>Your group decides together.</h2>
                <p>Your group makes plans and decisions in the chat. With the AI in the same conversation, you stop passing screenshots around and reach a decision sooner.</p>
            </div>
        </div>
    </section>

    {{-- Decisions --}}
    <section>
        <div class="wrap">
            <h2>Choices we made for your group</h2>
            <div class="grid grid-3">
                <div class="card">
                    <span class="num">1</span>
                    <h3>Normi lives in groups only</h3>
                    <p>There is no private chat with Normi, so your group shares the same answers and context.</p>
                </div>
                <div class="card">
                    <span class="num">2</span>
                    <h3>Normi answers when called</h3>
                    <p>Normi replies when someone mentions <span class="mention">@normi</span> and stays quiet otherwise.</p>
                </div>
                <div class="card">
                    <span class="num">3</span>
                    <h3>The balance belongs to the group</h3>
                    <p>Members contribute to one balance, and the group keeps its history.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Next --}}
    <section class="alt">
        <div class="wrap">
            <h2>Coming next</h2>
            <div class="prose">
                <p>Workspace is coming for teams and organizations that run several groups on one balance. We haven't set a price yet.</p>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="cta">
        <div class="wrap">
            <div class="prose">
                <h2>Bring your group together with one shared AI.</h2>
                <p>Normi Chat is made by Twenti Studio.</p>
                <div class="btn-row">
                    @include('marketing.partials.cta')
                    <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
                </div>
            </div>
        </div>
    </section>

@endsection
