@extends('layouts.dashboard')

@section('title', 'Distribution — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Distribution</h1>
        <p>BIR form checklists, delivery logs, and softcopy management for each client.</p>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.distribution.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business, contact, or client code&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
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
                            <td>
                                <span class="badge bg-dark">{{ $client->client_code ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="text-muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="text-muted">{{ $client->email }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge @if($entry['filed'] > 0) bg-success @else bg-secondary @endif">{{ $entry['filed'] }}/{{ $entry['total'] }}</span>
                            </td>
                            <td class="text-center">{{ $entry['softcopies'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.distribution.show', $client) }}" class="btn btn-outline-primary btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
