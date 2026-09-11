@extends('layouts.site')

@push('styles')
<style>
/* ---------- Help manual (interactive) ---------- */
.help-page { padding: 30px 0 54px; }
.help-page .container { max-width: 1040px; }

.help-head { max-width: 680px; margin: 0 auto 28px; text-align: center; }
.help-eyebrow { margin: 0 0 6px; font-size: 11px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--sky-deep); }
.help-head h1 { margin: 0 0 8px; font-size: clamp(26px, 4vw, 38px); }
.help-sub { margin: 0 0 20px; font-size: 14.5px; line-height: 1.6; color: var(--muted-text); }

.help-search { position: relative; max-width: 520px; margin: 0 auto; }
.help-search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted-text); pointer-events: none; display: grid; place-items: center; }
.help-search-icon svg { width: 18px; height: 18px; }
.help-search-input {
  width: 100%; padding: 12px 44px 12px 44px; font-size: 14px; color: var(--text);
  border: 1px solid var(--border-subtle); border-radius: 999px; background: var(--surface);
  box-shadow: var(--shadow-card); transition: border-color .15s ease, box-shadow .15s ease;
}
.help-search-input::placeholder { color: #9CA3AF; }
.help-search-input:focus { outline: none; border-color: var(--sky-deep); box-shadow: 0 0 0 3px rgba(90, 179, 240, .25); }
.help-search-input::-webkit-search-cancel-button { display: none; }
.help-search-clear {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  width: 28px; height: 28px; border: none; border-radius: 50%; cursor: pointer;
  background: var(--surface-sunken); color: var(--muted-text); font-size: 14px; line-height: 1;
}
.help-search-clear:hover { background: var(--border-subtle); color: var(--navy); }

.help-results-summary { margin: 12px 0 0; font-size: 12.5px; color: var(--muted-text); }

.help-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; margin: 0 0 14px; flex-wrap: wrap; }
.help-section-head h2 { margin: 0; font-size: 19px; }

.help-quickstart { margin: 0 0 30px; }
.help-quickstart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.help-quick-card {
  display: flex; flex-direction: column; gap: 7px; padding: 18px 20px; text-decoration: none; color: inherit;
  border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface);
  box-shadow: var(--shadow-card); transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
}
.help-quick-card:hover { border-color: var(--sky-deep); transform: translateY(-2px); box-shadow: var(--shadow-card-hover); text-decoration: none; }
.hq-ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; }
.hq-ico svg { width: 18px; height: 18px; }
.hq-client .hq-ico { background: var(--sky-soft); color: var(--sky-deep); }
.hq-admin .hq-ico { background: var(--navy); color: #fff; }
.help-quick-card b { font-family: var(--font-head); font-size: 15px; color: var(--navy); }
.help-quick-card span { font-size: 12.5px; line-height: 1.5; color: var(--muted-text); }
.help-quick-card em { margin-top: auto; font-style: normal; font-size: 12.5px; font-weight: 700; color: var(--sky-deep); }

.help-tasks { margin: 0 0 30px; }
.help-tasks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(225px, 1fr)); gap: 14px; }
.help-task-card {
  display: flex; flex-direction: column; gap: 8px; padding: 16px;
  border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface);
  box-shadow: var(--shadow-card);
}
.ht-ico { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: var(--sky-soft); color: var(--sky-deep); }
.ht-ico svg { width: 16px; height: 16px; }
.help-task-card b { font-size: 13.5px; color: var(--navy); }
.help-task-card > span { font-size: 12px; line-height: 1.5; color: var(--muted-text); }
.ht-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; }
.ht-actions .btn { white-space: nowrap; }

.help-guides { margin: 0 0 34px; }
.help-guides-head { align-items: center; margin-bottom: 14px; }
.help-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.help-tab {
  border: 1px solid var(--border-subtle); background: var(--surface); color: var(--muted-text);
  font-size: 12.5px; font-weight: 700; padding: 7px 14px; border-radius: 999px; cursor: pointer;
  transition: color .15s ease, border-color .15s ease, background .15s ease;
}
.help-tab:hover { color: var(--navy); border-color: var(--sky-deep); }
.help-tab[aria-pressed="true"] { background: var(--navy); border-color: var(--navy); color: #fff; }

.help-progress { display: flex; align-items: center; gap: 12px; margin: 0 0 14px; font-size: 12.5px; color: var(--muted-text); }
.help-progress-track { flex: 0 1 220px; max-width: 220px; height: 6px; border-radius: 999px; background: var(--surface-sunken); overflow: hidden; }
.help-progress-fill { display: block; height: 100%; border-radius: 999px; background: var(--sky-deep); width: 0; transition: width .3s ease; }

.help-guides-list { display: flex; flex-direction: column; gap: 12px; }

.help-guide { border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface); box-shadow: var(--shadow-card); scroll-margin-top: 84px; }
.help-guide[hidden] { display: none; }
.help-guide-head { margin: 0; }
.help-guide-toggle {
  display: grid; grid-template-columns: auto 1fr auto; grid-template-areas: "num title caret" "num desc caret";
  column-gap: 14px; row-gap: 3px; align-items: start; width: 100%; text-align: left;
  padding: 15px 18px; border: none; background: transparent; cursor: pointer; color: inherit;
}
.help-guide-num { grid-area: num; width: 34px; height: 34px; border-radius: 10px; background: var(--surface-sunken); color: var(--muted-text); display: grid; place-items: center; font-size: 12px; font-weight: 800; font-variant-numeric: tabular-nums; }
.help-guide.open .help-guide-num { background: var(--sky-soft); color: var(--sky-deep); }
.help-guide-title { grid-area: title; font-family: var(--font-head); font-size: 15px; font-weight: 700; color: var(--navy); transition: color .15s ease; }
.help-guide-toggle:hover .help-guide-title { color: var(--sky-deep); }
.help-guide-desc { grid-area: desc; font-size: 12.5px; color: var(--muted-text); line-height: 1.45; }
.help-guide-caret { grid-area: caret; align-self: start; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: var(--surface-sunken); color: var(--muted-text); transition: transform .2s ease, color .2s ease; }
.help-guide-caret svg { width: 14px; height: 14px; }
.help-guide.open .help-guide-caret { transform: rotate(180deg); color: var(--sky-deep); }

