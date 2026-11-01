@extends('marketing.layout', ['current' => 'pricing'])

@php
    $idr = fn (int|float $n) => 'Rp'.number_format($n, 0, '.', ',');
    $perCredit = $pricing['rupiah_per_credit'];
    $groupCredits = $pricing['group_creation_credits'];
    $packCredits = $pricing['package_credits'];
    $groupPrice = $idr($groupCredits * $perCredit);
    $packPrice = $idr($packCredits * $perCredit);
    $fee = rtrim(rtrim(number_format($pricing['qris_fee_percent'], 2, '.', ''), '0'), '.');
@endphp

@section('title', "Normi Chat prices: from {$groupPrice}")
@section('description', "Create a group for {$groupPrice} and get {$groupCredits} Normkredit. Chip in {$packPrice} for {$packCredits} Normkredit.")

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap">
            <h1>{{ $groupPrice }} to start.</h1>
            <div class="prose">
                <p>Pay once when you create a group and get {{ $groupCredits }} Normkredit right away. When the balance runs out, members can chip in. There is no monthly subscription.</p>
            </div>
        </div>
    </section>

    {{-- Plans --}}
    <section class="alt">
        <div class="wrap">
            <div class="plans" style="margin-top:0">
                <div class="card plan">
                    <h3>Create a group</h3>
                    <div class="price">{{ $groupPrice }}</div>
                    <div class="credit">{{ $groupCredits }} Normkredit</div>
                    <p class="desc">Creates the group. The balance belongs to the group.</p>
                    <div class="btn-row"><a href="{{ $loginUrl }}" class="btn">Create a group</a></div>
                </div>
                <div class="card plan">
                    <h3>Chip in and top up</h3>
                    <div class="price">{{ $packPrice }}</div>
                    <div class="credit">{{ $packCredits }} Normkredit per package</div>
                    <p class="desc">Any member can add to the group balance, up to {{ $pricing['max_packages_per_payment'] }} packages per payment.</p>
                    <div class="btn-row"><a href="{{ $loginUrl }}" class="btn btn-ghost">Top up balance</a></div>
                </div>
            </div>

            <h3 style="margin-top:48px">Included in every group</h3>
            <ul class="list">
                <li>Real-time chat for all members</li>
                <li>Normi through <span class="mention">@normi</span></li>
                <li>Access and member settings</li>
                <li>Backup and restore</li>
                <li>PDF and DOCX export</li>
            </ul>
            <div class="prose" style="margin-top:24px">
                <p>Payment is by QRIS. A {{ $fee }}% QRIS service fee is added to the total and shown before you pay.</p>
            </div>
        </div>
    </section>

    {{-- How Normkredit works --}}
    <section>
        <div class="wrap">
            <h2>What takes from the balance?</h2>
            <div class="prose">
                <p>1 Normkredit equals {{ $idr($perCredit) }}.</p>
                <p>The balance only drops when Normi answers. Chat between members costs nothing.</p>
                <p>The amount depends on the length of the answer and the model you pick. The remaining balance is always visible inside the group.</p>
            </div>
        </div>
    </section>

    {{-- Workspace --}}
    <section class="alt">
        <div class="wrap">
            <h2>Workspace, for many groups at once.</h2>
            <div class="prose">
                <p>Workspace is coming for teams and organizations that run several groups on one balance. There is no price yet. Write to us if you want to hear when it is ready.</p>
            </div>
            <div class="btn-row">
                <a href="{{ $page('/contact') }}" class="btn btn-ghost">Contact us</a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section>
        <div class="wrap">
            <h2>Questions and answers</h2>
            <div class="faq">
                <details>
                    <summary>Is there a free plan?</summary>
                    <p>Not yet. Creating a group costs {{ $groupPrice }} and includes {{ $groupCredits }} Normkredit.</p>
                </details>
                <details>
                    <summary>What is Normkredit?</summary>
                    <p>The unit of balance for AI use. One Normkredit is {{ $idr($perCredit) }}. The balance belongs to the group.</p>
                </details>
                <details>
                    <summary>Who pays when a new member joins?</summary>
                    <p>A member who joins chips in, and the payment goes into the group balance. The minimum is shown on the join page.</p>
                </details>
                <details>
                    <summary>Does normal chat use up the balance?</summary>
                    <p>No. The balance only drops when Normi answers.</p>
                </details>
                <details>
                    <summary>Can I use the AI alone, without a group?</summary>
                    <p>No. Normi only exists inside a group.</p>
                </details>
                <details>
                    <summary>What if the balance runs out?</summary>
                    <p>Anyone in the group can top it up by chipping in. Chat between members keeps working.</p>
                </details>
                <details>
                    <summary>Which AI models are used?</summary>
                    <p>Normi uses models from OpenAI. You pick the model in the group settings.</p>
                </details>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="alt cta">
        <div class="wrap">
            <div class="prose">
                <h2>Ready to create a group?</h2>
                <div class="btn-row">
                    <a href="{{ $loginUrl }}" class="btn">Get started</a>
                    <a href="{{ $page('/contact') }}" class="btn btn-ghost">Contact us</a>
                </div>
            </div>
        </div>
    </section>

@endsection
