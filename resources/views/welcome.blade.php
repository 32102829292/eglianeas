@extends('layouts.site')

@section('title', config('app.name', 'Egliane Accounting Services') . ' — Bookkeeping, Tax & Payroll')

@section('content')
<section class="landing" id="top">
    <div class="landing-inner">
        <a href="#top" class="landing-logo" aria-label="Egliane Accounting Services">
            <img src="/images/logo-icon.png" alt="Egliane logo">
        </a>
        <h1 class="landing-title">Egliane Accounting<br><span>Services</span></h1>
        <p class="landing-tagline">Bookkeeping &middot; Tax &middot; Payroll for small businesses.</p>

        <div class="landing-section">
            <div class="landing-label">The client portal</div>
            <div class="login-card">
                @auth
                    <p class="muted mb-3">You&rsquo;re signed in as <b>{{ auth()->user()->name }}</b>.</p>
                    <a href="{{ auth()->user()->getDashboardRoute() }}" class="btn btn-primary btn-block">Go to Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-block">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"><small>No account yet? Sign up here.</small></a>
                    @endif
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
                ['b', 'Bookkeeping', 'Accurate recording of your income, expenses, and receipts.', '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
                ['c', 'Tax Filing & BIR Compliance', 'Income tax, VAT, and percentage tax filed before every deadline.', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/>'],
                ['d', 'Financial Statements', 'Clear, audit-ready reports that show where your business stands.', '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
                ['e', 'Payroll', 'Computations, deductions, and remittances handled on schedule.', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['f', 'Business Registration', 'Assistance with DTI, SEC, BIR, and permits when starting out.', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                ['g', 'Consulting', 'Practical advice on cash flow, pricing, and tax strategy.', '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>'],
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
                <div class="section-head section-head--left">
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
                <div class="card mt-3 mb-0">
                    <h3 class="card-title">Get started in 3 steps</h3>
                    <ol class="detail-list">
                        <li><span class="k">1</span><span class="v">Sign up with your Gmail</span></li>
                        <li><span class="k">2</span><span class="v">Verify with a 6-digit code</span></li>
                        <li><span class="k">3</span><span class="v">Upload documents &amp; follow your filings</span></li>
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
            <div class="hero-cta hero-cta--center">
                <a href="{{ route('register') }}" class="btn btn-sky btn-lg">Get Started</a>
                <a href="{{ config('contact.facebook_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-lg" aria-label="Visit our Facebook Page">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" class="inline-social"><path d="M24 12.073C24 5.414 18.627.036 12 .036S0 5.414 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.026 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.931-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                    Facebook Page
                </a>
                <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">Message us on Messenger</a>
            </div>
        </div>
    </div>
</section>
@endsection