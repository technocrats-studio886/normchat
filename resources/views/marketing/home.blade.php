@extends('marketing.layout', ['current' => 'home'])

@section('title', 'Normi Chat: a group chat with AI')
@section('description', 'Chat together, ask Normi. Mention @normi in the group and the AI answers for every member. From Rp30,000.')

@php
    $idr = fn (int|float $n) => 'Rp'.number_format($n, 0, '.', ',');
    $groupPrice = $idr($pricing['group_creation_credits'] * $pricing['rupiah_per_credit']);
@endphp

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap split">
            <div>
                <h1>Chat together, ask Normi.</h1>
                <div class="prose">
                    <p>Normi Chat is a group chat with AI built in. Mention <span class="mention">@normi</span> in your message and Normi answers for everyone in the group.</p>
                </div>
                <div class="btn-row">
                    <a href="{{ $loginUrl }}" class="btn">Get started</a>
                    <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
                </div>
                <p class="chat-note" style="margin-top:16px">Sign in with Google. Pay with QRIS.</p>
            </div>
            <div>
                <div class="chat" role="img" aria-label="Example group conversation">
                    <div class="msg"><b>Rina</b><span>Have we agreed on the proposal structure yet?</span></div>
                    <div class="msg"><b>Dimas</b><span>Not yet. The budget section is still empty.</span></div>
                    <div class="msg"><b>You</b><span><span class="mention">@normi</span> summarize the budget points from the chat above.</span></div>
                    <div class="msg normi"><b>Normi</b><span>Two points came up: a cap per person, and a shared fund for printing costs.</span></div>
                </div>
                <p class="chat-note">Example conversation. Normi only answers when called.</p>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="alt">
        <div class="wrap">
            <h2>Three steps, and it runs.</h2>
            <div class="grid grid-3">
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-users-three"/></svg>
                    <h3>Create a group</h3>
                    <p>Pay {{ $groupPrice }} through QRIS. Your group gets {{ $pricing['group_creation_credits'] }} Normkredit right away.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-link-simple"/></svg>
                    <h3>Invite friends</h3>
                    <p>Share the Share ID. Friends join and chip in to add to the group balance.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-at"/></svg>
                    <h3>Call Normi</h3>
                    <p>Type <span class="mention">@normi</span> in the chat. Everyone in the group sees the answer.</p>
                </div>
            </div>
            <div class="btn-row">
                <a href="{{ $loginUrl }}" class="btn">Create a group</a>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section>
        <div class="wrap">
            <h2>What you get inside a group.</h2>
            <div class="grid grid-3">
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-at"/></svg>
                    <h3>Normi answers when called</h3>
                    <p>Chat between members is left alone. Mention <span class="mention">@normi</span> and then it answers.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-coins"/></svg>
                    <h3>One balance for everyone</h3>
                    <p>Normkredit belongs to the group. Anyone in the group can use it, with no subscription per person.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-sliders-horizontal"/></svg>
                    <h3>Pick the model</h3>
                    <p>A fast model for daily chat, a stronger model for hard questions.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-pencil-simple"/></svg>
                    <h3>Edit, delete, forward</h3>
                    <p>Fix a typo and the version history stays visible. Messages can be deleted or forwarded to another group.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-file-arrow-down"/></svg>
                    <h3>Backup and export</h3>
                    <p>Back up and restore group history. Download it as PDF or DOCX.</p>
                </div>
                <div class="card">
                    <svg class="icon" aria-hidden="true"><use href="#i-device-mobile"/></svg>
                    <h3>Phone and laptop</h3>
                    <p>Open it in a browser or add it to the home screen. No app store download.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Who it is for --}}
    <section class="alt">
        <div class="wrap">
            <h2>Made for groups that talk a lot.</h2>
            <div class="grid grid-3">
                <div class="card">
                    <h3>Study groups</h3>
                    <p>Ask Normi in the group, so every member sees the same answer.</p>
                </div>
                <div class="card">
                    <h3>Small teams</h3>
                    <p>Summarize a long discussion, look for title ideas, or draft together.</p>
                </div>
                <div class="card">
                    <h3>Communities</h3>
                    <p>One group, one balance, and the cost is split between members.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing teaser --}}
    <section>
        <div class="wrap">
            <h2>Starts at {{ $groupPrice }}.</h2>
            <div class="prose">
                <p>Pay once when you create a group, and it already includes {{ $pricing['group_creation_credits'] }} Normkredit. No monthly subscription. When the balance runs out, members chip in.</p>
            </div>
            <div class="btn-row">
                <a href="{{ $page('/pricing') }}" class="btn btn-ghost">See prices</a>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="alt cta">
        <div class="wrap">
            <div class="prose">
                <h2>Open your first group.</h2>
                <p>It takes about a minute. Sign in with Google, pay with QRIS, then invite your friends.</p>
                <div class="btn-row">
                    <a href="{{ $loginUrl }}" class="btn">Get started</a>
                </div>
            </div>
        </div>
    </section>

@endsection
