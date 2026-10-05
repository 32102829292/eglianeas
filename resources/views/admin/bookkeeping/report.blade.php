@extends('layouts.app')

@section('title', $config['title'].' Report')

@section('content')
@php
    $prefix = $config['route_prefix'];
    $unit = strtolower($config['unit']);
@endphp
<div class="bk-print-report" id="bk-print-report">
    <div class="bk-print-bar no-print">
        <div>
            <h2 class="bk-print-title">{{ $config['title'] }} Report</h2>
            <p class="muted mb-0">
                {{ $period->rangeLabel() }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route($prefix.'.index', [$config['query_key'] => $activePeriodKey]) }}"
               class="btn btn-outline btn-sm">Back to tracker</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    {{-- The comparison sheet keeps one row per target so a printed copy lines up
         with the workbook: only the column for that task carries a value. --}}
    <table class="bk-print-table">
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
                        <td class="bk-print-cell">
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
                            <div class="bk-print-sub">{{ $target->performedByDisplayName() }}</div>
                        @endif
                        @if ($target->paymentDetail())
                            <div class="bk-print-sub">{{ $target->paymentDetail() }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center muted">No targets recorded for this {{ $unit }}.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="bk-print-summary">
        <h3>Summary</h3>
        <ul>
            <li>Selected clients: <strong>{{ $stats['clients'] }}</strong></li>
            <li>Total targets: <strong>{{ $stats['targets'] }}</strong></li>
            <li>Completed: <strong>{{ $stats['completed'] }}</strong>
                ({{ $stats['onTime'] }} on time, {{ $stats['late'] }} late)</li>
            <li>Still pending: <strong>{{ $stats['pending'] }}</strong></li>
        </ul>
    </div>

    <div class="bk-print-lists">
        <div>
            <h3>Uncollected / Unfinished</h3>
            @forelse ($unfinishedRows as $row)
                <div class="bk-print-line">
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
                <div class="bk-print-line">
                    {{ $row['target']->displayClientName() }} &mdash;
                    <span class="muted">no payment recorded</span>
                </div>
            @empty
                <p class="muted">Nothing unpaid.</p>
            @endforelse
        </div>
    </div>

    <p class="bk-print-foot muted">
        Generated {{ now()->format('M j, Y g:i A') }} from {{ $config['title'] }}.
        Payment status is read from Billing; nothing is inferred.
    </p>
</div>
@endsection

@push('styles')
<style>
.cmp-cell-text { font-size: var(--text-xs); font-weight: 700; }
.cmp-none { color: #94A3B8; }

.bk-print-report { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 8px; padding: 1rem; }
.bk-print-bar { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.bk-print-title { margin: 0; font-size: 1.15rem; }
.bk-print-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.bk-print-table th, .bk-print-table td { border: 1px solid var(--border-subtle, #e5e7eb); padding: .4rem .5rem; text-align: left; vertical-align: top; }
.bk-print-table thead th { background: var(--primary-bg, #e0e7ff); font-weight: 600; }
.bk-print-cell { text-align: center; }
.bk-print-sub { font-size: .72rem; color: var(--muted, #6b7280); }
.bk-print-summary, .bk-print-lists { margin-top: 1.25rem; }
.bk-print-summary h3, .bk-print-lists h3 { font-size: .95rem; margin: 0 0 .4rem; }
.bk-print-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
.bk-print-line { font-size: .82rem; padding: .15rem 0; }
.bk-print-foot { margin-top: 1.25rem; font-size: .75rem; }

@media print {
    .no-print, .sidebar, .topbar, header, footer { display: none !important; }
    body { background: #fff; }
    .bk-print-report { border: 0; padding: 0; }
    .bk-print-table { font-size: 10pt; }
    .bk-print-table thead { display: table-header-group; }
    .bk-print-table tr { break-inside: avoid; }
}
</style>
@endpush
