@extends('layouts.dashboard')

@section('title', 'Billing — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing</h1>
            <p>Create and manage quarterly billing statements per client.</p>
        </div>
        <a href="{{ route('admin.billing.create') }}" class="btn btn-primary">Create billing</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($stats['billed'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱{{ number_format($stats['collected'], 2) }}</b>
        </div>
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
            <span class="stat-label">Overdue bills</span>
            <b class="stat-value">{{ $stats['overdue'] }}</b>
        </div>
    </div>

    @if ($missingSalesClients->count())
        <div class="alert-strip">
            <div class="alert-strip-head">
                <b>Missing sales &mdash; {{ App\Models\Billing::QUARTERS[$missingQuarter] }} Quarter {{ $missingYear }}</b>
                <small>These clients have not submitted their sales for the current quarter and are being reminded daily.</small>
            </div>
            <div class="alert-strip-body">
                @foreach ($missingSalesClients as $client)
                    <span class="alert-chip">
                        {{ $client->name }}
                        <small>{{ $client->business_name }}</small>
                        <a href="{{ route('admin.billing.create') }}" class="alert-chip-link">Add sales</a>
                    </span>
                @endforeach
            </div>
        </div>
    @else
        <div class="alert-strip alert-strip-ok">
            All clients have submitted their sales for {{ App\Models\Billing::QUARTERS[$missingQuarter] }} Quarter {{ $missingYear }}.
        </div>
    @endif

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.billing.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business or contact&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Business</th>
                        <th>Contact</th>
                        <th class="text-center">Bills</th>
                        <th class="text-end">Total billed</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php($client = $entry['user'])
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="text-muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="text-muted">{{ $client->email }}</small>
                            </td>
                            <td class="text-center">{{ $entry['billing_count'] }}</td>
                            <td class="text-end fw-semibold">{{ '₱'.number_format($entry['total_billed'], 2) }}</td>
                            <td class="text-end">
                                @if ($entry['outstanding'] > 0)
                                    <span class="text-danger fw-semibold">₱{{ number_format($entry['outstanding'], 2) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($entry['status'] === 'none')
                                    <span class="badge bg-secondary">No billing</span>
                                @else
                                    @php($s = $entry['status'])
                                    <span class="badge @if($s==='current') bg-success @elseif($s==='delinquent') bg-warning text-dark @elseif($s==='critical') bg-danger @else bg-secondary @endif">{{ App\Models\Billing::STATUSES[$s] }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.billing.show', $client) }}" class="btn btn-outline-primary btn-sm">Open billing</a>
                                <a href="{{ route('admin.billing.clientCsv', $client) }}" class="btn btn-link btn-sm">CSV</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
