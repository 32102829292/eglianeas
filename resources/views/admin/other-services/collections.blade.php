@extends('layouts.dashboard')

@section('title', 'Other Services — Collections & Follow-ups — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Other Services &mdash; Collections &amp; Follow-ups</h1>
            <p>Track and collect payments for one-off service requests.</p>
        </div>
        <a href="{{ route('admin.other-services.fill-up') }}" class="btn btn-primary">New service request</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱{{ number_format($stats['outstanding'], 2) }}</b>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">Overdue</span>
            <b class="stat-value">{{ $stats['overdueCount'] }}</b>
        </div>
        <div class="stat-card stat-icon-info">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="stat-label">Due within 7 days</span>
            <b class="stat-value">{{ $stats['dueSoon'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <span class="stat-label">Unpaid</span>
            <b class="stat-value">{{ $stats['unpaidCount'] }}</b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.other-services.collections') }}">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['unpaid' => 'Unpaid', 'overdue' => 'Overdue'] as $value => $label)
                    <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
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
                        <th>Due date</th>
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
                                <small class="text-muted">{{ $service->requested_at?->format('M j, Y') }}</small>
                            </td>
                            <td data-col="Amount" class="text-end fw-semibold">{{ $service->money() }}</td>
                            <td data-col="Status" class="text-center">
                                @php($s = $service->status)
                                <span class="badge @if($s==='paid') badge-success @elseif($s==='unpaid') badge-danger @elseif($s==='overdue') badge-danger @else badge-neutral @endif">{{ $service->statusLabel() }}</span>
                            </td>
                            <td data-col="Due date">
                                {{ $service->due_date?->format('M j, Y') ?? '—' }}
                                @if ($service->status === 'overdue')
                                    <div><small class="text-danger">{{ $service->due_date?->diffForHumans() }}</small></div>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end">
                                <a href="{{ route('admin.other-services.receipt', $service) }}" class="btn btn-outline-primary btn-sm">View receipt</a>
                                <form method="POST" action="{{ route('admin.other-services.pay', $service) }}" class="d-inline-flex align-items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="status" value="paid">
                                    <input type="date" name="paid_at" class="form-control form-control-sm" style="width:auto" value="{{ old('paid_at', now()->format('Y-m-d')) }}" title="Date paid" aria-label="Date paid">
                                    <button type="submit" class="btn btn-link btn-sm">Mark paid</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nothing to collect right now.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($services as $service)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $service->client?->business_name ?: $service->client?->name }}<br><small class="text-muted">{{ $service->client?->name }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value">{{ $service->serviceName() }}<br><small class="text-muted">{{ $service->requested_at?->format('M j, Y') }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Amount</span><span class="cv-value">{{ $service->money() }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $service->status)<span class="badge @if($s==='paid') badge-success @elseif($s==='unpaid') badge-danger @elseif($s==='overdue') badge-danger @else badge-neutral @endif">{{ $service->statusLabel() }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Due date</span><span class="cv-value">{{ $service->due_date?->format('M j, Y') ?? '—' }}@if ($service->status === 'overdue')<br><small class="text-danger">{{ $service->due_date?->diffForHumans() }}</small>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><a href="{{ route('admin.other-services.receipt', $service) }}" class="btn btn-outline-primary btn-sm">View receipt</a> <form method="POST" action="{{ route('admin.other-services.pay', $service) }}" class="d-inline-flex align-items-center gap-1">@csrf<input type="hidden" name="status" value="paid"><input type="date" name="paid_at" class="form-control form-control-sm" style="width:auto" value="{{ old('paid_at', now()->format('Y-m-d')) }}" title="Date paid" aria-label="Date paid"><button type="submit" class="btn btn-link btn-sm">Mark paid</button></form></span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">Nothing to collect right now.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
