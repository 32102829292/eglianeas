@extends('layouts.dashboard')

@section('title', 'Activity Logs — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Activity logs</h1>
        <p>Every important action across the portal.</p>
    </div>

    <form method="GET" action="{{ route('admin.activity-logs') }}" class="filter-bar">
        <input class="form-control" type="search" name="q" value="{{ $query }}" placeholder="Search action or details…">
        <button type="submit" class="btn btn-outline">Search</button>
        @if ($query)
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-link">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td data-col="User" class="cell-name">{{ $log->user?->name ?? 'Guest' }}</td>
                            <td data-col="Action"><code class="code-pill">{{ $log->action }}</code></td>
                            <td data-col="Details" class="muted">{{ $log->description }}</td>
                            <td data-col="IP" class="muted">{{ $log->ip_address }}</td>
                            <td data-col="When" class="muted">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No activity found.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($logs as $log)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">User</span><span class="cv-value">{{ $log->user?->name ?? 'Guest' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Action</span><span class="cv-value"><code class="code-pill">{{ $log->action }}</code></span></div>
                        <div class="cv-row"><span class="cv-label">Details</span><span class="cv-value">{{ $log->description }}</span></div>
                        <div class="cv-row"><span class="cv-label">IP</span><span class="cv-value">{{ $log->ip_address }}</span></div>
                        <div class="cv-row"><span class="cv-label">When</span><span class="cv-value">{{ $log->created_at->diffForHumans() }}</span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No activity found.</p>
                @endforelse
            </div>
        </div>
        {{ $logs->links('pagination.simple') }}
    </div>
@endsection
