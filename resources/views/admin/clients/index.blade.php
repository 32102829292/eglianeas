@extends('layouts.dashboard')

@section('title', 'Clients — Egliane Accounting Services')

@php
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };
@endphp

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Clients</h1>
            <p>Manage client accounts, their information, and account status.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.clients.create') }}" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Client
            </a>
            <form method="GET" action="{{ route('admin.clients.index') }}" class="page-search">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search name, business, email, or TIN&hellip;" data-live-filter>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </form>
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Masterlist
                </button>
                <div class="dropdown-menu" id="download-menu">
                    <a href="{{ route('admin.clients.exportXlsx', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as XLSX
                    </a>
                    <a href="{{ route('admin.clients.exportPdf', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-data">
        <div class="card-head">
            <span class="card-title">All Clients <span class="count-pill">{{ $clients->total() }}</span></span>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 clients-table">
                <colgroup>
                    <col class="col-business">
                    <col class="col-contact">
                    <col class="col-status">
                    <col class="col-payment">
                    <col class="col-outstanding">
                    <col class="col-since">
                    <col class="col-actions">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Business</th>
                        <th>Contact</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Payment</th>
                        <th class="text-end">Outstanding</th>
                        <th>Since</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        <tr data-filter-row>
                            <td data-col="Business">
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="muted">{{ $entry['profile']?->line_of_business ?? $entry['profile']?->business_type ?? '—' }}</small>
                                @if ($entry['profile']?->tin_no)
                                    <small class="muted d-block">TIN {{ $maskTin($entry['profile']->tin_no) }}</small>
                                @endif
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="muted"><a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></small>
                            </td>
                            <td class="text-center" data-col="Status">
                                @php($s = $entry['status'])
                                <span class="badge @if($s==='current') badge-success @elseif($s==='delinquent') badge-warn @elseif($s==='critical') badge-danger @else badge-neutral @endif">{{ $statuses[$s] ?? $s }}</span>
                            </td>
                            <td class="text-center" data-col="Payment">
                                @if ($entry['payment_status'])
                                    @php($p = $entry['payment_status'])
                                    <span class="badge @if($p==='paid') badge-success @elseif($p==='unpaid') badge-danger @elseif($p==='partial') badge-warn @else badge-neutral @endif">{{ ucfirst($p) }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="text-end" data-col="Outstanding">
                                @if ($entry['outstanding'] > 0)
                                    <span class="text-danger fw-semibold">₱{{ number_format($entry['outstanding'], 2) }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-col="Since">{{ $entry['profile']?->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-end" data-col="Actions">
                                <div class="client-actions">
                                    <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-primary btn-sm">Open client</a>
                                    <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline btn-sm">Edit</a>
                                    <span class="actions-divider"></span>
                                    <div class="dropdown-wrap">
                                        <button type="button" class="btn btn-outline btn-sm icon-btn" data-dropdown="client-more-{{ $client->id }}-t" aria-label="More actions" title="More actions">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                        </button>
                                        <div class="dropdown-menu" id="client-more-{{ $client->id }}-t">
                                            <form method="POST" action="{{ route('admin.clients.impersonate', $client) }}" onsubmit="return egliane.confirm.form(this, { title: 'View application as {{ addslashes($client->business_name ?: $client->name) }}?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                                                @csrf
                                                <button type="submit" class="dropdown-item btn-item">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    Login as client
                                                </button>
                                            </form>
                                            <div class="dropdown-divider"></div>
                                            <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item btn-item dropdown-item-danger">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    Delete client
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($clients as $entry)
                    @php($client = $entry['user'])
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $client->business_name ?: $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Contact</span><span class="cv-value">{{ $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $entry['status']){{ $statuses[$s] ?? $s }}</span></div>
                        <div class="cv-row"><span class="cv-label">Payment</span><span class="cv-value">{{ $entry['payment_status'] ? ucfirst($entry['payment_status']) : '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Outstanding</span><span class="cv-value">{{ $entry['outstanding'] > 0 ? '₱'.number_format($entry['outstanding'], 2) : '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Since</span><span class="cv-value">{{ $entry['profile']?->date_started?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value">
                            <div class="client-actions">
                                <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-primary btn-sm">Open client</a>
                                <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline btn-sm">Edit</a>
                                <span class="actions-divider"></span>
                                <div class="dropdown-wrap">
                                    <button type="button" class="btn btn-outline btn-sm icon-btn" data-dropdown="client-more-{{ $client->id }}-c" aria-label="More actions" title="More actions">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                    </button>
                                    <div class="dropdown-menu" id="client-more-{{ $client->id }}-c">
                                        <form method="POST" action="{{ route('admin.clients.impersonate', $client) }}" onsubmit="return egliane.confirm.form(this, { title: 'View application as {{ addslashes($client->business_name ?: $client->name) }}?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                                            @csrf
                                            <button type="submit" class="dropdown-item btn-item">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                Login as client
                                            </button>
                                        </form>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item btn-item dropdown-item-danger">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                Delete client
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No clients found.</p>
                @endforelse
            </div>
        </div>
        {{ $clients->links('pagination.simple') }}
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('input', function (e) {
            if (!e.target.matches('[data-live-filter]')) return;
            var term = e.target.value.toLowerCase();
            document.querySelectorAll('[data-filter-row]').forEach(function (row) {
                row.hidden = term !== '' && row.textContent.toLowerCase().indexOf(term) === -1;
            });
        });
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                document.querySelectorAll('.dropdown-menu').forEach(function (m) {
                    if (m !== menu) m.style.display = 'none';
                });
                if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
        });
    </script>
@endpush
