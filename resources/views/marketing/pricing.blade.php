@extends('marketing.layout', ['current' => 'pricing'])

{{-- Copy: docs/rancangan-copy-marketing.md (revisi 3, anti-slop). Angka dari setelan ($n, view composer marketing). --}}
@section('title', $n['free'] ? ($n['freeSecond'] ? 'Prices | Normi Chat | Your first two groups are free' : 'Prices | Normi Chat | Your first group is free') : "Prices | Normi Chat | From {$n['groupPrice']}")
@section('description', $n['free']
    ? ($n['freeSecond'] ? 'Your first two groups are free. ' : 'Your first group is free. ') . 'Members make a one-time contribution to join and top up one shared balance, with no monthly subscription.'
    : "A group costs {$n['groupPrice']} and starts with {$n['groupCredits']} Normkredit. Members make a one-time contribution to join and top up together.")

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="wrap">
            <h1>Pay for Normi together, with no monthly subscription.</h1>
            <div class="prose">
                <p>Your group shares one balance, and Normi uses it when it answers.{{ $n['free'] ? ($n['freeSecond'] ? ' Your first two groups are free' : ' Your first group is free') . ", so you can try Normi with your friends before anyone pays. After that, a group costs {$n['groupPrice']} and starts with {$n['groupCredits']} Normkredit." : '' }}</p>
            </div>
            <div class="btn-row">
                @include('marketing.partials.cta')
            </div>
        </div>
    </section>

    {{-- Paying as a group --}}
    <section class="alt">
        <div class="wrap">
            <h2>Paying as a group, step by step</h2>
            <div class="grid grid-2">
                @if ($n['free'])
                    <div class="card">
                        <span class="num">1</span>
                        <h3>Start free</h3>
                        <p>Your first group comes with {{ $n['freeFirst'] }} Normkredit{{ $n['freeSecond'] ? " and your second with {$n['freeSecond']}" : '' }}.{{ $n['freeSlots'] > 0 ? " The first {$n['freeSlots']} friends who join each free group pay nothing." : '' }}</p>
                    </div>
                @endif
                <div class="card">
                    <span class="num">{{ $n['free'] ? 2 : 1 }}</span>
                    <h3>Contribute once to join</h3>
                    <p>{{ $n['free'] ? 'After the free spots are taken, and in groups you create later, each' : 'Each' }} new member makes a one-time contribution before joining, starting at {{ $n['packPrice'] }} for {{ $n['packCredits'] }} Normkredit. The contribution goes into the group's balance.</p>
                </div>
                <div class="card">
                    <span class="num">{{ $n['free'] ? 3 : 2 }}</span>
                    <h3>Top up when you need to</h3>
                    <p>When the balance gets low, any member can add more Normkredit.</p>
                </div>
                <div class="card">
                    <span class="num">{{ $n['free'] ? 4 : 3 }}</span>
                    <h3>One balance for the group</h3>
                    <p>Members use Normi from the same balance, so they don't need separate AI subscriptions.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Compare</span></th>
                            @if ($n['free'])
                                <th scope="col">Free groups (your {{ $n['freeSecond'] ? '1st and 2nd' : '1st' }})</th>
                            @endif
                            <th scope="col">{{ $n['free'] ? ($n['freeSecond'] ? 'From your 3rd group' : 'From your 2nd group') : 'Each group' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Create a group</th>
                            @if ($n['free'])<td>Free</td>@endif
                            <td>{{ $n['groupPrice'] }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Starting balance</th>
                            @if ($n['free'])<td>{{ $n['freeFirst'] }}{{ $n['freeSecond'] ? " / {$n['freeSecond']}" : '' }} Normkredit</td>@endif
                            <td>{{ $n['groupCredits'] }} Normkredit</td>
                        </tr>
                        <tr>
                            <th scope="row">Members who join free</th>
                            @if ($n['free'])<td>{{ $n['freeSlots'] > 0 ? "The first {$n['freeSlots']}" : 'None' }}</td>@endif
                            <td>None</td>
                        </tr>
                        <tr>
                            <th scope="row">Joining</th>
                            @if ($n['free'])<td>{{ $n['freeSlots'] > 0 ? 'Later members contribute once' : 'Contribute once' }}, from {{ $n['packPrice'] }}</td>@endif
                            <td>Contribute once, from {{ $n['packPrice'] }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Top up</th>
                            @if ($n['free'])<td>Any member</td>@endif
                            <td>Any member, up to {{ $n['maxPacks'] }} packages per payment</td>
                        </tr>
                        <tr>
                            <th scope="row">Monthly fee</th>
                            @if ($n['free'])<td>None</td>@endif
                            <td>None</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="table-note">You pay with QRIS.</p>
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
                    @if ($n['free'])
                        <p>Each account gets {{ $n['freeSecond'] ? 'two free groups' : 'one free group' }}. The first comes with {{ $n['freeFirst'] }} Normkredit{{ $n['freeSecond'] ? " and the second with {$n['freeSecond']}" : '' }}{{ $n['freeSlots'] > 0 ? ", and the first {$n['freeSlots']} friends in each one join free" : '' }}. After that, a group costs {{ $n['groupPrice'] }} and starts with {{ $n['groupCredits'] }} Normkredit.</p>
                    @else
                        <p>We don't have a free plan at the moment. A group costs {{ $n['groupPrice'] }} and starts with {{ $n['groupCredits'] }} Normkredit.</p>
                    @endif
                </details>
                <details>
                    <summary>Do I pay each time I use Normi?</summary>
                    <p>You don't. Normi uses your group's shared balance, and you pay when you contribute to join or top up.</p>
                </details>
                <details>
                    <summary>Do new members have to pay?</summary>
                    @if ($n['free'] && $n['freeSlots'] > 0)
                        <p>In a free group, the first {{ $n['freeSlots'] }} members join free. Each member after that makes a one-time contribution before joining, and it goes into the shared balance.</p>
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
