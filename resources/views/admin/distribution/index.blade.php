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
            <table class="table">
                <thead>
                    <tr>
                        <th>Client Code</th>
                        <th>Business</th>
                        <th>Contact</th>
                        <th>BIR Forms</th>
                        <th>Softcopies</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        <tr>
                            <td>
                                <span class="code-pill">{{ $client->client_code ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="cell-name">{{ $client->business_name ?: $client->name }}</div>
                                <small class="muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="cell-name">{{ $client->name }}</div>
                                <small class="muted">{{ $client->email }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $entry['filed'] > 0 ? 'current' : 'neutral' }}">{{ $entry['filed'] }}/{{ $entry['total'] }}</span>
                            </td>
                            <td>{{ $entry['softcopies'] }}</td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.distribution.show', $client) }}" class="btn btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
