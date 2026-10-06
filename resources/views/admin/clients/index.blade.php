@extends('layouts.dashboard')

@section('title', 'Clients — Egliane Accounting Services')

@php
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };

    // Filter-preserving URLs for the active-filter chips. Changing a filter drops
    // the page number so the user lands on page 1 of the new result set.
    $resetUrl = route('admin.clients.index');
    $queryParams = request()->query();
    $searchRemoveUrl = route('admin.clients.index', array_diff_key($queryParams, ['q' => null, 'page' => null]));
    $birRemoveUrls = [];
    foreach ($selectedBirCodes as $selectedCode) {
        $params = array_diff_key($queryParams, ['bir_codes' => null, 'page' => null]);
        $remaining = array_values(array_diff($selectedBirCodes, [$selectedCode]));
        if ($remaining !== []) { $params['bir_codes'] = $remaining; }
        $birRemoveUrls[$selectedCode] = route('admin.clients.index', $params);
    }
    $birFilterSummary = $selectedBirCodes === [] ? 'All codes' : count($selectedBirCodes).' selected';
@endphp

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Clients</h1>
            <p>Manage client accounts, their information, and account status.</p>
        </div>
        <div class="page-head-actions">
            @if (auth()->user()->canManageClients())
                <a href="{{ route('admin.clients.create') }}" class="btn btn-primary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Client
                </a>
            @endif
            <form method="GET" action="{{ route('admin.clients.index') }}" class="page-search" id="client-filter-form">
                @if ($sort !== 'business_name')
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                @if ($direction !== 'asc')
                    <input type="hidden" name="direction" value="{{ $direction }}">
                @endif
                <input type="search" name="q" value="{{ $q }}" placeholder="Search name, business, email, or TIN&hellip;" data-live-filter>
                <div class="dropdown-wrap">
                    <button type="button" class="btn btn-outline btn-sm dropdown-toggle bir-filter-toggle" data-dropdown="bir-code-filter" aria-haspopup="true" aria-expanded="false">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v7l-4 2v-9L3 4z"/></svg>
                        BIR Codes
                        <span class="bir-filter-count" data-bir-summary>{{ $birFilterSummary }}</span>
                    </button>
                    <div class="dropdown-menu bir-filter-menu" id="bir-code-filter">
                        <div class="bir-filter-head">Show clients assigned these BIR codes</div>
                        <div class="bir-filter-grid">
                            @forelse ($birCodes as $birCode)
                                @php($birChecked = in_array($birCode, $selectedBirCodes, true))
                                <label class="bir-form-check {{ $birChecked ? 'is-checked' : '' }}">
                                    <input type="checkbox" name="bir_codes[]" value="{{ $birCode }}" @checked($birChecked) data-bir-code>
                                    <span>{{ $birCode }}</span>
                                </label>
                            @empty
                                <p class="bir-filter-empty">No BIR codes are configured yet.</p>
                            @endforelse
                        </div>
                        <div class="bir-filter-foot">
                            <button type="button" class="btn btn-link btn-sm" data-bir-clear>Clear</button>
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        </div>
                    </div>
                </div>
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
            <span class="card-title">{{ $hasActiveFilters ? 'Matching Clients' : 'All Clients' }} <span class="count-pill">{{ $clients->total() }}</span></span>
            <div class="sort-controls" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <label class="form-label mb-0" style="font-size: 12px;">Sort by:</label>
                <form method="GET" action="{{ route('admin.clients.index') }}" style="display: inline-flex; align-items: center; gap: 6px;">
                    @foreach (['q' => $q] as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    @foreach ($selectedBirCodes as $selectedCode)
                        <input type="hidden" name="bir_codes[]" value="{{ $selectedCode }}">
                    @endforeach
                    <select class="form-control form-control-sm" name="sort" style="width: auto; min-width: 160px;" onchange="this.form.submit()">
                        <option value="business_name" {{ $sort === 'business_name' ? 'selected' : '' }}>Business Name</option>
                        <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Client Name</option>
                        <option value="client_code" {{ $sort === 'client_code' ? 'selected' : '' }}>Client ID</option>
                        <option value="email" {{ $sort === 'email' ? 'selected' : '' }}>Email</option>
                        <option value="contact_no" {{ $sort === 'contact_no' ? 'selected' : '' }}>Contact Number</option>
                        <option value="business_type" {{ $sort === 'business_type' ? 'selected' : '' }}>Type of Business</option>
                        <option value="line_of_business" {{ $sort === 'line_of_business' ? 'selected' : '' }}>Line of Business</option>
                        <option value="date_started" {{ $sort === 'date_started' ? 'selected' : '' }}>Date Started</option>
                        <option value="created_at" {{ $sort === 'created_at' ? 'selected' : '' }}>Date Registered</option>
                    </select>
                    <select class="form-control form-control-sm" name="direction" style="width: auto; min-width: 100px;" onchange="this.form.submit()">
                        <option value="asc" {{ $direction === 'asc' ? 'selected' : '' }}>A–Z / Oldest–Newest</option>
                        <option value="desc" {{ $direction === 'desc' ? 'selected' : '' }}>Z–A / Newest–Oldest</option>
                    </select>
                </form>
            </div>
        </div>
        @if ($hasActiveFilters)
            <div class="active-filter-row">
                <span class="active-filter-label">Active filters:</span>
                @if ($q !== '')
                    <a href="{{ $searchRemoveUrl }}" class="filter-chip" title="Remove the search filter">
                        Search: {{ $q }}<span class="filter-chip-x" aria-hidden="true">&times;</span>
                    </a>
                @endif
                @foreach ($selectedBirCodes as $selectedCode)
                    <a href="{{ $birRemoveUrls[$selectedCode] }}" class="filter-chip" title="Remove the {{ $selectedCode }} filter">
                        BIR: {{ $selectedCode }}<span class="filter-chip-x" aria-hidden="true">&times;</span>
                    </a>
                @endforeach
                <a href="{{ $resetUrl }}" class="btn btn-outline btn-sm">Clear filters</a>
            </div>
        @endif
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 clients-table">
                <colgroup>
                    <col class="col-business">
                    <col class="col-bir">
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
                        <th>BIR Codes</th>
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
                            <td data-col="BIR Codes">
                                @if ($entry['bir_codes'])
                                    <div class="bir-chips bir-chips-list">
                                        @foreach ($entry['bir_codes'] as $entryBirCode)
                                            <span class="bir-chip bir-chip-{{ $entryBirCode['status'] }}" title="{{ $entryBirCode['label'] }}">{{ $entryBirCode['code'] }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="muted">&mdash;</span>
                                @endif
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="muted"><a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></small>
                                @if ($entry['profile']?->contact_no_tel)
                                    <small class="muted d-block"><a href="tel:{{ $entry['profile']->contact_no_tel }}" class="contact-link">{{ $entry['profile']->contact_no }}</a></small>
                                @endif
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
                                    @if (auth()->user()->canManageClients())
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
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                                <td colspan="8" class="empty-cell">
                                    @if ($hasActiveFilters)
                                        No clients match the current filters.
                                        <a href="{{ $resetUrl }}" class="btn btn-outline btn-sm">Clear filters</a>
                                    @else
                                        No clients found.
                                    @endif
                                </td>
                            </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($clients as $entry)
                    @php($client = $entry['user'])
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $client->business_name ?: $client->name }}</div>
                                <div class="cv-head-sub">{{ $entry['profile']?->line_of_business ?? $entry['profile']?->business_type ?? '—' }}{{ $entry['profile']?->tin_no ? ' · TIN '.$maskTin($entry['profile']->tin_no) : '' }}</div>
                            </div>
                            @php($s = $entry['status'])
                            <span class="badge @if($s==='current') badge-success @elseif($s==='delinquent') badge-warn @elseif($s==='critical') badge-danger @else badge-neutral @endif">{{ $statuses[$s] ?? $s }}</span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Contact</span><span class="cv-value">{{ $client->name }}<br><a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a>@if ($entry['profile']?->contact_no_tel)<br><a href="tel:{{ $entry['profile']->contact_no_tel }}" class="contact-link">{{ $entry['profile']->contact_no }}</a>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">BIR Codes</span><span class="cv-value">@if ($entry['bir_codes'])<div class="bir-chips">@foreach ($entry['bir_codes'] as $entryBirCode)<span class="bir-chip bir-chip-{{ $entryBirCode['status'] }}" title="{{ $entryBirCode['label'] }}">{{ $entryBirCode['code'] }}</span>@endforeach</div>@else<span class="muted">&mdash;</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Payment</span><span class="cv-value">@if ($entry['payment_status'])@php($p = $entry['payment_status'])<span class="badge @if($p==='paid') badge-success @elseif($p==='unpaid') badge-danger @elseif($p==='partial') badge-warn @else badge-neutral @endif">{{ ucfirst($p) }}</span>@else<span class="muted">—</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Outstanding</span><span class="cv-value">@if ($entry['outstanding'] > 0)<span class="text-danger fw-semibold">₱{{ number_format($entry['outstanding'], 2) }}</span>@else<span class="muted">—</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Since</span><span class="cv-value">{{ $entry['profile']?->date_started?->format('M j, Y') ?? '—' }}</span></div>
                        </div>
                        <div class="cv-card-actions">
                            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-primary btn-sm">Open client</a>
                            @if (auth()->user()->canManageClients())
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
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">
                        @if ($hasActiveFilters)
                            No clients match the current filters. <a href="{{ $resetUrl }}" class="btn btn-outline btn-sm">Clear filters</a>
                        @else
                            No clients found.
                        @endif
                    </p>
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
        // Keep aria-expanded truthful for every dropdown on the page, whichever
        // branch above opened or closed the menu.
        var syncDropdowns = function () {
            document.querySelectorAll('[data-dropdown]').forEach(function (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                toggle.setAttribute('aria-expanded', menu && menu.style.display === 'block' ? 'true' : 'false');
            });
        };
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                document.querySelectorAll('.dropdown-menu').forEach(function (m) {
                    if (m !== menu) m.style.display = 'none';
                });
                if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                syncDropdowns();
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
            syncDropdowns();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
            syncDropdowns();
        });

        // BIR code multi-select. Checkboxes do not auto-submit so several codes
        // can be ticked in one pass; Apply sends them with the rest of the form.
        var filterForm = document.getElementById('client-filter-form');
        if (filterForm) {
            var summary = filterForm.querySelector('[data-bir-summary]');
            var syncSummary = function () {
                if (!summary) return;
                var count = filterForm.querySelectorAll('[data-bir-code]:checked').length;
                summary.textContent = count === 0 ? 'All codes' : count + ' selected';
            };
            filterForm.addEventListener('change', function (e) {
                if (e.target.matches('[data-bir-code]')) syncSummary();
            });
            var clearBir = filterForm.querySelector('[data-bir-clear]');
            if (clearBir) {
                clearBir.addEventListener('click', function () {
                    filterForm.querySelectorAll('[data-bir-code]').forEach(function (cb) { cb.checked = false; });
                    syncSummary();
                    filterForm.submit();
                });
            }
        }
    </script>
@endpush
