@extends('layouts.dashboard')

@section('title', 'Technical Issue Reporting — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Technical Issue Reporting</h1>
            <p>{{ $canManage ? 'Review, prioritise and resolve every reported issue.' : 'Track the technical issues you have reported and their progress.' }}</p>
        </div>
        <a href="{{ route('admin.technical-issues.create', ['from' => request()->fullUrl()]) }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 6px; vertical-align: -3px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Report an Issue
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="stat-grid cols-4">
        <div class="stat-box"><b>{{ $stats['open'] }}</b><span>Open</span></div>
        <div class="stat-box"><b>{{ $stats['in_progress'] }}</b><span>In Progress</span></div>
        <div class="stat-box"><b>{{ $stats['resolved'] }}</b><span>Resolved</span></div>
        <div class="stat-box"><b>{{ $stats['closed'] }}</b><span>Closed</span></div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Filter Reports</h2>
        </div>
        <form method="GET" action="{{ route('admin.technical-issues.index') }}" class="filter-panel">
            <div class="form-grid three align-items-end gap-3">
                <div class="form-group">
                    <label class="form-label" for="filter_q">Search</label>
                    <input class="form-control" id="filter_q" name="q" type="text" value="{{ $q }}" placeholder="Search title, description, reference...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_status">Status</label>
                    <select class="form-control" id="filter_status" name="status">
                        <option value="">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_priority">Priority</label>
                    <select class="form-control" id="filter_priority" name="priority">
                        <option value="">All</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($activePriority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_category">Category</label>
                    <select class="form-control" id="filter_category" name="category">
                        <option value="">All</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected($activeCategory === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.technical-issues.index') }}" class="btn btn-outline">Clear</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">{{ $canManage ? 'All Reports' : 'My Reports' }}</h2>
            <span class="card-head-note">{{ $issues->total() }} {{ Str::plural('report', $issues->total()) }}</span>
        </div>

        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Issue</th>
                        <th>Category</th>
                        @if ($canManage)
                            <th>Reported By</th>
                        @endif
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Attachments</th>
                        <th>Last Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issues as $issue)
                        <tr>
                            <td data-col="Issue">
                                <div class="kaizen-challenge">{{ Str::limit($issue->title, 80) }}</div>
                                <div class="kaizen-cell-sub">{{ $issue->reference }} &middot; {{ $issue->created_at?->format('M j, Y g:ia') }}</div>
                            </td>
                            <td data-col="Category">{{ $issue->categoryLabel() }}</td>
                            @if ($canManage)
                                <td data-col="Reported By">{{ $issue->user->name ?? '—' }}</td>
                            @endif
                            <td data-col="Priority"><span class="badge {{ $issue->priorityBadgeClass() }}">{{ $issue->priorityLabel() }}</span></td>
                            <td data-col="Status"><span class="badge {{ $issue->statusBadgeClass() }}">{{ $issue->statusLabel() }}</span></td>
                            <td data-col="Attachments">
                                @if ((int) ($issue->attachments_count ?? 0) > 0)
                                    {{ (int) $issue->attachments_count }} {{ Str::plural('file', (int) $issue->attachments_count) }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-col="Last Updated">
                                <div>{{ $issue->updated_at?->format('M j, Y g:ia') }}</div>
                                <div class="kaizen-cell-sub">{{ $issue->updater->name ?? '—' }}</div>
                            </td>
                            <td data-col="Actions" class="text-end">
                                <a href="{{ route('admin.technical-issues.show', $issue) }}" class="btn btn-outline btn-sm">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="empty-cell">
                            @if ($hasFilters)
                                No reports match your current filters.
                            @else
                                No technical issues reported yet.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($issues as $issue)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $issue->title }}</div>
                                <div class="cv-head-sub">{{ $issue->reference }} &middot; {{ $issue->created_at?->format('M j, Y') }}</div>
                                <div class="cv-head-sub">
                                    <span class="badge {{ $issue->statusBadgeClass() }}">{{ $issue->statusLabel() }}</span>
                                    <span class="badge {{ $issue->priorityBadgeClass() }}">{{ $issue->priorityLabel() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-row"><span class="cv-label">Category</span><span class="cv-value">{{ $issue->categoryLabel() }}</span></div>
                            @if ($canManage)
                                <div class="cv-row"><span class="cv-label">Reported By</span><span class="cv-value">{{ $issue->user->name ?? '—' }}</span></div>
                            @endif
                            <div class="cv-row"><span class="cv-label">Attachments</span><span class="cv-value">{{ (int) ($issue->attachments_count ?? 0) }}</span></div>
                            <div class="cv-row"><span class="cv-label">Last Updated</span><span class="cv-value">{{ $issue->updated_at?->format('M j, Y g:ia') }}</span></div>
                        </div>
                        <div class="cv-card-actions">
                            <a href="{{ route('admin.technical-issues.show', $issue) }}" class="btn btn-outline btn-sm">View</a>
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">
                        @if ($hasFilters)
                            No reports match your current filters.
                        @else
                            No technical issues reported yet.
                        @endif
                    </p>
                @endforelse
            </div>
        </div>

        {{ $issues->links('pagination.simple') }}
    </div>
@endsection
