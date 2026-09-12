@php
    $user = auth()->user();
@endphp

@if ($user)
@php
    $isStaffOrAdmin = $user->isAdmin() || $user->isStaff();

    $clientSteps = [
        [
            'title'     => 'Welcome to Egliane Accounting Services!',
            'desc'      => 'This is your dashboard where you can quickly see your account status, important updates, and recent transactions at a glance.',
            'selectors' => ['.page-head', 'main.dash-main > *'],
        ],
        [
            'title'     => 'Your Billing Statements',
            'desc'      => 'View the billing statements Egliane has prepared for you, along with their status and billable history.',
            'selectors' => ['aside.dash-nav a[href*="/client/billing"]', '#dashDrawer a[href*="/client/billing"]'],
        ],
        [
            'title'     => 'Track Your Payments',
            'desc'      => 'See what you owe, check payment records and receipts, and keep track of collections for your accounts.',
            'selectors' => ['aside.dash-nav a[href*="/client/collections"]', '#dashDrawer a[href*="/client/collections"]'],
        ],
        [
            'title'     => 'Your Profile',
            'desc'      => 'Manage your account information, contact details, PIN, and sign-in security from your profile.',
            'selectors' => ['aside.dash-nav a[href*="/client/profile"]', '#dashDrawer a[href*="/client/profile"]'],
        ],
        [
            'title'     => 'Notifications',
            'desc'      => 'Important account and billing updates appear here. Tap the bell anytime to see what needs your attention.',
            'selectors' => ['#bellWrap'],
        ],
        [
            'title'     => 'Need Help?',
            'desc'      => 'Use the assistant whenever you need help finding information or navigating the system.',
            'selectors' => ['#chatFab'],
        ],
        [
            'title'    => 'You\'re All Set!',
            'desc'     => 'You now know the main places of your portal. You can replay this tour anytime from the Help page.',
            'final'    => true,
            'confirm'  => 'Get Started',
        ],
    ];

    $adminSteps = [
        [
            'title'     => 'Welcome to Egliane!',
            'desc'      => 'Your dashboard gives you a quick overview of your accounting operations, client activity, and recent updates.',
            'selectors' => ['.page-head', 'main.dash-main > *'],
        ],
        [
            'title'     => 'Manage Clients',
            'desc'      => 'View, search, and manage client records from this section.',
            'selectors' => ['aside.dash-nav a[href*="/admin/clients"]', '#dashDrawer a[href*="/admin/clients"]'],
        ],
        [
            'title'     => 'Manage Billing',
            'desc'      => 'Create and manage billing statements, set their status, and monitor billable history.',
            'selectors' => ['aside.dash-nav a[href*="/admin/billing"]', '#dashDrawer a[href*="/admin/billing"]'],
        ],
        [
            'title'     => 'Track Collections',
            'desc'      => 'Monitor payments, outstanding balances, follow-ups, and send payment reminders to clients.',
            'selectors' => ['aside.dash-nav a[href*="/admin/collections"]', '#dashDrawer a[href*="/admin/collections"]'],
        ],
        [
            'title'     => 'BIR Forms',
            'desc'      => 'Track which BIR forms each client needs and their current status.',
            'selectors' => ['aside.dash-nav a[href*="/admin/bir-forms"]', '#dashDrawer a[href*="/admin/bir-forms"]'],
        ],
        [
            'title'     => 'Reports',
            'desc'      => 'Access reports and downloadable records for your accounting operations.',
            'selectors' => ['aside.dash-nav a[href*="/admin/reports"]', '#dashDrawer a[href*="/admin/reports"]'],
        ],
        [
            'title'     => 'Notifications',
            'desc'      => 'Stay updated with important system events, reminder activity, and client follow-ups.',
            'selectors' => ['#bellWrap'],
        ],
        [
            'title'     => 'Need Assistance?',
            'desc'      => 'Use the assistant whenever you need help navigating the system or finding a tool.',
            'selectors' => ['#chatFab'],
        ],
        [
            'title'    => 'You\'re Ready!',
            'desc'     => 'You now know the main tools available to you. You can replay this tour anytime from the Help page.',
            'final'    => true,
            'confirm'  => 'Finish',
        ],
    ];

    $steps = $isStaffOrAdmin ? $adminSteps : $clientSteps;
@endphp
<div class="eas-onboard" id="easOnboarding" data-eas-onboarding data-role="{{ $user->role }}" data-version="1" hidden>
    <div class="eas-onboard-top" aria-hidden="true"></div>
    <div class="eas-onboard-left" aria-hidden="true"></div>
    <div class="eas-onboard-right" aria-hidden="true"></div>
    <div class="eas-onboard-bottom" aria-hidden="true"></div>
    <div class="eas-onboard-ring" aria-hidden="true"></div>

    <section class="eas-onboard-card" id="easOnboardCard" role="dialog" aria-modal="true" aria-labelledby="easOnboardTitle" aria-describedby="easOnboardDesc">
        <div class="eas-onboard-card-top">
            <span class="eas-onboard-progress" id="easOnboardProgress" aria-live="polite"></span>
            <button type="button" class="eas-onboard-close" id="easOnboardClose" aria-label="Close tutorial">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="eas-onboard-dots" id="easOnboardDots" aria-hidden="true"></div>
        <h2 class="eas-onboard-title" id="easOnboardTitle"></h2>
        <p class="eas-onboard-desc" id="easOnboardDesc"></p>
        <div class="eas-onboard-actions">
            <button type="button" class="eas-onboard-skip" id="easOnboardSkip">Skip</button>
            <button type="button" class="eas-onboard-back" id="easOnboardBack">Back</button>
            <button type="button" class="eas-onboard-next" id="easOnboardNext">Next</button>
        </div>
    </section>

    <script type="application/json" id="easOnboardSteps">{!! json_encode(['steps' => $steps], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
</div>
@endif