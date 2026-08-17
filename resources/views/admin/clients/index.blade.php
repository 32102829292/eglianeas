@extends('layouts.dashboard')

@section('title', 'Clients — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Clients</h1>
            <p>Manage client accounts, their information, and account status.</p>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.clients.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business, name, or email&hellip;" data-live-filter>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Outstanding</th>
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
                            <td>
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
                        <tr><td colspan="7" class="empty-cell">No clients found.</td></tr>
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
    </script>
@endpush
