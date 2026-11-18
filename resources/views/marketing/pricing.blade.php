@extends('marketing.layout', ['current' => 'pricing'])

{{-- Copy: docs/rancangan-copy-marketing.md (revisi 3, anti-slop). Angka dari setelan ($n, view composer marketing). --}}
@section('title', "Prices | Normi Chat | From {$n['groupPrice']}")
@section('description', "A group costs {$n['groupPrice']} and includes {$n['groupCredits']} Normkredit after payment. Promotional bonuses apply during the stated period.")

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap">
            <h1>Pay for Normi together, with no monthly subscription.</h1>
            <div class="prose">
                <p>Your group shares one balance, and Normi uses it when it answers.{{ $n['free'] ? ' ' . $n['offer']->enGroups() . ($n['offer']->count() === 1 ? ' is free' : ' are free') . ", so you can try Normi with your friends before anyone pays. After that, a group costs {$n['groupPrice']} and starts with {$n['groupCredits']} Normkredit." : '' }}</p>
            </div>
            <div class="btn-row">
                @include('marketing.partials.cta')
            </div>
        </div>
    </section>

    <section class="alt">
        <div class="wrap">
            <h2>Pay once to start your group</h2>
            @include('marketing.partials.group-offer')
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Each group</th><th scope="col">Included</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">Create a group</th><td>{{ $n['groupPrice'] }}</td></tr>
                        <tr><th scope="row">Starting balance after payment</th><td>{{ $n['groupCredits'] }} Normkredit</td></tr>
                        @if($n['promotion']['active'])
                            <tr><th scope="row">Promotion bonus</th><td>+{{ number_format($n['promotion']['bonus_credits'], 2, '.', ',') }} NC</td></tr>
                            <tr><th scope="row">Total during promotion</th><td>{{ number_format($n['promotion']['total_credits'], 2, '.', ',') }} NC</td></tr>
                        @endif
                        <tr><th scope="row">Friends who join free</th><td>{{ $n['freeSlots'] }}</td></tr>
                        <tr><th scope="row">Later members</th><td>Contribute once, from {{ $n['packPrice'] }} for {{ $n['packCredits'] }} Normkredit</td></tr>
                        <tr><th scope="row">Top up</th><td>Any member, up to {{ $n['maxPacks'] }} packages per payment</td></tr>
                        <tr><th scope="row">Monthly fee</th><td>None</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="table-note">You pay with QRIS. Any payment processing fee is shown at checkout.</p>
        </div>
    </section>

    {{-- Normkredit --}}
    <section>
        <div class="wrap">
            <h2>You pay when Normi answers.</h2>
            <div class="prose">
                <p>Normkredit is your group's shared AI balance, and 1 Normkredit equals {{ $n['perCredit'] }}. Chat between members costs nothing.</p>
                <p>Longer answers and stronger levels of AI use more Normkredit. Members can see the remaining balance in the group.</p>
            </div>
        </div>
    </section>

    {{-- AI levels --}}
    <section class="alt">
        <div class="wrap">
            <h2>Choose the level of AI for each message.</h2>
            <div class="prose">
                <p>Keep everyday questions on a light level and save Advanced for hard problems.</p>
            </div>
            <div class="table-wrap wide-only">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Compare</span></th>
                            <th scope="col">Fast</th>
                            <th scope="col">Smart</th>
                            <th scope="col">Advanced</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Best for</th>
                            <td>Quick questions and everyday chat</td>
                            <td>Writing and analysis</td>
                            <td>Hard problems and deep reasoning</td>
                        </tr>
                        <tr>
                            <th scope="row">What you get</th>
                            <td>Fast, light answers</td>
                            <td>A strong all-rounder</td>
                            <td>Our most capable level</td>
                        </tr>
                        <tr>
                            <th scope="row">Normkredit per answer</th>
                            <td>Lowest</td>
                            <td>Medium</td>
                            <td>Highest</td>
                        </tr>
                        <tr>
                            <th scope="row">Available</th>
                            <td>From the start</td>
                            <td>From the start</td>
                            <td>{{ $n['advancedAt'] ? "When the group balance reaches {$n['advancedAt']} Normkredit" : 'From the start' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            {{-- HP: isi tabel yang sama sebagai kartu. --}}
            <div class="levels">
                @foreach ([
                    ['Fast', 'Quick questions and everyday chat', 'Fast, light answers', 'Lowest', 'From the start'],
                    ['Smart', 'Writing and analysis', 'A strong all-rounder', 'Medium', 'From the start'],
                    ['Advanced', 'Hard problems and deep reasoning', 'Our most capable level', 'Highest', $n['advancedAt'] ? "When the group balance reaches {$n['advancedAt']} Normkredit" : 'From the start'],
                ] as [$level, $best, $get, $cost, $available])
                    <div class="card">
                        <h3>{{ $level }}</h3>
                        <dl>
                            <div><dt>Best for</dt><dd>{{ $best }}</dd></div>
                            <div><dt>What you get</dt><dd>{{ $get }}</dd></div>
                            <div><dt>Normkredit per answer</dt><dd>{{ $cost }}</dd></div>
                            <div><dt>Available</dt><dd>{{ $available }}</dd></div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Workspace --}}
    <section>
        <div class="wrap">
            <h2>Workspace for teams with many groups</h2>
            <div class="prose">
                <p>Workspace is coming for teams and organizations that run several groups on one balance. We haven't set a price yet, and you can write to us to hear when it launches.</p>
            </div>
            <div class="btn-row">
                <a href="{{ $page('/contact') }}" class="btn btn-ghost">Contact us</a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="alt">
        <div class="wrap">
            <h2>Questions and answers</h2>
            <div class="faq">
                <details>
                    <summary>Is there a free plan?</summary>

                        <p>We don't have a free plan at the moment. A group costs {{ $n['groupPrice'] }} and starts with {{ $n['groupCredits'] }} Normkredit.</p>

                </details>
                <details>
                    <summary>Do I pay each time I use Normi?</summary>
                    <p>You don't. Normi uses your group's shared balance, and you pay when you contribute to join or top up.</p>
                </details>
                <details>
                    <summary>Do new members have to pay?</summary>
                    @if ($n['freeSlots'] > 0)
                        <p>In each paid group, the first {{ $n['freeSlots'] }} members join free. Each member after that makes a one-time contribution before joining, and it goes into the shared balance.</p>
                    @else
                        <p>Each new member makes a one-time contribution before joining, and it goes into the shared balance.</p>
                    @endif
                </details>
                <details>
                    <summary>What happens when the balance runs out?</summary>
                    <p>Normi stops answering until a member tops up, and your group can keep chatting in the meantime.</p>
                </details>
                <details>
                    <summary>Can I talk to Normi privately?</summary>
                    <p>Normi works inside groups, so the whole group sees its answers. There is no private chat with Normi.</p>
                </details>
                <details>
                    <summary>How do I pay?</summary>
                    <p>You pay with QRIS from your banking or e-wallet app.</p>
                </details>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="cta">
        <div class="wrap">
            <div class="prose">
                <h2>Bring your group together with one shared AI.</h2>
                <div class="btn-row">
                    @include('marketing.partials.cta')
                    <a href="{{ $page('/contact') }}" class="btn btn-ghost">Contact us</a>
                </div>
            </div>
        </div>
    </section>

@endsection
