@extends('layouts.dashboard')

@section('title', 'Admin Concerns — Egliane Accounting Services')

@section('content')
@php
    // Admins can filter the whole board; Staff and Supervisors only ever see
    // their own assigned records, so the assignee filter is redundant there.
    $isStaffView = ! auth()->user()->isAdmin();
    $isAdmin = auth()->user()->isAdmin();
@endphp

<!-- Page Header -->
<header class="eas-page-head">
    <div class="eas-head-main">
        <div class="eas-head-left">
            <span class="eas-eyebrow">Admin Concerns</span>
            <h1 class="eas-head-title">Admin Concerns</h1>
            <p class="eas-head-sub">Track organizational concerns, assign responsibilities, and monitor corrective actions.</p>
        </div>
    </div>
    <div class="eas-head-actions">
        @if ($isAdmin)
            <button type="button" class="btn btn-primary eas-btn-primary" data-bs-toggle="modal" data-bs-target="#createConcernModal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Concern
            </button>
        @endif
    </div>
</header>

@php
    use Illuminate\Support\Str;

    // Supplied by the controller and computed across the whole Admin Concerns
    // board, not just the current page or the active filter.
    $acStatusCounts = $statusCounts ?? $concerns->getCollection()->countBy(fn ($c) => $c->effectiveStatus())->all();

    $acTotalCount = $statTotal ?? $concerns->total();
    $acPendingCount = (int) ($acStatusCounts[\App\Models\KaizenConcern::STATUS_PENDING] ?? 0);
    $acInProgressCount = (int) ($acStatusCounts[\App\Models\KaizenConcern::STATUS_IN_PROGRESS] ?? 0);
    $acResolvedCount = (int) ($acStatusCounts[\App\Models\KaizenConcern::STATUS_RESOLVED] ?? 0);
    $acClosedCount = (int) ($acStatusCounts[\App\Models\KaizenConcern::STATUS_CLOSED] ?? 0);
@endphp

