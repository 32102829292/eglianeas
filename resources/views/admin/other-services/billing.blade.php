@extends('layouts.dashboard')

@section('title', 'Other Services — Billing Statements — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Other Services &mdash; Billing Statements</h1>
            <p>One-off service requests outside the quarterly billing cycle.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.other-services.settings') }}" class="btn btn-outline btn-sm">Manage service types</a>
            <a href="{{ route('admin.other-services.fill-up') }}" class="btn btn-primary">New service request</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($stats['total'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱{{ number_format($stats['paid'], 2) }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱{{ number_format($stats['outstanding'], 2) }}</b>
        </div>
        <div class="stat-card stat-icon-info">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <span class="stat-label">Total requests</span>
            <b class="stat-value">{{ $stats['count'] }}</b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.other-services.billing') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business or contact&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Business</th>
                        <th>Service</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Status</th>
                        <th>Requested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td data-col="Business">
                                <div class="fw-semibold">{{ $service->client?->business_name ?: $service->client?->name }}</div>
                                <small class="text-muted">{{ $service->client?->name }}</small>
                            </td>
                            <td data-col="Service">
                                <div class="fw-semibold">{{ $service->serviceName() }}</div>
                                @if ($service->notes)
                                    <small class="text-muted">{{ Str::limit($service->notes, 50) }}</small>
                                @endif
                            </td>
                            <td data-col="Amount" class="text-end fw-semibold">{{ $service->money() }}</td>
                            <td data-col="Status" class="text-center">
                                @php($s = $service->status)
                                <span class="badge @if($s==='paid') badge-success @elseif($s==='unpaid') badge-danger @elseif($s==='overdue') badge-danger @else badge-neutral @endif">{{ $service->statusLabel() }}</span>
                            </td>
                            <td data-col="Requested">{{ $service->requested_at?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Actions" class="text-end">
                                <a href="{{ route('admin.other-services.receipt', $service) }}" class="btn btn-outline-primary btn-sm">View receipt</a>
                                <form method="POST" action="{{ route('admin.other-services.destroy', $service) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this service record?', message: 'This service request and its payment record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm text-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No service requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($services as $service)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $service->client?->business_name ?: $service->client?->name }}<br><small class="text-muted">{{ $service->client?->name }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value">{{ $service->serviceName() }}@if ($service->notes)<br><small class="text-muted">{{ Str::limit($service->notes, 50) }}</small>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Amount</span><span class="cv-value">{{ $service->money() }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $service->status)<span class="badge @if($s==='paid') badge-success @elseif($s==='unpaid') badge-danger @elseif($s==='overdue') badge-danger @else badge-neutral @endif">{{ $service->statusLabel() }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Requested</span><span class="cv-value">{{ $service->requested_at?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><a href="{{ route('admin.other-services.receipt', $service) }}" class="btn btn-outline-primary btn-sm">View receipt</a> <form method="POST" action="{{ route('admin.other-services.destroy', $service) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this service record?', message: 'This service request and its payment record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm text-danger">Delete</button></form></span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No service requests yet.</p>
                @endforelse
            </div>
        </div>
        {{ $services->links('pagination.simple') }}
    </div>
@endsection
