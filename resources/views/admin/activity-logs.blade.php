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
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="cell-name">{{ $log->user?->name ?? 'Guest' }}</td>
                            <td><code class="code-pill">{{ $log->action }}</code></td>
                            <td class="muted">{{ $log->description }}</td>
                            <td class="muted">{{ $log->ip_address }}</td>
                            <td class="muted">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No activity found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('pagination.simple') }}
    </div>
@endsection
