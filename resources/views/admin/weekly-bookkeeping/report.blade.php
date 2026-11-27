@extends('layouts.app')

@section('title', 'Weekly Bookkeeping Report')

@section('content')
@php
    /*
     * Group the report rows by target date, the same way the tracker sheet does,
     * so the printed copy reads like the workbook: a band per date with that
     * day's tasks under it, and unscheduled targets last. The controller already
     * sorts by date then task type; grouping only labels those runs and never
     * reorders them. Presentation only — nothing is recomputed or saved.
     */
    $reportGroups = collect($rows)
        ->groupBy(fn ($row) => $row['target']->target_date?->format('Y-m-d') ?? 'unscheduled')
        ->map(fn ($group, $key) => [
            'key' => (string) $key,
            'date' => $key === 'unscheduled' ? null : \Illuminate\Support\Carbon::parse($key),
            'rows' => $group->values(),
            'done' => $group->filter(fn ($row) => $row['target']->isCompleted())->count(),
        ])
        ->sortBy(fn ($group) => $group['key'] === 'unscheduled' ? '9999-12-31' : $group['key'])
        ->values();

    // Column order of the four stage cells, matching the header row below.
    $stageKeys = ['pickup', 'record', 'return', 'payment'];

    $reportPercent = $stats['targets'] > 0 ? (int) round(($stats['completed'] / $stats['targets']) * 100) : 0;
@endphp

