@extends('layouts.dashboard')

@section('title', 'Document Distribution — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Document Distribution</h1>
        <p>BIR form checklists, delivery logs, and softcopy management for each client.</p>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.distribution.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business, contact, or client code&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client Code</th>
                        <th>Business</th>
                        <th>Contact</th>
                        <th class="text-center">BIR Forms</th>
                        <th class="text-center">Softcopies</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        <tr>
                            <td data-col="Client Code">
                                <span class="badge bg-dark">{{ $client->client_code ?? '—' }}</span>
                            </td>
                            <td data-col="Business">
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="text-muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="text-muted">{{ $client->email }}</small>
                            </td>
                            <td class="text-center" data-col="BIR Forms">
                                <span class="badge @if($entry['filed'] > 0) bg-success @else bg-secondary @endif">{{ $entry['filed'] }}/{{ $entry['total'] }}</span>
                            </td>
                            <td class="text-center" data-col="Softcopies">{{ $entry['softcopies'] }}</td>
                            <td class="text-end" data-col="Actions">
                                <a href="{{ route('admin.distribution.show', $client) }}" class="btn btn-outline-primary btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($clients as $entry)
                    @php($client = $entry['user'])
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Client Code</span><span class="cv-value">{{ $client->client_code ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value">{{ $client->business_name ?: $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Contact</span><span class="cv-value">{{ $client->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">BIR Forms</span><span class="cv-value">{{ $entry['filed'] }}/{{ $entry['total'] }}</span></div>
                        <div class="cv-row"><span class="cv-label">Softcopies</span><span class="cv-value">{{ $entry['softcopies'] }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><a href="{{ route('admin.distribution.show', $client) }}" class="btn btn-outline-primary btn-sm">Open</a></span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No clients found.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
