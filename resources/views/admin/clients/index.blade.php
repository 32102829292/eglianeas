@extends('layouts.dashboard')

@section('title', 'Clients — Egliane Accounting Services')

@section('content')
    <div class="page-header-bar">
        <div class="page-header-left">
            <h1>Clients</h1>
            <p>Manage client accounts, their information, and account status.</p>
        </div>
        <div class="page-header-right">
            <form method="GET" action="{{ route('admin.clients.index') }}" class="page-search">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search business, name, or email&hellip;" data-live-filter>
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
            <span class="card-title">All Clients <span class="count-pill">{{ $clients->count() }}</span></span>
        </div>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="col-num">Outstanding</th>
                        <th>Since</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        <tr data-filter-row>
                            <td>
                                <div class="cell-name">{{ $client->business_name ?: $client->name }}</div>
                                <small class="muted">{{ $entry['profile']?->line_of_business ?? $entry['profile']?->business_type ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="cell-name">{{ $client->name }}</div>
                                <small class="muted">{{ $client->email }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $entry['status'] }}">{{ $statuses[$entry['status']] ?? $entry['status'] }}</span>
                            </td>
                            <td>
                                @if ($entry['payment_status'])
                                    <span class="badge badge-{{ $entry['payment_status'] }}">{{ ucfirst($entry['payment_status']) }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="col-num">
                                @if ($entry['outstanding'] > 0)
                                    <b class="text-danger">₱{{ number_format($entry['outstanding'], 2) }}</b>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $entry['profile']?->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline btn-sm">View</a>
                                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">
                            <div class="empty-state">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px; opacity:.4;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <div>No clients found.</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
        });
    </script>
@endpush
