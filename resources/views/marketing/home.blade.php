@extends('marketing.layout', ['current' => 'home'])

{{-- Copy: docs/rancangan-copy-marketing.md (revisi 3, anti-slop). Angka dari setelan ($n, view composer marketing). --}}
@section('title', 'Normi Chat | One AI for the whole group')
@section('description', 'Meet Normi, the AI your group can ask for a recap, a first draft, or a hand with the next decision.')

@section('content')


    <section class="hero home-hero">
        <div class="wrap home-intro">
            <div class="hero-copy">
                <h1>One AI for the whole group.</h1>
                <p class="hero-lead">Mention <span class="mention">@normi</span> to recap a long discussion or turn an idea into a first draft. Everyone can pick up the conversation from there.</p>
                <div class="btn-row">
                    @include('marketing.partials.cta')
                    <a href="#demo" class="btn btn-ghost">See how it works</a>
                </div>
            </div>
        </div>
        <div class="wrap home-demo" id="demo">
            <div class="demo-wrap">
                <div class="chat" data-live-demo role="group" aria-label="Example of Normi drafting a workshop reminder">
                    <div class="chat-head"><span>Workshop planning</span><span class="live-badge"><i></i>Live demo</span></div>
                    <div data-demo-body>
                        <div class="msg" data-demo-step><b>Rina</b><span>The workshop is Friday at 2.</span></div>
                        <div class="msg" data-demo-step><b>Dimas</b><span>I booked Room 4. Everyone needs a laptop.</span></div>
                        <div class="msg mine" data-demo-step data-demo-pause="1300"><b>You</b><span><span class="mention" data-demo-mention>@normi</span> draft a reminder for the group.</span></div>
                        <div class="msg normi typing" data-demo-typing><b>Normi</b><span class="dots"><i></i><i></i><i></i><em>Thinking</em></span></div>
                        <div class="msg normi" data-demo-step><b>Normi</b><span data-demo-answer>See you in Room 4 on Friday at 2 for the workshop. Bring your laptop!</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how">
        <div class="wrap">
            <h2>How to bring Normi into your group</h2>
            <div class="grid grid-3">
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-users-three"/></svg>
                    <h3>1. Start your group</h3>

                        <p>Sign in with Google and give your group a name.</p>

                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-link-simple"/></svg>
                    <h3>2. Invite your friends</h3>
                    <p>Send your friends the group code so they can join the conversation.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-at"/></svg>
                    <h3>3. Ask @normi</h3>
                    <p>Ask <span class="mention">@normi</span> a question in the chat. Anyone in the group can follow up.</p>
                </div>
            </div>
            <div class="btn-row">
                @include('marketing.partials.cta')
            </div>
        </div>
    </section>

    {{-- Benefits --}}
    <section class="alt">
        <div class="wrap">
            <h2>Your group gets more done with one shared AI.</h2>
            <div class="grid grid-3">
                <div class="card lead">
                    <svg class="icon" aria-hidden="true"><use href="#i-coins"/></svg>
                    <h3>One balance for the whole group</h3>
                    <p>Stop paying for AI one person at a time. Your group shares one Normkredit balance, and any member can use it or top it up. There is no monthly subscription to cancel.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-at"/></svg>
                    <h3>A chat that still feels like yours</h3>
                    <p>Normi stays quiet until someone types <span class="mention">@normi</span>, so your side talk stays between you.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-sliders-horizontal"/></svg>
                    <h3>The right AI for every task</h3>
                    <p>Use a quick level for everyday questions and a stronger one for writing and analysis. Advanced, our most capable level, takes on hard problems.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-users-three"/></svg>
                    <h3>Settle it with a vote</h3>
                    <p>Start a poll in the chat, and the group sees the result in one place.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-pencil-simple"/></svg>
                    <h3>Edits stay visible</h3>
                    <p>When someone edits a message, the group can still see the earlier version.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-file-arrow-down"/></svg>
                    <h3>Keep your group's history</h3>
                    <p>Back up and restore your group's history, or download it as PDF or DOCX for a report.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-device-mobile"/></svg>
                    <h3>Use it on your phone or laptop</h3>
                    <p>Normi Chat runs in the browser, and you can add it to your home screen without installing an app.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Mode Normi dari menu + di kolom pesan (App\Enums\AiMode). Tiap kartu: manfaat, kapan dipakai, cara kerjanya. --}}
    <section id="modes">
        <div class="wrap">
            <h2>Ask Normi to teach, draw, build, or research.</h2>
            <p class="section-intro">Tap + next to the message box, pick a mode, and mention <span class="mention">@normi</span> in your message. The result shows up in the group chat, where everyone can read it and reply.</p>
            <div class="grid grid-2">
                <div class="card mode-card">
                    <svg class="icon" aria-hidden="true"><use href="#i-book-open"/></svg>
                    <span class="mode-tag">Guided Learning</span>
                    <h3>Learn a topic step by step</h3>
                    <p>Use it when your group is studying a new subject or preparing for an exam together.</p>
                    <p class="how">Normi first asks what you want to learn and what you already know. It then lays out a short learning path and explains one idea at a time, with an example and a quick question to check your understanding. For homework, it gives hints first so you can try before you see the full solution.</p>
                </div>
                <div class="card mode-card">
                    <svg class="icon" aria-hidden="true"><use href="#i-image"/></svg>
                    <span class="mode-tag">Create image</span>
                    <h3>Turn an idea into a picture</h3>
                    <p>Use it for a poster draft, an illustration for a presentation, or a quick visual to show the group what you have in mind.</p>
                    <p class="how">Describe the image you want. To change an existing picture, reply to it in the chat and tell Normi what to adjust. The new image appears in the conversation so the group can review it together.</p>
                </div>
                <div class="card mode-card">
                    <svg class="icon" aria-hidden="true"><use href="#i-browser"/></svg>
                    <span class="mode-tag">Canvas</span>
                    <h3>Get a finished document or web page</h3>
                    <p>Use it when you need a complete piece of work to start from, such as a report, a proposal, a study summary, or a small web page like a quiz or a calculator.</p>
                    <p class="how">Normi builds it in a Canvas panel, where you can switch between the preview and the code. Reply to the Canvas to ask for changes, and Normi revises the same piece.</p>
                </div>
                <div class="card mode-card">
                    <svg class="icon" aria-hidden="true"><use href="#i-magnifying-glass-plus"/></svg>
                    <span class="mode-tag">Deep Research</span>
                    <h3>Research with sources you can check</h3>
                    <p>Use it before a decision or an assignment that depends on facts, when a quick answer is not enough.</p>
                    <p class="how">Normi searches the web, compares several sources, and writes a report with a summary, the main findings, and a link for each important claim. When it cannot find enough reliable sources, Normi tells you.</p>
                </div>
            </div>
            <p class="modes-note">Each mode uses Normkredit from your group's shared balance, just like other answers from Normi.</p>
        </div>
    </section>

    {{-- Who it is for --}}
    <section>
        <div class="wrap">
            <h2>Built for groups that learn and work together.</h2>
            <div class="grid grid-3">
                <div class="card">
                    <h3>Study groups</h3>
                    <p>One person asks <span class="mention">@normi</span>, and the whole group studies from the same explanation.</p>
                </div>
                <div class="card">
                    <h3>Small teams</h3>
                    <p>Ask Normi to turn a long discussion into a summary with next steps, and skip writing the minutes yourself.</p>
                </div>
                <div class="card">
                    <h3>Communities and organizations</h3>
                    <p>Plan events with one AI assistant that members fund together.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing teaser --}}
    <section class="alt">
        <div class="wrap">

            <h2>One shared balance for {{ $n['groupPrice'] }}</h2>
            <div class="prose" data-paid-group-offer>
                <p>Your group starts with {{ $n['groupCredits'] }} Normkredit.
                    @if ($n['promotion']['active'])
                        Get {{ rtrim(rtrim(number_format($n['promotion']['bonus_credits'], 2, '.', ','), '0'), '.') }} bonus NC when you start a group during the promotion.
                    @endif
                    Top up whenever you need more.</p>
                @if ($n['freeSlots'] > 0)
                    <p>Your first {{ $n['freeSlots'] }} {{ $n['freeSlots'] === 1 ? 'friend joins' : 'friends join' }} free.</p>
                @endif
            </div>
            <div class="btn-row">
                <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="cta">
        <div class="wrap">
            <div class="prose">
                <h2>What’s your group working on?</h2>

                <div class="btn-row">
                    @include('marketing.partials.cta')
                </div>
            </div>
        </div>
    </section>

@endsection
