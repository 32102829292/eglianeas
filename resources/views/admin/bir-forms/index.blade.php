@extends('layouts.dashboard')

@section('title', 'BIR Forms — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>BIR Forms</h1>
            <p>Select which BIR forms apply to each client. Only checked forms appear on the Document Distribution page.</p>
        </div>
        <div class="page-head-actions">
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="bir-download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download BIR Forms Summary
                </button>
                <div class="dropdown-menu" id="bir-download-menu">
                    <a href="{{ route('admin.bir-forms.exportXlsx', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as XLSX
                    </a>
                    <a href="{{ route('admin.bir-forms.exportPdf', ['q' => $q]) }}" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.bir-forms.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business, contact, or client code&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Client Code</th>
                        <th>Business</th>
                        <th>Contact</th>
                        @foreach ($formTypes as $ft)
                            <th class="text-center" style="font-size:11px;white-space:nowrap;">{{ $ft }}</th>
                        @endforeach
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        @php($statuses = $entry['statuses'])
                        <tr>
                            <td>
                                <span class="badge badge-navy">{{ $client->client_code ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="muted"><a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></small>
                            </td>
                            @foreach ($formTypes as $ft)
                                @php($isOn = $statuses[$ft] ?? false)
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.bir-forms.toggle', $client) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="form_type" value="{{ $ft }}">
                                        <button type="submit" class="bir-toggle {{ $isOn ? 'bir-toggle-on' : '' }}" title="{{ $ft }}: {{ $isOn ? 'Applicable' : 'Not applicable' }}">
                                            @if ($isOn)
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            @endif
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td class="text-center">
                                <span class="badge @if($entry['applicableCount'] > 0) badge-success @else badge-neutral @endif">{{ $entry['applicableCount'] }}/{{ $entry['totalForms'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($formTypes) }}" class="empty-cell">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($clients as $entry)
                    @php($client = $entry['user'])
                    @php($statuses = $entry['statuses'])
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Client Code</span><span class="cv-value"><span class="badge badge-navy">{{ $client->client_code ?? '—' }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $client->business_name ?: $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Contact</span><span class="cv-value">{{ $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Total</span><span class="cv-value"><span class="badge @if($entry['applicableCount'] > 0) badge-success @else badge-neutral @endif">{{ $entry['applicableCount'] }}/{{ $entry['totalForms'] }}</span></span></div>
                        @foreach ($formTypes as $ft)
                            @php($isOn = $statuses[$ft] ?? false)
                            <div class="cv-row">
                                <span class="cv-label">{{ $ft }}</span>
                                <span class="cv-value">
                                    <form method="POST" action="{{ route('admin.bir-forms.toggle', $client) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="form_type" value="{{ $ft }}">
                                        <button type="submit" class="bir-toggle {{ $isOn ? 'bir-toggle-on' : '' }}" title="{{ $ft }}: {{ $isOn ? 'Applicable' : 'Not applicable' }}">
                                            @if ($isOn)
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            @endif
                                        </button>
                                    </form>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="cv-card cv-empty">No clients found.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
