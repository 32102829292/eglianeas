@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Monitor')

@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $statusOptions = [
        ''          => 'All Status',
        'submitted' => 'Submitted',
        'late'      => 'Late',
        'missing'   => 'Missing',
    ];
    $roleOptions = [
        ''           => 'All Roles',
        'staff'      => 'Staff',
        'supervisor' => 'Supervisor',
    ];

    $selectedDate = $date->format('Y-m-d');
    $isToday      = $date->isToday();

    // Keep the selected date selectable even when it has no journal rows yet
    // (reachable via Previous/Next), so applying another filter never jumps dates.
    $dateChoices = $availableDates->contains($selectedDate)
        ? $availableDates
        : $availableDates->prepend($selectedDate);

    // Counts come straight from the controller's (already filtered) collection.
    $totalCount     = $journals->count();
    $submittedCount = $journals->where('status', 'submitted')->count();
    $lateCount      = $journals->where('status', 'late')->count();
    $missingCount   = $journals->where('status', 'missing')->count();

    $share = static fn (int $part): int => $totalCount > 0
        ? (int) round($part / $totalCount * 100)
        : 0;

    // Anyone who handed something in, on time or not.
    $completionRate = $share($submittedCount + $lateCount);

    $hasActiveFilters = (bool) ($selectedUserId || $selectedRole || $selectedStatus);
    $monitorRoute     = 'admin.daily-journal.admin-monitor';
    $resetUrl         = route($monitorRoute, ['date' => $selectedDate]);

    // Stat cards double as status filters and keep the other active filters.
    $statusUrl = static fn (?string $status): string => route($monitorRoute, array_filter([
        'date'    => $selectedDate,
        'user_id' => $selectedUserId,
        'role'    => $selectedRole,
        'status'  => $status,
    ]));

    $summaryCards = [
        ['label' => 'Submitted', 'status' => 'submitted', 'value' => $submittedCount, 'tone' => 'ok',     'icon' => 'check', 'share' => $share($submittedCount)],
        ['label' => 'Late',      'status' => 'late',      'value' => $lateCount,      'tone' => 'warn',   'icon' => 'clock', 'share' => $share($lateCount)],
        ['label' => 'Missing',   'status' => 'missing',   'value' => $missingCount,   'tone' => 'danger', 'icon' => 'alert', 'share' => $share($missingCount)],
        ['label' => 'Total',     'status' => null,        'value' => $totalCount,     'tone' => 'info',   'icon' => 'users', 'share' => 100],
    ];

    $statusTones = ['submitted' => 'ok', 'late' => 'warn', 'missing' => 'danger'];

    $goalCount = $goalsByUser->flatten(1)->count();
@endphp

@section('content')
<div class="djam">
<header class="djam-head">
    <div class="djam-head-main">
        <span class="djam-head-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
        </span>
        <div>
            <h1>Daily Accomplishment Monitor</h1>
            <p>Track employee journal submissions and follow up on anything late or missing.</p>
        </div>
    </div>

    <div class="djam-datenav">
        <a href="{{ route($monitorRoute, array_filter(['date' => $date->copy()->subDay()->format('Y-m-d'), 'user_id' => $selectedUserId, 'role' => $selectedRole, 'status' => $selectedStatus])) }}"
           class="djam-nav-btn" rel="prev" title="Previous day" aria-label="Previous day">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="djam-date-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>{{ $date->format('l, F j, Y') }}</span>
        </div>
        <a href="{{ route($monitorRoute, array_filter(['date' => $date->copy()->addDay()->format('Y-m-d'), 'user_id' => $selectedUserId, 'role' => $selectedRole, 'status' => $selectedStatus])) }}"
           class="djam-nav-btn" rel="next" title="Next day" aria-label="Next day">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="{{ route($monitorRoute, array_filter(['user_id' => $selectedUserId, 'role' => $selectedRole, 'status' => $selectedStatus])) }}"
           class="djam-today-btn {{ $isToday ? 'is-current' : '' }}" title="Jump to today" @if ($isToday) aria-current="date" @endif>Today</a>
    </div>
</header>

