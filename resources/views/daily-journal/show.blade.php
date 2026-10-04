@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report — '.$journal->user->name)

@section('content')
@php
    $isMissing = $journal->isMissing();

    $backUrl = auth()->user()->isAdmin()
        ? route('admin.daily-journal.admin-monitor', ['date' => $journal->required_date->format('Y-m-d')])
        : route('admin.daily-journal.supervisor-monitor', ['date' => $journal->required_date->format('Y-m-d')]);

    // Deleting is only ever offered for a report that actually holds something.
    $canDelete = auth()->user()->isAdmin() && ! $isMissing;

    // A journal is three free-text answers plus optional evidence, so completion
    // is simply how many of those answers the employee filled in. Nothing is
    // invented here and nothing new is stored.
    $sections = [
        [
            'field' => 'achievements',
            'title' => 'Accomplishments',
            'subtitle' => 'What was completed today.',
            'empty' => 'No accomplishments were submitted for this day.',
            'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        ],
        [
            'field' => 'problems_encountered',
            'title' => 'Problems Encountered',
            'subtitle' => 'Issues that came up during the day.',
            'empty' => 'No problems were reported for this day.',
            'icon' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        ],
        [
            'field' => 'suggested_solutions',
            'title' => 'Solutions / Action Taken',
            'subtitle' => 'How those issues were handled.',
            'empty' => 'No solutions or actions were submitted for this day.',
            'icon' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>',
        ],
    ];

    $filled = collect($sections)->filter(fn ($s) => trim((string) $journal->{$s['field']}) !== '')->count();
    $completion = (int) round($filled / count($sections) * 100);
@endphp

{{-- Header: the employee name leads, because that is what identifies the report. --}}
<header class="djr-head">
    <div class="djr-head-main">
        <p class="djr-eyebrow">Daily Accomplishment Report</p>
        <h1>
            {{ $journal->user->name }}
            <span class="djr-head-role">&middot; {{ ucfirst($journal->user->role) }}</span>
        </h1>
        <p class="djr-head-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            {{ $journal->required_date->format('F j, Y') }}
        </p>
    </div>
    <div class="djr-head-actions">
        <span class="djr-status djr-status--{{ $journal->status }}">
            {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
        </span>
        <a href="{{ $backUrl }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back
        </a>
    </div>
</header>

{{-- At-a-glance strip. Deliberately a single compact row, not a row of big cards. --}}
<section class="djr-summary" aria-label="Report status summary">
    <div class="djr-summary-cell">
        <span class="djr-label">Report Status</span>
        <span class="djr-summary-value">{{ $journal->getStatusLabel() }}</span>
    </div>
    <div class="djr-summary-cell">
        <span class="djr-label">Submitted</span>
        <span class="djr-summary-value">{{ $journal->submitted_at ? $journal->submitted_at->format('M j, g:i A') : 'Not submitted' }}</span>
    </div>
    <div class="djr-summary-cell">
        <span class="djr-label">Created</span>
        <span class="djr-summary-value">{{ $journal->created_at->format('M j, g:i A') }}</span>
    </div>
    <div class="djr-summary-cell">
        <span class="djr-label">Completion</span>
        <span class="djr-summary-value" title="{{ $filled }} of {{ count($sections) }} report sections answered">{{ $completion }}%</span>
    </div>
</section>

<div class="djr-grid">
    {{-- Row 1, main column. --}}
    <section class="djr-card">
        <header class="djr-card-head">
            <h2 class="djr-card-title">Employee Information</h2>
        </header>
        <div class="djr-card-body">
            <dl class="djr-info">
                <div class="djr-info-cell">
                    <dt>Name</dt>
                    <dd>{{ $journal->user->name }}</dd>
                </div>
                <div class="djr-info-cell">
                    <dt>Role</dt>
                    <dd><span class="djr-role">{{ ucfirst($journal->user->role) }}</span></dd>
                </div>
                <div class="djr-info-cell">
                    <dt>Date</dt>
                    <dd>{{ $journal->required_date->format('F j, Y') }}</dd>
                </div>
                <div class="djr-info-cell">
                    <dt>Status</dt>
                    <dd>
                        <span class="djr-status djr-status--{{ $journal->status }}">
                            {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
                        </span>
                    </dd>
                </div>
                <div class="djr-info-cell">
                    <dt>Submitted</dt>
                    <dd>{{ $journal->submitted_at ? $journal->submitted_at->format('F j, Y g:i A') : 'Not submitted' }}</dd>
                </div>
                <div class="djr-info-cell">
                    <dt>Created</dt>
                    <dd>{{ $journal->created_at->format('F j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- Row 1, sidebar on desktop; falls directly under Employee Information on
         tablet and mobile because the grid collapses to a single column. --}}
    <aside class="djr-side">
        <section class="djr-card">
            <header class="djr-card-head">
                <h2 class="djr-card-title">Quick Actions</h2>
            </header>
            <div class="djr-card-body">
                @if ($canDelete)
                    <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="inline-form" onsubmit="return confirm('Delete this journal entry? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Delete Entry
                        </button>
                    </form>
                @else
                    <div class="djr-empty">
                        <span class="djr-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <p>No actions are available for this report.</p>
                    </div>
                @endif
            </div>
        </section>

        @if ($journal->reminder_count > 0)
            <section class="djr-card">
                <header class="djr-card-head">
                    <h2 class="djr-card-title">Reminders</h2>
                </header>
                <div class="djr-card-body">
                    <p class="djr-side-note">
                        Reminded {{ $journal->reminder_count }} time{{ $journal->reminder_count === 1 ? '' : 's' }}.
                        @if ($journal->reminded_at)
                            Last {{ $journal->reminded_at->diffForHumans() }}.
                        @endif
                    </p>
                </div>
            </section>
        @endif
    </aside>

    {{-- Row 2, main column. The sidebar cell below row 1 stays empty, which is
         what keeps the sidebar as short as its own content. --}}
    <div class="djr-sections">
        @if ($isMissing)
            <div class="djr-callout">
                <span class="djr-callout-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
                <div class="djr-callout-body">
                    <p class="djr-callout-title">Report Status &mdash; {{ $journal->getStatusLabel() }}</p>
                    <p>This accomplishment report has not been submitted.</p>
                    <a href="{{ $backUrl }}" class="btn btn-outline btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Back to Daily Accomplishments
                    </a>
                </div>
            </div>
        @endif

        @foreach ($sections as $section)
            @php($answer = trim((string) $journal->{$section['field']}))
            <section class="djr-card">
                <header class="djr-card-head">
                    <span class="djr-section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $section['icon'] !!}</svg>
                    </span>
                    <div>
                        <h2 class="djr-card-title">{{ $section['title'] }}</h2>
                        <p class="djr-card-sub">{{ $section['subtitle'] }}</p>
                    </div>
                </header>
                <div class="djr-card-body">
                    @if ($answer === '')
                        <div class="djr-empty">
                            <span class="djr-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <p>{{ $section['empty'] }}</p>
                        </div>
                    @else
                        <div class="djr-prose">{!! nl2br(e($answer)) !!}</div>
                    @endif
                </div>
            </section>
        @endforeach

        @if ($journal->hasEvidence())
            <section class="djr-card">
                <header class="djr-card-head">
                    <span class="djr-section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </span>
                    <div>
                        <h2 class="djr-card-title">Pictures / Evidence</h2>
                        <p class="djr-card-sub">{{ $journal->evidenceCount() }} file{{ $journal->evidenceCount() === 1 ? '' : 's' }} attached to this report.</p>
                    </div>
                </header>
                <div class="djr-card-body">
                    <div class="djr-evidence-list">
                        @foreach ($journal->evidencePaths() as $path)
                            <a href="{{ Storage::disk('supabase')->temporaryUrl($path, now()->addHours(1)) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm evidence-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                {{ basename($path) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
/* ============================================================
   Daily Accomplishment Report
   Page-scoped. Every card sizes to its own content: no fixed or
   minimum heights anywhere, so an empty section collapses to a
   couple of lines instead of a blank panel.
   ============================================================ */

/* ---------- Header ---------- */
.djr-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.djr-eyebrow { margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted-text); }
.djr-head h1 { margin: 0 0 4px; font-family: var(--font-head); font-size: 26px; font-weight: 700; color: var(--navy); letter-spacing: -.01em; }
.djr-head-role { font-size: 15px; font-weight: 600; color: var(--muted-text); }
.djr-head-date { margin: 0; display: flex; align-items: center; gap: 6px; font-size: 13.5px; color: var(--muted-text); }
.djr-head-date svg { width: 15px; height: 15px; flex: none; }
.djr-head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.djr-head-actions .btn { display: inline-flex; align-items: center; gap: 6px; }
.djr-head-actions .btn svg { width: 16px; height: 16px; flex: none; }

/* ---------- Status chip ----------
   Tints come from the app's existing badge palette so the report
   matches the rest of the admin rather than introducing new hues. */
.djr-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border: 1px solid transparent; border-radius: 999px; font-size: 12.5px; font-weight: 700; line-height: 1.45; white-space: nowrap; }
.djr-status--submitted { background: #E6F7EE; color: #196F3D; border-color: rgba(25,111,61,.18); }
.djr-status--late { background: #FFF3E0; color: #92400E; border-color: rgba(146,64,14,.18); }
.djr-status--missing { background: #FDECEA; color: #A93226; border-color: rgba(169,50,38,.16); }

/* ---------- Summary strip ---------- */
.djr-summary { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 1px; margin-bottom: 16px; background: var(--border-subtle); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; }
.djr-summary-cell { display: flex; flex-direction: column; gap: 3px; padding: 12px 16px; background: var(--surface); min-width: 0; }
.djr-label { font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted-text); }
.djr-summary-value { font-size: 14.5px; font-weight: 600; color: var(--navy); overflow-wrap: anywhere; }

/* ---------- Layout ----------
   One column by default. At 1024px the sidebar takes its own column,
   and because Employee Information / sidebar / sections are three
   separate grid children they land in rows 1 and 2 in that order.
   Collapsing to one column therefore puts Quick Actions directly
   under Employee Information, which is the desired mobile order. */
.djr-grid { display: grid; grid-template-columns: minmax(0,1fr); gap: 16px; align-items: start; }
@media (min-width: 1024px) { .djr-grid { grid-template-columns: minmax(0,1fr) 288px; } }
.djr-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; align-self: start; }
@media (min-width: 1024px) { .djr-side { position: sticky; top: 1.5rem; } }
.djr-sections { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

/* ---------- Cards ---------- */
.djr-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); box-shadow: var(--shadow-card); overflow: hidden; }
.djr-card-head { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); background: var(--surface-raised); }
.djr-card-title { margin: 0; font-family: var(--font-head); font-size: 18px; font-weight: 700; color: var(--navy); letter-spacing: -.01em; }
.djr-card-sub { margin: 2px 0 0; font-size: 12.5px; color: var(--muted-text); }
.djr-card-body { padding: 20px; font-size: 14.5px; }
.djr-side-note { margin: 0; font-size: 13.5px; line-height: 1.55; color: var(--muted-text); }

/* ---------- Section icon ---------- */
.djr-section-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; min-width: 36px; min-height: 36px; border-radius: 10px; background: var(--sky-soft); color: #1A5276; }
.djr-section-icon svg { width: 20px; height: 20px; flex: none; }

/* ---------- Employee information grid ---------- */
.djr-info { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px 20px; margin: 0; }
.djr-info-cell dt { margin-bottom: 3px; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted-text); }
.djr-info-cell dd { margin: 0; font-size: 14.5px; font-weight: 600; color: var(--navy); overflow-wrap: anywhere; }
.djr-role { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; background: var(--sky-soft); color: #1A5276; font-size: 12.5px; font-weight: 700; }

/* ---------- Submitted prose ---------- */
.djr-prose { font-size: 14.5px; line-height: 1.7; color: var(--text); white-space: pre-wrap; overflow-wrap: anywhere; }

/* ---------- Empty states ----------
   The chip is pinned to 48px on every axis with flex: 0 0 48px, so no
   flex or grid parent can stretch it into an oval and the SVG inside
   keeps its own 24px proportions. The surrounding card still has no
   minimum height, so it collapses to the height of this box. */
.djr-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 16px; text-align: center; border: 1px dashed var(--border-subtle); border-radius: var(--radius-sm); background: var(--surface-raised); }
.djr-empty-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 48px; width: 48px; height: 48px; min-width: 48px; min-height: 48px; border-radius: 50%; background: var(--surface-sunken); color: var(--muted-text); }
.djr-empty-icon svg { width: 24px; height: 24px; flex: none; }
.djr-empty p { margin: 0; font-size: 13.5px; line-height: 1.55; color: var(--muted-text); }

