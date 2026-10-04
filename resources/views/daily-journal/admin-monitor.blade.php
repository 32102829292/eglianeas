@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Monitor')

@php
    use Carbon\Carbon;

    $statusOptions = [
        '' => 'All Status',
        'submitted' => 'Submitted',
        'late' => 'Late',
        'missing' => 'Missing',
    ];
    $roleOptions = [
        '' => 'All Roles',
        'staff' => 'Staff',
        'supervisor' => 'Supervisor',
    ];

    $selectedDate = $date->format('Y-m-d');

    // Keep the selected date selectable even when it has no journal rows yet
    // (reachable via Previous/Next), so applying another filter never jumps dates.
    $dateChoices = $availableDates->contains($selectedDate)
        ? $availableDates
        : $availableDates->prepend($selectedDate);

    // Counts come straight from the controller's filtered collection.
    $totalCount     = $journals->count();
    $submittedCount = $journals->where('status', 'submitted')->count();
    $lateCount      = $journals->where('status', 'late')->count();
    $missingCount   = $journals->where('status', 'missing')->count();

    $share = static fn (int $part): int => $totalCount > 0
        ? (int) round($part / $totalCount * 100)
        : 0;

    $summaryCards = [
        ['label' => 'Submitted', 'value' => $submittedCount, 'tone' => 'ok',     'icon' => 'check', 'share' => $share($submittedCount)],
        ['label' => 'Late',      'value' => $lateCount,      'tone' => 'warn',   'icon' => 'clock', 'share' => $share($lateCount)],
        ['label' => 'Missing',   'value' => $missingCount,   'tone' => 'danger', 'icon' => 'alert', 'share' => $share($missingCount)],
        ['label' => 'Total',     'value' => $totalCount,     'tone' => 'info',   'icon' => 'users', 'share' => 100],
    ];

    $statusTones = ['submitted' => 'ok', 'late' => 'warn', 'missing' => 'danger'];

    $hasActiveFilters = (bool) ($selectedUserId || $selectedRole || $selectedStatus);
    $resetUrl = route('admin.daily-journal.admin-monitor', ['date' => $selectedDate]);
@endphp

@section('content')
<div class="page-head page-head-row djam-head">
    <div class="djam-head-main">
        <nav class="djam-crumbs" aria-label="Breadcrumb">
            <span>Daily Journal</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="is-current">Monitor</span>
        </nav>
        <h1>Daily Accomplishment Monitor</h1>
        <p>Track employee journal submissions and follow up on anything late or missing.</p>
    </div>

    <div class="djam-head-side">
        <div class="djam-datecard">
            <span class="djam-datecard-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <span class="djam-datecard-text">
                <span class="djam-datecard-label">Report date</span>
                <span class="djam-datecard-value">{{ $date->format('l, F j, Y') }}</span>
            </span>
        </div>

        <div class="djam-datenav">
            <a href="{{ route('admin.daily-journal.admin-monitor', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
               class="btn btn-outline btn-sm djam-datenav-btn" rel="prev" title="Previous day">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                <span>Previous</span>
            </a>
            <a href="{{ route('admin.daily-journal.admin-monitor') }}" class="btn btn-outline btn-sm djam-datenav-btn" title="Jump to today">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>
                <span>Today</span>
            </a>
            <a href="{{ route('admin.daily-journal.admin-monitor', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
               class="btn btn-outline btn-sm djam-datenav-btn" rel="next" title="Next day">
                <span>Next</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </div>
</div>

<section class="djam-stats" aria-label="Daily summary">
    @foreach ($summaryCards as $card)
        <article class="djam-stat djam-stat--{{ $card['tone'] }}">
            <div class="djam-stat-top">
                <div class="djam-stat-text">
                    <div class="djam-stat-value">{{ $card['value'] }}</div>
                    <div class="djam-stat-label">{{ $card['label'] }}</div>
                </div>
                <span class="djam-stat-icon" aria-hidden="true">
                    @switch($card['icon'])
                        @case('check')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            @break
                        @case('clock')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            @break
                        @case('alert')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            @break
                        @default
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            @break
                    @endswitch
                </span>
            </div>
            <div class="djam-stat-track" role="presentation">
                <span class="djam-stat-fill" style="width: {{ $card['share'] }}%"></span>
            </div>
        </article>
    @endforeach
