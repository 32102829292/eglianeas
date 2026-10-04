@extends('layouts.app')

@section('title', 'Weekly Bookkeeping Report')

@section('content')
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

    {{-- The comparison sheet keeps one row per target so a printed copy lines up
         with the workbook: only the column for that task carries a value. --}}
    <table class="wk-report-table">
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
            @forelse ($rows as $row)
                @php $target = $row['target']; @endphp
                <tr>
                    <td>{{ $target->target_date?->format('M j, Y') ?? 'Any day' }}</td>
                    <td>{{ $target->taskLabel() }}</td>
                    <td>{{ $target->displayClientName() }}</td>
                    <td>{{ $target->assignedStaffDisplayName() !== '' ? $target->assignedStaffDisplayName() : 'Unassigned' }}</td>

                    @foreach ($row['cells'] as $type => $cellTarget)
                        <td class="wk-report-cell">
                            @if ($cellTarget)
                                <span class="cmp-cell-text">{{ $cellTarget->cellLabel() }}</span>
                            @else
                                <span class="cmp-none">&ndash;</span>
                            @endif
                        </td>
                    @endforeach

                    <td>
                        @if ($target->isCompleted() && $target->timingLabel())
                            {{ $target->timingLabel() }}
                        @elseif ($target->isPastDue())
                            Overdue
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
                ({{ $stats['onTime'] }} on time, {{ $stats['late'] }} late)</li>
            <li>Still pending: <strong>{{ $stats['pending'] }}</strong></li>
        </ul>
    </div>

    <div class="wk-report-lists">
        <div>
            <h3>Uncollected / Unfinished</h3>
            @forelse ($unfinishedRows as $row)
                <div class="wk-report-line">
                    {{ $row['target']->displayClientName() }} &mdash;
                    {{ $row['target']->taskLabel() }}
                    <span class="muted">({{ $row['target']->effectiveStatusLabel() }})</span>
                </div>
            @empty
                <p class="muted">Nothing outstanding.</p>
            @endforelse
        </div>

        <div>
            <h3>Unpaid</h3>
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
.wk-report-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.wk-report-table th, .wk-report-table td { border: 1px solid var(--border-subtle, #e5e7eb); padding: .4rem .5rem; text-align: left; vertical-align: top; }
.wk-report-table thead th { background: var(--primary-bg, #e0e7ff); font-weight: 600; }
.wk-report-cell { text-align: center; }
.wk-report-sub { font-size: .72rem; color: var(--muted, #6b7280); }
.wk-report-summary, .wk-report-lists { margin-top: 1.25rem; }
.wk-report-summary h3, .wk-report-lists h3 { font-size: .95rem; margin: 0 0 .4rem; }
.wk-report-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
.wk-report-line { font-size: .82rem; padding: .15rem 0; }
.wk-report-foot { margin-top: 1.25rem; font-size: .75rem; }

@media print {
    .no-print, .sidebar, .topbar, header, footer { display: none !important; }
    body { background: #fff; }
    .wk-report { border: 0; padding: 0; }
    .wk-report-table { font-size: 10pt; }
    .wk-report-table thead { display: table-header-group; }
    .wk-report-table tr { break-inside: avoid; }
}
</style>
@endpush