<!-- Summary Statistics -->
<div class="eas-stat-grid">
    <div class="eas-stat-card eas-stat-total">
        <div class="eas-stat-icon eas-stat-icon-total">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Total Concerns</span>
            <span class="eas-stat-value">{{ $acTotalCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-pending">
        <div class="eas-stat-icon eas-stat-icon-pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Pending</span>
            <span class="eas-stat-value">{{ $acPendingCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-inprogress">
        <div class="eas-stat-icon eas-stat-icon-inprogress">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 1a4 4 0 00-4 4v4a4 4 0 004 4h4a4 4 0 004-4V4a4 4 0 00-4-4z"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">In Progress</span>
            <span class="eas-stat-value">{{ $acInProgressCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-implemented">
        <div class="eas-stat-icon eas-stat-icon-implemented">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Resolved</span>
            <span class="eas-stat-value">{{ $acResolvedCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-notimplemented">
        <div class="eas-stat-icon eas-stat-icon-notimplemented">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="8" x2="16" y2="16"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Closed</span>
            <span class="eas-stat-value">{{ $acClosedCount }}</span>
        </div>
    </div>
</div>

<!-- Admin Concerns Board -->
@include('admin.kaizen-concerns.partials.board', [
    'boardRoute' => 'admin.kaizen-concerns.admin-concerns.index',
    'recordLabel' => 'Admin Concern',
    'boardHeading' => 'Admin Concerns',
    'pluralUnit' => 'concern',
    'showAssigned' => true,
    'emptyMessage' => 'No admin concerns yet. Use "Create Concern" to raise the first one.',
    'filteredEmpty' => 'No admin concerns match your current filters.',
])

<!-- Create Concern Modal (Bootstrap 5.3.3) -->
@if (auth()->user()->isAdmin())
<div class="modal fade concern-modal" id="createConcernModal" tabindex="-1" role="dialog"
     aria-labelledby="createConcernModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.kaizen-concerns.store') }}" class="concern-modal-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createConcernModalTitle">Create Admin Concern</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-error" role="alert">
                            Please correct the highlighted field(s) below.
                        </div>
                    @endif
                    @include('admin.kaizen-concerns.partials.create-fields')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Concern</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($errors->any())
<script>
    window.addEventListener('load', function () {
        var el = document.getElementById('createConcernModal');
        if (el && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });
</script>
@endif
@endsection

@push('styles')
<style>
/* ============================================================
   Admin Concerns - Premium Modern Design
   ============================================================ */

/* ---------- Page Header ---------- */
.eas-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-head-main {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    flex-wrap: wrap;
}

.eas-head-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.eas-eyebrow {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-head-title {
    margin: 0 0 6px;
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.eas-head-sub {
    margin: 0;
    font-size: 14px;
    color: var(--muted-text);
}

.eas-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.eas-head-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.eas-head-actions .btn svg {
    width: 16px;
    height: 16px;
    flex: none;
}

/* ---------- Stats Grid ---------- */
.eas-stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.eas-stat-card {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    padding: 16px;
    background: var(--surface);
    display: flex;
    align-items: center;
    gap: 12px;
}

.eas-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #F1F5F9;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
    color: var(--navy);
}

.eas-stat-icon-total { color: #1D4ED8; background: #EFF6FF; }
.eas-stat-icon-pending { color: #F59E0B; background: #FFFBEB; }
.eas-stat-icon-inprogress { color: #2563EB; background: #EFF6FF; }
.eas-stat-icon-implemented { color: #10B981; background: #ECFDF5; }
.eas-stat-icon-notimplemented { color: #9CA3AF; background: #F9FAFB; }

.eas-stat-info {
    min-width: 0;
}

.eas-stat-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-stat-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--navy);
}

/* ---------- Board Table ---------- */
.eas-card {
    margin-bottom: 16px;
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}

.eas-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-raised);
}

.eas-card-head h2 {
    margin: 0;
    font-family: var(--font-head);
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
}

.eas-card-head p {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--muted-text);
}

.eas-table-wrap {
    overflow-x: auto;
}

.eas-table {
    width: 100%;
    border-collapse: collapse;
}

.eas-thead-muted th {
    background: var(--surface-raised);
    color: var(--muted-text);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border-subtle);
    text-align: left;
}

.eas-thead-muted th.text-end {
    text-align: right;
}

.eas-table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border-subtle);
    vertical-align: middle;
}

.eas-table tbody tr {
    transition: background 0.15s ease;
}

.eas-table tbody tr:hover {
    background: #F8FAFC;
}

.kaizen-row-implemented {
    background: #F0FDF4 !important;
}

.table-danger {
    background: #FEF2F2 !important;
}

/* ---------- Criteria Breakdown ---------- */
.eas-criteria-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
}

.eas-score-criterion {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--surface);
}

.eas-score-criterion--collapsed .eas-score-criterion-body {
    display: none;
}

.eas-score-criterion-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-raised);
    cursor: pointer;
    transition: background 0.2s ease;
}

.eas-score-criterion-head:hover {
    background: #F8FAFC;
}

.eas-score-criterion-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.eas-score-criterion-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #F1F5F9;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
    color: var(--navy);
}

.eas-score-criterion-icon path,
.eas-score-criterion-icon line,
.eas-score-criterion-icon polyline,
.eas-score-criterion-icon circle {
    stroke: currentColor;
    fill: none;
}

.eas-score-criterion-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.eas-score-criterion-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.eas-score-criterion-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-criterion-weight {
    font-size: 11px;
    font-weight: 600;
    color: var(--muted-text);
}

.eas-score-criterion-score {
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex: none;
    min-width: 90px;
    justify-content: flex-end;
}

.eas-score-points {
    font-size: 20px;
    font-weight: 800;
    color: var(--navy);
    line-height: 1;
}

.eas-score-points--unevaluated {
    color: var(--muted-text);
}

.eas-score-points-max {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted-text);
}

.eas-score-points-deduction {
    font-size: 16px;
    font-weight: 700;
    color: #ef4444;
    margin-left: 6px;
}

.eas-score-criterion-toggle {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    background: var(--surface);
    color: var(--muted-text);
    cursor: pointer;
    padding: 0;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
}

.eas-score-criterion-toggle:hover {
    border-color: #2563eb;
    color: #2563eb;
}

.eas-score-criterion-toggle[aria-expanded="true"] {
    background: #EAF1FC;
    color: #2563eb;
    border-color: #2563eb;
}

.eas-score-toggle-icon {
    width: 16px;
    height: 16px;
    flex: none;
}

.eas-score-criterion-progress {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border-subtle);
    background: #FAFBFC;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.eas-score-progress-track {
    flex: 1;
    height: 6px;
    background: #E5E7EB;
    border-radius: 3px;
    overflow: hidden;
}