</section>

<form method="GET" action="{{ route('admin.daily-journal.admin-monitor') }}" class="djam-filters">
    <div class="djam-filters-head">
        <div>
            <h2>Monitor Filters</h2>
            <p>Narrow the monitor list by employee, role, submission status or report date.</p>
        </div>
        @if ($hasActiveFilters)
            <span class="djam-filters-flag">Filters active</span>
        @endif
    </div>

    <div class="djam-filter-grid">
        <div class="djam-field">
            <label for="user_id" class="djam-label">Employee</label>
            <select name="user_id" id="user_id" class="form-select djam-select">
                <option value="">All Employees</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" {{ $selectedUserId == $employee->id ? 'selected' : '' }}>
                        {{ $employee->name }} ({{ ucfirst($employee->role) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="role" class="djam-label">Role</label>
            <select name="role" id="role" class="form-select djam-select">
                @foreach ($roleOptions as $value => $label)
                    <option value="{{ $value }}" {{ $selectedRole == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="status" class="djam-label">Submission status</label>
            <select name="status" id="status" class="form-select djam-select">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ $selectedStatus == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="date" class="djam-label">Report date</label>
            <select name="date" id="date" class="form-select djam-select">
                @foreach ($dateChoices as $availableDate)
                    <option value="{{ $availableDate }}" {{ $availableDate == $selectedDate ? 'selected' : '' }}>
                        {{ Carbon::parse($availableDate)->format('D, M j, Y') }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="djam-filter-actions">
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Apply filters</span>
        </button>
        <a href="{{ $resetUrl }}" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            <span>Reset</span>
        </a>
    </div>
</form>

<section class="djam-card" aria-labelledby="djam-submissions-title">
    <div class="djam-card-head">
        <div>
            <h2 id="djam-submissions-title">Employee submissions</h2>
            <p>{{ $totalCount }} {{ \Illuminate\Support\Str::plural('record', $totalCount) }} shown for {{ $date->format('F j, Y') }}</p>
        </div>
    </div>

    @if ($journals->isEmpty())
        <div class="djam-empty">
            <span class="djam-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </span>
            <h3>No matching journal records</h3>
            <p>
                No employee journal records match the selected filters
                for {{ $date->format('F j, Y') }}.
                @if ($hasActiveFilters)
                    Try clearing the filters to see every journal recorded for this date.
                @else
                    Nothing has been recorded for this report date yet.
                @endif
            </p>
            @if ($hasActiveFilters)
                <a href="{{ $resetUrl }}" class="btn btn-outline btn-sm">Clear filters</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover djam-table">
                <thead>
                    <tr>
                        <th scope="col">Employee</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted</th>
                        <th scope="col" class="djam-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($journals as $journal)
                        @php
                            $tone = $statusTones[$journal->status] ?? 'info';
                            $initial = mb_strtoupper(mb_substr($journal->user->name, 0, 1));
                        @endphp
                        <tr>
                            <td>
                                <div class="djam-person">
                                    <span class="djam-avatar" aria-hidden="true">{{ $initial }}</span>
                                    <span class="djam-person-text">
                                        <span class="djam-person-name">{{ $journal->user->name }}</span>
                                        <span class="djam-person-email">{{ $journal->user->email }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="djam-chip djam-chip--role">{{ ucfirst($journal->user->role) }}</span>
                            </td>
                            <td>
                                <span class="djam-chip djam-chip--{{ $tone }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        @if ($journal->status === 'submitted')
                                            <polyline points="20 6 9 17 4 12"/>
                                        @elseif ($journal->status === 'late')
                                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                        @else
                                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                        @endif
                                    </svg>
                                    {{ $journal->getStatusLabel() }}
                                </span>
                            </td>
                            <td>
                                @if ($journal->submitted_at)
                                    <span class="djam-submitted">
                                        <span class="djam-submitted-date">{{ $journal->submitted_at->format('M j, Y') }}</span>
                                        <span class="djam-submitted-time">{{ $journal->submitted_at->format('g:i A') }}</span>
                                    </span>
                                @else
                                    <span class="djam-none">&mdash;</span>
                                @endif
                            </td>
                            <td class="djam-td-actions">
                                <div class="djam-row-actions">
                                    <a href="{{ route('daily-journal.show', $journal) }}" class="btn btn-outline btn-sm djam-action-btn" title="View details" aria-label="View details">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="djam-inline-form" onsubmit="return confirm('Delete this journal entry?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm text-danger djam-action-btn" title="Delete" aria-label="Delete">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection

@push('styles')
<style>
/* Scoped to .djam-* so no shared dashboard class (.filter-bar, .stat-card) is overridden. */
.djam-head { align-items: flex-start; gap: 1rem; }
.djam-head-main { min-width: 0; }
.djam-crumbs { display: flex; align-items: center; gap: .3rem; margin-bottom: .4rem; color: var(--muted, #64748b); font-size: .75rem; font-weight: 600; letter-spacing: .02em; }
.djam-crumbs svg { width: 12px; height: 12px; flex: none; opacity: .6; }
.djam-crumbs .is-current { color: var(--navy, #1e3a5f); }
.djam-head h1 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1.55rem; font-weight: 800; letter-spacing: -.015em; line-height: 1.2; }
.djam-head p { margin: .35rem 0 0; color: var(--muted, #64748b); font-size: .9rem; }

.djam-head-side { display: flex; flex-direction: column; align-items: flex-end; gap: .6rem; flex: none; }
.djam-datecard { display: flex; align-items: center; gap: .6rem; padding: .55rem .8rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; box-shadow: 0 4px 14px rgba(15, 47, 91, .05); }
.djam-datecard-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex: none; border-radius: 9px; background: #eef4fd; color: #2563eb; }
.djam-datecard-icon svg { width: 17px; height: 17px; flex: none; }
.djam-datecard-text { display: flex; flex-direction: column; line-height: 1.25; }
.djam-datecard-label { color: var(--muted, #64748b); font-size: .65rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
.djam-datecard-value { color: var(--navy, #1e3a5f); font-size: .875rem; font-weight: 700; white-space: nowrap; }
.djam-datenav { display: flex; gap: .4rem; }
.djam-datenav-btn { display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap; }
.djam-datenav-btn svg { width: 15px; height: 15px; flex: none; }

.djam-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.djam-stat { position: relative; display: flex; flex-direction: column; gap: .75rem; padding: 1rem 1.1rem .9rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 6px 18px rgba(15, 47, 91, .05); overflow: hidden; }
.djam-stat::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--djam-accent, #2563eb); }
.djam-stat-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
.djam-stat-text { min-width: 0; }
.djam-stat-value { color: var(--navy, #1e3a5f); font-size: 1.85rem; font-weight: 800; line-height: 1.05; letter-spacing: -.02em; }
.djam-stat-label { margin-top: .2rem; color: var(--muted, #64748b); font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.djam-stat-icon { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; flex: none; border-radius: 11px; background: var(--djam-soft, #eef4fd); color: var(--djam-accent, #2563eb); }
.djam-stat-icon svg { width: 19px; height: 19px; flex: none; }
.djam-stat-track { height: 4px; border-radius: 999px; background: #eef1f6; overflow: hidden; }
.djam-stat-fill { display: block; height: 100%; border-radius: 999px; background: var(--djam-accent, #2563eb); }

.djam-stat--ok { --djam-accent: #0f9d58; --djam-soft: #e7f6ee; }
.djam-stat--warn { --djam-accent: #b45309; --djam-soft: #fdf1e2; }
.djam-stat--danger { --djam-accent: #c0392b; --djam-soft: #fdecea; }
.djam-stat--info { --djam-accent: #2563eb; --djam-soft: #eef4fd; }

.djam-filters { margin-bottom: 1.25rem; padding: 1.1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); }
.djam-filters-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.djam-filters-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.djam-filters-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.djam-filters-flag { flex: none; padding: .25rem .6rem; border-radius: 999px; background: #eef4fd; color: #2563eb; font-size: .7rem; font-weight: 700; letter-spacing: .03em; }
.djam-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .9rem; }
.djam-field { min-width: 0; }
.djam-label { display: block; margin: 0 0 .35rem; color: var(--muted, #64748b); font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.djam-select { min-height: 2.55rem; font-size: .875rem; }
.djam-filter-actions { display: flex; gap: .5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle, #e5e7eb); }
.djam-filter-actions .btn { display: inline-flex; align-items: center; gap: .35rem; }
.djam-filter-actions svg { width: 15px; height: 15px; flex: none; }

.djam-card { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); overflow: hidden; }
.djam-card-head { padding: 1.05rem 1.25rem; border-bottom: 1px solid var(--border-subtle, #e5e7eb); }
.djam-card-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.djam-card-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }

.djam-table { margin: 0; min-width: 720px; }
.djam-table thead th { padding: .75rem 1.25rem; background: #f8fafc; border-bottom: 1px solid var(--border-subtle, #e5e7eb); color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
.djam-table tbody td { padding: .85rem 1.25rem; vertical-align: middle; }
.djam-th-actions, .djam-td-actions { text-align: right; }

.djam-person { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.djam-avatar { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: none; border-radius: 50%; background: #eef4fd; color: #2563eb; font-size: .8rem; font-weight: 800; }
.djam-person-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.djam-person-name { color: var(--navy, #1e3a5f); font-weight: 700; }
.djam-person-email { color: var(--muted, #64748b); font-size: .78rem; overflow-wrap: anywhere; }

.djam-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .6rem; border: 1px solid transparent; border-radius: 999px; font-size: .72rem; font-weight: 700; line-height: 1.2; white-space: nowrap; }
.djam-chip svg { width: 13px; height: 13px; flex: none; }
.djam-chip--role { background: #f1f5f9; border-color: #e2e8f0; color: #475569; }
.djam-chip--ok { background: #e7f6ee; border-color: #c7ebd8; color: #0b7a45; }
.djam-chip--warn { background: #fdf1e2; border-color: #f6ddb9; color: #92400e; }
.djam-chip--danger { background: #fdecea; border-color: #f7cfca; color: #a5281a; }
.djam-chip--info { background: #eef4fd; border-color: #d5e3f8; color: #1d4ed8; }

.djam-submitted { display: flex; flex-direction: column; line-height: 1.3; }
.djam-submitted-date { color: var(--navy, #1e3a5f); font-weight: 600; font-size: .85rem; }
.djam-submitted-time { color: var(--muted, #64748b); font-size: .78rem; }
.djam-none { color: #94a3b8; }

.djam-row-actions { display: flex; gap: .4rem; align-items: center; justify-content: flex-end; }
.djam-inline-form { display: inline-flex; margin: 0; }
.djam-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 2.1rem; height: 2.1rem; padding: 0; }
.djam-action-btn svg { width: 15px; height: 15px; flex: none; }

.djam-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 3rem 1.5rem; text-align: center; }
.djam-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex: none; border-radius: 14px; background: #f1f5f9; color: #64748b; }
.djam-empty-icon svg { width: 24px; height: 24px; flex: none; }
.djam-empty h3 { margin: .35rem 0 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.djam-empty p { max-width: 30rem; margin: 0; color: var(--muted, #64748b); font-size: .85rem; }

@media (max-width: 1199px) {
    .djam-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .djam-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .djam-head { flex-direction: column; align-items: stretch; }
    .djam-head-side { flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; }
}
@media (max-width: 640px) {
    .djam-filter-grid { grid-template-columns: minmax(0, 1fr); }
    .djam-filters-head { flex-direction: column; gap: .5rem; }
    .djam-filter-actions .btn { flex: 1; justify-content: center; min-height: 44px; }
    /* dashboard.css sets .btn-sm { min-height: 44px } under 560px; match the width so the
       icon button stays square instead of being stretched into a tall rectangle. */
    .djam-action-btn { width: 44px; height: 44px; }
    .djam-head-side { flex-direction: column; align-items: stretch; }
    .djam-datenav { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .djam-datenav-btn { justify-content: center; }
    .djam-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .djam-stat { padding: .85rem .9rem .8rem; }
    .djam-stat-value { font-size: 1.5rem; }
    .djam-stat-icon { width: 32px; height: 32px; }
    .djam-stat-icon svg { width: 17px; height: 17px; }
    .djam-card-head, .djam-filters { padding-left: 1rem; padding-right: 1rem; }
}
@media (max-width: 380px) {
    .djam-stats { grid-template-columns: minmax(0, 1fr); }
}
</style>
@endpush
