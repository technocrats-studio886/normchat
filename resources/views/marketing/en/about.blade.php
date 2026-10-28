@extends('marketing.layout', ['active' => 'about'])

@section('title', 'About Normi Chat — Group Chat and Collaboration with AI')
@section('description', 'Get to know Normi Chat, a mobile-first group chat platform that brings people and AI together through real-time conversation and group management tools.')

@section('content')
    <section class="page-hero">
        <div class="wrap">
            <p class="eyebrow">About Normi Chat</p>
            <h1>Built for conversation. Designed for collaboration.</h1>
            <p class="lead">We believe a group chat should be more than a place to trade messages. It can be a space to share ideas, talk through possibilities, and bring in AI when extra help is needed.</p>
            <p class="lead" style="color:#fff">That is the idea behind Normi Chat.</p>
        </div>
    </section>

    <section class="rule">
        <div class="wrap split">
            <div>
                <p class="eyebrow">The product</p>
                <h2 class="h2">One space for people and AI.</h2>
            </div>
            <div class="stack">
                <p>Normi Chat is a group chat platform designed to bring conversations between people together with help from AI.</p>
                <p>Rather than treating AI as a separate destination, Normi Chat makes it part of the discussion space. Group members exchange messages in real time and call AI with a mention when they need help or another point of view.</p>
                <p>The result is a more connected way to work with AI: people, conversation, and context all stay in one place.</p>
                <p>With a mobile-first approach, Normi Chat puts practical navigation and easy interactions first on mobile devices, while still supporting desktop use.</p>
                <p class="emph">Normi Chat is a product in the Uplift line, under Sysnavia.</p>
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap">
            <p class="eyebrow">Our principles</p>
            <h2 class="h2">Technology should follow how people communicate.</h2>
            <p class="lead" style="margin-top:20px">We believe good technology isn't about offering more features. It should help people get things done in a simpler way that fits their habits. These principles shape how Normi Chat brings together people, AI, and conversation management.</p>

            <div class="principles">
                <div class="principle">
                    <span class="n">Principle 1</span>
                    <h3>AI that arrives on time.</h3>
                    <p>AI should help when it's needed.</p>
                    <p>With the mention system, Normi Chat lets group members call AI to answer a question or explore an idea, without AI replying to every message on its own. You stay in control of the conversation.</p>
                </div>
                <div class="principle">
                    <span class="n">Principle 2</span>
                    <h3>Mobile experience first.</h3>
                    <p>Conversations don't only happen at a computer.</p>
                    <p>Normi Chat is designed mobile-first, with compact navigation and comfortable interactions that keep people connected on the devices they use every day.</p>
                </div>
                <div class="principle">
                    <span class="n">Principle 3</span>
                    <h3>Clear control.</h3>
                    <p>Every group has different needs and characteristics.</p>
                    <p>Normi Chat provides group access settings and user permissions so group owners can manage members and decide how the space is used. Good collaboration needs an organized space.</p>
                </div>
                <div class="principle">
                    <span class="n">Principle 4</span>
                    <h3>Keeping conversation history valuable.</h3>
                    <p>Discussions can hold decisions, ideas, and information worth coming back to.</p>
                    <p>With backup, restore, and export, Normi Chat helps groups manage their discussion history so it can serve as a reference when needed.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap audience" style="margin-top:0">
            <div>
                <p class="eyebrow">Who it's for</p>
                <h2 class="h2" style="font-size:clamp(22px,2.8vw,30px)">For people who grow through collaboration.</h2>
            </div>
            <div>
                <p class="lead" style="margin-bottom:28px">Normi Chat is designed for groups that want everyday communication and AI help in one conversation space.</p>
                <div class="audience-list">
                    <div class="audience-item">
                        <h3>Teams and project groups</h3>
                        <p>Talk through the work, trade ideas, and bring in AI when the team needs another perspective to develop an idea.</p>
                    </div>
                    <div class="audience-item">
                        <h3>Study groups</h3>
                        <p>Go over material, ask questions, and use AI to help explain concepts while the discussion is happening.</p>
                    </div>
                    <div class="audience-item">
                        <h3>Communities and collaborative groups</h3>
                        <p>Bring members together in one conversation space, with group access settings and a discussion history you can manage.</p>
                    </div>
                </div>
                <p class="price-note" style="margin-top:20px">Every group has its own goals. Normi Chat gives them a shared space to keep their conversations connected.</p>
            </div>
        </div>
    </section>

    <section class="rule closing">
        <div class="wrap">
            <h2>Better collaboration starts with conversation.</h2>
            <p class="lead">Bring together the people who matter to you. Involve AI when it's needed. Grow ideas together.</p>
            <p class="lead" style="margin-top:12px">Discover a group chat experience designed around the way people communicate.</p>
            <div class="hero-actions">
                <a href="{{ $startUrl }}" class="btn btn-primary">Get Started</a>
                <a href="{{ $base }}/pricing" class="btn btn-ghost">See Pricing</a>
            </div>
        </div>
    </section>
@endsection
