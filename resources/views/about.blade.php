@extends('layouts.site')

@section('title', 'About — Egliane Accounting Services')

@section('content')
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">About us</span>
            <h2>Mission and Vision</h2>
            <p>The principles that guide how we serve every client.</p>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container">
        @php
            $missionText = $about->mission ?? 'To provide reliable, accurate, and transparent accounting services that allow our clients to focus on what they do best, knowing their financial health is in expert hands.';
            $visionText = $about->vision ?? 'To set the standard for excellence in professional accounting services, building a legacy of financial integrity and client prosperity.';
            $valueCount = $coreValues->count();
            $valueSummary = $valueCount > 0
                ? 'Guided by ' . $valueCount . ' core values that define how we work with every client.'
                : 'Guided by core values that define how we work with every client.';
        @endphp
        <div class="about-banner">
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
        <div class="section-head">
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
        @endphp
        <div class="values-grid">
            @foreach ($displayValues as $value)
                @php
                    $iconPath = $valueIcons[$value->label] ?? '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>';
                @endphp
                <div class="value-card">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconPath !!}</svg>
                    </div>
                    <h3>{{ $value->label }}</h3>
                </div>
            @endforeach
        </div>
    </div>
</section>

@if ($certificates->count())
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Legitimacy</span>
            <h2>Certificates &amp; Registrations</h2>
            <p>Our official registrations and professional credentials.</p>
        </div>
        <div class="cert-gallery">
            @foreach ($certificates as $cert)
                <div class="cert-card">
                    @if ($cert->isImage())
                        <a href="{{ route('certificates.file', $cert) }}" target="_blank" class="cert-thumb-wrap">
                            <img src="{{ route('certificates.file', $cert) }}" alt="{{ $cert->label }}" class="cert-thumb">
                        </a>
                    @else
                        <a href="{{ route('certificates.file', $cert) }}" target="_blank" class="cert-thumb-wrap cert-thumb-pdf">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/></svg>
                            <span>View PDF</span>
                        </a>
                    @endif
                    <span class="cert-label">{{ $cert->label }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section section-alt">
    <div class="container">
        <div class="cta-band">
            <h2>Ready to hand over your books?</h2>
            <p>Sign up today and let our team handle your accounting while you focus on your business.</p>
            <div class="hero-cta" style="justify-content:center;">
                <a href="{{ route('register') }}" class="btn btn-sky btn-lg">Get Started</a>
                <a href="https://www.facebook.com/profile.php?id=100063691286931" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">Message us on Messenger</a>
            </div>
        </div>
    </div>
</section>
@endsection
