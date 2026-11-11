@extends('layouts.dashboard')

@section('title', 'Employee Improvement Board — Kaizen Strategy — Egliane Accounting Services')

@section('content')
@php
    use Illuminate\Support\Str;

    $isOperational = auth()->user()->isOperational();
    $isAdmin = auth()->user()->isAdmin();
    $isStaffView = auth()->user()->isStaff();

    $boardRoute = 'admin.kaizen-concerns.index';
    $recordLabel = 'Employee Suggestion';

    // Carried through the details and edit screens so "Back" returns to this
    // exact board view with its filters and page intact.
    $boardReturnUrl = request()->fullUrl();

    // Supplied by the controller and computed across the whole board, not just
    // the current page or the active filter.
    $statusCounts = $statusCounts ?? $concerns->getCollection()->countBy(fn ($c) => $c->effectiveStatus())->all();

    $totalCount = $statTotal ?? $concerns->total();
    $pendingCount = (int) ($statusCounts[\App\Models\KaizenConcern::STATUS_PENDING] ?? 0);
    $inProgressCount = (int) ($statusCounts[\App\Models\KaizenConcern::STATUS_IN_PROGRESS] ?? 0);
    $implementedCount = (int) ($statusCounts[\App\Models\KaizenConcern::STATUS_IMPLEMENTED] ?? 0);
    $notImplementedCount = (int) ($statusCounts[\App\Models\KaizenConcern::STATUS_NOT_IMPLEMENTED] ?? 0);
@endphp

<!-- Page Header -->
<header class="eas-page-head">
    <div class="eas-head-main">
        <div class="eas-head-left">
            <span class="eas-eyebrow">Kaizen Strategy</span>
            <h1 class="eas-head-title">Employee Improvement Board</h1>
            <p class="eas-head-sub">Track employee suggestions, review progress, and recognize implemented improvements.</p>
        </div>
    </div>
    <div class="eas-head-actions">
        @if ($isOperational)
            <a href="{{ route('admin.kaizen-concerns.submit') }}" class="btn btn-primary eas-btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Submit Improvement
            </a>
        @endif
    </div>
</header>

