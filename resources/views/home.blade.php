@extends('layouts.site')

@section('title', 'Egliane Accounting Services — Bookkeeping, Tax & Payroll')

@section('content')
{{-- ============================================================
     LATEST ANNOUNCEMENTS — first content block, right below navbar
     ============================================================ --}}
<section class="section section--announcements-top" id="announcements">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">Updates</span>
            <h2>Latest Announcements</h2>
            <p>Stay informed with the latest updates from Egliane Accounting Services.</p>
        </div>

        @if ($announcements->count())
            @php
                $featured = $announcements->first();
                $more = $announcements->slice(1);
            @endphp

            {{-- Featured / latest announcement --}}
            @php
                $fAuthor = $featured->poster?->name ?? 'Egliane Admin';
                $fInitials = collect(preg_split('/\s+/', trim($fAuthor) ?: 'Egliane Admin'))
                    ->filter()->take(2)
                    ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->implode('');
            @endphp
            <article class="lp-ann-featured lp-reveal">
                <div class="lp-ann-featured-inner">
                    <div class="lp-ann-featured-main">
                        <span class="lp-ann-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v3L9 15v3H6l-3-2V8.5z"/><path d="m15 13 3 3 5-5"/></svg>
                            Latest update
                        </span>
                        <div class="lp-ann-head">
                            <span class="avatar avatar-tint lp-ann-avatar" aria-hidden="true">{{ $fInitials }}</span>
                            <span class="lp-ann-author">
                                <b>{{ $fAuthor }}</b>
                                <time datetime="{{ $featured->posted_at?->toIso8601String() }}" title="{{ $featured->posted_at?->format('M j, Y g:i A') }}">
                                    {{ $featured->posted_at?->diffForHumans() }}
                                </time>
                            </span>
                        </div>
                        @if ($featured->title)
                            <h3 class="lp-ann-title">{{ $featured->title }}</h3>
                        @endif
                        @if ($featured->body)
                            <p class="lp-ann-body">{{ $featured->body }}</p>
                        @endif
                    </div>
                    @if ($featured->hasImage())
                        <div class="lp-ann-featured-thumb" data-ann-thumb>
                            <img src="{{ route('announcements.image', [$featured], false) }}" alt="Image attached to this announcement" loading="lazy"
                                 onload="this.parentElement.classList.add('lp-ann-featured-thumb--loaded')"
                                 onerror="this.remove(); this.parentElement.classList.add('lp-ann-featured-thumb--failed');">
                        </div>
                    @endif
                </div>
            </article>

            {{-- Recent announcements --}}
            @if ($more->count())
                <div class="lp-ann-grid" role="complementary" aria-label="Recent announcements">
                    @foreach ($more as $announcement)
                        @php
                            $annAuthor = $announcement->poster?->name ?? 'Egliane Admin';
                            $annInitials = collect(preg_split('/\s+/', trim($annAuthor) ?: 'Egliane Admin'))
                                ->filter()->take(2)
                                ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->implode('');
                        @endphp
                        <article class="lp-ann-card lp-reveal">
                            <div class="lp-ann-head">
                                <span class="avatar avatar-tint lp-ann-avatar" aria-hidden="true">{{ $annInitials }}</span>
                                <span class="lp-ann-author">
                                    <b>{{ $annAuthor }}</b>
                                    <time datetime="{{ $announcement->posted_at?->toIso8601String() }}" title="{{ $announcement->posted_at?->format('M j, Y g:i A') }}">
                                        {{ $announcement->posted_at?->diffForHumans() }}
                                    </time>
                                </span>
                            </div>
                            @if ($announcement->title)
                                <h3 class="lp-ann-title">{{ $announcement->title }}</h3>
                            @endif
                            @if ($announcement->hasImage())
                                <div class="lp-ann-thumb" data-ann-thumb>
                                    <img src="{{ route('announcements.image', [$announcement], false) }}" alt="Image attached to this announcement" loading="lazy"
                                         onload="this.parentElement.classList.add('lp-ann-thumb--loaded')"
                                         onerror="this.remove(); this.parentElement.classList.add('lp-ann-thumb--failed');">
                                </div>
                            @endif
                            @if ($announcement->body)
                                <p class="lp-ann-body">{{ $announcement->body }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        @else
            <div class="empty-state lp-reveal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <h3>No announcements yet</h3>
                <p>Check back soon for news and reminders from the Egliane team.</p>
            </div>
        @endif
    </div>
</section>

{{-- ============================================================
     HERO
     ============================================================ --}}
<section class="hero lp-hero" id="top">
    <div class="container">
        <div class="hero-grid">
            <div class="lp-reveal">
                <span class="hero-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Serving small businesses since 2017
                </span>
                <h1>Egliane <span class="accent">Accounting Services</span></h1>
                <p class="hero-sub">Reliable bookkeeping, tax, payroll, and accounting support for your business.</p>
                <div class="hero-cta">
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-outline btn-lg">Get Started</a>
                    <a href="{{ route('home') }}#services" class="hero-scroll-link">Explore services <span aria-hidden="true">&darr;</span></a>
                </div>
                <ul class="hero-points" aria-label="Highlights">
                    <li>Bookkeeping &amp; financial reporting</li>
                    <li>BIR-compliant tax filing</li>
                    <li>Payroll support</li>
                </ul>
            </div>

            <div class="hero-visual lp-hero-visual lp-reveal" aria-hidden="true">
                <div class="lp-hero-blob lp-hero-blob--1"></div>
                <div class="lp-hero-blob lp-hero-blob--2"></div>

                <div class="lp-hero-card lp-hero-card--main">
                    <div class="lp-hero-card-head">
                        <span class="lp-hero-card-dots"><i></i><i></i><i></i></span>
                        <span class="lp-hero-card-pill">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                            Accounting dashboard
                        </span>
                    </div>

                    <div class="lp-hero-card-list">
                        <div class="lp-hero-line">
                            <span class="lp-hero-line-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="lp-hero-line-txt"><b>Books reconciled</b><small>Income, expenses &amp; receipts organized</small></span>
                            <span class="lp-hero-tag">On track</span>
                        </div>
                        <div class="lp-hero-line">
                            <span class="lp-hero-line-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="lp-hero-line-txt"><b>BIR filings tracked</b><small>Deadlines monitored for you</small></span>
                            <span class="lp-hero-tag">Compliant</span>
                        </div>
                        <div class="lp-hero-line">
                            <span class="lp-hero-line-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="lp-hero-line-txt"><b>Payroll support</b><small>Computations &amp; remittances handled</small></span>
                            <span class="lp-hero-tag">Ready</span>
                        </div>
                    </div>

                    <div class="lp-hero-chart" role="img" aria-label="Quarterly filing progress chart">
                        <span class="lp-hero-chart-bar" style="height: 38%"></span>
                        <span class="lp-hero-chart-bar" style="height: 52%"></span>
                        <span class="lp-hero-chart-bar" style="height: 46%"></span>
                        <span class="lp-hero-chart-bar" style="height: 64%"></span>
                        <span class="lp-hero-chart-bar" style="height: 72%"></span>
                        <span class="lp-hero-chart-bar lp-hero-chart-bar--accent" style="height: 86%"></span>
                    </div>

                    <div class="lp-hero-card-foot">
                        <span>Quarterly filings</span>
                        <span class="lp-hero-dots"><i class="on"></i><i class="on"></i><i class="on"></i><i class="on"></i>&nbsp;up to date</span>
                    </div>
                </div>

                <div class="lp-hero-notes">
                    <div class="lp-hero-note">
                        <span class="lp-hero-note-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="lp-hero-note-txt"><b>Tax deadlines</b><small>Never miss a filing date</small></span>
                    </div>
                    <div class="lp-hero-note">
                        <span class="lp-hero-note-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h.01M11 9h.01M15 9h.01M7 13h10"/></svg>
                        </span>
                        <span class="lp-hero-note-txt"><b>Client portal</b><small>Your records, anywhere</small></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     SECURE PORTAL / LOGIN + PIN
     ============================================================ --}}
