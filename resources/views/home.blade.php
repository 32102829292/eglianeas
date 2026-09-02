@extends('layouts.site')

@section('title', 'Egliane Accounting Services — Bookkeeping, Tax & Payroll')

@section('content')
<section class="landing" id="top">
    <div class="landing-inner">
        <a href="#top" class="landing-logo" aria-label="Egliane Accounting Services">
            <img src="/images/logo-icon.png" alt="Egliane logo">
        </a>
        <h1 class="landing-title">Egliane Accounting<br><span>Services</span></h1>
        <p class="landing-tagline">Bookkeeping &middot; Tax &middot; Payroll for small businesses.</p>

        <div class="landing-section">
            <div class="landing-label">Posted Announcements</div>
            @if ($announcements->count())
                <div class="feed" role="feed">
                    @foreach ($announcements as $announcement)
                        <article class="feed-card">
                            <div class="feed-avatar" aria-hidden="true">
                                <img src="/pwa-icons/icon-32.png" alt="">
                            </div>
                            <div class="feed-body">
                                <div class="feed-meta">
                                    <b>{{ $announcement->poster?->name ?? 'Egliane Admin' }}</b>
                                    <time title="{{ $announcement->posted_at?->format('M j, Y g:i A') }}">{{ $announcement->posted_at?->diffForHumans() }}</time>
                                </div>
                                @if ($announcement->title)
                                    <h3>{{ $announcement->title }}</h3>
                                @endif
                                @if ($announcement->hasImage())
                                    <div style="margin: 10px 0;">
                                        <img src="{{ $announcement->imageUrl() }}" alt="Announcement image" style="max-width: 100%; border-radius: 8px; border: 1px solid #e5e7eb;">
                                    </div>
                                @endif
                                <p>{{ $announcement->body }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="feed-empty">No announcements yet. Check back soon.</p>
            @endif
        </div>

        <div class="landing-section">
            <div class="landing-label">Log In</div>
            <div class="login-card">
                @auth
                    <p class="muted" style="margin-bottom:14px;">You&rsquo;re signed in as <b>{{ auth()->user()->name }}</b>.</p>
                    <a href="{{ auth()->user()->getDashboardRoute() }}" class="btn btn-primary btn-block">Go to Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline-form mt-2" onsubmit="return egliane.confirm.form(this, { title: 'Log out?', message: 'You&rsquo;ll need to log in again to access your account.', confirmLabel: 'Log out' });">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-block">Log out</button>
                    </form>
                @else
                    @include('auth.partials.login-form')
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="section" id="services">
    <div class="container">
        <div class="section-head">
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
            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconPath !!}</svg>
                </div>
                <h3>{{ $title }}</h3>
                <p>{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-alt" id="about">
    <div class="container">
        <div class="about-grid">
            <div>
                <div class="section-head" style="text-align:left;margin-bottom:18px;">
                    <span class="eyebrow">Why Egliane</span>
                    <h2>An accounting firm built for small businesses</h2>
                </div>
                <p class="muted">Egliane Accounting Services has been helping small businesses stay compliant and
                    organized since 2017. Now you can access everything — your transactions, filings, and documents —
                    right from your phone.</p>
                <ul class="about-list">
                    <li><b>Track your money</b> — every transaction recorded and categorized.</li>
                    <li><b>Never miss a deadline</b> — see filing status and due dates instantly.</li>
                    <li><b>Upload documents</b> — receipts and forms, even while offline.</li>
                    <li><b>Quick login</b> — use your PIN or face recognition.</li>
                </ul>
                <a href="{{ route('register') }}" class="btn btn-sky">Create your free account</a>
            </div>
            <div>
                <div class="about-stats">
                    <div class="stat-box"><b>2017</b><span>Serving clients since</span></div>
                    <div class="stat-box"><b>100%</b><span>BIR-compliant</span></div>
                    <div class="stat-box"><b>24/7</b><span>Access your records</span></div>
                </div>
                <div class="card mt-3" style="margin-bottom:0;">
                    <h3 class="card-title">Get started in 3 steps</h3>
                    <ol class="detail-list">
                        <li><span class="k">1</span><span class="v" style="font-weight:400;">Sign up with your Gmail</span></li>
                        <li><span class="k">2</span><span class="v" style="font-weight:400;">Verify with a 6-digit code</span></li>
                        <li><span class="k">3</span><span class="v" style="font-weight:400;">Upload documents &amp; follow your filings</span></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="contact">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Get in touch</span>
            <h2>Have questions? Talk to us.</h2>
            <p>Reach out on Messenger or email — we usually reply within the day.</p>
        </div>
        <div class="cta-band">
            <h2>Ready to hand over your books?</h2>
            <p>Sign up today and start uploading your receipts. Our accountants will take it from there.</p>
            <div class="hero-cta" style="justify-content:center;">
                <a href="{{ route('register') }}" class="btn btn-sky btn-lg">Get Started</a>
                <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">Message us on Messenger</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="/js/auth.js" defer></script>
@endpush
