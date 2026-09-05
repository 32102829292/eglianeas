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
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Client
            </a>
            <form method="GET" action="{{ route('admin.clients.index') }}" class="page-search">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search name, business, email, or TIN&hellip;" data-live-filter>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </form>
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Masterlist
                </button>
                <div class="dropdown-menu" id="download-menu">
                    <a href="{{ route('admin.clients.exportXlsx', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as XLSX
                    </a>
                    <a href="{{ route('admin.clients.exportPdf', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
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
            <table class="table table-hover align-middle mb-0">
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
                                <form method="POST" action="{{ route('admin.clients.impersonate', $client) }}" style="display:inline;" onsubmit="return egliane.confirm.form(this, { title: 'View application as {{ addslashes($client->business_name ?: $client->name) }}?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm" title="Login as this client">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Login as
                                    </button>
                                </form>
                                <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline-primary btn-sm">View</a>
                                <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-link btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline;" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                </form>
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
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline-primary btn-sm">View</a> <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-link btn-sm">Edit</a> <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline;" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form></span></div>
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
                if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
        });
    </script>
@endpush
