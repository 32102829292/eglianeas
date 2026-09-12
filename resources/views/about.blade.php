@extends('layouts.site')

@section('title', 'About — Egliane Accounting Services')

@section('content')
{{-- ============================================================
     HERO
     ============================================================ --}}
<section class="hero about-hero" id="about-top">
    <div class="container">
        <div class="about-hero-inner lp-reveal">
            <span class="hero-eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                About Egliane
            </span>
            <h1>Reliable Accounting <span class="accent">Support</span> for Your Business</h1>
            <p class="hero-sub">Egliane Accounting Services helps small businesses stay compliant and organized with dependable bookkeeping, BIR-compliant tax filing, payroll, and financial reporting — all backed by a secure client portal.</p>
            <div class="hero-cta hero-cta--center">
                <a href="{{ route('register') }}" class="btn btn-sky btn-lg">Get Started</a>
                <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener" class="btn btn-outline btn-lg">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<span class="about-rule" aria-hidden="true"></span>

{{-- ============================================================
     COMPANY INTRODUCTION
     ============================================================ --}}
<section class="section about-intro">
    <div class="container">
        <div class="about-grid">
            <div class="lp-reveal">
                <div class="section-head section-head--left">
                    <span class="eyebrow">Who we are</span>
                    <h2>An accounting team built for your business</h2>
                </div>
                <p class="muted">Egliane Accounting Services has been helping small businesses stay compliant and organized since 2017. From day-to-day bookkeeping to full compliance, we handle the accounting work so you can focus on running your business.</p>
                <ul class="about-list">
                    <li><b>Organized financial records</b> — every income, expense, and receipt recorded and categorized.</li>
                    <li><b>BIR-compliant tax filing</b> — deadlines monitored so you stay prepared.</li>
                    <li><b>Payroll support</b> — computations, deductions, and remittances handled on schedule.</li>
                    <li><b>Secure client portal</b> — view filings, statements, and documents anytime, anywhere.</li>
                </ul>
                <a href="{{ route('register') }}" class="btn btn-sky">Get Started</a>
            </div>

            <div class="lp-reveal">
                <div class="hero-card about-snapshot">
                    <div class="hero-card-head">
                        <span class="dot" aria-hidden="true"></span>
                        <b class="about-snapshot-title">Egliane at a glance</b>
                    </div>
                    <div class="hero-card-row">
                        <span class="label">Established</span>
                        <span class="val">Since 2017</span>
                    </div>
                    <div class="hero-card-row">
                        <span class="label">Record access</span>
                        <span class="val">24/7 portal</span>
                    </div>
                    <div class="hero-card-row">
                        <span class="label">Compliance</span>
                        <span class="val">BIR-compliant</span>
                    </div>
                    <div class="hero-card-row">
                        <span class="label">Sign-in security</span>
                        <span class="val">6-digit PIN</span>
                    </div>
                    <div class="about-stats">
                        <div class="stat-box"><b>2017</b><span>Serving clients since</span></div>
                        <div class="stat-box"><b>24/7</b><span>Access your records</span></div>
                        <div class="stat-box"><b>100%</b><span>BIR-compliant</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section--no-pad-top">
    <div class="container">
        @php
            $missionText = $about->mission ?? 'To provide reliable, accurate, and transparent accounting services that allow our clients to focus on what they do best, knowing their financial health is in expert hands.';
            $visionText = $about->vision ?? 'To set the standard for excellence in professional accounting services, building a legacy of financial integrity and client prosperity.';
            $valueCount = $coreValues->count();
            $valueSummary = $valueCount > 0
                ? 'Guided by ' . $valueCount . ' core values that define how we work with every client.'
                : 'Guided by core values that define how we work with every client.';
        @endphp
        <div class="section-head lp-reveal">
            <span class="eyebrow">Mission and Vision</span>
            <h2>What drives our work every day</h2>
            <p>The principles that guide how we serve every client.</p>
        </div>
        <div class="about-banner lp-reveal">
            <div class="about-banner-col">
                <div class="about-banner-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3>Company Mission</h3>
                <div class="about-banner-rule"></div>
                <p>{{ $missionText }}</p>
            </div>
            <div class="about-banner-divider"></div>
            <div class="about-banner-col">
                <div class="about-banner-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                </div>
                <h3>Company Values</h3>
                <div class="about-banner-rule"></div>
                <p>{{ $valueSummary }}</p>
            </div>
            <div class="about-banner-divider"></div>
            <div class="about-banner-col">
                <div class="about-banner-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                </div>
                <h3>Our Vision</h3>
                <div class="about-banner-rule"></div>
                <p>{{ $visionText }}</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">What we stand for</span>
            <h2>Core Values</h2>
        </div>
        @php
            $displayValues = $coreValues->isNotEmpty() ? $coreValues : collect([
                (object)['label' => 'Integrity'],
                (object)['label' => 'Precision'],
                (object)['label' => 'Accessibility'],
                (object)['label' => 'Professional Excellence'],
                (object)['label' => 'Accountability & Stewardship'],
                (object)['label' => 'Continuous Improvement & Competence'],
                (object)['label' => 'Objectivity & Professional Independence'],
            ]);

            $valueIcons = [
                'Integrity' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
                'Precision' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
                'Accessibility' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'Professional Excellence' => '<path d="M12 15l-2 5 2-1 2 1-2-5z"/><circle cx="12" cy="8" r="6"/><path d="M9 8l2 2 4-4"/>',
                'Accountability & Stewardship' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/><path d="M9 9h6"/>',
                'Continuous Improvement & Competence' => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
                'Objectivity & Professional Independence' => '<path d="M12 3v18"/><path d="M1 6l5 6-5 6"/><path d="M23 6l-5 6 5 6"/><circle cx="8" cy="6" r="0"/><circle cx="16" cy="6" r="0"/>',
            ];

            $valueDescriptions = [
                'Integrity' => 'Honest, transparent handling of your financial records, built on a foundation of financial integrity.',
                'Precision' => 'Clean and accurate books and filings, checked thoroughly before every deadline.',
                'Accessibility' => 'Your filings, statements, and documents available anytime in your secure client portal.',
                'Professional Excellence' => 'Professional accounting service delivered with care from experienced accountants.',
                'Accountability & Stewardship' => 'We look after your financial records with the same care as our own.',
                'Continuous Improvement & Competence' => 'We keep up with rules and tools so your books stay accurate and compliant.',
                'Objectivity & Professional Independence' => 'Independent, unbiased accounting work and advice you can rely on.',
            ];
        @endphp
        <div class="values-grid">
            @foreach ($displayValues as $value)
                @php
                    $iconPath = $valueIcons[$value->label] ?? '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>';
                    $desc = $valueDescriptions[$value->label] ?? null;
                @endphp
                <div class="value-card lp-reveal">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconPath !!}</svg>
                    </div>
                    <h3>{{ $value->label }}</h3>
                    @if ($desc)
                        <p>{{ $desc }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

@if ($certificates->count())
<section class="section">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">Legitimacy</span>
            <h2>Certificates &amp; Registrations</h2>
            <p>Our official registrations and professional credentials.</p>
        </div>
        <div class="cert-gallery">
            @foreach ($certificates as $cert)
                <div class="cert-card">
                    @if ($cert->isImage())
                        <div class="cert-thumb-wrap cert-lightbox-trigger" data-cert-url="{{ route('certificates.file', $cert) }}" data-cert-label="{{ $cert->label }}" data-cert-type="image" role="button" tabindex="0">
                            <img src="{{ route('certificates.file', $cert) }}" alt="{{ $cert->label }}" class="cert-thumb">
                        </div>
                    @else
                        <div class="cert-thumb-wrap cert-thumb-pdf cert-lightbox-trigger" data-cert-url="{{ route('certificates.file', $cert) }}" data-cert-label="{{ $cert->label }}" data-cert-type="pdf" role="button" tabindex="0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/></svg>
                            <span>View PDF</span>
                        </div>
                    @endif
                    <span class="cert-label">{{ $cert->label }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

<div id="certLightbox" class="cert-lightbox" role="dialog" aria-modal="true" aria-label="Certificate viewer" hidden>
    <div class="cert-lightbox-backdrop"></div>
    <div class="cert-lightbox-content">
        <button class="cert-lightbox-close" aria-label="Close">&times;</button>
        <div class="cert-lightbox-body"></div>
        <div class="cert-lightbox-caption"></div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('certLightbox');
    if (!modal) return;
    var backdrop = modal.querySelector('.cert-lightbox-backdrop');
    var body = modal.querySelector('.cert-lightbox-body');
    var caption = modal.querySelector('.cert-lightbox-caption');
    var closeBtn = modal.querySelector('.cert-lightbox-close');

    function openLightbox(url, label, type) {
        body.innerHTML = '';
        if (type === 'pdf') {
            body.innerHTML = '<iframe src="' + url + '" class="cert-lightbox-embed" title="' + label + '"></iframe>';
        } else {
            var img = document.createElement('img');
            img.src = url;
            img.alt = label;
            img.className = 'cert-lightbox-img';
            body.appendChild(img);
        }
        caption.textContent = label || '';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () { modal.classList.add('open'); });
    }

    function closeLightbox() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(function () {
            modal.hidden = true;
            body.innerHTML = '';
        }, 250);
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.cert-lightbox-trigger');
        if (trigger) {
            e.preventDefault();
            openLightbox(trigger.dataset.certUrl, trigger.dataset.certLabel, trigger.dataset.certType);
            return;
        }
        if (e.target === backdrop || e.target === closeBtn) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeLightbox();
        }
    });

    document.querySelectorAll('.cert-lightbox-trigger').forEach(function (el) {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                el.click();
            }
        });
    });
})();
</script>
@endpush
@endif