.eas-score-progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.eas-score-progress-fill--empty {
    background: #E5E7EB;
    width: 100%;
    opacity: 0.4;
}

.eas-score-progress-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--navy);
    white-space: nowrap;
    min-width: 70px;
    text-align: right;
}

.eas-score-criterion-status {
    padding: 8px 16px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-score-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.eas-score-status--evaluated {
    background: #E6F7EE;
    color: #196F3D;
}

.eas-score-status--unevaluated {
    background: #F1F5F9;
    color: #6B7280;
}

.eas-score-criterion-body {
    padding: 20px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    background: var(--surface);
}

.eas-score-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.eas-score-field-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-field-value {
    font-size: 14px;
    color: var(--text);
    line-height: 1.55;
}

.eas-score-field-value--muted {
    color: var(--muted-text);
    font-style: italic;
}

.eas-score-field--manual {
    border-top: 1px solid var(--border-subtle);
    padding-top: 16px;
    margin-top: 16px;
    background: #FEF3C7;
    border-radius: 8px;
}

/* ---------- Assessment History ---------- */
.eas-score-history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 24px;
}

.eas-score-history-item {
    padding: 12px 14px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
}

.eas-score-history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.eas-score-history-action {
    font-size: 13px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-history-action--manual {
    color: #196F3D;
}

.eas-score-history-action--auto {
    color: #1D4ED8;
}

.eas-score-history-date {
    font-size: 11px;
    color: var(--muted-text);
}

.eas-score-history-body p {
    margin: 0;
    font-size: 13px;
    line-height: 1.55;
    color: var(--muted-text);
}

.eas-score-history-reviewer {
    font-weight: 700;
    color: var(--navy);
}

.eas-score-history-score {
    font-weight: 700;
}

.eas-score-history-score--prev {
    color: #6B7280;
}

.eas-score-history-score--next {
    color: var(--navy);
}

.eas-score-history-reason {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    font-style: italic;
    color: var(--muted-text);
}

/* ---------- Reassessment ---------- */
.eas-score-reassess {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border-subtle);
}

.eas-score-reassess-panel {
    margin-top: 12px;
    padding: 20px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
}

.eas-score-reassess-panel h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-reassess-panel p {
    margin: 0 0 16px;
    font-size: 14px;
    color: var(--muted-text);
}

.eas-score-reassess-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.eas-score-reassess-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.eas-score-reassess-field--wide {
    grid-column: 1 / -1;
}

.eas-score-reassess-field label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-reassess-field input,
.eas-score-reassess-field select,
.eas-score-reassess-field textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-subtle);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text);
    font: inherit;
    font-size: 14px;
}

.eas-score-reassess-field textarea {
    resize: vertical;
    line-height: 1.5;
}

.eas-score-reassess-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .eas-page-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .eas-head-main {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .eas-page-head h1 {
        font-size: 22px;
    }

    .eas-page-head-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .eas-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .eas-page-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .eas-head-main {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .eas-page-head h1 {
        font-size: 22px;
    }

    .eas-page-head-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .eas-stat-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush