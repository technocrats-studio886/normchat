@extends('marketing.layout', ['current' => 'about'])

@section('title', 'About Normi Chat')
@section('description', 'Normi Chat puts AI inside a group chat so everyone uses it together. An Uplift product from Sysnavia.')

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap">
            <h1>We build AI that a whole group can use.</h1>
            <div class="prose">
                <p>Most people use AI alone, so a good answer stays on one person's screen. Normi Chat puts AI inside the group chat. Everyone reads the answer, and everyone can reply to it.</p>
            </div>
            <div class="facts">
                <div class="fact"><div class="k">Company</div><div class="v">Sysnavia</div></div>
                <div class="fact"><div class="k">Product line</div><div class="v">Uplift</div></div>
                <div class="fact"><div class="k">Product</div><div class="v">Normi Chat</div></div>
                <div class="fact"><div class="k">Format</div><div class="v">Web app</div></div>
            </div>
        </div>
    </section>

    {{-- About --}}
    <section class="alt">
        <div class="wrap split">
            <div>
                <h2>About Normi Chat</h2>
                <p>Normi Chat is a group chat app with AI. It is built as a web app that feels right on a phone and works well on a laptop.</p>
                <p>Normi Chat is an Uplift product from Sysnavia.</p>
            </div>
            <div>
                <h2>Where the name comes from</h2>
                <p>Normi comes from <em>normies</em>: ordinary people who want to use AI without learning how it works. Normi is also the AI that lives in every group. Call it with <span class="mention">@normi</span>.</p>
            </div>
        </div>
    </section>

    {{-- Problem --}}
    <section>
        <div class="wrap">
            <h2>What we are fixing</h2>
            <div class="prose">
                <p>A study group asks AI the same question five times, in five separate chats. A small team pastes the same long discussion into five different tools. The answers never meet in one place.</p>
            </div>
        </div>
    </section>

    {{-- Principles --}}
    <section class="alt">
        <div class="wrap">
            <h2>Four principles</h2>
            <div class="grid grid-2">
                <div class="card">
                    <span class="num">1</span>
                    <h3>AI is shared</h3>
                    <p>Normi lives only inside groups. There is no private mode, so the discussion keeps one context.</p>
                </div>
                <div class="card">
                    <span class="num">2</span>
                    <h3>It answers when called</h3>
                    <p>Normi does not read and reply to every message.</p>
                </div>
                <div class="card">
                    <span class="num">3</span>
                    <h3>Pay for what you use</h3>
                    <p>Cost comes from the answers actually written, counted in Normkredit.</p>
                </div>
                <div class="card">
                    <span class="num">4</span>
                    <h3>History belongs to the group</h3>
                    <p>Messages can be backed up, restored, and downloaded at any time.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Next --}}
    <section>
        <div class="wrap">
            <h2>What comes next</h2>
            <div class="prose">
                <p>Workspace is coming for teams and organizations that run several groups on one balance. There is no price yet.</p>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="alt cta">
        <div class="wrap">
            <div class="prose">
                <h2>Try it with your group.</h2>
                <div class="btn-row">
                    <a href="{{ $loginUrl }}" class="btn">Get started</a>
                    <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
                </div>
            </div>
        </div>
    </section>

@endsection