<section class="section section-alt">
    <div class="container">
        <div class="section-head lp-reveal">
            <span class="eyebrow">People</span>
            <h2>Meet Our Team</h2>
            <p>The people behind Egliane's reliable accounting services.</p>
        </div>
        <div class="team-photo lp-reveal">
            <img src="{{ asset('images/teams.jfif') }}" alt="Egliane Accounting Services team" width="1024" height="768">
        </div>
        <p class="team-photo-caption">The Egliane team — accountants, specialists, and support staff working together for every client.</p>
        @php
            $teamByRank = $teamMembers->groupBy('rank');
            $rankOrder = ['Managerial', 'Supervisory', 'Supervisory / Specialist', 'Technical Specialist / Advisory', 'Rank and File / Analyst', 'Rank and File / Specialist', 'Rank and File'];
        @endphp
        <div class="team-grid">
            @foreach ($rankOrder as $rank)
                @if ($teamByRank->has($rank))
                    @foreach ($teamByRank[$rank] as $member)
                        <div class="team-card">
                            <div class="team-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <h3 class="team-name">{{ $member->name }}</h3>
                            <span class="team-position">{{ $member->position }}</span>
                            @if ($member->reports_to)
                                <span class="team-reports-to">Reports to: {{ $member->reports_to }}</span>
                            @endif
                            <p class="team-duties">{{ $member->duties }}</p>
                            @if ($member->supervises)
                                <div class="team-supervises">
                                    <span class="team-supervises-label">Supervises</span>
                                    <p>{{ $member->supervises }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endforeach
            @foreach ($teamByRank as $rank => $members)
                @if (!in_array($rank, $rankOrder))
                    @foreach ($members as $member)
                        <div class="team-card">
                            <div class="team-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <h3 class="team-name">{{ $member->name }}</h3>
                            <span class="team-position">{{ $member->position }}</span>
                            @if ($member->reports_to)
                                <span class="team-reports-to">Reports to: {{ $member->reports_to }}</span>
                            @endif
                            <p class="team-duties">{{ $member->duties }}</p>
                            @if ($member->supervises)
                                <div class="team-supervises">
                                    <span class="team-supervises-label">Supervises</span>
                                    <p>{{ $member->supervises }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta-band lp-reveal">
            <h2>Ready to hand over your books?</h2>
            <p>Sign up today and let our team handle your accounting while you focus on your business.</p>
            <div class="hero-cta hero-cta--center">
                <a href="{{ route('register') }}" class="btn btn-sky btn-lg">Get Started</a>
                <a href="{{ config('contact.messenger_url') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">Contact Us</a>
            </div>
        </div>
    </div>
</section>
@endsection
