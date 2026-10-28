@extends('marketing.layout', ['active' => 'home'])

@section('title', 'Normi Chat — Group Chat with AI')
@section('description', 'Bring people and AI together in one group chat. Talk in real time, call on AI when you need it, and keep your conversation history under control with Normi Chat.')

@section('content')
        {{-- Hero --}}
        <section class="hero" id="home">
            <div class="wrap hero-grid">
                <div>
                    <p class="eyebrow">Group chat, with AI built in</p>
                    <h1>More than chatting. A place to <em>think and grow</em> together.</h1>
                    <p class="lead">Normi Chat puts people and AI in one conversation. Trade ideas in real time, work through things with your team, and call on AI the moment you need help. All in a single thread, without hopping between apps.</p>
                    <div class="hero-actions">
                        <a href="{{ $startUrl }}" class="btn btn-primary">Get Started</a>
                        <a href="{{ $base }}/pricing" class="btn btn-ghost">See Pricing</a>
                    </div>
                    <p class="hero-note">One space for your team, your ideas, and your conversations with AI.</p>
                </div>

                <div>
                    <div class="chat" role="img" aria-label="Example group conversation that calls AI with @normi">
                        <div class="chat-bar">
                            <strong>Client Proposal Team</strong>
                            <span>4 members</span>
                        </div>
                        <div class="chat-body">
                            <div class="msg">
                                <small>Rina</small>
                                <p>Have we agreed on the proposal structure yet?</p>
                            </div>
                            <div class="msg">
                                <small>Dimas</small>
                                <p>Not yet. The budget section is still empty, even though we talked it through this morning.</p>
                            </div>
                            <div class="msg me">
                                <small>You</small>
                                <p><span class="tag">@normi</span> summarize the budget points from the discussion above.</p>
                            </div>
                            <div class="msg ai">
                                <small>Normi AI</small>
                                <p>Two decisions from the discussion above haven't been written down yet:
                                <span style="display:block;margin-top:6px">1. Team costs are separate from production costs.<br>2. There's a fallback option if the schedule slips.</span></p>
                            </div>
                        </div>
                        <div class="chat-foot">Write a message, or mention @normi…</div>
                    </div>
                    <p class="chat-caption">Example conversation. AI only replies when you call it.</p>
                </div>
            </div>
        </section>

        {{-- Introduction --}}
        <section class="rule" id="about-intro">
            <div class="wrap intro-grid">
                <div>
                    <p class="eyebrow">Meet Normi Chat</p>
                    <h2 class="h2">Bigger ideas start with the right conversation.</h2>
                </div>
                <div class="intro-copy">
                    <p>Great ideas rarely come from one person alone. They grow through questions, different points of view, discussion, and help that arrives at the right moment.</p>
                    <p>Normi Chat brings all of that into one conversation space. Talk with your team, share your thinking, and bring in AI when you want another perspective.</p>
                    <p class="emph">There's no need to keep discussion and AI help apart, and no need to break the flow of a conversation just to look something up.</p>
                    <p>One space is enough to keep the discussion moving and the ideas growing.</p>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="rule" id="features">
            <div class="wrap">
                <div class="features-head">
                    <p class="eyebrow">Features</p>
                    <h2 class="h2">Everything you need for more meaningful conversations.</h2>
                    <p class="lead">From real-time chat to AI help on demand, Normi Chat gives groups the tools to discuss, collaborate, and manage their conversations with more focus.</p>
                </div>

                <article class="feature">
                    <span class="n">01</span>
                    <h3>AI that helps, not interrupts.<span class="kicker">AI on demand</span></h3>
                    <div>
                        <p>AI should offer help, not take over the conversation. In Normi Chat, AI responds when you call it with the <strong>@normi</strong> mention.</p>
                        <p>Ask a question, explore an idea, or request help without worrying that AI will cut in on every message your members send. You decide when AI joins the discussion.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">02</span>
                    <h3>Everyone stays connected in one conversation.<span class="kicker">Real-time chat</span></h3>
                    <div>
                        <p>Send messages and follow the discussion as it happens. Questions, replies, and AI answers all live in the same space, so the context is easy to follow.</p>
                        <p>No need to split the conversation across different places.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">03</span>
                    <h3>Your group, your rules.<span class="kicker">Flexible group control</span></h3>
                    <div>
                        <p>Create a group, invite members, and manage access the way you need. With group access settings and user permissions, you decide who can take part in the conversation.</p>
                        <p>A good collaboration space starts with clear management.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">04</span>
                    <h3>Important conversations deserve to be kept.<span class="kicker">Manageable history</span></h3>
                    <div>
                        <p>Save and manage your conversation history with backup and restore. When you need documentation, export a conversation to PDF or DOCX so it's easier to read and reference.</p>
                        <p>Important information shouldn't stop being useful when the conversation ends.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">05</span>
                    <h3>Your conversations, wherever you are.<span class="kicker">Built for mobile</span></h3>
                    <div>
                        <p>Normi Chat takes a mobile-first approach, so it's comfortable to use in a phone browser. Compact navigation and practical interactions keep you connected without needing to sit at a computer.</p>
                        <p>Open it, talk it through, and pick your idea back up whenever you need to.</p>
                    </div>
                </article>
            </div>
        </section>

        {{-- How it works --}}
        <section class="rule" id="how-it-works">
            <div class="wrap">
                <p class="eyebrow">Start collaborating</p>
                <h2 class="h2">Three steps to a more productive conversation.</h2>
                <p class="lead" style="margin-top:20px">Start simple. Bring your group together, then involve AI when it's needed.</p>

                <div class="steps">
                    <div class="step">
                        <span class="n">01</span>
                        <h3>Create a conversation space</h3>
                        <p>Create a new group, set its access settings, and choose the AI model you want the group to use.</p>
                    </div>
                    <div class="step">
                        <span class="n">02</span>
                        <h3>Invite your members</h3>
                        <p>Share the Share ID with your members and manage their access so the conversation stays in the right space.</p>
                    </div>
                    <div class="step">
                        <span class="n">03</span>
                        <h3>Start the discussion</h3>
                        <p>Send messages in real time, trade ideas, and call AI with a mention whenever you need more information or another point of view.</p>
                    </div>
                </div>

                <div class="section-cta">
                    <a href="{{ $startUrl }}" class="btn btn-primary">Create Your Group</a>
                </div>
            </div>
        </section>

        {{-- Value --}}
        <section class="value" id="value">
            <div class="wrap value-grid">
                <h2>AI doesn't have to take over the conversation to make a difference.</h2>
                <div class="value-copy">
                    <p>Good collaboration happens when everyone has the chance to think, ask, and build ideas together.</p>
                    <p>Normi Chat makes AI part of that process without making it the center of every interaction.</p>
                    <p class="strong">People stay at the heart of the conversation. AI is there to help when it's needed.</p>
                    <div><a href="{{ $base }}/about" class="btn btn-dark">Get to Know Normi Chat</a></div>
                </div>
            </div>
        </section>

        {{-- Closing --}}
        <section class="rule closing">
            <div class="wrap">
                <h2>Let's start a better conversation.</h2>
                <p class="lead">Bring your group together in one space, involve AI when it's needed, and give every idea room to grow.</p>
                <div class="hero-actions">
                    <a href="{{ $startUrl }}" class="btn btn-primary">Get Started</a>
                    <a href="{{ $base }}/pricing" class="btn btn-ghost">See Pricing</a>
                </div>
            </div>
        </section>
@endsection