<section class="section section-alt" id="portal">
    <div class="container">
        <div class="lp-portal-grid">
            <div class="lp-reveal">
                <span class="eyebrow">Secure portal access</span>
                <h2>Access your Egliane account securely</h2>
                <p>Your secure portal gives you instant access to:</p>
                <ul class="lp-portal-checks">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        View billing statements
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Track payments
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        View your documents
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Manage your account
                    </li>
                </ul>
            </div>
            <div class="lp-reveal">
                <div class="lp-portal-card">
                    <span class="lp-portal-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <h3>Client Portal Login</h3>
                    <p>Log in to your secure Egliane account.</p>
                    <a href="{{ route('login') }}" class="btn btn-sky btn-lg btn-block">Login to Portal</a>
                    <span class="lp-portal-pin">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Secure PIN authentication
                    </span>
                    <small>Your account is protected with an additional PIN verification step.</small>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     SERVICES
     ============================================================ --}}
<section class="section" id="services">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">What we do</span>
            <h2>Accounting services that keep you compliant</h2>
            <p>From daily bookkeeping to full compliance, we handle the paperwork so you can run your business.</p>
        </div>
        <div class="services-grid">
            @foreach ([
                ['b', 'Bookkeeping', 'Accurate recording of your income, expenses, and receipts — updated regularly and easy to review anytime, anywhere.', '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
                ['c', 'Tax Filing & BIR Compliance', 'Income tax, VAT, percentage tax and more — filed accurately and before every deadline.', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/>'],
                ['d', 'Financial Statements', 'Clear, audit-ready financial statements and reports that show exactly where your business stands.', '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
                ['e', 'Payroll', 'Payroll computation, deductions, and remittances (SSS, PhilHealth, Pag-IBIG) handled on schedule.', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['f', 'Business Registration', 'Assistance with DTI, SEC, BIR, and permit requirements when you are starting or growing your business.', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                ['g', 'Consulting', 'Practical advice on cash flow, pricing, and tax strategy — straight from experienced accountants.', '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>'],
            ] as [$iconId, $title, $desc, $iconPath])
            <div class="service-card lp-reveal">
                <span class="service-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconPath !!}</svg>
                </span>
                <h3>{{ $title }}</h3>
                <p>{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================================================
     WHY EGLIANE / ABOUT
     ============================================================ --}}
<section class="section section-alt" id="about">
    <div class="container">
        <div class="about-grid">
            <div class="lp-reveal">
                <div class="section-head section-head--left">
                    <span class="eyebrow">Why Egliane</span>
                    <h2>An accounting firm built for small businesses</h2>
                </div>
                <p class="muted">Egliane Accounting Services has been helping small businesses stay compliant and
                    organized since 2017. Now you can access everything — your transactions, filings, and documents —
                    right from your phone.</p>
                <ul class="about-list">
                    <li><b>Organized financial records</b> — every transaction recorded and categorized.</li>
                    <li><b>Accessible client portal</b> — see filing status and due dates instantly.</li>
                    <li><b>Secure document sharing</b> — receipts and forms, uploaded straight to your accountant.</li>
                    <li><b>Easy billing &amp; payment tracking</b> — view statements and payment status at a glance.</li>
                </ul>
                <a href="{{ route('register') }}" class="btn btn-sky">Create your free account</a>
            </div>
            <div class="lp-reveal">
                <div class="card mt-3 mb-0">
                    <h3 class="card-title">Get started in 3 steps</h3>
                    <ol class="detail-list">
                        <li><span class="k">1</span><span class="v">Sign up with your Gmail</span></li>
                        <li><span class="k">2</span><span class="v">Verify with a 6-digit code</span></li>
                        <li><span class="k">3</span><span class="v">Upload documents &amp; follow your filings</span></li>
                    </ol>
                </div>
                <div class="about-stats">
                    <div class="stat-box"><b>2017</b><span>Serving clients since</span></div>
                    <div class="stat-box"><b>24/7</b><span>Access your records</span></div>
                    <div class="stat-box"><b>100%</b><span>BIR-compliant</span></div>
                </div>
            </div>
        </div>

        <div class="about-values lp-reveal">
            <div class="section-head">
                <span class="eyebrow">What you get</span>
                <h2>Everything your books need, under one roof</h2>
            </div>
            <div class="values-grid">
                <div class="value-card lp-reveal">
                    <span class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>
                    </span>
                    <h3>Accurate Financial Records</h3>
                    <p>Keep your books organized and up to date.</p>
                </div>
                <div class="value-card lp-reveal">
                    <span class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <h3>Tax &amp; BIR Compliance</h3>
                    <p>Stay prepared and compliant with your filing requirements.</p>
                </div>
                <div class="value-card lp-reveal">
                    <span class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <h3>Payroll Support</h3>
                    <p>Manage payroll information accurately and efficiently.</p>
                </div>
                <div class="value-card lp-reveal">
                    <span class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="m18 9-4-4-5 5-3-3"/></svg>
                    </span>
                    <h3>Business Insights</h3>
                    <p>Get clearer visibility into your business finances.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================================================
     CONTACT
     ============================================================ --}}
<section class="section" id="contact">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">Get in touch</span>
            <h2>Have questions? Talk to us.</h2>
            <p>Reach out on Messenger or email — we usually reply within the day.</p>
        </div>
        <div class="lp-contact-grid">
            <a href="tel:+639765841391" class="lp-contact-card lp-reveal">
                <span class="lp-contact-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </span>
                <b>Call us</b>
                <span>0976 584 1391</span>
            </a>
            <a href="mailto:eglianeas2017@gmail.com" class="lp-contact-card lp-reveal">
                <span class="lp-contact-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <b>Email us</b>
                <span>eglianeas2017@gmail.com</span>
            </a>
            <a href="{{ config('contact.facebook_url') }}" target="_blank" rel="noopener noreferrer" class="lp-contact-card lp-reveal">
                <span class="lp-contact-ico">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M24 12.073C24 5.414 18.627.036 12 .036S0 5.414 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.026 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.931-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                </span>
                <b>Facebook</b>
                <span>Egliane Accounting Service</span>
            </a>
            <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener" class="lp-contact-card lp-reveal">
                <span class="lp-contact-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <b>Messenger</b>
                <span>Message us anytime</span>
            </a>
        </div>
    </div>
</section>
@endsection