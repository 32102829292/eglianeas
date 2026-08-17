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
@endsection
