@extends('layouts.site')

@section('title', 'About — Egliane Accounting Services')

@section('content')
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">About us</span>
            <h2>Mission, Vision &amp; Core Values</h2>
            <p>The principles that guide how we serve every client.</p>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container" style="max-width:760px;">
        <div class="card" style="margin-bottom:24px;">
            <h3 class="card-title" style="color:var(--sky-deep);">Our Mission</h3>
            <p style="font-size:15px; line-height:1.7; margin-bottom:0;">{{ $about->mission ?? 'To empower small businesses with reliable, accurate, and accessible accounting services that ensure full compliance and peace of mind, so entrepreneurs can focus on what matters most — growing their business.' }}</p>
        </div>
        <div class="card">
            <h3 class="card-title" style="color:var(--sky-deep);">Our Vision</h3>
            <p style="font-size:15px; line-height:1.7; margin-bottom:0;">{{ $about->vision ?? 'To be the most trusted partner for small business accounting in the Philippines, known for precision, integrity, and a commitment to every client\'s success.' }}</p>
        </div>
    </div>
</section>

<section class="section">
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
        @endphp
        <div class="services-grid">
            @foreach ($displayValues as $value)
                <div class="service-card">
                    <div class="service-icon" style="background:var(--sky-soft); color:var(--sky-deep);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                    </div>
                    <h3 style="font-size:15px;">{{ $value->label }}</h3>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section">
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

@if ($certificates->count())
<section class="section section-alt">
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
@endsection
