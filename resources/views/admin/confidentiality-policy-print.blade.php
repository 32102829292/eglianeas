<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confidentiality Policy — Egliane Accounting Services</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #111; font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; }
        body { font-size: 11pt; line-height: 1.6; }

        .print-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px; padding: 12px 18px; background: #fff; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 10; }
        .print-toolbar .tb-title { font-weight: 600; font-size: 15px; color: #1b1b3a; margin-right: auto; white-space: nowrap; }
        .print-toolbar .tb-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .print-toolbar .tb-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            height: 40px; padding: 0 18px; border-radius: 9px;
            font-family: inherit; font-size: 13.5px; font-weight: 600; line-height: 1;
            cursor: pointer; text-decoration: none; white-space: nowrap;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
        }
        .print-toolbar .tb-btn:active { transform: translateY(1px); }
        .print-toolbar .tb-btn:focus-visible { outline: 3px solid rgba(46, 155, 222, .45); outline-offset: 2px; }
        .print-toolbar .tb-btn svg { width: 16px; height: 16px; flex: 0 0 auto; }
        .print-toolbar .tb-print {
            background: #1b1b3a; border: 1px solid #1b1b3a; color: #fff; box-shadow: 0 1px 2px rgba(27, 27, 58, .12);
        }
        .print-toolbar .tb-print:hover { background: #2e2e55; border-color: #2e2e55; box-shadow: 0 3px 8px rgba(27, 27, 58, .18); }
        .print-toolbar .tb-print:active { background: #12122b; box-shadow: none; }
        @media (max-width: 520px) {
            .print-toolbar { padding: 10px 12px; gap: 8px 10px; }
            .print-toolbar .tb-title { font-size: 14px; }
            .print-toolbar .tb-btn { height: 44px; padding: 0 16px; }
        }

        .doc { max-width: 820px; margin: 0 auto; padding: 34px 40px 24px; }

        .doc-header { display: flex; align-items: center; gap: 14px; border-bottom: 3px solid #1b1b3a; padding-bottom: 16px; margin-bottom: 22px; }
        .doc-header img { width: 52px; height: 52px; object-fit: contain; }
        .doc-header .org { font-size: 13px; letter-spacing: .3px; color: #555; text-transform: uppercase; }
        .doc-header h1 { margin: 2px 0 0; font-size: 22px; color: #1b1b3a; letter-spacing: .5px; }

        .doc-meta { display: flex; justify-content: space-between; font-size: 10.5pt; color: #333; margin-bottom: 18px; }

        h2 { font-size: 13.5pt; color: #1b1b3a; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin: 22px 0 10px; }
        h3 { font-size: 11.5pt; color: #1b1b3a; margin: 16px 0 6px; }
        p { margin: 0 0 10px; text-align: justify; }
        ul { margin: 0 0 12px; padding-left: 22px; }
        li { margin-bottom: 4px; }

        .doc-footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 8.5pt; color: #666; display: flex; justify-content: space-between; gap: 12px; }

        .eas-page-number { position: fixed; bottom: 6mm; right: 14mm; font-size: 9pt; color: #333; }
        .eas-page-number::before { content: "Page "; }
        .eas-page-number::after { content: counter(page); }

        @page { size: A4; margin: 18mm 16mm; }

        @media print {
            .print-toolbar { display: none !important; }
            .no-print { display: none !important; }
            body { font-size: 10.5pt; }
            .doc { padding: 0; }
            h2 { break-after: avoid; }
        }

        @media screen and (max-width: 640px) {
            .doc { padding: 20px 16px; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <span class="tb-title">Confidentiality Policy Document</span>
        <div class="tb-actions">
            <button type="button" class="tb-btn tb-print" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print this document
            </button>
        </div>
    </div>

    <div class="doc">
        <div class="doc-header">
            <img src="/images/logo-icon.png" alt="Egliane Accounting Services logo">
            <div>
                <div class="org">Egliane Accounting Services</div>
                <h1>Confidentiality Policy</h1>
            </div>
        </div>

        <div class="doc-meta">
            <span><strong>Policy Version:</strong> {{ $policyVersion }}</span>
            <span>Prepared: {{ now()->format('F j, Y') }}</span>
        </div>

        <section>
            <h2>1. Policy Statement</h2>
            <p>All client information, financial data, and documents accessible through this platform are strictly confidential. As a member of Egliane Accounting Services, you agree not to disclose, share, screenshot, copy, or distribute any client information to any party outside Egliane Accounting Services, without prior written authorization.</p>
            <p>This policy applies to all client data you access through this system, including but not limited to: personal information, financial records, tax documents, uploaded files, and any other information visible in the admin dashboard.</p>
            <p>Violation of this policy may result in disciplinary action, including termination of your account access.</p>
        </section>

        <section>
            <h2>2. Scope of Confidential Information</h2>
            <p>All documents uploaded to or downloaded from this system are confidential. You may not reproduce, distribute, or share any document obtained through this platform. Screenshots, copies, or reproductions of documents are strictly prohibited. Watermarks may be applied to documents for traceability.</p>
        </section>

        <section>
            <h2>3. Data Privacy Act of 2012 (Republic Act No. 10173)</h2>
            <p>The Data Privacy Act of 2012 (Republic Act No. 10173) governs the protection of personal data in the Philippines. It protects individuals' privacy by regulating the collection, processing, storage, and sharing of personal information. Its core principles include:</p>
            <ul>
                <li><strong>Transparency</strong> &mdash; data subjects have the right to be informed about how their personal information is collected and used.</li>
                <li><strong>Legitimate Purpose</strong> &mdash; personal data is processed only for declared, specified, and legitimate purposes, with the data subject's consent or as authorized by law.</li>
                <li><strong>Proportionality</strong> &mdash; only personal information necessary for the declared purpose is collected and processed.</li>
                <li><strong>Security</strong> &mdash; reasonable and appropriate organizational, physical, and technical security measures are maintained to protect personal data against unauthorized access, use, or disclosure.</li>
            </ul>
            <p>Egliane Accounting Services handles all client and member information in accordance with Republic Act No. 10173, including the principles above and the data subject rights it provides.</p>
        </section>

        <div class="doc-footer">
            <span>Egliane Accounting Services &mdash; Confidentiality Policy v{{ $policyVersion }}</span>
            <span>This document is for internal use only.</span>
        </div>
    </div>

    <div class="eas-page-number"></div>

    <script>
        (function () {
            function printNow() { if (document.readyState === 'complete') { window.print(); } }
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        })();
    </script>
</body>
</html>