<div class="wk-report" id="wk-report">
    <div class="wk-report-bar no-print">
        <div>
            <h2 class="wk-report-title">Weekly Bookkeeping Report</h2>
            <p class="muted mb-0">
                {{ $weekStart->format('M j, Y') }} &ndash; {{ $weekEnd->format('M j, Y') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $weekStart->format('Y-m-d')]) }}"
               class="btn btn-outline btn-sm">Back to tracker</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    {{-- The screen bar above is hidden when printing, so the printed copy carries
         its own title and week. --}}
    <div class="wk-report-printhead">
        <h1>Weekly Bookkeeping Report</h1>
        <p>{{ $weekStart->format('M j, Y') }} &ndash; {{ $weekEnd->format('M j, Y') }}</p>
    </div>

    {{-- The comparison sheet keeps one row per target so a printed copy lines up
         with the workbook: only the column for that task carries a value. --}}
    <table class="wk-report-table">
        <caption class="wk-report-sr">Weekly bookkeeping targets for {{ $weekStart->format('M j, Y') }} to {{ $weekEnd->format('M j, Y') }}</caption>
        <thead>
            <tr>
                <th scope="col">Target Date</th>
                <th scope="col">Task Type</th>
                <th scope="col">Client Name</th>
                <th scope="col">Assigned Staff</th>
                <th scope="col">Pick-Up</th>
                <th scope="col">Record</th>
                <th scope="col">Return</th>
                <th scope="col">Billing</th>
                <th scope="col">Actual vs Target</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportGroups as $group)
                <tr class="wk-report-dayhead">
                    <th scope="colgroup" colspan="9">
                        <span class="wk-report-dayname">
                            @if ($group['date'])
                                {{ $group['date']->format('l') }} {{ $group['date']->format('m/d/Y') }}
                            @else
                                Unscheduled
                            @endif
                        </span>
                        <span class="wk-report-daycount">{{ $group['done'] }}/{{ $group['rows']->count() }} done</span>
                    </th>
                </tr>

                @foreach ($group['rows'] as $row)
                    @php
                        $target = $row['target'];
                        $assigned = $target->assignedStaffDisplayName();
                        $isLate = ! $target->isCompleted() && $target->isPastDue();
                    @endphp
                    <tr>
                        <td class="wk-report-date">{{ $target->target_date?->format('M j, Y') ?? 'Any day' }}</td>
                        <td>{{ $target->taskLabel() }}</td>
                        <td>{{ $target->displayClientName() }}</td>
                        <td>{{ $assigned !== '' ? $assigned : 'Unassigned' }}</td>

                        @foreach ($stageKeys as $stageKey)
                            @php $cellTarget = $row['cells'][$stageKey] ?? null; @endphp
                            <td class="wk-report-cell">
                                @if ($cellTarget)
                                    <span class="cmp-cell-text">{{ $cellTarget->cellLabel() }}</span>
                                @else
                                    <span class="cmp-none">&ndash;</span>
                                @endif
                            </td>
                        @endforeach

                        <td>
                            {{-- A finished task is never reported as overdue, even
                                 when its date has passed and it has no timing label. --}}
                            @if ($target->isCompleted() && $target->timingLabel())
                                {{ $target->timingLabel() }}
                            @elseif ($isLate)
                                <span class="wk-report-late">Overdue</span>
                            @else
                                {{ $target->effectiveStatusLabel() }}
                            @endif
                            @if ($target->performedByDisplayName())
                                <div class="wk-report-sub">{{ $target->performedByDisplayName() }}</div>
                            @endif
                            @if ($target->paymentDetail())
                                <div class="wk-report-sub">{{ $target->paymentDetail() }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="9" class="text-center muted">No targets recorded for this week.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="wk-report-summary">
        <h3>Summary</h3>
        <ul>
            <li>Selected clients: <strong>{{ $stats['clients'] }}</strong></li>
            <li>Total targets: <strong>{{ $stats['targets'] }}</strong></li>
            <li>Completed: <strong>{{ $stats['completed'] }}</strong>
                ({{ $stats['onTime'] }} on time, {{ $stats['late'] }} late)
                @if ($stats['targets'] > 0)
                    &mdash; {{ $reportPercent }}% of targets
                @endif
            </li>
            <li>Still pending: <strong>{{ $stats['pending'] }}</strong></li>
        </ul>
    </div>

    <div class="wk-report-lists">
        <div>
            <h3>Uncollected / Unfinished ({{ count($unfinishedRows) }})</h3>
            @forelse ($unfinishedRows as $row)
                <div class="wk-report-line">
                    {{ $row['target']->displayClientName() }} &mdash;
                    {{ $row['target']->taskLabel() }}
                    @if ($row['target']->target_date)
                        <span class="muted">&middot; {{ $row['target']->target_date->format('M j') }}</span>
                    @endif
                    <span class="muted">({{ $row['target']->effectiveStatusLabel() }})</span>
                </div>
            @empty
                <p class="muted">Nothing outstanding.</p>
            @endforelse
        </div>

        <div>
            <h3>Unpaid ({{ count($unpaidRows) }})</h3>
            @forelse ($unpaidRows as $row)
                <div class="wk-report-line">
                    {{ $row['target']->displayClientName() }} &mdash;
                    <span class="muted">no payment recorded</span>
                </div>
            @empty
                <p class="muted">Nothing unpaid.</p>
            @endforelse
        </div>
    </div>

    <p class="wk-report-foot muted">
        Generated {{ now()->format('M j, Y g:i A') }} from Weekly Bookkeeping.
        Payment status is read from Billing; nothing is inferred.
    </p>
</div>
@endsection

@push('styles')
<style>
.wk-report { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 8px; padding: 1rem; }
.wk-report-bar { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.wk-report-title { margin: 0; font-size: 1.15rem; }
.wk-report-printhead { display: none; }
.wk-report-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

/* The table scrolls sideways on a narrow screen instead of breaking the page. */
.wk-report { overflow-x: auto; }
.wk-report-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.wk-report-table th, .wk-report-table td { border: 1px solid var(--border-subtle, #e5e7eb); padding: .4rem .5rem; text-align: left; vertical-align: top; }
.wk-report-table thead th { background: var(--primary-bg, #e0e7ff); font-weight: 600; }
.wk-report-date { white-space: nowrap; }
.wk-report-cell { text-align: center; }
.wk-report-sub { font-size: .72rem; color: var(--muted, #6b7280); }
.wk-report-late { font-weight: 700; color: #b91c1c; }

/* One band per target date, like the tracker sheet. */
.wk-report-dayhead th { background: var(--surface-sunken, #f3f4f6); font-weight: 700; font-size: .8rem; }
.wk-report-daycount { margin-left: .75rem; font-weight: 500; color: var(--muted, #6b7280); }

.wk-report-summary, .wk-report-lists { margin-top: 1.25rem; }
.wk-report-summary h3, .wk-report-lists h3 { font-size: .95rem; margin: 0 0 .4rem; }
.wk-report-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
.wk-report-line { font-size: .82rem; padding: .15rem 0; }
.wk-report-foot { margin-top: 1.25rem; font-size: .75rem; }

@media print {
    /* Landscape fits the nine columns; the paper size stays the printer default. */
    @page { size: landscape; margin: 12mm; }

    .no-print, .sidebar, .topbar, header, footer { display: none !important; }
    body { background: #fff; }
    /* The dashboard stylesheet hides every element for print and only re-shows
       billing statements. Without this, printing this report would produce a
       blank page. Scoped to this report alone. */
    body * { visibility: hidden; }
    .wk-report, .wk-report * { visibility: visible; }
    .wk-report { position: absolute; left: 0; top: 0; width: 100%; border: 0; padding: 0; overflow: visible; }

    .wk-report-printhead { display: block; margin-bottom: 8pt; }
    .wk-report-printhead h1 { margin: 0; font-size: 14pt; }
    .wk-report-printhead p { margin: 2pt 0 0; font-size: 10pt; color: #444; }

    .wk-report-table { font-size: 10pt; }
    .wk-report-table thead { display: table-header-group; }
    .wk-report-table tr { break-inside: avoid; }
    .wk-report-dayhead { break-after: avoid; }

    /* Keep the header and band shading when the browser would drop backgrounds. */
    .wk-report-table thead th, .wk-report-dayhead th {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .wk-report-summary, .wk-report-lists { break-inside: avoid; }
}
</style>
@endpush