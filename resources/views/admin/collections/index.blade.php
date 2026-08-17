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
