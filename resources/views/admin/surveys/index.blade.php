@extends('layouts.dashboard')

@section('title', 'Customer Satisfaction Survey — Egliane Accounting Services')

@section('content')
    <div class="page-header-bar">
        <div class="page-header-left">
            <h1>Customer satisfaction survey</h1>
            <p>Monthly client survey responses and who is yet to complete theirs.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <span class="stat-label">Avg rating (30 days)</span>
            <b class="stat-value">{{ $average !== null ? $average . ' / 5' : '—' }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Responses (30 days)</span>
            <b class="stat-value">{{ $responses->count() }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Clients due</span>
            <b class="stat-value">{{ $dueClients->count() }}</b>
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
                        <th></th>
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
                            <td class="text-center">
                                <span class="rating-stars" aria-label="{{ $response->service_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->service_rating) on @endif">★</span>@endfor</span>
                            </td>
                            <td class="text-center">
                                <span class="rating-stars" aria-label="{{ $response->portal_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->portal_rating) on @endif">★</span>@endfor</span>
                            </td>
                            <td>
                                @if ($response->comments)
                                    <span class="text-wrap">{{ mb_strimwidth($response->comments, 0, 50, '…') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-muted small">{{ $response->submitted_at->format('M j, Y g:i A') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline btn-sm" data-toggle="#response-{{ $response->id }}">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No survey responses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($responses as $response)
        <div id="response-{{ $response->id }}" class="modal hidden">
            <div class="modal-card">
                <h3>{{ $response->user->name }}</h3>
                <p class="modal-meta">
                    @if ($response->user->business_name)
                        {{ $response->user->business_name }} &middot;
                    @endif
                    {{ $response->user->email }}
                    @if ($response->user->client_code)
                        &middot; {{ $response->user->client_code }}
                    @endif
                </p>

                <div class="modal-ratings">
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Overall</span>
                        <span class="rating-stars" aria-label="{{ $response->overall_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->overall_rating) on @endif">★</span>@endfor</span>
                    </div>
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Services</span>
                        <span class="rating-stars" aria-label="{{ $response->service_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->service_rating) on @endif">★</span>@endfor</span>
                    </div>
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Portal</span>
                        <span class="rating-stars" aria-label="{{ $response->portal_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->portal_rating) on @endif">★</span>@endfor</span>
                    </div>
                </div>

                <div class="modal-comment">
                    <strong>Comments</strong>
                    @if ($response->comments)
                        <p>{{ $response->comments }}</p>
                    @else
                        <p class="text-muted">No comments left.</p>
                    @endif
                </div>

                <p class="modal-date">Submitted {{ $response->submitted_at->format('F j, Y \a\t g:i A') }}</p>

                <button type="button" class="btn btn-outline btn-block" data-modal-close="#response-{{ $response->id }}">Close</button>
            </div>
        </div>
    @endforeach

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
        .modal-meta { color: var(--text-muted); font-size: 14px; margin: 0 0 14px; }
        .modal-ratings { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
        .modal-rating-row { display: flex; align-items: center; gap: 10px; }
        .modal-rating-label { font-size: 13px; font-weight: 600; color: var(--text-muted); min-width: 64px; }
        .modal-comment { margin-bottom: 14px; }
        .modal-comment strong { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 4px; }
        .modal-comment p { margin: 0; font-size: 14px; color: var(--text); line-height: 1.5; }
        .modal-date { font-size: 13px; color: var(--text-muted); margin: 0 0 16px; }
    </style>
@endsection