@extends('marketing.layout', ['active' => 'pricing'])

@section('title', 'Normi Chat Pricing — Group Setup and Normkredit')
@section('description', 'See what it costs to create a Normi Chat group, how many Normkredit you start with, the price of top-up packages, and how AI usage is calculated.')

@php
    $idr = fn (int $value) => 'Rp'.number_format($value, 0, '.', ',');
    $nk = fn (int $value) => number_format($value, 0, '.', ',');
@endphp

@section('content')
    <section class="page-hero">
        <div class="wrap">
            <p class="eyebrow">Simple, transparent pricing</p>
            <h1>One balance to start collaborating.</h1>
            <p class="lead">Start with one group, bring your members together, and use AI help when you need it. Add Normkredit as your group's needs grow.</p>
            <p class="hero-note">One shared balance. No per-person subscriptions.</p>
        </div>
    </section>

    <section class="rule" style="padding-top:72px">
        <div class="wrap">
            <div class="price-main">
                <div class="price-side">
                    <span class="tier">Create a group</span>
                    <span class="price-figure">{{ $idr($groupQuote->baseAmount) }}</span>
                    <span class="price-unit">per group, including {{ $nk($groupQuote->credits) }} starting Normkredit</span>
                    <p>One conversation space for your group, with AI capabilities and conversation management tools.</p>
                    <a href="{{ $startUrl }}" class="btn btn-primary">Create a Group</a>
                </div>
                <div class="price-list">
                    <h3>What's included</h3>
                    <ul class="checklist">
                        <li>{{ $nk($groupQuote->credits) }} Normkredit as the group's starting balance.</li>
                        <li>Real-time group chat.</li>
                        <li>AI participation configured for your group.</li>
                        <li>Access settings and member management.</li>
                        <li>Conversation backup and restore.</li>
                        <li>Conversation export to PDF and DOCX.</li>
                    </ul>
                </div>
            </div>
            <p class="price-note">Payment is by QRIS. A {{ $feeLabel }}% QRIS service fee is added to the total, and it's shown before you pay.</p>
        </div>
    </section>

    <section class="rule">
        <div class="wrap">
            <p class="eyebrow">Additional purchases</p>
            <h2 class="h2">Add capacity when you need it.</h2>
            <p class="lead" style="margin-top:20px">Start with a clear cost. Group members can add Normkredit to the balance whenever the group needs more AI capacity.</p>

            <div class="buy-grid">
                <div class="buy">
                    <h3>Pool in &amp; top up Normkredit</h3>
                    <p class="amount">{{ $idr($packageQuote->baseAmount) }} <small>/ package · {{ $nk($packageQuote->credits) }} Normkredit</small></p>
                    <p>Add AI usage credit to your group's balance. Buy one package, or several at once, up to {{ $maxPackages }} packages in a single payment.</p>
                    <a href="{{ $startUrl }}" class="btn btn-ghost">Top Up Normkredit</a>
                </div>
                <div class="buy">
                    <h3>Price per Normkredit</h3>
                    <p class="amount">{{ $idr($rupiahPerCredit) }} <small>/ Normkredit</small></p>
                    <p>The base cost of each Normkredit. What's actually used depends on your usage and the multiplier of the AI model you choose, so you pay for what you use.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Transparency</p>
                <h2 class="h2">Know what you're paying for.</h2>
            </div>
            <div class="stack">
                <p>Normi Chat keeps the cost of creating a group separate from adding more balance, so each part of the bill is easy to understand.</p>
                <p>Creating a group covers access to all features along with a starting balance. More balance can be bought whenever it's needed.</p>
                <p>AI usage is counted in tokens. The number of tokens used can differ from request to request, depending on actual usage and the multiplier of the AI model you choose.</p>
                <p class="emph">That's why AI consumption isn't the same for every request. Check your group's remaining Normkredit to see how much AI capacity you have left.</p>
            </div>
        </div>
    </section>

    <section class="rule" id="faq">
        <div class="wrap faq-grid">
            <div>
                <p class="eyebrow">Common questions</p>
                <h2 class="h2" style="font-size:clamp(24px,3vw,32px)">Questions about pricing.</h2>
            </div>
            <div class="faq-list">
                <details>
                    <summary>Does Normi Chat have a free plan?</summary>
                    <p>Normi Chat runs on a paid balance. Creating a group costs {{ $idr($groupQuote->baseAmount) }} and already includes {{ $nk($groupQuote->credits) }} starting Normkredit.</p>
                </details>
                <details>
                    <summary>What is Normkredit?</summary>
                    <p>Normkredit is the credit unit used to manage AI usage in Normi Chat. One Normkredit is priced at {{ $idr($rupiahPerCredit) }}. The actual tokens consumed depend on usage and the multiplier of the AI model you choose.</p>
                </details>
                <details>
                    <summary>Can I add more AI credit?</summary>
                    <p>Yes. You can buy extra Normkredit packages, each {{ $nk($packageQuote->credits) }} Normkredit for {{ $idr($packageQuote->baseAmount) }}, whenever your group needs more AI capacity.</p>
                </details>
                <details>
                    <summary>What if members want to share the cost?</summary>
                    <p>Members can chip in to the group balance. The contributions go into one shared balance used by every member, within the access rules the group owner sets.</p>
                </details>
                <details>
                    <summary>Do chats between members use up the balance?</summary>
                    <p>No. The balance is only deducted when AI answers a request you called with the @normi mention.</p>
                </details>
                <details>
                    <summary>Which AI model is used?</summary>
                    <p>The group owner picks the AI model for the group from the options available in the app. Each model has its own multiplier, and that multiplier affects how much Normkredit is deducted.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="rule closing">
        <div class="wrap">
            <h2>Ready to start the conversation together?</h2>
            <p class="lead">Start with one conversation space, bring your group together, and add capacity as you need it.</p>
            <div class="hero-actions">
                <a href="{{ $startUrl }}" class="btn btn-primary">Get Started</a>
                <a href="{{ $base }}/contact" class="btn btn-ghost">Contact Us</a>
            </div>
        </div>
    </section>
@endsection