<!-- Summary Statistics -->
<div class="eas-stat-grid">
    <div class="eas-stat-card eas-stat-total">
        <div class="eas-stat-icon eas-stat-icon-total">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Total Suggestions</span>
            <span class="eas-stat-value">{{ $totalCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-pending">
        <div class="eas-stat-icon eas-stat-icon-pending">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Pending</span>
            <span class="eas-stat-value">{{ $pendingCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-inprogress">
        <div class="eas-stat-icon eas-stat-icon-inprogress">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 1a4 4 0 00-4 4v4a4 4 0 004 4h4a4 4 0 004-4V4a4 4 0 00-4-4z"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">In Progress</span>
            <span class="eas-stat-value">{{ $inProgressCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-implemented">
        <div class="eas-stat-icon eas-stat-icon-implemented">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Implemented</span>
            <span class="eas-stat-value">{{ $implementedCount }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-notimplemented">
        <div class="eas-stat-icon eas-stat-icon-notimplemented">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="8" x2="16" y2="16"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Not Implemented</span>
            <span class="eas-stat-value">{{ $notImplementedCount }}</span>
        </div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="eas-card eas-filter-card">
    <div class="eas-card-head">
        <h3 class="eas-filter-title">Filter Suggestions</h3>
    </div>
    <div class="eas-card-body">
        <form method="GET" action="{{ route($boardRoute) }}" class="eas-filter-form" role="search" autocomplete="off">
            <div class="eas-filter-grid">
                <div class="eas-filter-group eas-filter-group-search">
                    <label class="eas-filter-label" for="filter_q">Search</label>
                    <input class="eas-form-control" id="filter_q" name="q" type="text"
                           value="{{ $q }}" placeholder="Search challenge, solution or notes"
                           autocomplete="off" autocapitalize="off" spellcheck="false">
                </div>
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_status">Status</label>
                    <select class="eas-form-control" id="filter_status" name="status" autocomplete="off">
                        <option value="">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isStaffView)
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_assigned_staff_id">Assigned Employee</label>
                    <select class="eas-form-control" id="filter_assigned_staff_id" name="assigned_staff_id" autocomplete="off">
                        <option value="">All</option>
                        <option value="unassigned" @selected($activeAssignedStaffId === 'unassigned')>Unassigned</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected($activeAssignedStaffId == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endunless
                <div class="eas-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($hasFilters)
                        <a href="{{ route($boardRoute) }}" class="btn btn-outline">Clear Filters</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Board Table -->
<div class="eas-card">
    <div class="eas-card-head">
        <div>
            <h2 class="eas-card-title">{{ $boardHeading ?? 'Employee Improvement Board' }}</h2>
            <span class="eas-card-count">{{ $concerns->total() }} {{ Str::plural('suggestion', $concerns->total()) }}</span>
        </div>
    </div>
    <div class="eas-card-body">
        <div class="eas-table-wrap">
            <table class="eas-table eas-table-hover align-middle mb-0 kaizen-board-table">
                <thead class="eas-thead-muted">
                    <tr>
                        <th class="kaizen-col-suggestion">{{ $recordLabel }}</th>
                        <th class="kaizen-col-by">Submitted By</th>
                        <th class="kaizen-col-status">Status</th>
                        <th class="kaizen-col-date">Target Date</th>
                        <th class="kaizen-col-assignee">Assigned Employee</th>
                        <th class="kaizen-col-impl">Implementation</th>
                        <th class="kaizen-col-evidence">Evidence</th>
                        <th class="text-end kaizen-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($concerns as $concern)
                        @php
                            $evidenceCount = (int) ($concern->evidences_count ?? 0);
                        @endphp
                        <tr class="{{ $concern->isImplemented() ? 'kaizen-row-implemented' : ($concern->effectiveStatus() === \App\Models\KaizenConcern::STATUS_OVERDUE ? 'table-danger' : '') }}">
                            <td data-col="{{ $recordLabel }}" class="kaizen-cell-suggestion">
                                <span class="badge kaizen-suggestion-tag">{{ $recordLabel }}</span>
                                <div class="kaizen-challenge" title="{{ $concern->challenge }}">{{ Str::limit($concern->challenge, 90) }}</div>
                                <div class="kaizen-cell-sub">
                                    Identified {{ $concern->date_identified?->format('M j, Y') ?? '—' }}
                                    @if ($concern->recommended_solution)
                                        &middot; {{ Str::limit($concern->recommended_solution, 60) }}
                                    @endif
                                </div>
                            </td>
                            <td data-col="Submitted By" class="kaizen-cell-by">
                                <div class="kaizen-by-name">{{ $concern->creator->name ?? '—' }}</div>
                            </td>
                            <td data-col="Status" class="kaizen-cell-status">
                                @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
                            </td>
                            <td data-col="Target Date" class="kaizen-cell-date">{{ $concern->target_date?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Assigned Employee" class="kaizen-cell-assignee">
                                @if ($concern->assignedStaff)
                                    <span class="kaizen-assignee">{{ $concern->assignedStaff->name }}</span>
                                @else
                                    <span class="kaizen-unassigned">Unassigned</span>
                                @endif
                            </td>
                            <td data-col="Implementation" class="kaizen-cell-impl">
                                @if ($concern->isImplemented())
                                    <div class="kaizen-impl-date">Implemented on:</div>
                                    <div class="kaizen-impl-value">{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}</div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-col="Evidence" class="kaizen-cell-evidence">
                                @if ($evidenceCount > 0)
                                    <div class="kaizen-evidence">
                                        <a href="{{ route('admin.kaizen-concerns.show', ['concern' => $concern, 'back' => $boardReturnUrl]) }}#evidence" class="btn btn-outline btn-sm">
                                            View Evidence
                                        </a>
                                        <div class="kaizen-cell-sub">{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }} attached</div>
                                    </div>
                                @else
                                    <span class="muted">No evidence</span>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end kaizen-cell-actions">
                                <div class="kaizen-actions">
                                    <button type="button" class="btn btn-outline btn-sm ks-view"
                                        data-ks-view data-bs-toggle="modal" data-bs-target="#ksDetailModal"
                                        data-ks-id="{{ $concern->id }}"
                                        data-ks-href="{{ route('admin.kaizen-concerns.show', ['concern' => $concern, 'back' => $boardReturnUrl]) }}"
                                        data-ks-edit="{{ $isAdmin ? route('admin.kaizen-concerns.edit', ['concern' => $concern, 'back' => $boardReturnUrl]) : '' }}"
                                        data-ks-challenge="{{ $concern->challenge }}"
                                        data-ks-solution="{{ $concern->recommended_solution ?? '' }}"
                                        data-ks-notes="{{ $concern->notes ?? '' }}"
                                        data-ks-submitted-by="{{ $concern->creator->name ?? '—' }}"
                                        data-ks-date-identified="{{ $concern->date_identified?->format('M j, Y') ?? '—' }}"
                                        data-ks-status="{{ $concern->effectiveStatus() }}"
                                        data-ks-status-label="{{ $concern->statusLabel() }}"
                                        data-ks-target-date="{{ $concern->target_date?->format('M j, Y') ?? '—' }}"
                                        data-ks-assigned-staff="{{ $concern->assignedStaff?->name ?? 'Unassigned' }}"
                                        data-ks-implemented="{{ $concern->isImplemented() ? 1 : 0 }}"
                                        data-ks-implementation-date="{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}"
                                        title="View suggestion details">
                                        View
                                    </button>
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('admin.kaizen-concerns.edit', ['concern' => $concern, 'back' => $boardReturnUrl]) }}" class="btn btn-outline btn-sm">Edit</a>
                                    @endif
                                    @include('admin.kaizen-concerns.partials.assign-form', ['concern' => $concern])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-cell">
                            @if ($hasFilters)
                                {{ $filteredEmpty ?? 'No suggestions match your current filters.' }}
                            @else
                                {{ $emptyMessage ?? 'No improvement suggestions yet. Use "Submit Improvement" to add the first one.' }}
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $concerns->links('pagination.simple') }}
    </div>
</div>

@php
    $ksEvidenceMap = [];
    foreach ($concerns->getCollection() as $ksConcern) {
        $ksEvidenceMap[$ksConcern->id] = array_values($ksConcern->evidences->map(function ($ksEvidence) use ($ksConcern) {
            return [
                'id' => $ksEvidence->id,
                'name' => $ksEvidence->original_name,
                'view' => URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), ['concern' => $ksConcern->id, 'evidence' => $ksEvidence->id]),
                'download' => URL::temporarySignedRoute('admin.kaizen-concerns.evidence.download', now()->addMinutes(30), ['concern' => $ksConcern->id, 'evidence' => $ksEvidence->id]),
                'uploader' => $ksEvidence->uploader->name ?? '—',
                'size' => $ksEvidence->size,
                'date' => $ksEvidence->created_at?->format('M j, Y'),
            ];
        })->toArray());
    }
@endphp
<script id="ksEvidenceMap" type="application/json">
{{ json_encode($ksEvidenceMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
</script>

<!-- Suggestion Details Modal -->
<div class="modal fade" id="ksDetailModal" tabindex="-1" aria-labelledby="ksDetailModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ksDetailModalTitle">Suggestion Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body ks-detail-body">
                <div class="ks-detail-head">
                    <span class="ks-detail-id" id="ksMId">#</span>
                    <span class="ks-detail-status" id="ksMStatus"></span>
                </div>
                <div class="ks-detail-grid">
                    <div class="ks-detail-field ks-full">
                        <span class="ks-detail-field-label">Challenge / Opportunity</span>
                        <div class="ks-detail-field-value" id="ksMChallenge"></div>
                    </div>
                    <div class="ks-detail-field ks-full">
                        <span class="ks-detail-field-label">Recommended Solution</span>
                        <div class="ks-detail-field-value" id="ksMSolution"></div>
                    </div>
                    <div class="ks-detail-field ks-full">
                        <span class="ks-detail-field-label">Notes</span>
                        <div class="ks-detail-field-value" id="ksMNotes"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Submitted By</span>
                        <div class="ks-detail-field-value" id="ksMSubmittedBy"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Date Identified</span>
                        <div class="ks-detail-field-value" id="ksMDateIdentified"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Status</span>
                        <div class="ks-detail-field-value ks-detail-status-value" id="ksMStatusValue"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Target Date</span>
                        <div class="ks-detail-field-value" id="ksMTargetDate"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Assigned Employee</span>
                        <div class="ks-detail-field-value" id="ksMAssignedStaff"></div>
                    </div>
                    <div class="ks-detail-field">
                        <span class="ks-detail-field-label">Implementation Date</span>
                        <div class="ks-detail-field-value" id="ksMImplementationDate"></div>
                    </div>
                    <div class="ks-detail-field ks-full">
                        <span class="ks-detail-field-label">Evidence</span>
                        <div class="ks-detail-field-value" id="ksMEvidence"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                @if ($isAdmin)
                    <a id="ksMEdit" class="btn btn-outline" href="#">Edit / Update Status</a>
                @endif
                <a id="ksMOpen" class="btn btn-outline" target="_blank">Open full record</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* ============================================================
   Kaizen Strategy - Employee Improvement Board
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

/* ---------- Filter Card ---------- */
.eas-filter-card {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    background: var(--surface);
    margin-bottom: 24px;
    overflow: hidden;
}

.eas-filter-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--navy);
    padding-top: 2px;
}

.eas-filter-form {
    padding: 16px 20px;
}

.eas-filter-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

.eas-filter-group {
    display: flex;
    flex: 1 1 180px;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.eas-filter-group-search {
    flex: 2 1 280px;
}

.eas-filter-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

/* The filter card is its own surface, so it carries the input styling instead
   of relying on the global `.form-control` rule (which does not cover these
   fields and would otherwise leave the browser-default controls). */
.eas-form-control {
    width: 100%;
    min-height: 38px;
    box-sizing: border-box;
    border: 1px solid #D6DDE6;
    border-radius: 10px;
    padding: 8px 12px;
    font-family: var(--font-body);
    font-size: 14px;
    line-height: 1.35;
    color: var(--text);
    background: #fff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.eas-form-control:focus {
    border-color: var(--sky);
    box-shadow: 0 0 0 3px rgba(90, 179, 240, 0.18);
}

.eas-form-control::placeholder {
    color: #9AA4B2;
}

.eas-filter-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: flex-end;
    justify-content: flex-end;
    gap: 8px;
    margin-left: auto;
}

.eas-filter-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    font-size: 13px;
    padding: 6px 16px;
}

/* ---------- Generic EAS card ---------- */
.eas-card {
    margin-bottom: 20px;
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

.eas-card-title {
    margin: 0;
    font-family: var(--font-head);
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
}

.eas-card-count {
    display: block;
    margin-top: 3px;
    font-size: 12.5px;
    color: var(--muted-text);
}

.eas-card-body {
    padding: 0;
}

.eas-btn-primary,
.eas-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* ---------- Board Table ---------- */
.eas-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.kaizen-board-table {
    min-width: 1080px;
}

.kaizen-assignee {
    font-weight: 600;
    color: var(--navy);
    white-space: nowrap;
}

.kaizen-unassigned {
    color: var(--muted-text);
    font-size: 12.5px;
    white-space: nowrap;
}

.eas-table {
    width: 100%;
    border-collapse: collapse;
}

.kaizen-board-table .eas-thead-muted th {
    white-space: nowrap;
    vertical-align: bottom;
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

.kaizen-cell-suggestion {
    min-width: 260px;
    max-width: 380px;
}

.kaizen-challenge {
    font-weight: 600;
    color: var(--navy);
    line-height: 1.35;
    overflow-wrap: anywhere;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    max-height: 4.1em;
}

.kaizen-cell-by {
    min-width: 110px;
}

.kaizen-by-name {
    font-weight: 600;
    color: var(--navy);
    overflow-wrap: anywhere;
}

.kaizen-cell-status {
    min-width: 120px;
}

.kaizen-cell-date {
    white-space: nowrap;
    color: var(--muted-text);
    font-variant-numeric: tabular-nums;
}

.kaizen-cell-assignee {
    min-width: 130px;
}

.kaizen-cell-impl {
    min-width: 130px;
}

.kaizen-cell-evidence {
    min-width: 110px;
}

.kaizen-evidence {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
}

.kaizen-cell-sub {
    font-size: 12px;
    color: var(--muted-text);
    overflow-wrap: anywhere;
}

.kaizen-impl-date {
    font-size: 12px;
    color: var(--muted-text);
}

.kaizen-impl-value {
    font-weight: 600;
    color: var(--navy);
}

.kaizen-cell-actions {
    white-space: nowrap;
}

.kaizen-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    white-space: nowrap;
}

.kaizen-actions .inline-assign-form {
    margin-left: 0;
    margin-top: 0;
}

.kaizen-cell-actions .btn {
    font-size: 12px;
    padding: 5px 12px;
    flex-shrink: 0;
    white-space: nowrap;
}

.empty-cell {
    text-align: center;
    color: var(--muted-text);
    padding: 32px 16px;
}

/* ---------- Status Pill ---------- */
.kaizen-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.kaizen-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.kaizen-dot-done { background: #10B981; }
.kaizen-dot-none { background: #9CA3AF; }
.kaizen-dot-progress { background: #2563EB; }
.kaizen-dot-overdue { background: #EF4444; }
.kaizen-dot-pending { background: #F59E0B; }

.kaizen-status-done { background: #ECFDF5; color: #10B981; }
.kaizen-status-none { background: #F9FAFB; color: #6B7280; }

/* ---------- Detail Modal ---------- */
.ks-detail-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-subtle);
}

.ks-detail-id {
    font-size: 13px;
    font-weight: 700;
    color: var(--muted-text);
}

.ks-detail-status {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 999px;
}

.ks-detail-status--pending { background: #FEF3E2; color: #92400E; }
.ks-detail-status--in_progress { background: #EFF6FF; color: #1D4ED8; }
.ks-detail-status--completed { background: #ECFDF5; color: #10B981; }
.ks-detail-status--not_implemented { background: #F9FAFB; color: #6B7280; }
.ks-detail-status--overdue { background: #FEF2F2; color: #B91C1C; }

.ks-detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

@media (min-width: 700px) {
    .ks-detail-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.ks-detail-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ks-detail-field.ks-full {
    grid-column: 1 / -1;
}

.ks-detail-field-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.ks-detail-field-value {
    font-size: 14px;
    color: var(--text);
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.ks-detail-field-value.ks-detail-status-value {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.ks-detail-field-value.ks-detail-status-value--pending { background: #FEF3E2; color: #92400E; }
.ks-detail-field-value.ks-detail-status-value--in_progress { background: #EFF6FF; color: #1D4ED8; }
.ks-detail-field-value.ks-detail-status-value--completed { background: #ECFDF5; color: #10B981; }
.ks-detail-field-value.ks-detail-status-value--not_implemented { background: #F9FAFB; color: #6B7280; }
.ks-detail-field-value.ks-detail-status-value--overdue { background: #FEF2F2; color: #B91C1C; }

.ks-detail-evidence-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    border: 1px solid var(--border-subtle);
    border-radius: 9px;
    padding: 8px 12px;
    margin-top: 6px;
    background: var(--surface-raised);
}

.ks-detail-evidence-name {
    font-size: 13px;
    color: var(--navy);
    text-decoration: underline;
    flex: 1;
    min-width: 0;
}

.ks-detail-evidence-sub {
    font-size: 12px;
    color: var(--muted-text);
    flex: 1;
    min-width: 0;
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

    .eas-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .eas-stat-grid {
        grid-template-columns: 1fr;
    }

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

    .eas-filter-group,
    .eas-filter-group-search {
        flex-basis: 100%;
    }

    .eas-filter-actions {
        width: 100%;
        margin-left: 0;
        justify-content: flex-end;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var evidenceMap = {};
    var mapEl = document.getElementById('ksEvidenceMap');
    if (mapEl) {
        try { evidenceMap = JSON.parse(mapEl.textContent || '{}'); } catch (e) {}
    }

    var modal = document.getElementById('ksDetailModal');
    if (!modal) {
        return;
    }

    var bsModal = null;

    function modalInstance() {
        if (!bsModal) {
            bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        }
        return bsModal;
    }

    /*
     * A Bootstrap modal is a page-level overlay: while it is open it owns the
     * `.modal-backdrop` node and the `modal-open` body class. If the page is
     * left while that overlay is up (Edit/Update or a browser Back/Forward),
     * the browser's back-forward cache restores the exact same DOM, including
     * the dark backdrop and the scroll lock, but Bootstrap does not re-run its
     * cleanup. The result is a dark screen that cannot scroll or be clicked.
     *
     * Remove only the stale artifacts, and never touch a modal that is still
     * legitimately open on the page.
     */
    function removeModalArtifacts(force) {
        if (!force && document.querySelector('.modal.show')) {
            return;
        }

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });

        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        if (force) {
            modal.classList.remove('show');
            modal.style.removeProperty('display');
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
        }
    }

    modal.addEventListener('hidden.bs.modal', function () {
        removeModalArtifacts(false);
    });

    /* Clean the snapshot so a restored board never starts behind a backdrop. */
    window.addEventListener('pagehide', function () {
        removeModalArtifacts(true);
    });

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }

        /* The instance was restored with the cached heap; drop it so the next
           View click creates a fresh, consistent Bootstrap modal. */
        var restoredInstance = bootstrap.Modal.getInstance(modal);
        if (restoredInstance) {
            restoredInstance.dispose();
        }
        bsModal = null;
        removeModalArtifacts(true);
    });

    document.querySelectorAll('[data-ks-view]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var d = this.dataset;

            document.getElementById('ksMId').textContent = '#' + (d.ksId || '—');

            var statusPill = document.getElementById('ksMStatus');
            statusPill.textContent = d.ksStatusLabel || '—';
            statusPill.className = 'ks-detail-status ks-detail-status--' + (d.ksStatus || 'pending');

            document.getElementById('ksMChallenge').textContent = d.ksChallenge || '—';
            document.getElementById('ksMSolution').textContent = d.ksSolution || '—';
            document.getElementById('ksMNotes').textContent = d.ksNotes || '—';
            document.getElementById('ksMSubmittedBy').textContent = d.ksSubmittedBy || '—';
            document.getElementById('ksMDateIdentified').textContent = d.ksDateIdentified || '—';

            var statusValue = document.getElementById('ksMStatusValue');
            statusValue.textContent = d.ksStatusLabel || '—';
            statusValue.className = 'ks-detail-field-value ks-detail-status-value ks-detail-status-value--' + (d.ksStatus || 'pending');

            document.getElementById('ksMTargetDate').textContent = d.ksTargetDate || '—';
            document.getElementById('ksMAssignedStaff').textContent = d.ksAssignedStaff || '—';
            document.getElementById('ksMImplementationDate').textContent = d.ksImplemented === '1' && d.ksImplementationDate
                ? 'Implemented on ' + d.ksImplementationDate
                : (d.ksImplementationDate || '—');

            var list = document.getElementById('ksMEvidence');
            list.innerHTML = '';
            var items = evidenceMap[d.ksId] || [];
            if (items.length) {
                items.forEach(function (it) {
                    var row = document.createElement('div');
                    row.className = 'ks-detail-evidence-item';
                    var nameEl = document.createElement('a');
                    nameEl.href = it.view || '#';
                    nameEl.className = 'ks-detail-evidence-name';
                    nameEl.target = '_blank';
                    nameEl.rel = 'noopener';
                    nameEl.textContent = it.name || 'Evidence';
                    var subEl = document.createElement('div');
                    subEl.className = 'ks-detail-evidence-sub';
                    subEl.textContent = 'Added by ' + (it.uploader || '—') + ' · ' + (it.date || '—') +
                        (it.size ? ' · ' + Math.round(it.size / 1024) + ' KB' : '');
                    var dl = document.createElement('a');
                    dl.href = it.download || '#';
                    dl.className = 'btn btn-outline btn-sm';
                    dl.target = '_blank';
                    dl.rel = 'noopener';
                    dl.textContent = 'Download';
                    row.appendChild(nameEl);
                    row.appendChild(subEl);
                    row.appendChild(dl);
                    list.appendChild(row);
                });
            } else {
                list.innerHTML = '<span class="muted">No evidence</span>';
            }

            document.getElementById('ksMOpen').href = d.ksHref || '#';

            var editLink = document.getElementById('ksMEdit');
            if (editLink) {
                editLink.href = d.ksEdit || '#';
            }

            modalInstance().show();
        });
    });

    /* The footer Edit link navigates away. Hide first so leaving the page can
       never preserve an open overlay for the next Back/Forward restore. */
    var editLink = document.getElementById('ksMEdit');
    if (editLink) {
        editLink.addEventListener('click', function () {
            if (bsModal) {
                bsModal.hide();
            }
        });
    }
});
</script>
@endpush
@endsection