/* ---------- Missing callout ---------- */
.djr-callout { display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px; border: 1px solid rgba(169,50,38,.16); border-left: 3px solid var(--danger); border-radius: var(--radius-sm); background: var(--danger-soft); }
.djr-callout-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; min-width: 40px; min-height: 40px; border-radius: 50%; background: rgba(231,76,60,.14); color: var(--danger); }
.djr-callout-icon svg { width: 20px; height: 20px; flex: none; }
.djr-callout-body { flex: 1 1 auto; min-width: 0; }
.djr-callout-title { margin: 0 0 3px; font-size: 14.5px; font-weight: 700; color: #A93226; }
.djr-callout-body > p:not(.djr-callout-title) { margin: 0; font-size: 13.5px; color: var(--text); }
.djr-callout .btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; }
.djr-callout .btn svg { width: 16px; height: 16px; flex: none; }

/* ---------- Evidence ---------- */
.djr-evidence-list { display: flex; flex-wrap: wrap; gap: 8px; }
.djr-evidence-list .btn { display: inline-flex; align-items: center; max-width: 100%; }
.djr-evidence-list .btn svg { width: 16px; height: 16px; flex: none; }
.evidence-link { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .djr-info { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 768px) {
    .djr-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 560px) {
    .djr-head { align-items: stretch; }
    .djr-head h1 { font-size: 22px; }
    .djr-head-role { display: block; font-size: 14px; }
    .djr-head-actions { width: 100%; }
    .djr-info { grid-template-columns: minmax(0,1fr); }
    .djr-card-body { padding: 16px; }
    .djr-card-head { padding: 14px 16px; }
    .djr-card-title { font-size: 17px; }
}
</style>
@endpush
