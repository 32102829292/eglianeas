@extends('layouts.dashboard')

@section('title', 'Customer Satisfaction Survey — Egliane Accounting Services')

@section('content')
    <div class="page-header-bar">
        <div class="page-header-left">
            <h1>Customer satisfaction survey</h1>
            <p>Monthly client survey responses and who is yet to complete theirs.</p>
        </div>
    </div>

    <div class="profile-meta">
        <div class="profile-meta-chip">
            <span class="meta-k">Average rating (30 days)</span>
            <span class="meta-v">
                @if ($average !== null)
                    {{ $average }} / 5
                @else
                    No responses yet
                @endif
            </span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Responses (30 days)</span>
            <span class="meta-v">{{ $responses->count() }}</span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Clients due</span>
            <span class="meta-v">{{ $dueClients->count() }}</span>
        </div>
    </div>

    <div class="card card-data">
        <div class="card-head">
            <span class="card-title">Recent responses <span class="count-pill">{{ $responses->count() }}</span></span>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th class="text-center">Overall</th>
                        <th class="text-center">Services</th>
                        <th class="text-center">Portal</th>
                        <th>Comments</th>
                        <th class="text-end">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($responses as $response)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $response->user->name }}</div>
                                <div class="text-muted small">{{ $response->user->email }}</div>
                            </td>
                            <td class="text-center">
                                <span class="rating-stars" aria-label="{{ $response->overall_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->overall_rating) on @endif">★</span>@endfor</span>
                            </td>
                            <td class="text-center">{{ $response->service_rating }}</td>
                            <td class="text-center">{{ $response->portal_rating }}</td>
                            <td>
                                @if ($response->comments)
                                    <span class="text-wrap">{{ mb_strimwidth($response->comments, 0, 80, '…') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-muted small">{{ $response->submitted_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No survey responses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-data">
        <div class="card-head">
            <span class="card-title">Clients yet to complete (last 30 days) <span class="count-pill">{{ $dueClients->count() }}</span></span>
        </div>
        <div class="card-body">
            @if ($dueClients->isEmpty())
                <p class="text-muted mb-0">All clients are up to date.</p>
            @else
                <div class="chip-flex">
                    @foreach ($dueClients as $client)
                        <span class="admin-chip">{{ $client->name }}
                            @if ($client->client_code)
                                <span class="text-muted">({{ $client->client_code }})</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        .rating-stars { color: #d9dde6; letter-spacing: 1px; white-space: nowrap; }
        .rating-stars .star.on { color: #f5a623; }
        .chip-flex { display: flex; flex-wrap: wrap; gap: 8px; }
        .admin-chip { background: var(--bg-alt, #f1f3f8); border: 1px solid var(--border-subtle, #e2e6ef); border-radius: 999px; padding: 5px 12px; font-size: 13px; }
    </style>
@endsection