.help-guide-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .22s ease; }
.help-guide.open .help-guide-panel { grid-template-rows: 1fr; }
.help-guide-panel-inner { overflow: hidden; }
.help-guide-body { padding: 0 18px 18px 66px; }
.help-guide-body > :first-child { margin-top: 0; }

.help-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; counter-reset: helpstep; }
.help-step { position: relative; padding-left: 40px; counter-increment: helpstep; }
.help-step::before { content: counter(helpstep, decimal-leading-zero); position: absolute; left: 0; top: 1px; width: 28px; height: 28px; border-radius: 8px; background: var(--sky-soft); color: var(--sky-deep); font-size: 11.5px; font-weight: 800; display: grid; place-items: center; font-variant-numeric: tabular-nums; }
.help-step-text { font-size: 13px; line-height: 1.6; color: var(--text); }
.help-step-text b { color: var(--navy); }
.help-guide-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-light); }
.help-guide-actions .btn { white-space: nowrap; }
.help-guide-note { display: flex; gap: 7px; align-items: flex-start; margin: 16px 0 0; font-size: 12px; line-height: 1.5; color: var(--muted-text); }
.help-guide-note svg { flex: 0 0 auto; margin-top: 1px; }

.help-mark { background: rgba(242, 153, 74, .32); color: inherit; border-radius: 3px; padding: 0 2px; }

