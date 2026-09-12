@extends('layouts.dashboard')

@section('title', 'Collections & Follow-ups — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Collections &amp; Follow-ups</h1>
            <p>Track unpaid, pending, and overdue billings and follow up with clients.</p>
        </div>
        <a href="{{ route('admin.billing.create') }}" class="btn btn-primary">Create billing statement</a>
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
        <div class="stat-card">
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
        <form id="collections-filter" method="GET" action="{{ route('admin.collections.index') }}">
            <label class="toolbar-field">
                <span class="toolbar-label">Quarter</span>
                <select name="quarter" class="toolbar-select" aria-label="Filter by quarter">
                    <option value="">All quarters</option>
                    @foreach ($availableQuarters as $quarter)
                        <option value="{{ $quarter->key() }}" @selected($activeQuarter?->equals($quarter))>{{ $quarter->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="toolbar-field">
                <span class="toolbar-label">Status</span>
                <select name="status" class="toolbar-select" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    @foreach (['pending' => 'Pending', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue'] as $value => $label)
                        <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 collections-table">
                <colgroup>
                    <col class="col-business">
                    <col class="col-period">
                    <col class="col-total">
                    <col class="col-status">
                    <col class="col-due">
                    <col class="col-actions">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Business</th>
                        <th>Period</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th>Due date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($billings as $billing)
                        <tr>
                            <td data-col="Business">
                                <div class="fw-semibold">{{ $billing->client?->business_name ?: $billing->client?->name }}</div>
                                <small class="muted">{{ $billing->client?->name }}</small>
                            </td>
                            <td data-col="Period">
                                <div class="fw-semibold">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="muted">{{ $billing->period_label }}</small>
                            </td>
                            <td class="text-end fw-semibold td-money" data-col="Total">{{ $billing->money($billing->total) }}</td>
                            <td class="text-center td-status" data-col="Status">
                                <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td class="td-due" data-col="Due date">
                                {{ $billing->due_date?->format('M j, Y') ?? '—' }}
                                @if ($billing->status === 'overdue')
                                    <div><small class="text-danger">{{ $billing->due_date?->diffForHumans() }}</small></div>
                                @endif
                            </td>
                            <td class="col-actions" data-col="Actions">
                                @include('admin.collections._row-actions', ['billing' => $billing, 'menuType' => 'table'])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">Nothing to collect right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($billings as $billing)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $billing->client?->business_name ?: $billing->client?->name }}</div>
                                <div class="cv-head-sub">{{ $billing->periodTitleUppercase() }} BILLING</div>
                            </div>
                            <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Client</span><span class="cv-value">{{ $billing->client?->name }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Total</span><span class="cv-value">{{ $billing->money($billing->total) }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Due date</span><span class="cv-value">{{ $billing->due_date?->format('M j, Y') ?? '—' }}{{ $billing->status === 'overdue' ? ' ('.$billing->due_date?->diffForHumans().')' : '' }}</span></div>
                        </div>
                        <div class="cv-card-actions">
                            @include('admin.collections._row-actions', ['billing' => $billing, 'menuType' => 'card'])
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">Nothing to collect right now.</p>
                @endforelse
            </div>
        </div>
        {{ $billings->links('pagination.simple') }}
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('collections-filter').addEventListener('submit', function (e) {
        var quarter = this.elements.namedItem('quarter');
        if (quarter && !quarter.value) quarter.disabled = true;
        var status = this.elements.namedItem('status');
        if (status && !status.value) status.disabled = true;
    });

    (function () {
        var menus = Array.prototype.slice.call(document.querySelectorAll('.more-menu'));

        function closeAll(except) {
            menus.forEach(function (menu) {
                if (menu === except) return;
                menu.style.display = 'none';
                var btn = document.getElementById(menu.getAttribute('aria-labelledby'));
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        }

        function setPos(menu) {
            var rect = menu.getBoundingClientRect();
            menu.classList.toggle('dropdown-menu--up', rect.bottom > window.innerHeight - 8);
        }

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                if (!menu) return;
                var willOpen = menu.style.display !== 'block';
                closeAll(menu);
                if (willOpen) {
                    menu.style.display = 'block';
                    toggle.setAttribute('aria-expanded', 'true');
                    setPos(menu);
                } else {
                    menu.style.display = 'none';
                    toggle.setAttribute('aria-expanded', 'false');
                }
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });

        window.addEventListener('resize', function () {
            closeAll();
        });
    })();
</script>
@endpush
