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
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($stats['billed'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱{{ number_format($stats['collected'], 2) }}</b>
        </div>
        <div class="stat-card stat-warn">
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱{{ number_format($stats['outstanding'], 2) }}</b>
        </div>
        <div class="stat-card stat-danger">
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
            <table class="table">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Contact</th>
                        <th>Bills</th>
                        <th>Total billed</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php($client = $entry['user'])
                        <tr>
                            <td>
                                <div class="cell-name">{{ $client->business_name ?: $client->name }}</div>
                                <small class="muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="cell-name">{{ $client->name }}</div>
                                <small class="muted">{{ $client->email }}</small>
                            </td>
                            <td>{{ $entry['billing_count'] }}</td>
                            <td><b>{{ '₱'.number_format($entry['total_billed'], 2) }}</b></td>
                            <td>
                                @if ($entry['outstanding'] > 0)
                                    <b class="text-danger">₱{{ number_format($entry['outstanding'], 2) }}</b>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($entry['status'] === 'none')
                                    <span class="badge badge-neutral">No billing</span>
                                @else
                                    <span class="badge badge-{{ $entry['status'] }}">{{ App\Models\Billing::STATUSES[$entry['status']] }}</span>
                                @endif
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.billing.show', $client) }}" class="btn btn-outline btn-sm">Open billing</a>
                                <a href="{{ route('admin.billing.clientCsv', $client) }}" class="link">CSV</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