{{-- ===== Summary: each card filters the list by that status ===== --}}
<section class="djam-stats" aria-label="Daily summary">
    @foreach ($summaryCards as $card)
        @php $isActive = ($card['status'] ?? '') === (string) $selectedStatus; @endphp
        <a href="{{ $statusUrl($card['status']) }}"
           class="djam-stat djam-stat--{{ $card['tone'] }} {{ $isActive ? 'is-active' : '' }}"
           @if ($isActive) aria-current="true" @endif
           title="{{ $card['status'] ? 'Show only ' . strtolower($card['label']) : 'Show all statuses' }}">
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
            <div class="djam-stat-foot">
                <div class="djam-stat-track" role="presentation">
                    <span class="djam-stat-fill" style="width: {{ $card['share'] }}%"></span>
                </div>
                <span class="djam-stat-share">{{ $card['share'] }}%</span>
            </div>
        </a>
    @endforeach
</section>

{{-- ===== Goals for the Day (collapsible) ===== --}}
<details class="dg-panel" aria-label="Goals for the Day" @if ($goalsByUser->isNotEmpty() && $goalsByUser->count() <= 4) open @endif>
    <summary class="dg-panel-head">
        <div class="dg-panel-title">
            <span class="dg-panel-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
            </span>
            <div>
                <h2>
                    Goals for the Day
                    @if ($goalCount > 0)
                        <span class="dg-count">{{ $goalCount }}</span>
                    @endif
                </h2>
                <p>Targeted goals for {{ $date->format('l, F j, Y') }}. Used as the reference when evaluating Daily Accomplishment Reports.</p>
            </div>
        </div>
        <div class="dg-head-actions">
            <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d')]) }}"
               class="djam-btn djam-btn-primary djam-btn-sm dg-edit-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Edit Goals
            </a>
            <span class="dg-chevron" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
        </div>
    </summary>

    <div class="dg-goals-container">
        @if ($goalsByUser->isEmpty())
            <div class="dg-empty-state">
                <span class="dg-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </span>
                <div class="dg-empty-text">
                    <strong>No goals set for this day.</strong>
                    <p>No targeted goals have been assigned for {{ $date->format('F j, Y') }}. Use &ldquo;Edit Goals&rdquo; to create them.</p>
                </div>
            </div>
        @else
            <div class="dg-employee-grid">
                @foreach ($goalsByUser as $userId => $userGoals)
                    @php $goalUser = $userGoals->first()->user; @endphp
                    <div class="dg-employee-goals">
                        <div class="dg-emp-header">
                            <span class="dg-emp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($goalUser->name, 0, 1)) }}</span>
                            <span class="dg-emp-name">{{ $goalUser->name }}</span>
                            <span class="dg-emp-role">{{ ucfirst($goalUser->role) }} &middot; {{ $userGoals->count() }} {{ Str::plural('goal', $userGoals->count()) }}</span>
                        </div>
                        <ul class="dg-goal-list">
                            @foreach ($userGoals as $goal)
                                <li class="dg-goal-item">
                                    <span class="dg-goal-title">{{ $goal->title }}</span>
                                    @if ($goal->description)
                                        <span class="dg-goal-desc">{{ $goal->description }}</span>
                                    @endif
                                    @if ($goal->success_criteria)
                                        <span class="dg-goal-meta">
                                            <span class="dg-criteria-tag">Success: {{ $goal->success_criteria }}</span>
                                        </span>
                                    @endif
                                    @if ($goal->target_date)
                                        <span class="dg-goal-date">{{ $goal->target_date->format('D, M j') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</details>

{{-- ===== Filters ===== --}}
<form method="GET" action="{{ route($monitorRoute) }}" class="djam-filters" data-auto-submit>
    <div class="djam-filters-head">
        <div>
            <h2>Monitor Filters</h2>
            <p>Narrow the list by employee, role, submission status or report date. Changes apply as you pick them.</p>
        </div>
        @if ($hasActiveFilters)
            <a href="{{ $resetUrl }}" class="djam-filters-flag" title="Clear all filters">
                Filters active
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </a>
        @endif
    </div>

    <div class="djam-filter-grid">
        <div class="djam-field">
            <label for="user_id" class="djam-label">Employee</label>
            <select name="user_id" id="user_id" class="djam-select">
                <option value="">All Employees</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected($selectedUserId == $employee->id)>
                        {{ $employee->name }} ({{ ucfirst($employee->role) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="role" class="djam-label">Role</label>
            <select name="role" id="role" class="djam-select">
                @foreach ($roleOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedRole == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="status" class="djam-label">Submission status</label>
            <select name="status" id="status" class="djam-select">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedStatus == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="djam-field">
            <label for="date" class="djam-label">Report date</label>
            <select name="date" id="date" class="djam-select">
                @foreach ($dateChoices as $availableDate)
                    <option value="{{ $availableDate }}" @selected($availableDate == $selectedDate)>
                        {{ Carbon::parse($availableDate)->format('D, M j, Y') }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Visible without JavaScript; hidden once auto-submit is active. --}}
    <div class="djam-filter-actions" data-filter-actions>
        <button type="submit" class="djam-btn djam-btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Apply filters</span>
        </button>
        <a href="{{ $resetUrl }}" class="djam-btn djam-btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            <span>Reset</span>
        </a>
    </div>
</form>

{{-- ===== Submissions ===== --}}
<section class="djam-card" aria-labelledby="djam-submissions-title">
    <div class="djam-card-head">
        <div>
            <h2 id="djam-submissions-title">Employee submissions</h2>
            <p data-record-count>
                {{ $totalCount }} {{ Str::plural('record', $totalCount) }} shown for {{ $date->format('F j, Y') }}
                @if ($totalCount > 0) &middot; {{ $completionRate }}% handed in @endif
            </p>
        </div>

        @if ($journals->isNotEmpty())
            <div class="djam-card-tools">
                <label class="djam-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span class="visually-hidden">Search employees</span>
                    <input type="search" class="djam-search-input" placeholder="Search name or email" data-table-search autocomplete="off">
                </label>

                @if ($missingCount > 0)
                    <button type="button" class="djam-btn djam-btn-ghost djam-btn-sm djam-copy-btn" data-copy-missing>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span data-copy-label>Copy missing list</span>
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if ($totalCount > 0)
        <div class="djam-split" role="img"
             aria-label="{{ $submittedCount }} submitted, {{ $lateCount }} late, {{ $missingCount }} missing">
            <span class="djam-split-seg djam-split-seg--ok"     style="width: {{ $share($submittedCount) }}%"></span>
            <span class="djam-split-seg djam-split-seg--warn"   style="width: {{ $share($lateCount) }}%"></span>
            <span class="djam-split-seg djam-split-seg--danger" style="width: {{ $share($missingCount) }}%"></span>
        </div>
    @endif

    @if ($journals->isEmpty())
        <div class="djam-empty">
            <span class="djam-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </span>
            <h3>No matching journal records</h3>
            <p>
                No employee journal records match the selected filters for {{ $date->format('F j, Y') }}.
                @if ($hasActiveFilters)
                    Try clearing the filters to see every journal recorded for this date.
                @else
                    Nothing has been recorded for this report date yet.
                @endif
            </p>
            @if ($hasActiveFilters)
                <a href="{{ $resetUrl }}" class="djam-btn djam-btn-ghost djam-btn-sm">Clear filters</a>
            @endif
        </div>
    @else
        <div class="djam-table-wrap">
            <table class="djam-table">
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
                            $tone      = $statusTones[$journal->status] ?? 'info';
                            $user      = $journal->user ?: null;
                            $initial   = $user ? mb_strtoupper(mb_substr($user->name, 0, 1)) : '?';
                            $userName  = $user ? $user->name : 'Unknown / Deleted User';
                            $userEmail = $user ? $user->email : '';
                            $userRole  = $user ? $user->role : '';
                        @endphp
                        <tr class="djam-row djam-row--{{ $tone }}"
                            data-row
                            data-status="{{ $journal->status }}"
                            data-name="{{ $userName }}"
                            data-search="{{ Str::lower($userName . ' ' . $userEmail) }}">
                            <td>
                                <div class="djam-person">
                                    <span class="djam-avatar" aria-hidden="true">{{ $initial }}</span>
                                    <span class="djam-person-text">
                                        <span class="djam-person-name">{{ $userName }}</span>
                                        @if ($userEmail)
                                            <span class="djam-person-email">{{ $userEmail }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td>
                                @if ($userRole)
                                    <span class="djam-chip djam-chip--role">{{ ucfirst($userRole) }}</span>
                                @else
                                    <span class="djam-none">&mdash;</span>
                                @endif
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
                                    <a href="{{ route('daily-journal.show', $journal) }}" class="djam-btn djam-btn-ghost djam-action-btn" title="View details" aria-label="View details for {{ $userName }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="djam-inline-form" data-confirm="Delete the journal entry for {{ $userName }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="djam-btn djam-btn-ghost djam-btn-danger djam-action-btn" title="Delete" aria-label="Delete entry for {{ $userName }}">
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

        <div class="djam-empty djam-empty--search" data-search-empty hidden>
            <h3>No one matches that search</h3>
            <p>Check the spelling, or clear the search box to see everyone again.</p>
        </div>
    @endif
</section>
</div>
@endsection

@push('styles')
<style>
.djam {
    --djam-ink: #1b2447;
    --djam-muted: #5b6585;
    --djam-line: #e3e7f3;
    --djam-surface: #ffffff;
    --djam-sunken: #f7f8ff;
    --djam-tint: #eef0fc;
    --djam-brand: #4338ca;
    --djam-brand-dark: #3730a3;
    color: var(--djam-ink);
}
.djam h1, .djam h2, .djam h3, .djam h4, .djam p { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* ---------- Header ---------- */
.djam-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.djam-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.djam-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--djam-tint); color: var(--djam-brand); }
.djam-head-icon svg { width: 30px; height: 30px; }
.djam-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.djam-head p { margin-top: 2px; color: var(--djam-muted); font-size: .95rem; }
.djam-datenav { display: flex; align-items: center; gap: 8px; }
.djam-date-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 12px; font-weight: 600; font-size: .95rem; white-space: nowrap; }
.djam-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 12px; color: var(--djam-ink); text-decoration: none; }
.djam-nav-btn:hover { background: var(--djam-tint); }
.djam-today-btn { padding: 10px 16px; border-radius: 12px; background: var(--djam-surface); border: 1px solid var(--djam-line); color: var(--djam-ink); font-weight: 700; font-size: .9rem; text-decoration: none; }
.djam-today-btn:hover { background: var(--djam-tint); }
.djam-today-btn.is-current { background: var(--djam-ink); border-color: var(--djam-ink); color: #fff; }

/* ---------- Buttons ---------- */
.djam-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.djam-btn svg { width: 15px; height: 15px; flex: none; }
.djam-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--djam-brand-dark)); color: #fff; }
.djam-btn-primary:hover { background: var(--djam-brand-dark); color: #fff; }
.djam-btn-ghost { background: var(--djam-surface); border-color: var(--djam-line); color: var(--djam-ink); }
.djam-btn-ghost:hover { background: var(--djam-tint); }
.djam-btn-sm { padding: 7px 12px; font-size: .82rem; }
.djam-btn-danger { color: #a93226; }
.djam-btn-danger:hover { background: #fdecea; border-color: #f1b5ae; }
.djam-btn:focus-visible, .djam-nav-btn:focus-visible, .djam-today-btn:focus-visible, .djam-stat:focus-visible, .djam-select:focus-visible, .djam-search-input:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* ---------- Stat cards (links) ---------- */
.djam-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
.djam-stat { display: flex; flex-direction: column; gap: 14px; padding: 16px 18px; background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 18px; color: inherit; text-decoration: none; transition: border-color .15s, background .15s; }
.djam-stat:hover { border-color: var(--djam-accent, var(--djam-brand)); }
.djam-stat.is-active { border-color: var(--djam-accent, var(--djam-brand)); background: var(--djam-soft, var(--djam-tint)); }
.djam-stat-top { display: flex; flex-direction: row-reverse; justify-content: flex-end; align-items: center; gap: 14px; }
.djam-stat-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: var(--djam-soft, var(--djam-tint)); color: var(--djam-accent, var(--djam-brand)); }
.djam-stat.is-active .djam-stat-icon { background: #fff; }
.djam-stat-icon svg { width: 22px; height: 22px; }
.djam-stat-value { font-size: 1.9rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
.djam-stat-label { margin-top: 4px; color: var(--djam-muted); font-size: .85rem; font-weight: 700; }
.djam-stat-foot { display: flex; align-items: center; gap: 10px; }
.djam-stat-track { flex: 1; height: 6px; border-radius: 999px; background: #e9ebf7; overflow: hidden; }
.djam-stat-fill { display: block; height: 100%; border-radius: 999px; background: var(--djam-accent, var(--djam-brand)); }
.djam-stat-share { color: var(--djam-muted); font-size: .75rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.djam-stat--ok { --djam-accent: #0f9d58; --djam-soft: #e7f6ee; }
.djam-stat--warn { --djam-accent: #b45309; --djam-soft: #fdf1e2; }
.djam-stat--danger { --djam-accent: #c0392b; --djam-soft: #fdecea; }
.djam-stat--info { --djam-accent: var(--djam-brand); --djam-soft: var(--djam-tint); }

/* ---------- Goals for the Day (collapsible) ---------- */
.dg-panel { margin-bottom: 20px; background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 18px; overflow: hidden; }
.dg-panel > summary { list-style: none; cursor: pointer; }
.dg-panel > summary::-webkit-details-marker { display: none; }
.dg-panel > summary:focus-visible { outline: 3px solid #a5b4fc; outline-offset: -3px; }
.dg-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; }
.dg-panel[open] > .dg-panel-head { border-bottom: 1px solid var(--djam-line); }
.dg-panel-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.dg-panel-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--djam-tint); color: var(--djam-brand); }
.dg-panel-icon svg { width: 22px; height: 22px; }
.dg-panel-title h2 { display: flex; align-items: center; gap: 8px; font-size: 1.05rem; font-weight: 800; }
.dg-panel-title p { margin-top: 2px; color: var(--djam-muted); font-size: .85rem; }
.dg-count { padding: 1px 9px; border-radius: 999px; background: var(--djam-tint); color: var(--djam-brand); font-size: .75rem; font-weight: 700; }
.dg-head-actions { display: flex; align-items: center; gap: 10px; flex: none; }
.dg-edit-btn svg { width: 14px; height: 14px; }
.dg-chevron { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center; color: var(--djam-muted); transition: transform .2s; }
.dg-chevron svg { width: 18px; height: 18px; }
.dg-panel[open] .dg-chevron { transform: rotate(180deg); }
.dg-goals-container { padding: 16px 20px 20px; }
.dg-empty-state { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 24px 12px; text-align: center; border: 1px dashed #c9cff0; border-radius: 14px; background: var(--djam-sunken); }
.dg-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--djam-muted); }
.dg-empty-icon svg { width: 24px; height: 24px; }
.dg-empty-text strong { display: block; margin-bottom: 4px; font-size: 1rem; font-weight: 800; }
.dg-empty-text p { color: var(--djam-muted); font-size: .85rem; }
.dg-employee-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; align-items: start; }
.dg-employee-goals { border: 1px solid var(--djam-line); border-radius: 14px; overflow: hidden; background: var(--djam-surface); }
.dg-emp-header { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--djam-sunken); border-bottom: 1px solid var(--djam-line); }
.dg-emp-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; background: var(--djam-tint); color: var(--djam-brand); font-weight: 800; font-size: .8rem; }
.dg-emp-name { font-weight: 800; font-size: .92rem; }
.dg-emp-role { margin-left: auto; color: var(--djam-muted); font-size: .75rem; white-space: nowrap; }
.dg-goal-list { list-style: none; margin: 0; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.dg-goal-item { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px 8px; padding: 10px 12px; background: var(--djam-sunken); border-radius: 10px; font-size: .88rem; }
.dg-goal-title { font-weight: 700; overflow-wrap: anywhere; }
.dg-goal-desc { flex-basis: 100%; order: 3; color: var(--djam-muted); font-size: .82rem; overflow-wrap: anywhere; }
.dg-goal-meta { order: 4; }
.dg-criteria-tag { display: inline-flex; padding: 2px 8px; border-radius: 6px; background: var(--djam-tint); color: var(--djam-brand); font-size: .72rem; font-weight: 700; }
.dg-goal-date { color: var(--djam-muted); font-size: .75rem; font-weight: 700; white-space: nowrap; }

/* ---------- Filters ---------- */
.djam-filters { margin-bottom: 20px; padding: 18px 20px; background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 18px; }
.djam-filters-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.djam-filters-head h2 { font-size: 1.05rem; font-weight: 800; }
.djam-filters-head p { margin-top: 2px; color: var(--djam-muted); font-size: .85rem; }
.djam-filters-flag { display: inline-flex; align-items: center; gap: 4px; flex: none; padding: 4px 8px 4px 12px; border-radius: 999px; background: var(--djam-tint); color: var(--djam-brand); font-size: .75rem; font-weight: 700; text-decoration: none; }
.djam-filters-flag:hover { background: #dfe3fa; }
.djam-filters-flag svg { width: 13px; height: 13px; }
.djam-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.djam-field { min-width: 0; }
.djam-label { display: block; margin-bottom: 6px; color: var(--djam-ink); font-size: .82rem; font-weight: 700; }
.djam-select { width: 100%; min-height: 42px; padding: 8px 12px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; color: var(--djam-ink); font: inherit; font-size: .9rem; box-sizing: border-box; }
.djam-filter-actions { display: flex; gap: 8px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--djam-line); }
.djam-filter-actions[hidden] { display: none; }

/* ---------- Submissions card ---------- */
.djam-card { background: var(--djam-surface); border: 1px solid var(--djam-line); border-radius: 18px; overflow: hidden; }
.djam-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 16px; padding: 18px 20px 14px; }
.djam-card-head h2 { font-size: 1.05rem; font-weight: 800; }
.djam-card-head p { margin-top: 2px; color: var(--djam-muted); font-size: .85rem; }
.djam-card-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.djam-search { position: relative; display: block; margin: 0; }
.djam-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.djam-search-input { width: 16rem; max-width: 100%; min-height: 38px; padding: 7px 12px 7px 34px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; font: inherit; font-size: .88rem; color: var(--djam-ink); box-sizing: border-box; }
.djam-split { display: flex; height: 8px; margin: 0 20px 16px; border-radius: 999px; overflow: hidden; background: #e9ebf7; }
.djam-split-seg { display: block; height: 100%; }
.djam-split-seg--ok { background: #0f9d58; }
.djam-split-seg--warn { background: #f59e0b; }
.djam-split-seg--danger { background: #dc4c3f; }

.djam-table-wrap { overflow-x: auto; border-top: 1px solid var(--djam-line); }
.djam-table { width: 100%; min-width: 720px; border-collapse: collapse; margin: 0; }
.djam-table thead th { padding: 12px 20px; background: #e9ebfb; text-align: left; font-size: .85rem; font-weight: 700; white-space: nowrap; }
.djam-table tbody td { padding: 14px 20px; border-top: 1px solid var(--djam-line); vertical-align: middle; }
.djam-table tbody tr:first-child td { border-top: 0; }
.djam-table tbody tr:hover td { background: var(--djam-sunken); }
.djam-table[hidden] { display: none; }
.djam-th-actions, .djam-td-actions { text-align: right; }
.djam-row--danger td:first-child { box-shadow: inset 3px 0 0 #dc4c3f; }
.djam-row--warn td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
.djam-row[hidden] { display: none; }

.djam-person { display: flex; align-items: center; gap: 12px; min-width: 0; }
.djam-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 38px; width: 38px; height: 38px; border-radius: 50%; background: var(--djam-tint); color: var(--djam-brand); font-size: .85rem; font-weight: 800; }
.djam-person-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.djam-person-name { font-weight: 700; }
.djam-person-email { color: var(--djam-muted); font-size: .8rem; overflow-wrap: anywhere; }

.djam-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border: 1px solid transparent; border-radius: 999px; font-size: .78rem; font-weight: 700; line-height: 1.2; white-space: nowrap; }
.djam-chip svg { width: 13px; height: 13px; flex: none; }
.djam-chip--role { background: var(--djam-tint); border-color: #dde1f7; color: var(--djam-brand); }
.djam-chip--ok { background: #e7f6ee; border-color: #c7ebd8; color: #0b7a45; }
.djam-chip--warn { background: #fdf1e2; border-color: #f6ddb9; color: #92400e; }
.djam-chip--danger { background: #fdecea; border-color: #f7cfca; color: #a5281a; }
.djam-chip--info { background: #eaf1ff; border-color: #d5e3f8; color: #1d4ed8; }

.djam-submitted { display: flex; flex-direction: column; line-height: 1.3; }
.djam-submitted-date { font-weight: 600; font-size: .88rem; }
.djam-submitted-time { color: var(--djam-muted); font-size: .8rem; }
.djam-none { color: #94a3b8; }
.djam-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.djam-inline-form { display: inline-flex; margin: 0; }
.djam-action-btn { width: 36px; height: 36px; padding: 0; }

.djam-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; margin: 4px 20px 20px; padding: 32px 20px; text-align: center; border: 1px dashed #c9cff0; border-radius: 14px; background: var(--djam-sunken); }
.djam-empty[hidden] { display: none; }
.djam-empty--search { margin-top: 12px; }
.djam-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--djam-muted); }
.djam-empty-icon svg { width: 24px; height: 24px; }
.djam-empty h3 { margin-top: 4px; font-size: 1rem; font-weight: 800; }
.djam-empty p { max-width: 30rem; color: var(--djam-muted); font-size: .88rem; }

/* ---------- Responsive ---------- */
@media (max-width: 1199px) {
    .djam-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .djam-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 800px) {
    .djam-head h1 { font-size: 1.3rem; }
    .djam-datenav { flex-wrap: wrap; }
}
@media (max-width: 640px) {
    .djam-filter-grid { grid-template-columns: minmax(0, 1fr); }
    .djam-filters-head { flex-direction: column; gap: 8px; }
    .djam-filter-actions .djam-btn { flex: 1; min-height: 44px; }
    .djam-card-tools { width: 100%; }
    .djam-search { flex: 1 1 100%; }
    .djam-search-input { width: 100%; }
    .djam-action-btn { width: 44px; height: 44px; }
    .dg-panel-head { flex-wrap: wrap; }
    .dg-head-actions { width: 100%; justify-content: space-between; }
    .dg-employee-grid { grid-template-columns: minmax(0, 1fr); }
    .djam-card-head, .djam-filters { padding-left: 16px; padding-right: 16px; }
    .djam-split { margin-left: 16px; margin-right: 16px; }
}
@media (max-width: 380px) {
    .djam-stats { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) {
    .djam-stat, .dg-chevron { transition: none; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    /* ---- Filters apply as soon as a select changes ---- */
    var filterForm = document.querySelector('form[data-auto-submit]');
    if (filterForm) {
        var actions = filterForm.querySelector('[data-filter-actions]');
        if (actions) { actions.hidden = true; }
        Array.prototype.forEach.call(filterForm.querySelectorAll('select'), function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    /* ---- Confirm before deleting ---- */
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
        form.addEventListener('submit', function (event) {
            if (! window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
        });
    });

    /* ---- Search the rows already on the page ---- */
    var search = document.querySelector('[data-table-search]');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));
    var emptyState = document.querySelector('[data-search-empty]');
    var table = document.querySelector('.djam-table');
    var countLine = document.querySelector('[data-record-count]');
    var originalCount = countLine ? countLine.innerHTML : '';

    if (search && rows.length) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            var shown = 0;

            rows.forEach(function (row) {
                var match = term === '' || row.getAttribute('data-search').indexOf(term) !== -1;
                row.hidden = ! match;
                if (match) { shown++; }
            });

            if (table) { table.hidden = shown === 0; }
            if (emptyState) { emptyState.hidden = shown !== 0; }
            if (countLine) {
                countLine.innerHTML = term === ''
                    ? originalCount
                    : 'Showing ' + shown + ' of ' + rows.length + ' records';
            }
        });
    }

    /* ---- Copy everyone who is missing a report ---- */
    var copyBtn = document.querySelector('[data-copy-missing]');
    if (copyBtn) {
        var label = copyBtn.querySelector('[data-copy-label]');
        var original = label.textContent;

        copyBtn.addEventListener('click', function () {
            var names = rows
                .filter(function (row) { return row.getAttribute('data-status') === 'missing'; })
                .map(function (row) { return row.getAttribute('data-name'); });

            if (! names.length || ! navigator.clipboard) { return; }

            navigator.clipboard.writeText(names.join('\n')).then(function () {
                label.textContent = 'Copied ' + names.length + ' ' + (names.length === 1 ? 'name' : 'names');
                setTimeout(function () { label.textContent = original; }, 2000);
            });
        });
    }
})();
</script>
@endpush