.help-noresults { text-align: center; padding: 34px 18px; border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-sunken); }
.help-noresults[hidden] { display: none; }
.help-noresults .hn-ico { margin: 0 auto; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--surface); color: var(--muted-text); }
.help-noresults .hn-ico svg { width: 18px; height: 18px; }
.help-noresults b { display: block; margin: 10px 0 4px; font-family: var(--font-head); font-size: 15px; color: var(--navy); }
.help-noresults p { margin: 0; font-size: 12.5px; color: var(--muted-text); }
.help-nr-term { border: none; background: none; padding: 0 2px; font-size: 12.5px; font-weight: 700; color: var(--sky-deep); text-decoration: underline; cursor: pointer; }
.help-nr-sep { margin: 0 3px; color: #C6CDD7; }

.help-more { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; padding: 22px 26px; border-radius: var(--radius-card); background: linear-gradient(135deg, var(--navy) 0%, var(--navy-soft) 100%); color: #fff; }
.help-more h2 { margin: 0 0 4px; font-size: 18px; color: #fff; }
.help-more p { margin: 0; font-size: 13px; line-height: 1.5; opacity: .85; max-width: 46ch; }
.help-more .btn { background: #fff; color: var(--navy); border: none; font-weight: 700; }
.help-more .btn:hover { background: var(--sky); color: #fff; }

@media (max-width: 700px) {
  .help-quickstart-grid { grid-template-columns: 1fr; }
  .help-guide-body { padding-left: 18px; }
  .help-guide-toggle { grid-template-columns: auto 1fr auto; column-gap: 10px; }
}
</style>
@endpush

@section('title', 'How to Use This System — Egliane Accounting Services')

@section('content')
@php
    $user = auth()->user();
    $isClient = $user && $user->isClient();
    $isStaffOrAdmin = $user && $user->isStaffOrAdmin();
    $showAdminGuides = $isStaffOrAdmin || ! $user;

    $clientGuides = [
        [
            'id' => 'getting-started',
            'title' => 'Getting Started',
            'desc' => 'Log in, explore your dashboard, and know where everything lives.',
            'keywords' => 'log in login sign in dashboard menu overview basics getting started announcements reminders notifications pin email password',
            'steps' => [
                'Log in to the <b>Client Portal</b> with your email and PIN.',
                'Your dashboard shows this year\'s <b>Income</b>, <b>Expenses</b>, <b>Transactions</b>, pending filings, and your account status.',
                'From the menu you can reach <b>Billing Statements</b>, <b>Collections</b>, <b>Documents</b>, <b>Profile</b>, and <b>Security</b>.',
                'Reminders appear as alerts (bell icon) in the portal, and announced notices appear on the website home page.',
            ],
            'actions' => [['label' => 'Open Dashboard', 'href' => route('client.dashboard')]],
        ],
        [
            'id' => 'sales',
            'title' => 'Income, Expenses &amp; Sales',
            'desc' => 'See how your numbers are recorded and what to do if something looks off.',
            'keywords' => 'sales income expenses transactions entries records accountant draft save discrepancy numbers figures filings',
            'steps' => [
                'Your accountant records your sales, expenses, and other transactions for you.',
                'These appear on your dashboard as <b>Income</b> and <b>Expenses</b> summaries for the current year.',
                '<b>Recent transactions</b> and <b>recent filings</b> are listed on the same page.',
                'Spot a discrepancy? Raise it under <b>Client Concerns</b> so our staff can correct it.',
            ],
            'actions' => [['label' => 'Open Dashboard', 'href' => route('client.dashboard')]],
        ],
        [
            'id' => 'billing',
            'title' => 'Billing Statements',
            'desc' => 'View statements by period or quarter, plus paid breakdowns.',
            'keywords' => 'billing statement statements invoice fee professional remittance quarter period view receipt paid total amount due',
            'steps' => [
                'Open <b>Billing Statements</b> from the menu.',
                'Pick the <b>period or quarter</b> to see the statements for that span.',
                'Each statement lists your <b>professional fee</b> and any <b>remittance</b> line items.',
                'Click <b>View statement</b> to open it; once paid, <b>View receipt</b> shows the paid breakdown and payment details.',
            ],
            'actions' => [['label' => 'View Billing', 'href' => route('client.billing.index')]],
        ],
        [
            'id' => 'announcements',
            'title' => 'Announcements &amp; Notifications',
            'desc' => 'Notices from Egliane and how you will hear about them.',
            'keywords' => 'announcements notices news updates reminders notifications bell push alerts messages broadcast',
            'steps' => [
                'Announcements from Egliane are posted on the website\'s <b>home page</b> under &ldquo;Posted Announcements&rdquo;.',
                'Billing and filing reminders arrive as <b>alerts</b> (bell icon) in the portal dashboard.',
                'You can enable browser <b>push notifications</b> from <b>Security Settings</b> so reminders reach you even when the portal is closed.',
            ],
            'actions' => [['label' => 'Security Settings', 'href' => route('security.index')]],
        ],
        [
            'id' => 'documents',
            'title' => 'Documents',
            'desc' => 'Open the files that Egliane has shared with you.',
            'keywords' => 'documents document files share download view softcopy copy watermarks files attachments',
            'steps' => [
                'Open <b>Documents</b> from the menu.',
                'Files shared with you by Egliane appear here.',
                'Open a document to <b>view</b> or <b>download</b> it (watermarks may be applied for security).',
            ],
            'actions' => [['label' => 'Open Documents', 'href' => route('client.documents.index')]],
        ],
        [
            'id' => 'collections',
            'title' => 'Collections &amp; Follow-ups',
            'desc' => 'Keep track of the payments you have made.',
            'keywords' => 'collections collection payment payments paid due receipt unpaid tracking follow ups receipts balance',
            'steps' => [
                'Open <b>Collections</b> from the menu.',
                'The payments you have made appear here for each period.',
                'Use <b>View receipt</b> on a paid statement to see its full breakdown and payment details.',
            ],
            'actions' => [['label' => 'View Collections', 'href' => route('client.collections.index')]],
        ],
        [
            'id' => 'other-services',
            'title' => 'Other Services',
            'desc' => 'One-off engagements are billed separately here.',
            'keywords' => 'other services consultancy bookkeeping one-off engagement additional extra billing receipts collections',
            'steps' => [
                'Open <b>Other Services</b> in the menu.',
                'One-off engagements (such as <b>consultancy</b> or <b>bookkeeping</b>) appear under <b>Billing Statements</b> there.',
                'Payments show under <b>Collections</b>, and paid services include a <b>View receipt</b> link.',
            ],
            'actions' => [
                ['label' => 'Other Services — Billing', 'href' => route('client.other-services.billing')],
                ['label' => 'Other Services — Collections', 'href' => route('client.other-services.collections')],
            ],
        ],
        [
            'id' => 'service-tracker',
            'title' => 'Service Tracker &amp; Concerns',
            'desc' => 'Follow your deliverables and reach Egliane staff.',
            'keywords' => 'service tracker concerns follow up deliverables progress status issues worry complaint reach out',
            'steps' => [
                'Use <b>Service Tracker</b> to follow the deliverables in progress.',
                'Use <b>Concerns</b> to raise a question or flag; it goes straight to Egliane staff.',
            ],
            'actions' => [
                ['label' => 'Service Tracker', 'href' => route('client.service-tracker.index')],
                ['label' => 'Concerns', 'href' => route('client.service-tracker.concerns')],
            ],
        ],
        [
            'id' => 'security',
            'title' => 'Profile &amp; Security',
            'desc' => 'Update your contacts, PIN, and biometric settings.',
            'keywords' => 'profile contact email address phone pin password biometric face security settings push notification two factor 2fa',
            'steps' => [
                'Open <b>Profile</b> to update your contact information.',
                'Set or change your <b>PIN</b> and enable <b>Face / biometric</b> login from <b>Security Settings</b>.',
                'Reminders can also be delivered as <b>push notifications</b> — manage them in <b>Security Settings</b>.',
                'Your registered name can only be changed by contacting Egliane.',
            ],
            'actions' => [
                ['label' => 'Open Profile', 'href' => route('client.profile.edit')],
                ['label' => 'Security Settings', 'href' => route('security.index')],
            ],
        ],
        [
            'id' => 'survey',
            'title' => 'Monthly Survey',
            'desc' => 'A quick check-in so we can keep improving.',
            'keywords' => 'survey feedback satisfaction monthly rating review feedback form',
            'steps' => [
                'After logging in you may be asked to complete a short <b>monthly survey</b>.',
                'It helps Egliane improve its service — it only takes a moment.',
            ],
            'actions' => [],
        ],
    ];

    $adminGuides = [
        [
            'id' => 'admin-started',
            'title' => 'Admin / Staff — Workspace',
            'desc' => 'Your workspace at a glance.',
            'keywords' => 'admin staff login workspace sidebar overview dashboard confidentiality confidential',
            'steps' => [
                'Log in with your <b>admin or staff</b> account.',
                'The <b>left sidebar</b> gives you access to every area of the workspace.',
                'Staff can manage most client work; a few controls (such as <b>Activity Logs</b>, deleting accounts, and <b>marking statements paid</b>) are reserved for admins.',
                'Client data is <b>confidential</b> — keep it private and never share it.',
            ],
            'actions' => [['label' => 'Open Dashboard', 'href' => route('admin.dashboard')]],
        ],
        [
            'id' => 'clients',
            'title' => 'Managing Clients',
            'desc' => 'Add, edit, and manage client accounts.',
            'keywords' => 'client clients add create new edit view account status active on hold closed impersonate login as client taxpayer business contact billing',
            'steps' => [
                'Open <b>Clients</b> and click <b>Add Client</b> to create a new account.',
                'Fill in <b>business</b> and <b>taxpayer</b> details, plus primary and second contact info.',
                'Use <b>View</b> to see all client details and quick actions (impersonate, status, payment status).',
                'Change account <b>status</b> (Active / On-hold / Closed) from the client view.',
                '<b>Login as Client</b> previews the app exactly as that client sees it — exit anytime from the top banner.',
            ],
            'actions' => [
                ['label' => 'Manage Clients', 'href' => route('admin.clients.index')],
                ['label' => 'Add Client', 'href' => route('admin.clients.create')],
            ],
        ],
        [
            'id' => 'billing-admin',
            'title' => 'Admin — Billing Statements',
            'desc' => 'Create statements and finalize them so clients can see them.',
            'keywords' => 'billing statement statements create draft finalize fee categories items total client period remittance professional admin settings',
            'steps' => [
                'Open <b>Billing Statements</b> then <b>Create Statement</b>.',
                'Pick the <b>client</b> and <b>billing period</b>. Preset fee categories auto-fill; use <b>+ Add another item</b> for one-off charges.',
                'The <b>total updates automatically</b> from every line item amount.',
                'Save as <b>Draft</b> first, then <b>Finalize</b> to make it visible to the client.',
                'Adjust <b>fee presets</b> and <b>payment methods</b> under Billing Settings.',
            ],
            'actions' => [
                ['label' => 'Billing Statements', 'href' => route('admin.billing.index')],
                ['label' => 'Create Statement', 'href' => route('admin.billing.create')],
                ['label' => 'Billing Settings', 'href' => route('admin.billing.settings')],
            ],
        ],
        [
            'id' => 'collections-admin',
            'title' => 'Admin — Collections &amp; Follow-ups',
            'desc' => 'Get unpaid statements settled.',
            'keywords' => 'collections unpaid paid mark paid remind reminder due date total filter status message csv email messenger admin',
            'steps' => [
                'Open <b>Collections</b> to see unpaid statements with their due dates and totals.',
                'Filter by <b>period or status</b> to focus on what needs action.',
                'Mark a statement <b>Paid</b> once payment is received (with the date paid) — <b>admin only</b>.',
                'Use <b>Send reminder</b> to nudge unpaid statements by the reminder channel.',
                'Share a receipt via <b>CSV</b>, email, or Messenger from the receipt view.',
            ],
            'actions' => [['label' => 'Open Collections', 'href' => route('admin.collections.index')]],
        ],
        [
            'id' => 'distribution',
            'title' => 'Distribution &amp; BIR Forms',
            'desc' => 'Deliver and log documents to clients.',
            'keywords' => 'distribution deliver delivery bir forms softcopy map location documents download log tracking forms',
            'steps' => [
                'Open a client\'s <b>Distribution</b> page to log delivered BIR forms and set delivery entries.',
                'Upload <b>softcopy</b> documents and update the map location as needed.',
                '<b>BIR Forms</b> lists exactly which forms each client needs.',
            ],
            'actions' => [
                ['label' => 'Document Distribution', 'href' => route('admin.distribution.index')],
                ['label' => 'BIR Forms', 'href' => route('admin.bir-forms.index')],
            ],
        ],
        [
            'id' => 'other-services-admin',
            'title' => 'Admin — Other Services',
            'desc' => 'Billing for non-monthly engagements.',
            'keywords' => 'other services consultancy bookkeeping one-off engagement fill up form billing receipt collections admin',
            'steps' => [
                'Manage non-monthly engagements (such as one-off <b>bookkeeping</b> or <b>consultancy</b>) under <b>Other Services</b>.',
                'Create their <b>billing statements</b> and receipts here; payments are collected on the <b>Collections</b> tab.',
            ],
            'actions' => [['label' => 'Other Services', 'href' => route('admin.other-services.billing')]],
        ],
        [
            'id' => 'system-settings',
            'title' => 'System Settings',
            'desc' => 'Team accounts, announcements, and workspace preferences.',
            'keywords' => 'system settings team accounts users announcements chatbot about activity logs billing settings fee rates payment methods',
            'steps' => [
                '<b>Team Accounts</b> — add and manage admin/staff portal accounts.',
                '<b>Announcements</b> — publish notices that appear on the website and to clients.',
                '<b>Billing Settings</b> — manage fee presets and payment methods.',
                '<b>Chatbot</b> — tune the automated assistant\'s responses.',
                '<b>About</b> — manage the public About page content, certificates, and team.',
                '<b>Activity Logs</b> — review a trace of important actions for accountability (<b>admin only</b>).',
            ],
            'actions' => [
                ['label' => 'Team Accounts', 'href' => route('admin.users.index')],
                ['label' => 'Announcements', 'href' => route('admin.announcements.index')],
                ['label' => 'Activity Logs', 'href' => route('admin.activity-logs'), 'admin' => true],
            ],
        ],
    ];

    foreach ($clientGuides as &$cg) { $cg['audience'] = 'client'; }
    unset($cg);
    foreach ($adminGuides as &$ag) { $ag['audience'] = 'admin'; }
    unset($ag);

    $guides = $showAdminGuides ? array_merge($clientGuides, $adminGuides) : $clientGuides;
@endphp
<div class="help-page" id="helpPage">
    <div class="container">

        <header class="help-head">
            <p class="help-eyebrow">Help Center</p>
            <h1>How to Use This System</h1>
            <p class="help-sub">A simple, step-by-step manual for the Client Portal and the Admin / Staff workspace. Open a guide below, or search for exactly what you need.</p>
            <div class="help-search" role="search">
                <span class="help-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                </span>
                <input type="search" id="helpSearchInput" class="help-search-input" placeholder="Search the manual&hellip; billing, receipt, quarter" aria-label="Search the manual" autocomplete="off" spellcheck="false">
                <button type="button" class="help-search-clear" id="helpSearchClear" aria-label="Clear search" hidden>&#10005;</button>
            </div>
            <p class="help-results-summary" id="helpResultsSummary" aria-live="polite" hidden></p>
        </header>

        @if (! $isClient)
        <section class="help-quickstart">
            <div class="help-section-head"><h2>Start here</h2></div>
            <div class="help-quickstart-grid">
                <a class="help-quick-card hq-client" href="#guides" data-help-filter="client">
                    <span class="hq-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <b>Client users</b>
                    <span>Sales, billing, documents, collections, security, and more.</span>
                    <em>Open the Client guide &rarr;</em>
                </a>
                <a class="help-quick-card hq-admin" href="#guides" data-help-filter="admin">
                    <span class="hq-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </span>
                    <b>Admin / Staff</b>
                    <span>Clients, billing, collections, distribution, and settings.</span>
                    <em>Open the Admin guide &rarr;</em>
                </a>
            </div>
        </section>
        @endif

        <section class="help-tasks">
            <div class="help-section-head"><h2>What do you want to do?</h2></div>
            <div class="help-tasks-grid">

                @if ($isClient)
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/><path d="M12 12l3-2"/></svg>
                    </span>
                    <b>Income &amp; expenses</b>
                    <span>See this year&rsquo;s income, expenses, and transactions at a glance.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('client.dashboard') }}">Open Dashboard</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
                    </span>
                    <b>Billing Statements</b>
                    <span>View statements, quarters, and paid receipts.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('client.billing.index') }}">View Billing</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
                    </span>
                    <b>Documents</b>
                    <span>Open the files Egliane has shared with you.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('client.documents.index') }}">Open Documents</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
                    </span>
                    <b>Collections &amp; Follow-ups</b>
                    <span>Track the payments you have made.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('client.collections.index') }}">View Collections</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <b>Profile &amp; Security</b>
                    <span>Update contacts, PIN, and biometric login.</span>
                    <div class="ht-actions">
                        <a class="btn btn-primary btn-sm" href="{{ route('client.profile.edit') }}">Open Profile</a>
                        <a class="btn btn-outline btn-sm" href="{{ route('security.index') }}">Security</a>
                    </div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <b>Contact support</b>
                    <span>Ask the built-in assistant, available on every page.</span>
                    <div class="ht-actions"><button type="button" class="btn btn-sky btn-sm" data-help-assistant>Open assistant</button></div>
                </div>

                @elseif ($isStaffOrAdmin)
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <b>Manage Clients</b>
                    <span>Add, edit, and manage client accounts.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.clients.index') }}">Client List</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
                    </span>
                    <b>Billing Statements</b>
                    <span>Create and finalize statements for clients.</span>
                    <div class="ht-actions">
                        <a class="btn btn-primary btn-sm" href="{{ route('admin.billing.index') }}">Open Billing</a>
                        <a class="btn btn-outline btn-sm" href="{{ route('admin.billing.create') }}">Create</a>
                    </div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
                    </span>
                    <b>Collections &amp; Follow-ups</b>
                    <span>Track unpaid statements and send reminders.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.collections.index') }}">Open Collections</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11v2a1 1 0 0 0 1 1h2l4 4V6l-4 4H4a1 1 0 0 0-1 1z"/><path d="M14 8a5 5 0 0 1 0 8"/><path d="M17 5a9 9 0 0 1 0 14"/></svg>
                    </span>
                    <b>Announcements</b>
                    <span>Post notices that appear on the website and to clients.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.announcements.index') }}">Manage</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
                    </span>
                    <b>Document Distribution</b>
                    <span>Deliver BIR forms and other documents.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.distribution.index') }}">Open</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </span>
                    <b>BIR Forms</b>
                    <span>See which forms each client needs.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.bir-forms.index') }}">Open</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <b>Client Feedback</b>
                    <span>Review the monthly client surveys.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.surveys.index') }}">Review</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>
                    </span>
                    <b>Team Accounts</b>
                    <span>Manage admin and staff portal accounts.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.users.index') }}">Manage</a></div>
                </div>

                @else
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/><path d="M12 12l3-2"/></svg>
                    </span>
                    <b>Income &amp; expenses</b>
                    <span>See this year&rsquo;s income, expenses, and transactions.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
                    </span>
                    <b>Billing Statements</b>
                    <span>Read how statements and receipts are organized.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
                    </span>
                    <b>Documents</b>
                    <span>Files shared by Egliane appear here.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
                    </span>
                    <b>Collections &amp; Follow-ups</b>
                    <span>Track the payments you have made.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <b>Profile &amp; Security</b>
                    <span>Update contacts, PIN, and biometric login.</span>
                    <div class="ht-actions"><a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a></div>
                </div>
                <div class="help-task-card">
                    <span class="ht-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <b>Contact support</b>
                    <span>Ask the built-in assistant, available on every page.</span>
                    <div class="ht-actions"><button type="button" class="btn btn-sky btn-sm" data-help-assistant>Open assistant</button></div>
                </div>
                @endif

            </div>
        </section>

        <section class="help-guides" id="guides">
            <div class="help-section-head help-guides-head">
                <h2>Guides</h2>
                <div class="help-tabs" role="group" aria-label="Filter guides by audience">
                    <button type="button" class="help-tab" data-help-tab="all" aria-pressed="true">All guides</button>
                    <button type="button" class="help-tab" data-help-tab="client" aria-pressed="false">Client</button>
                    @if (! $isClient)
                    <button type="button" class="help-tab" data-help-tab="admin" aria-pressed="false">Admin / Staff</button>
                    @endif
                </div>
            </div>

            <p class="help-progress" id="helpProgress" hidden>
                <span class="help-progress-track" aria-hidden="true"><span class="help-progress-fill" id="helpProgressFill"></span></span>
                <span id="helpProgressLabel" aria-live="polite"></span>
            </p>

            <div class="help-guides-list">
                @foreach ($guides as $index => $guide)
                    @php $openGuide = $loop->first; @endphp
                    <article class="help-guide{{ $openGuide ? ' open' : '' }}" id="guide-{{ $guide['id'] }}" data-audience="{{ $guide['audience'] }}" data-keywords="{{ $guide['keywords'] }}">
                        <h3 class="help-guide-head">
                            <button type="button" class="help-guide-toggle" aria-expanded="{{ $openGuide ? 'true' : 'false' }}" aria-controls="panel-guide-{{ $guide['id'] }}" id="btn-guide-{{ $guide['id'] }}">
                                <span class="help-guide-num" aria-hidden="true"></span>
                                <span class="help-guide-title">{!! $guide['title'] !!}</span>
                                <span class="help-guide-desc">{!! $guide['desc'] !!}</span>
                                <span class="help-guide-caret" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </span>
                            </button>
                        </h3>
                        <div class="help-guide-panel" id="panel-guide-{{ $guide['id'] }}" role="region" aria-labelledby="btn-guide-{{ $guide['id'] }}">
                            <div class="help-guide-panel-inner">
                                <div class="help-guide-body">
                                    @if (! empty($guide['steps']))
                                    <ol class="help-steps">
                                        @foreach ($guide['steps'] as $step)
                                            <li class="help-step"><span class="help-step-text">{!! $step !!}</span></li>
                                        @endforeach
                                    </ol>
                                    @endif
                                    @if (! empty($guide['actions']) && ($guide['audience'] === 'admin' ? $isStaffOrAdmin : $isClient))
                                        <div class="help-guide-actions">
                                            @foreach ($guide['actions'] as $action)
                                                @if (isset($action['admin']) && $action['admin'] && ! ($user && $user->isAdmin()))
                                                    @continue
                                                @endif
                                                <a class="btn btn-primary btn-sm" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                                            @endforeach
                                        </div>
                                    @elseif (! $user && ! empty($guide['actions']))
                                        <p class="help-guide-note">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                                            Sign in as @if ($guide['audience'] === 'admin') an admin or staff member @else a client @endif to use the portal.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="help-noresults" id="helpNoResults" hidden>
                <span class="hn-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/><path d="M8.6 8.6l4.8 4.8M13.4 8.6l-4.8 4.8"/></svg>
                </span>
                <b>No guides found</b>
                <p>Try a different keyword &mdash; for example:
                    @foreach (['billing', 'sales', 'documents', 'collections', 'payment', 'receipt', 'quarter', 'client', 'admin', 'staff', 'announcement'] as $term)
                        <button type="button" class="help-nr-term" data-help-term="{{ $term }}">{{ $term }}</button>@if (! $loop->last)<span class="help-nr-sep">&middot;</span>@endif
                    @endforeach
                </p>
            </div>
        </section>

        <section class="help-more">
            <div>
                <h2>Still need help?</h2>
                <p>Ask the built-in assistant &mdash; it&rsquo;s available on every page of the portal (bottom-right corner).</p>
            </div>
            <button type="button" class="btn" id="helpOpenAssistant" data-help-assistant>Open the assistant</button>
        </section>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('helpPage');
    if (!root) return;

    var guides = Array.prototype.slice.call(root.querySelectorAll('.help-guide'));
    var tabs = Array.prototype.slice.call(root.querySelectorAll('.help-tab'));
    var searchInput = document.getElementById('helpSearchInput');
    var searchClear = document.getElementById('helpSearchClear');
    var resultsSummary = document.getElementById('helpResultsSummary');
    var noResults = document.getElementById('helpNoResults');
    var progressWrap = document.getElementById('helpProgress');
    var progressFill = document.getElementById('helpProgressFill');
    var progressLabel = document.getElementById('helpProgressLabel');
    var assistantButtons = Array.prototype.slice.call(document.querySelectorAll('[data-help-assistant]'));
    var filterLinks = Array.prototype.slice.call(document.querySelectorAll('[data-help-filter]'));

    var PROGRESS_KEY = 'egliane.help.viewed.v1';
    var activeTab = 'all';
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function getEl(scope, selector) { return scope.querySelector(selector); }
    function getEls(scope, selector) { return Array.prototype.slice.call(scope.querySelectorAll(selector)); }

    /* ---------- Pristine snapshots for highlight restore ---------- */
    var pristine = {};
    guides.forEach(function (g) {
        pristine[g.id] = {
            title: getEl(g, '.help-guide-title').innerHTML,
            desc: getEl(g, '.help-guide-desc').innerHTML,
            body: getEl(g, '.help-guide-body').innerHTML,
        };
        g.dataset.wasOpen = g.classList.contains('open') ? '1' : '0';
    });

    function restorePristine(g) {
        getEl(g, '.help-guide-title').innerHTML = pristine[g.id].title;
        getEl(g, '.help-guide-desc').innerHTML = pristine[g.id].desc;
        getEl(g, '.help-guide-body').innerHTML = pristine[g.id].body;
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightGuide(g, query) {
        var re = new RegExp('(' + escapeRegExp(query) + ')', 'gi');
        getEl(g, '.help-guide-title').innerHTML = pristine[g.id].title.replace(re, '<mark class="help-mark">$1</mark>');
        getEl(g, '.help-guide-desc').innerHTML = pristine[g.id].desc.replace(re, '<mark class="help-mark">$1</mark>');
        getEl(g, '.help-guide-body').innerHTML = pristine[g.id].body.replace(re, '<mark class="help-mark">$1</mark>');
    }

    function visible(g) {
        return activeTab === 'all' || g.dataset.audience === activeTab;
    }

    function setOpen(g, open) {
        g.classList.toggle('open', open);
        var btn = getEl(g, '.help-guide-toggle');
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) markViewed(g);
    }

    function markViewed(g) {
        var store = readProgress();
        if (store.indexOf(g.id) === -1) {
            store.push(g.id);
            try { localStorage.setItem(PROGRESS_KEY, JSON.stringify(store)); } catch (e) {}
        }
        refreshProgress();
    }

    function readProgress() {
        try {
            var raw = localStorage.getItem(PROGRESS_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function refreshProgress() {
        var shown = guides.filter(function (g) { return !g.hidden; });
        if (!shown.length) { progressWrap.hidden = true; return; }
        var store = readProgress();
        var done = shown.filter(function (g) { return store.indexOf(g.id) !== -1; }).length;
        var pct = Math.round((done / shown.length) * 100);
        progressFill.style.width = pct + '%';
        if (done >= shown.length) {
            progressLabel.textContent = 'All ' + shown.length + ' guides viewed';
            progressWrap.hidden = false;
        } else if (done > 0) {
            progressLabel.textContent = done + ' of ' + shown.length + ' guides viewed';
            progressWrap.hidden = false;
        } else {
            progressWrap.hidden = true;
        }
    }

    /* ---------- Search ---------- */
    function refreshVisibility() {
        var q = searchInput.value.trim();
        var count = 0;
        guides.forEach(function (g) {
            var show = visible(g) && !(q !== '' && g.classList.contains('help-hidden-search'));
            g.hidden = !show;
            if (show) count++;
        });
        searchClear.hidden = q === '';
        if (q === '') {
            noResults.hidden = true;
            resultsSummary.hidden = true;
        } else {
            resultsSummary.textContent = count === 1
                ? '1 guide matches "' + q + '"'
                : count + ' guides match "' + q + '"';
            resultsSummary.hidden = count === 0;
            noResults.hidden = count !== 0;
        }
        refreshProgress();
    }

    function runSearch(raw) {
        var q = (raw || '').trim().replace(/\s+/g, ' ').toLowerCase();
        var searching = q !== '';
        guides.forEach(function (g) {
            restorePristine(g);
            if (!searching) {
                g.classList.remove('help-hidden-search');
                setOpen(g, g.dataset.wasOpen === '1');
                return;
            }
            var bodyText = getEl(g, '.help-guide-body').innerText || '';
            var hay = ((g.dataset.keywords || '') + ' ' + bodyText).toLowerCase();
            var matched = hay.indexOf(q) !== -1;
            g.classList.toggle('help-hidden-search', !matched);
            if (matched) {
                setOpen(g, true);
                highlightGuide(g, q);
            } else {
                setOpen(g, false);
            }
        });
        refreshVisibility();
    }

    var searchTimer = null;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { runSearch(searchInput.value); }, 120);
    });
    searchClear.addEventListener('click', function () {
        clearTimeout(searchTimer);
        searchInput.value = '';
        runSearch('');
        searchInput.focus();
    });

    document.querySelectorAll('[data-help-term]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            clearTimeout(searchTimer);
            searchInput.value = btn.dataset.helpTerm;
            runSearch(searchInput.value);
            searchInput.focus();
        });
    });

    /* ---------- Tabs ---------- */
    function setTab(tab) {
        activeTab = tab;
        tabs.forEach(function (b) {
            var on = b.dataset.helpTab === tab;
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (searchInput.value.trim()) {
            runSearch(searchInput.value);
        } else {
            guides.forEach(function (g) { g.classList.remove('help-hidden-search'); });
            refreshVisibility();
        }
    }
    tabs.forEach(function (b) {
        b.addEventListener('click', function () { setTab(b.dataset.helpTab); });
    });

    /* ---------- Accordion ---------- */
    guides.forEach(function (g) {
        var toggle = getEl(g, '.help-guide-toggle');
        toggle.addEventListener('click', function () {
            var willOpen = !g.classList.contains('open');
            setOpen(g, willOpen);
            g.dataset.wasOpen = willOpen ? '1' : '0';
        });
    });

    /* ---------- Deep links & scroll ---------- */
    function scrollToGuide(g) {
        g.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
    }

    function openGuideById(id) {
        var g = document.getElementById(id);
        if (!g || !g.classList.contains('help-guide')) return;
        if (activeTab !== 'all' && !visible(g)) setTab(g.dataset.audience);
        g.classList.remove('help-hidden-search');
        g.hidden = false;
        setOpen(g, true);
        scrollToGuide(g);
    }

    filterLinks.forEach(function (a) {
        a.addEventListener('click', function () {
            if (a.dataset.helpFilter) setTab(a.dataset.helpFilter);
        });
    });

    function handleHash() {
        var id = (window.location.hash || '').replace('#', '');
        if (id) openGuideById(id);
    }
    window.addEventListener('hashchange', handleHash);
    if (window.location.hash) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
        handleHash();
    }

    /* ---------- Assistant ---------- */
    function openAssistant() {
        var chatbot = window.egliane && window.egliane.chatbot;
        if (chatbot && typeof chatbot.toggle === 'function') {
            chatbot.toggle(true);
            return;
        }
        var fab = document.getElementById('chatFab');
        if (fab) fab.click();
    }
    assistantButtons.forEach(function (btn) { btn.addEventListener('click', openAssistant); });

    refreshProgress();
});
</script>
@endpush