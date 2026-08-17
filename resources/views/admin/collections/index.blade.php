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
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Business</th>
                        <th>Period</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th>Due date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($billings as $billing)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $billing->client?->business_name ?: $billing->client?->name }}</div>
                                <small class="text-muted">{{ $billing->client?->name }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="text-muted">{{ $billing->period_label }}</small>
                            </td>
                            <td class="text-end fw-semibold">{{ $billing->money($billing->total) }}</td>
                            <td class="text-center">
                                @php($s = $billing->status)
                                <span class="badge @if($s==='paid') bg-success @elseif($s==='unpaid') bg-danger @elseif($s==='overdue') bg-danger @elseif($s==='pending') bg-warning text-dark @else bg-secondary @endif">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td>
                                {{ $billing->due_date?->format('M j, Y') ?? '—' }}
                                @if ($billing->status === 'overdue')
                                    <div><small class="text-danger">{{ $billing->due_date?->diffForHumans() }}</small></div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.billing.receipt', $billing) }}" class="btn btn-outline-primary btn-sm">View receipt</a>
                                <form method="POST" action="{{ route('admin.collections.remind', $billing) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm">Send reminder</button>
                                </form>
                                <form method="POST" action="{{ route('admin.billing.pay', $billing) }}" class="d-inline-flex align-items-center gap-1">
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
        </div>
    </div>
@endsection
