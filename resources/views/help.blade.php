@extends('layouts.site')

@section('title', 'How to Use This System — Egliane Accounting Services')

@section('content')
<div class="container help-page" style="max-width:820px; margin:0 auto; padding:40px 20px;">
    <h1 style="margin-bottom:8px;">How to Use This System</h1>
    <p style="color:var(--text-muted); margin-bottom:32px;">
        A structured guide to the Client portal and the Admin/Staff workspace. For quick questions,
        use the chat assistant (bottom-right corner). This page is your step-by-step reference.
    </p>

    <div class="accordion" id="helpAccordion">

        {{-- CLIENT GUIDE --}}
        <div class="accordion-item">
            <h2 class="accordion-header" id="helpClientHead">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#helpClient" aria-expanded="true" aria-controls="helpClient">
                    Clients — Getting Started
                </button>
            </h2>
            <div id="helpClient" class="accordion-collapse collapse show" aria-labelledby="helpClientHead" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <h5>Submit your sales</h5>
                    <ol>
                        <li>Log in, then open <strong>Sales Entry</strong> from your dashboard or side menu.</li>
                        <li>Enter your sales for the period and click <strong>Save</strong>.</li>
                        <li>Your accountant will process it; you can edit the draft until it is locked.</li>
                    </ol>

                    <h5>View your billing statements</h5>
                    <ol>
                        <li>Open <strong>Billing Statements</strong> from the menu.</li>
                        <li>Select a statement period to see your monthly professional fee and any remittance line items.</li>
                        <li>Click <strong>View receipt</strong> to see the paid breakdown and payment details.</li>
                    </ol>

                    <h5>Check your documents</h5>
                    <ol>
                        <li>Go to <strong>Documents</strong>. Files shared with you by Egliane appear here.</li>
                        <li>Open a document to view or download it (watermarks may be applied for security).</li>
                    </ol>

                    <h5>Track your services &amp; raise concerns</h5>
                    <ol>
                        <li>Use <strong>Service Tracker</strong> to follow deliverables in progress.</li>
                        <li>Use <strong>Client Concerns</strong> to raise a concern; it goes straight to Egliane staff.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- LAST DOUBLED-CLICK / SURVEY / PROFILE SIDE NOTE --}}
        <div class="accordion-item">
            <h2 class="accordion-header" id="helpClient2Head">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpClient2" aria-expanded="false" aria-controls="helpClient2">
                    Clients — Account, Security &amp; Feedback
                </button>
            </h2>
            <div id="helpClient2" class="accordion-collapse collapse" aria-labelledby="helpClient2Head" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <h5>Profile &amp; contact details</h5>
                    <p>Open <strong>Profile</strong> to update your contact information, PIN, or enable Face / biometric login. Your registered name can only be changed by contacting Egliane.</p>

                    <h5>Notifications &amp; reminders</h5>
                    <p>Reminders appear as alerts (bell icon) and can also be delivered as push notifications. In <strong>Security</strong> you can manage push and biometric preferences.</p>

                    <h5>Monthly satisfaction survey</h5>
                    <p>After logging in you may be asked to complete a short monthly survey. It helps Egliane improve its service.</p>
                </div>
            </div>
        </div>

        {{-- ADMIN / STAFF GUIDE --}}
        <div class="accordion-item">
            <h2 class="accordion-header" id="helpAdminHead">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpAdmin" aria-expanded="false" aria-controls="helpAdmin">
                    Admin / Staff — Managing Clients
                </button>
            </h2>
            <div id="helpAdmin" class="accordion-collapse collapse" aria-labelledby="helpAdminHead" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <h5>Add / edit a client</h5>
                    <ol>
                        <li>Open <strong>Clients</strong> and click <strong>Add Client</strong>.</li>
                        <li>Fill in business and taxpayer details, plus primary and second contact info.</li>
                        <li>Use <strong>View</strong> to see all client detail and quick actions (impersonate, status, payment status).</li>
                    </ol>

                    <h5>Account status &amp; impersonation</h5>
                    <ul>
                        <li>Change a client's account status (Active / On-hold / Closed) from the client view.</li>
                        <li><strong>Login as Client</strong> previews the app exactly as that client sees it. Exit anytime from the top banner.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="helpBillingHead">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpBilling" aria-expanded="false" aria-controls="helpBilling">
                    Admin / Staff — Billing
                </button>
            </h2>
            <div id="helpBilling" class="accordion-collapse collapse" aria-labelledby="helpBillingHead" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <h5>Create a billing statement</h5>
                    <ol>
                        <li>Open <strong>Billing Statements</strong> then <strong>Create Statement</strong>.</li>
                        <li>Pick the client and billing period. Preset fee categories auto-fill; use <strong>+ Add another item</strong> for one-off charges.</li>
                        <li>The total updates automatically from every line item amount.</li>
                        <li>Save as <strong>Draft</strong> first, then <strong>Finalize</strong> to make it visible to the client.</li>
                    </ol>

                    <h5>Send reminders &amp; receipts</h5>
                    <ul>
                        <li>Mark a statement <strong>Paid</strong> from the Collections view once payment is received.</li>
                        <li>Use <strong>Send reminder</strong> to nudge unpaid statements by the reminder channel.</li>
                        <li>Share a statement via <strong>CSV</strong>, email, or Messenger from the receipt view.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="helpDistHead">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpDist" aria-expanded="false" aria-controls="helpDist">
                    Admin / Staff — Distribution &amp; Other Services
                </button>
            </h2>
            <div id="helpDist" class="accordion-collapse collapse" aria-labelledby="helpDistHead" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <h5>Distribution (deliverables)</h5>
                    <p>Open a client's <strong>Distribution</strong> page to log delivered BIR forms, set delivery entries, and upload softcopy documents. Update map location as needed.</p>

                    <h5>Other services</h5>
                    <p>Manage non-monthly engagements (e.g. one-off bookkeeping, consultancy) under <strong>Other Services</strong>, including their billing and receipts.</p>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="helpSysHead">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpSys" aria-expanded="false" aria-controls="helpSys">
                    Admin — System Settings
                </button>
            </h2>
            <div id="helpSys" class="accordion-collapse collapse" aria-labelledby="helpSysHead" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                    <ul>
                        <li><strong>Team Accounts</strong> — add and manage admin/staff portal accounts.</li>
                        <li><strong>Announcements</strong> — publish notices shown to clients.</li>
                        <li><strong>Billing Settings</strong> — manage fee presets and payment methods.</li>
                        <li><strong>Chatbot</strong> — tune the automated assistant's responses.</li>
                        <li><strong>About</strong> — manage the public About page content, certificates, and team.</li>
                        <li><strong>Activity Logs</strong> — review a trace of important actions for accountability.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection