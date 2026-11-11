@extends('layouts.dashboard')

@section('title', 'Staff Performance — Egliane Accounting Services')

@section('content')
    {{-- Page Header --}}
    <header class="eas-page-head">
        <div class="eas-head-main">
            <div class="eas-head-left">
                <span class="eas-eyebrow">Staff Performance</span>
                <h1 class="eas-head-title">Performance Dashboard</h1>
                <p class="eas-head-sub">Monitor staff evaluations, scores, and contribution records across all Kaizen tasks.</p>
            </div>
        </div>
        <div class="eas-head-actions">
            <a href="{{ route('admin.staff-performance.config') }}" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
                Scoring Config
            </a>
        </div>
    </header>

    {{-- Summary Cards --}}
    <div class="eas-stat-grid">
        <div class="eas-stat-card eas-stat-total">
            <div class="eas-stat-icon eas-stat-icon-total">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
            </div>
            <div class="eas-stat-info">
                <span class="eas-stat-label">Total Staff</span>
                <span class="eas-stat-value">{{ $totalStaff }}</span>
            </div>
        </div>
        <div class="eas-stat-card eas-stat-evaluated">
            <div class="eas-stat-icon eas-stat-icon-evaluated">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="eas-stat-info">
                <span class="eas-stat-label">Evaluated</span>
                <span class="eas-stat-value">{{ $evaluatedStaff }}</span>
            </div>
        </div>
        <div class="eas-stat-card eas-stat-average">
            <div class="eas-stat-icon eas-stat-icon-average">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 16 12 8"/></svg>
            </div>
            <div class="eas-stat-info">
                <span class="eas-stat-label">Avg Score</span>
                <span class="eas-stat-value">{{ $avgScore ?? '—' }}</span>
            </div>
        </div>
        <div class="eas-stat-card eas-stat-outstanding">
            <div class="eas-stat-icon eas-stat-icon-outstanding">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="eas-stat-info">
                <span class="eas-stat-label">Outstanding</span>
                <span class="eas-stat-value">{{ $outstandingCount }}</span>
            </div>
        </div>
        <div class="eas-stat-card eas-stat-pending">
            <div class="eas-stat-icon eas-stat-icon-pending">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16"/></svg>
            </div>
            <div class="eas-stat-info">
                <span class="eas-stat-label">Pending</span>
                <span class="eas-stat-value">{{ $pendingCount }}</span>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="eas-card">
        <div class="eas-card-head">
            <h2 class="eas-card-title">Filter Evaluations</h2>
        </div>
        <div class="eas-card-body">
            <form method="GET" class="eas-filter-form">
                <div class="eas-filter-grid eas-filter-grid-three">
                    <div class="eas-filter-group">
                        <label class="eas-filter-label" for="period_id">Evaluation Period</label>
                        <select class="eas-form-control" id="period_id" name="period_id">
                            <option value="">All periods</option>
                            @foreach ($periods as $p)
                                <option value="{{ $p->id }}" {{ $period && $p->id == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->dateRangeLabel() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="eas-filter-group">
                        <label class="eas-filter-label" for="staff_id">Staff Member</label>
                        <select class="eas-form-control" id="staff_id" name="staff_id">
                            <option value="">All staff</option>
                            @foreach ($staffList as $s)
                                <option value="{{ $s->id }}" {{ $selectedStaffId && $s->id == $selectedStaffId ? 'selected' : '' }}>{{ $s->name }} ({{ $s->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="eas-filter-group">
                        <label class="eas-filter-label" for="grade">Grade</label>
                        <select class="eas-form-control" id="grade" name="grade">
                            <option value="">All grades</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g['value'] }}" {{ $selectedGrade === $g['value'] ? 'selected' : '' }}>{{ $g['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="eas-filter-group">
                        <label class="eas-filter-label" for="status">Status</label>
                        <select class="eas-form-control" id="status" name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s['value'] }}" {{ $selectedStatus === $s['value'] ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="eas-filter-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('staff-performance.index') }}" class="btn btn-outline">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Evaluations Table --}}
    <div class="eas-card">
        <div class="eas-card-head">
            <div>
                <h2 class="eas-card-title">Staff Evaluations</h2>
                <span class="eas-card-count">{{ $evaluations->total() }} {{ Str::plural('evaluation', $evaluations->total()) }}</span>
            </div>
        </div>
        <div class="eas-card-body">
            <div class="eas-table-wrap table-card-view">
                <table class="eas-table eas-table-hover align-middle mb-0 priority-board-table">
                    <thead class="eas-thead-muted">
                        <tr>
                            <th>Staff Member</th>
                            <th class="text-nowrap">Role</th>
                            <th class="text-nowrap">Overall Score</th>
                            <th class="text-nowrap">Grade</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap">Source</th>
                            <th class="text-nowrap">Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($evaluations as $eval)
                        <tr>
                            <td>
                                <a href="{{ route('admin.staff-performance.staff', $eval->staff) }}">{{ $eval->staff->name }}</a>
                            </td>
                            <td class="text-nowrap">{{ ucfirst($eval->staff->role) }}</td>
                            <td class="text-nowrap">
                                <span class="badge {{ $eval->overall_score >= 90 ? 'badge-success' : ($eval->overall_score >= 80 ? 'badge-info' : 'badge-warn') }}">
                                    {{ $eval->overall_score ?? '—' }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <span class="badge {{ $eval->performance_grade === 'Outstanding' ? 'badge-success' : ($eval->performance_grade === 'Excellent' ? 'badge-info' : 'badge-neutral') }}">
                                    {{ $eval->performance_grade ?? '—' }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <span class="badge {{ $eval->evaluation_status === 'finalized' ? 'badge-success' : 'badge-warn' }}">
                                    {{ ucfirst($eval->evaluation_status ?? '—') }}
                                </span>
                            </td>
                            <td class="text-nowrap">{{ $eval->source_type ?? '—' }}</td>
                            <td class="text-nowrap">{{ $eval->updated_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.staff-performance.staff', $eval->staff) }}" class="btn btn-outline btn-sm">View Details</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="empty-cell">No evaluations found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $evaluations->links() }}
        </div>
    </div>
@endsection