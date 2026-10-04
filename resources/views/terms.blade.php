@extends('layouts.site')

@section('title', 'Terms & Confidentiality — Egliane Accounting Services')

@push('styles')
<style>
    .terms-head-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 6px; }
    .terms-head-row h1 { margin-bottom: 0; }
    .terms-sub { font-size: 13.5px; line-height: 1.6; color: var(--text-muted, #64748b); margin: 0 0 24px; }
    .terms-print-btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 12.5px; font-weight: 600;
        color: var(--navy, #1B1B3A); background: #fff; border: 1px solid var(--navy, #1B1B3A);
        border-radius: 8px; padding: 8px 14px; cursor: pointer; white-space: nowrap;
        transition: background .15s ease, color .15s ease;
    }
    .terms-print-btn svg { width: 15px; height: 15px; flex: 0 0 auto; }
    .terms-print-btn:hover, .terms-print-btn:focus { background: var(--navy, #1B1B3A); color: #fff; }

    .page-legal section { margin-bottom: 30px; }

    .dpp-panel {
        position: relative;
        border: 1px solid var(--border, #cfd6e4); border-left: 4px solid var(--navy, #1B1B3A);
        border-radius: 14px;
        background: linear-gradient(180deg, #f5f8ff 0%, #ffffff 100%);
        padding: 24px 26px;
        margin: 0 0 30px;
    }
    .dpp-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
    .dpp-head h2 { margin-bottom: 0; }
    .dpp-badge {
        display: inline-flex; align-items: center;
        font-size: 11.5px; font-weight: 700; letter-spacing: .02em;
        color: var(--navy-deep, #10101f); background: var(--sky-soft, #e8f4fd);
        border: 1px solid var(--sky-deep, #0a6fb8); border-radius: 999px; padding: 4px 12px;
        white-space: nowrap;
    }
    .dpp-print { font-size: 13.5px; line-height: 1.7; color: var(--text, #334155); background: #fff; border: 1px solid var(--border, #dbe1ec); border-radius: 12px; padding: 30px 36px; margin-top: 18px; }
    .dpp-print-head { border-bottom: 2px solid var(--navy, #1B1B3A); padding-bottom: 12px; margin-bottom: 16px; }
    .dpp-print-head .dpp-doc-brand { margin: 0 0 2px; font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 12px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--navy-deep, #10101f); }
    .dpp-print-head .dpp-doc-title { font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 19px; font-weight: 700; color: var(--navy, #1B1B3A); margin: 0 0 2px; }
    .dpp-print-head .dpp-doc-sub { font-size: 12.5px; color: var(--text-muted, #64748b); margin: 0 0 2px; }
    .dpp-print-head .dpp-doc-ver { font-size: 12px; font-weight: 600; color: var(--text-muted, #64748b); margin: 0; }
    .dpp-print h3 { font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 14px; font-weight: 700; color: var(--navy, #1B1B3A); margin: 18px 0 6px; }
    .dpp-print h3:first-of-type { margin-top: 0; }
    .dpp-print p { margin: 0 0 8px; }
    .dpp-print ul { margin: 0 0 10px; padding-left: 20px; }
    .dpp-print li { margin-bottom: 4px; }

    .dpp-ack { margin-top: 26px; }
    .dpp-ack-statement { font-weight: 600; margin: 4px 0 0 0; }
    .dpp-sig-block { margin-top: 26px; border-top: 2px solid var(--navy, #1B1B3A); padding-top: 20px; text-align: center; }
    .dpp-sig-img { display: block; margin: 0 auto 8px; max-height: 90px; max-width: 280px; width: auto; }
    .dpp-sig-line { width: 300px; max-width: 72%; margin: 8px auto 0; border-top: 1px solid #1b1b3a; }
    .dpp-sig-caption { font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 11.5px; color: #555; letter-spacing: .04em; margin: 4px 0 0; }
    .dpp-sig-fields { display: flex; gap: 26px; margin-top: 26px; text-align: left; }
    .dpp-sig-field { flex: 1 1 0; min-width: 0; }
    .dpp-sig-field .dpp-sig-k { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #555; margin-bottom: 4px; }
    .dpp-sig-field .dpp-sig-v { display: block; font-size: 14px; color: var(--navy, #1B1B3A); border-bottom: 1px solid #1b1b3a; padding: 5px 4px 2px; min-height: 24px; }
    .dpp-sig-field .dpp-sig-v.is-plain { border-bottom: 0; min-height: auto; padding: 7px 4px 2px; }

    .ack-signed-head { margin-bottom: 14px; }
    .ack-signed-head .ack-signed-label { font-family: "Space Grotesk", var(--font-ui, sans-serif); font-size: 15px; font-weight: 700; color: var(--navy, #1B1B3A); }

    .ack-form { padding: 24px 26px; }
    .ack-statement { font-size: 13.5px; line-height: 1.65; color: var(--text, #334155); margin: 0 0 4px; font-weight: 500; }
    .ack-guest-note { font-size: 13.5px; line-height: 1.65; color: var(--text-muted, #64748b); margin: 0; }
    .ack-guest-note a { color: var(--navy, #1B1B3A); font-weight: 600; }

    @media (max-width: 575px) {
        .terms-head-row { flex-direction: column; align-items: flex-start; }
        .terms-print-btn { align-self: flex-start; }
        .dpp-panel { padding: 20px 18px; }
        .dpp-print { padding: 22px 18px; }
        .ack-form { padding: 20px 16px; }
        .dpp-head { flex-direction: column; align-items: flex-start; }
        .dpp-sig-line { width: 100%; max-width: 100%; }
        .dpp-sig-fields { flex-direction: column; gap: 14px; }
    }

    @page {
        size: A4;
        margin: 12mm 14mm;
        @bottom-center {
            content: "Egliane Accounting Services — Confidentiality & Data Privacy Policy — Page " counter(page) " of " counter(pages);
            font-size: 8.5pt;
            color: #444;
        }
    }

    @media print {
        /* Strip every screen-only layout constraint so the policy prints as a
           normal flowing document. No fixed heights, no viewport sizing,
           no absolute page containers — content fills the page naturally. */
        html, body, main, .page-legal, .dpp-panel, .dpp-print {
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
        }
        body { display: block !important; background: #fff !important; }

        /* Screen-only furniture: remove from layout entirely. */
        .site-header, .site-footer, .nav-toggle, .mobile-nav,
        .chat-fab, .chat-widget, .offline-banner,
        .page-loader, #pageLoader, .confirm-modal,
        .terms-head-row, .terms-sub,
        section[aria-labelledby="terms-of-use"],
        .dpp-head,
        section[aria-labelledby="dpa-ack-title"] { display: none !important; }

        /* Site content wrapper: normal flow, zero per-page padding. */
        main { padding: 0 !important; }
        .page-legal { max-width: none !important; margin: 0 !important; padding: 0 !important; }
        .page-legal section { margin: 0 !important; }

        /* Screen card becomes a plain block wrapper. */
        .dpp-panel {
            display: block !important;
            border: 0 !important;
            border-left: 0 !important;
            border-radius: 0 !important;
            background: #fff !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* The document itself: normal static block flow. */
        .dpp-print {
            position: static !important;
            width: auto !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: #fff !important;
            box-shadow: none !important;
            font-size: 10.5px;
            line-height: 1.5;
            color: #000;
        }
        .dpp-print, .dpp-print * { visibility: visible !important; }

        /* Compact professional header */
        .dpp-print-head { border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
        .dpp-print-head .dpp-doc-brand { font-size: 9px; letter-spacing: .08em; margin: 0 0 2px; }
        .dpp-print-head .dpp-doc-title { font-size: 18px; margin: 0 0 2px; }
        .dpp-print-head .dpp-doc-sub { font-size: 10px; margin: 0 0 2px; }
        .dpp-print-head .dpp-doc-ver { font-size: 9px; margin: 0; }
        .dpp-print h3, .dpp-print .dpp-doc-title { color: #000; }
        .dpp-print .dpp-doc-sub, .dpp-print-head .dpp-doc-ver { color: #333; }

        /* Body typography: compact but readable, headings never orphaned */
        .dpp-print h3 { font-size: 12px; margin: 14px 0 4px; page-break-after: avoid; break-after: avoid; }
        .dpp-print h3:first-of-type { margin-top: 0; }
        .dpp-print p { margin: 0 0 6px; orphans: 3; widows: 3; }
        .dpp-print ul { margin: 2px 0 8px; padding-left: 16px; }
        .dpp-print li { margin-bottom: 3px; page-break-inside: avoid; break-inside: avoid; }

        /* Acknowledgement: flows naturally, never split. No forced page break. */
        .dpp-ack { margin-top: 16px; break-before: auto; page-break-before: auto; break-inside: avoid; page-break-inside: avoid; }
        .dpp-ack-statement { margin-top: 4px; }
        .dpp-sig-block { margin-top: 14px; padding-top: 12px; border-top-color: #000; break-inside: avoid; page-break-inside: avoid; }
        .dpp-sig-img { display: block; margin: 0 auto 6px; max-height: 44px; max-width: 240px; width: auto; }
        .dpp-sig-line { border-top-color: #000; }
        .dpp-sig-caption { color: #444; }
        .dpp-sig-field .dpp-sig-v { border-bottom-color: #000; color: #000; }

        a { color: inherit; text-decoration: none; }
    }
</style>
@endpush

@section('content')
    @php
        $user = auth()->user();
        $roleLabel = $user?->isAdmin() ? 'Admin' : ($user?->isStaff() ? 'Staff' : ($user?->isSupervisor() ? 'Supervisor' : ($user?->isClient() ? 'Client' : null)));
        $signatureLabel = $roleLabel ? $roleLabel.' Signature' : null;
        $isSigned = $user !== null && $signature !== null;
        $signedAt = $signature?->signed_at ?? $user?->confidentiality_acknowledged_at;
    @endphp

    <div class="page-legal">
        <div class="terms-head-row">
            <h1>Terms &amp; Confidentiality</h1>
            <button type="button" class="terms-print-btn" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
                Print Data Privacy Policy
            </button>
        </div>

        <p class="terms-sub">This page states the terms of using the Egliane Accounting Services portal, the confidentiality obligations that apply to your access, and the Data Privacy acknowledgement required of every account.</p>

        <section aria-labelledby="terms-of-use">
            <h2 id="terms-of-use">Terms of Use</h2>
            <ul>
                <li>By creating an account, you agree to use this portal only for your own business records.</li>
                <li>Keep your login details private and do not share your account.</li>
                <li>Submit accurate and truthful information.</li>
                <li>Documents uploaded are reviewed by Egliane's accounting team for processing purposes.</li>
                <li>Egliane may contact you at the email address you registered.</li>
                <li>Unauthorized access or misuse may result in account termination.</li>
            </ul>
        </section>

        <section class="dpp-panel" aria-labelledby="dpp-title">
            <div class="dpp-head">
                <h2 id="dpp-title">Confidentiality &amp; Data Privacy Policy</h2>
                <span class="dpp-badge">Republic Act No. 10173 &middot; Data Privacy Act of 2012</span>
            </div>

            <div class="dpp-print">
                <div class="dpp-print-head">
                    <p class="dpp-doc-brand">Egliane Accounting Services</p>
                    <h2 class="dpp-doc-title">Confidentiality &amp; Data Privacy Policy</h2>
                    <p class="dpp-doc-sub">Republic Act No. 10173 &mdash; Data Privacy Act of 2012</p>
                    <p class="dpp-doc-ver">Policy Version {{ $policyVersion }}</p>
                </div>

                <h3>Confidentiality Policy</h3>
                <p>All client information, financial data, and documents accessible through this platform are strictly confidential. Users agree not to disclose, share, screenshot, copy, or distribute any information obtained through this system to any party outside Egliane Accounting Services and the account holder, without prior written authorization.</p>
                <p>This policy applies to all client data and documents you access through this system, including but not limited to: personal information, financial records, tax documents, uploaded files, and any other information visible in the portal.</p>
                <p>Violation of this policy may result in disciplinary action, including termination of your account access.</p>

                <h3>Scope of Confidential Information</h3>
                <p>All documents uploaded to or downloaded from this system are confidential. You may not reproduce, distribute, or share any document obtained through this platform. Screenshots, copies, or reproductions of documents are strictly prohibited. Watermarks may be applied to documents for traceability.</p>

                <h3>Data Privacy Act of 2012 (Republic Act No. 10173)</h3>
                <p>The Data Privacy Act of 2012 (Republic Act No. 10173) governs the protection of personal data in the Philippines. It protects individuals' privacy by regulating the collection, processing, storage, and sharing of personal information. Its core principles include:</p>
                <ul>
                    <li><strong>Transparency</strong> &mdash; data subjects have the right to be informed about how their personal information is collected and used.</li>
                    <li><strong>Legitimate Purpose</strong> &mdash; personal data is processed only for declared, specified, and legitimate purposes, with the data subject's consent or as authorized by law.</li>
                    <li><strong>Proportionality</strong> &mdash; only personal information necessary for the declared purpose is collected and processed.</li>
                    <li><strong>Security</strong> &mdash; reasonable and appropriate organizational, physical, and technical security measures are maintained to protect personal data against unauthorized access, use, or disclosure.</li>
                </ul>
                <p>Egliane Accounting Services handles all client and member information in accordance with Republic Act No. 10173, including the principles above and the data subject rights it provides.</p>

                @if ($user)
                    <div class="dpp-ack">
                        <h3>ACKNOWLEDGEMENT</h3>
                        <p class="dpp-ack-statement">I acknowledge that I have read and understood the Data Privacy and Confidentiality Policy of Egliane Accounting Services and agree to comply with its requirements.</p>

                        <div class="dpp-sig-block">
                            @if ($isSigned && $signature->hasImagePath())
                                <img class="dpp-sig-img" src="{{ route('confidentiality.signature.image', $signature) }}" alt="Electronic signature" onerror="this.remove();">
                            @endif
                            <div class="dpp-sig-line"></div>
                            <p class="dpp-sig-caption">Signature</p>

                            <div class="dpp-sig-fields">
                                <div class="dpp-sig-field">
                                    <span class="dpp-sig-k">Printed Name</span>
                                    <span class="dpp-sig-v">{{ $isSigned ? $user->name : '' }}</span>
                                </div>
                                <div class="dpp-sig-field">
                                    <span class="dpp-sig-k">Role</span>
                                    <span class="dpp-sig-v is-plain">{{ $roleLabel }}</span>
                                </div>
                                <div class="dpp-sig-field">
                                    <span class="dpp-sig-k">Date</span>
                                    <span class="dpp-sig-v">{{ $isSigned ? $signedAt?->format('F j, Y') : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section aria-labelledby="dpa-ack-title">
            <h2 id="dpa-ack-title">Data Privacy Acknowledgement</h2>

            @if (! $user)
                <div class="card ack-card">
                    <p class="ack-guest-note">You must be logged in to submit your Data Privacy acknowledgement. <a href="{{ route('login') }}">Log in to your account</a> to read, acknowledge, and sign the policy.</p>
                </div>
            @elseif ($isSigned)
                <div class="card ack-signed-card">
                    <div class="ack-signed-head">
                        <span class="ack-signed-label">Electronic Signature</span>
                    </div>

                    <div class="ack-signed-banner">
                        <span class="ack-signed-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        <div>
                            <strong>Signature Recorded</strong>
                            <span>Policy version {{ $policyVersion }} has been acknowledged by your account. Your electronic signature is shown below and appears on your signed copy of the printed policy.</span>
                        </div>
                    </div>

                    <div class="ack-signed-sig">
                        <span class="ack-sig-label">Your {{ $signatureLabel }}</span>
                        @if ($signature && $signature->hasImagePath())
                            <span class="ack-sig-frame">
                                <img src="{{ route('confidentiality.signature.image', $signature) }}" alt="Your electronic signature" loading="lazy" onerror="this.remove();">
                            </span>
                        @else
                            <span class="ack-sig-frame ack-sig-empty">Signature image unavailable</span>
                        @endif
                    </div>

                    <div class="ack-signed-rows">
                        <div class="ack-signed-row">
                            <span class="ack-k">Signed by</span>
                            <span class="ack-v">{{ $user->name }}</span>
                        </div>
                        <div class="ack-signed-row">
                            <span class="ack-k">Role</span>
                            <span class="ack-v">{{ $roleLabel }}</span>
                        </div>
                        <div class="ack-signed-row">
                            <span class="ack-k">Signed</span>
                            <span class="ack-v">{{ $signedAt?->format('F j, Y') }}</span>
                        </div>
                    </div>

                    <p class="ack-signed-note">If the policy is updated to a new version, you will be asked to read, acknowledge, and sign again. Your signed copy is available through the Print Data Privacy Policy button above.</p>
                </div>
            @else
                <form method="POST" action="{{ route('terms.acknowledge.store') }}" class="card ack-card ack-form" id="termsAckForm" novalidate>
                    @csrf

                    <p class="ack-statement">I acknowledge that I have read and understood the Confidentiality and Data Privacy Policy of Egliane Accounting Services and agree to comply with Republic Act No. 10173, the Data Privacy Act of 2012, and the organization's confidentiality and data-protection requirements.</p>

                    <label class="ack-checkbox" for="termsAckCheck">
                        <input type="checkbox" name="agree" id="termsAckCheck" value="1" @checked(old('agree'))>
                        <span>I have read and understood the Data Privacy and Confidentiality Policy.</span>
                    </label>
                    @error('agree')<div class="form-error" role="alert">{{ $message }}</div>@enderror

                    <p class="ack-legal-note">This acknowledgement is required by Egliane Accounting Services as part of account and portal access. It is an organizational acknowledgement requirement and does not itself claim to be a legal requirement imposed by Republic Act No. 10173.</p>

                    <div class="ack-signer" style="margin-top: 16px;">
                        <div class="ack-signer-avatar" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </div>
                        <div class="ack-signer-lines">
                            <span class="ack-signer-name">{{ $user->name }}</span>
                            <span class="ack-signer-mail">{{ $user->email }}</span>
                        </div>
                        <div class="ack-signer-meta">
                            <span class="ack-signer-role">{{ $roleLabel }}</span>
                            <span class="ack-signer-id">Account ID #{{ $user->id }}</span>
                        </div>
                    </div>

                    <div class="ack-sig-area">
                        <div class="sig-pad-field">
                            <label class="form-label ack-sig-label" for="sigPad">
                                {{ $signatureLabel }}
                                <span class="ack-required" aria-hidden="true">*</span>
                            </label>
                            <div class="sig-pad-box" id="sigPadBox">
                                <canvas id="sigPad" role="img" aria-label="Draw your {{ strtolower($signatureLabel) }} here"></canvas>
                                <span class="sig-pad-placeholder" id="sigPadPlaceholder">Sign here with your mouse or finger</span>
                            </div>
                            <div class="sig-pad-actions">
                                <button type="button" class="btn btn-outline btn-sm" id="sigClearBtn">Clear</button>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureData" value="{{ old('signature_data') }}">
                            <div class="ack-sig-error" id="ackSigError" role="alert" hidden>Please provide your electronic signature to continue.</div>
                            @error('signature_data')<div class="form-error" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="ack-form-actions">
                        <button type="submit" class="btn btn-primary" id="ackSubmitBtn">Submit Acknowledgement</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('termsAckForm');
    var cb = document.getElementById('termsAckCheck');
    var pad = document.getElementById('sigPad');

    if (!form || !pad) {
        return;
    }

    var box = document.getElementById('sigPadBox');
    var placeholder = document.getElementById('sigPadPlaceholder');
    var clearBtn = document.getElementById('sigClearBtn');
    var hidden = document.getElementById('signatureData');
    var errorBox = document.getElementById('ackSigError');
    var ctx = pad.getContext('2d');
    var drawing = false;
    var signatureDrawn = false;
    var PAD_HEIGHT = 150;

    function sizeCanvas() {
        var dpr = window.devicePixelRatio || 1;
        var width = Math.max(box.clientWidth, 240);
        pad.width = Math.round(width * dpr);
        pad.height = Math.round(PAD_HEIGHT * dpr);
        pad.style.width = width + 'px';
        pad.style.height = PAD_HEIGHT + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2.25;
        ctx.strokeStyle = '#1a1a2e';
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, PAD_HEIGHT);
    }

    function pos(e) {
        var rect = pad.getBoundingClientRect();
        var c = (e.touches && e.touches[0]) || e;
        return {
            x: c.clientX - rect.left,
            y: c.clientY - rect.top
        };
    }

    function onDown(e) {
        e.preventDefault();
        drawing = true;
        var p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        if (pad.setPointerCapture) pad.setPointerCapture(e.pointerId);
    }

    function onMove(e) {
        if (!drawing) return;
        e.preventDefault();
        var p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }

    function onUp() {
        if (!drawing) return;
        drawing = false;
        signatureDrawn = true;
        placeholder.style.display = 'none';
        hidden.value = pad.toDataURL('image/png');
        hideError();
    }

    function clearSignature() {
        if (ctx) ctx.clearRect(0, 0, pad.width, pad.height);
        sizeCanvas();
        signatureDrawn = false;
        hidden.value = '';
        placeholder.style.display = '';
        hideError();
    }

    function showError() {
        errorBox.hidden = false;
        box.classList.add('ack-sig-invalid');
        pad.focus();
    }

    function hideError() {
        errorBox.hidden = true;
        box.classList.remove('ack-sig-invalid');
    }

    if (window.PointerEvent) {
        pad.addEventListener('pointerdown', onDown);
        pad.addEventListener('pointermove', onMove);
        pad.addEventListener('pointerup', onUp);
        pad.addEventListener('pointercancel', function () { drawing = false; });
    } else {
        pad.addEventListener('mousedown', onDown);
        pad.addEventListener('mousemove', onMove);
        pad.addEventListener('mouseup', onUp);
        pad.addEventListener('touchstart', onDown);
        pad.addEventListener('touchmove', onMove);
        pad.addEventListener('touchend', onUp);
    }

    clearBtn.addEventListener('click', clearSignature);

    form.addEventListener('submit', function (e) {
        if (!cb.checked) {
            e.preventDefault();
            cb.focus();
            return;
        }
        if (!signatureDrawn) {
            e.preventDefault();
            showError();
            return;
        }
    });

    sizeCanvas();
    window.addEventListener('resize', function () {
        var had = signatureDrawn;
        sizeCanvas();
        if (had) signatureDrawn = true;
    });
})();
</script>
@endpush