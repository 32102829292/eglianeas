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
