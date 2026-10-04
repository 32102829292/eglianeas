@php
    $user = auth()->user();
    $missingCount = \App\Services\DailyJournalService::getMissingCountForUser($user->id);
    $hasMissingToday = \App\Services\DailyJournalService::hasMissingForToday($user->id);
@endphp

@if ($user->isOperational() && $hasMissingToday)
<style>
    .daily-journal-alert {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #fcd34d;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.15);
        animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .daily-journal-alert .alert-icon {
        flex-shrink: 0;
        width: 2.25rem;
        height: 2.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fbbf24;
        border-radius: 50%;
        color: #fff;
    }
    .daily-journal-alert .alert-icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }
    .daily-journal-alert .alert-text {
        flex: 1;
        min-width: 0;
    }
    .daily-journal-alert .alert-text strong {
        display: block;
        font-size: 1rem;
        color: #92400e;
        margin-bottom: 0.25rem;
    }
    .daily-journal-alert .alert-text p {
        margin: 0;
        font-size: 0.875rem;
        color: #78350f;
        line-height: 1.5;
    }
    .daily-journal-alert .btn {
        flex-shrink: 0;
        margin-top: 0.25rem;
    }
    .daily-journal-alert .alert-dismiss {
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        border-radius: 8px;
        color: #92400e;
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.2s, background-color 0.2s;
    }
    .daily-journal-alert .alert-dismiss:hover {
        opacity: 1;
        background-color: rgba(146, 64, 14, 0.1);
    }
    .daily-journal-alert .alert-dismiss svg {
        width: 1.25rem;
        height: 1.25rem;
    }
    @media (max-width: 640px) {
        .daily-journal-alert {
            flex-direction: column;
            gap: 0.75rem;
        }
        .daily-journal-alert .alert-dismiss {
            align-self: flex-end;
            margin-top: -0.5rem;
        }
    }
</style>
<div class="daily-journal-alert" id="dailyJournalAlert">
    <div class="alert-content">
        <div class="alert-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="alert-text">
            <strong>Daily Journal Missing</strong>
            <p>Your Daily Accomplishment Report for {{ now()->format('F j, Y') }} has not been submitted.</p>
        </div>
        <a href="{{ route('daily-journal.create') }}" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Submit Journal
        </a>
    </div>
    <button type="button" class="alert-dismiss" aria-label="Dismiss" onclick="dismissDailyJournalAlert()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
</div>
@endif

<script>
function dismissDailyJournalAlert() {
    const alert = document.getElementById('dailyJournalAlert');
    if (alert) {
        alert.style.display = 'none';
        // Store dismissal in sessionStorage so it persists for this session only
        sessionStorage.setItem('dailyJournalAlertDismissed', new Date().toISOString().split('T')[0]);
    }
}

// Check if already dismissed for today
(function() {
    const dismissed = sessionStorage.getItem('dailyJournalAlertDismissed');
    const today = new Date().toISOString().split('T')[0];
    if (dismissed === today) {
        const alert = document.getElementById('dailyJournalAlert');
        if (alert) alert.style.display = 'none';
    }
})();
</script>