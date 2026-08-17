@extends('layouts.dashboard')

@section('title', 'Collections — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Collections</h1>
            <p>Track unpaid, pending, and overdue billings and follow up with clients.</p>
        </div>
        <a href="{{ route('admin.billing.create') }}" class="btn btn-primary">Create billing</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-warn">
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱{{ number_format($stats['outstanding'], 2) }}</b>
        </div>
        <div class="stat-card stat-danger">
            <span class="stat-label">Overdue bills</span>
            <b class="stat-value">{{ $stats['overdueCount'] }}</b>
        </div>
        <div class="stat-card">
            <span class="stat-label">Due within 7 days</span>
            <b class="stat-value">{{ $stats['dueSoon'] }}</b>
        </div>
        <div class="stat-card">
            <span class="stat-label">Awaiting sales (pending)</span>
            <b class="stat-value">{{ $stats['pendingCount'] }}</b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.collections.index') }}">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['pending' => 'Pending', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue'] as $value => $label)
                    <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Period</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Due date</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($billings as $billing)
                        <tr>
                            <td>
                                <div class="cell-name">{{ $billing->client?->business_name ?: $billing->client?->name }}</div>
                                <small class="muted">{{ $billing->client?->name }}</small>
                            </td>
                            <td>
                                <div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="muted">{{ $billing->period_label }}</small>
                            </td>
                            <td><b>{{ $billing->money($billing->total) }}</b></td>
                            <td>
                                <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td>
                                {{ $billing->due_date?->format('M j, Y') ?? '—' }}
                                @if ($billing->status === 'overdue')
                                    <div><small class="text-danger">{{ $billing->due_date?->diffForHumans() }}</small></div>
                                @endif
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.billing.receipt', $billing) }}" class="btn btn-outline btn-sm">View receipt</a>
                                <form method="POST" action="{{ route('admin.collections.remind', $billing) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="link">Send reminder</button>
                                </form>
                                <form method="POST" action="{{ route('admin.billing.pay', $billing) }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="status" value="paid">
                                    <input type="date" name="paid_at" class="form-control-inline" value="{{ old('paid_at', now()->format('Y-m-d')) }}" title="Date paid" aria-label="Date paid">
                                    <button type="submit" class="link">Mark paid</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">Nothing to collect right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
