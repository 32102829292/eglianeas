@extends('layouts.dashboard')

@section('title', 'Billing Statements — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing Statements</h1>
            <p>Create and manage quarterly billing statements per client.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.billing.create') }}" class="btn btn-primary">Create billing statement</a>
        </div>
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

    <div class="page-toolbar">
        <form class="search-combo" method="GET" action="{{ route('admin.billing.index') }}" role="search">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business or contact&hellip;" aria-label="Search billing statements">
            <button type="submit" class="search-combo-btn">Filter</button>
        </form>
        <div class="page-toolbar-group">
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-outline btn-sm dropdown-toggle" data-dropdown="billing-download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Billing Summary
                </button>
                <div class="dropdown-menu billing-download-panel" id="billing-download-menu">
                    <div class="billing-download-row">
                        <label class="form-label" for="dl-quarter">Quarter</label>
                        <select class="form-control form-control-sm" id="dl-quarter">
                            <option value="">All Quarters</option>
                            <option value="1">1st Quarter</option>
                            <option value="2">2nd Quarter</option>
                            <option value="3">3rd Quarter</option>
                            <option value="4">4th Quarter</option>
                        </select>
                    </div>
                    <div class="billing-download-row">
                        <label class="form-label" for="dl-year">Year</label>
                        <select class="form-control form-control-sm" id="dl-year"></select>
                    </div>
                    <div class="billing-download-btns">
                        <a href="#" id="dl-xlsx" class="btn btn-outline-primary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            XLSX
                        </a>
                        <a href="#" id="dl-pdf" class="btn btn-outline-danger btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            PDF
                        </a>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.billing.paymentSettings') }}" class="btn btn-outline btn-sm">Payment Settings</a>
            <a href="{{ route('admin.billing.settings') }}" class="btn btn-outline btn-sm">Fee Presets</a>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
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
                            <td data-col="Business">
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="text-muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="text-muted"><a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></small>
                            </td>
                            <td class="text-center" data-col="Bills">{{ $entry['billing_count'] }}</td>
                            <td class="text-end fw-semibold" data-col="Total billed">{{ '₱'.number_format($entry['total_billed'], 2) }}</td>
                            <td class="text-end" data-col="Outstanding">
                                @if ($entry['outstanding'] > 0)
                                    <span class="text-danger fw-semibold">₱{{ number_format($entry['outstanding'], 2) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center" data-col="Status">
                                @if ($entry['status'] === 'none')
                                    <span class="badge badge-neutral">No billing statements</span>
                                @else
                                    @php($s = $entry['status'])
                                    <span class="badge badge-{{ $s }}">{{ App\Models\Billing::STATUSES[$s] ?? ucfirst($s) }}</span>
                                @endif
                            </td>
                            <td class="text-end" data-col="Actions">
                                <a href="{{ route('admin.billing.show', $client) }}" class="btn btn-outline-primary btn-sm">Open billing statement</a>
                                <a href="{{ route('admin.billing.clientCsv', $client) }}" class="btn btn-link btn-sm">CSV</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($entries as $entry)
                    @php($client = $entry['user'])
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $client->business_name ?: $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Contact</span><span class="cv-value">{{ $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Bills</span><span class="cv-value">{{ $entry['billing_count'] }}</span></div>
                        <div class="cv-row"><span class="cv-label">Total billed</span><span class="cv-value">{{ '₱'.number_format($entry['total_billed'], 2) }}</span></div>
                        <div class="cv-row"><span class="cv-label">Outstanding</span><span class="cv-value">{{ $entry['outstanding'] > 0 ? '₱'.number_format($entry['outstanding'], 2) : '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">{{ $entry['status'] === 'none' ? 'No billing statements' : (App\Models\Billing::STATUSES[$entry['status']] ?? ucfirst($entry['status'])) }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><a href="{{ route('admin.billing.show', $client) }}" class="btn btn-outline-primary btn-sm">Open billing statement</a> <a href="{{ route('admin.billing.clientCsv', $client) }}" class="btn btn-link btn-sm">CSV</a></span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No clients found.</p>
                @endforelse
            </div>
        </div>
        {{ $entries->links('pagination.simple') }}
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown]');
        if (toggle) {
            var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
            if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            return;
        }
        if (e.target.closest('.dropdown-menu')) return;
        document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
    });
    (function () {
        var quarterSelect = document.getElementById('dl-quarter');
        var yearSelect = document.getElementById('dl-year');
        var xlsxLink = document.getElementById('dl-xlsx');
        var pdfLink = document.getElementById('dl-pdf');
        var xlsxBase = '{{ route("admin.billing.exportSummaryXlsx") }}';
        var pdfBase = '{{ route("admin.billing.exportSummaryPdf") }}';

    function buildUrl(base) {
        var params = new URLSearchParams();
        if (quarterSelect.value) params.set('quarter', quarterSelect.value);
        if (yearSelect.value) params.set('year', yearSelect.value);
        var qs = params.toString();
        return qs ? base + '?' + qs : base;
    }

    function refreshLinks() {
        xlsxLink.href = buildUrl(xlsxBase);
        pdfLink.href = buildUrl(pdfBase);
    }

    fetch('{{ route("admin.billing.years") }}')
        .then(function (r) { return r.json(); })
        .then(function (years) {
            var currentYear = new Date().getFullYear();
            years.forEach(function (y) {
                var opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                if (y === currentYear) opt.selected = true;
                yearSelect.appendChild(opt);
            });
            if (!years.includes(currentYear) && years.length) {
                yearSelect.value = years[0];
            }
            refreshLinks();
        });

    quarterSelect.addEventListener('change', refreshLinks);
    yearSelect.addEventListener('change', refreshLinks);
})();
</script>
@endpush
