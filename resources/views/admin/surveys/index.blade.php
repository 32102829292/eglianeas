@extends('layouts.dashboard')

@section('title', 'Customer Satisfaction Survey — Egliane Accounting Services')

@php
    $avgStars = $average !== null ? (int) round($average) : 0;
    $hasResponses = $responses->total() > 0;
@endphp

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Customer satisfaction survey</h1>
            <p>Monthly client survey responses and who is yet to complete theirs.</p>
        </div>
    </div>

    <div class="stat-grid survey-kpis">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <span class="stat-label">Avg rating (30 days)</span>
            @if ($average !== null)
                <b class="stat-value">{{ $average }}<span class="stat-value-suffix">/ 5</span></b>
                <span class="rating-stars stat-stars" role="img" aria-label="{{ $average }} out of 5 on average">
                    @for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $avgStars) on @endif">★</span>@endfor
                </span>
                <span class="stat-meta">Last 30 days</span>
            @else
                <b class="stat-value">—</b>
                <span class="stat-meta">No responses yet</span>
            @endif
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Responses (30 days)</span>
            <b class="stat-value">{{ $responses->total() }}</b>
            <span class="stat-meta">Last 30 days</span>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Clients due</span>
            <b class="stat-value">{{ $dueClients->total() }}</b>
            <span class="stat-meta">Last 30 days</span>
        </div>
    </div>

    <div class="card card-data">
        <div class="card-head survey-card-head">
            <h3 class="card-title">Recent responses <span class="count-pill">{{ $responses->total() }}</span></h3>
        </div>

        @if ($hasResponses)
            <div class="table-wrap table-card-view">
                <table class="table table-hover align-middle mb-0 survey-table">
                    <thead class="thead-muted">
                        <tr>
                            <th>Client</th>
                            <th class="text-center">Overall</th>
                            <th class="text-center">Services</th>
                            <th class="text-center">Portal</th>
                            <th>Comments</th>
                            <th class="text-end">Submitted</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($responses as $response)
                            <tr>
                                <td>
                                    <div class="survey-client-name">{{ $response->user->name }}</div>
                                    <div class="survey-client-mail"><a href="mailto:{{ $response->user->email }}" class="contact-link">{{ $response->user->email }}</a></div>
                                </td>
                                <td class="text-center">
                                    <span class="rating-stars" role="img" aria-label="{{ $response->overall_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->overall_rating) on @endif">★</span>@endfor</span>
                                </td>
                                <td class="text-center">
                                    <span class="rating-stars" role="img" aria-label="{{ $response->service_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->service_rating) on @endif">★</span>@endfor</span>
                                </td>
                                <td class="text-center">
                                    <span class="rating-stars" role="img" aria-label="{{ $response->portal_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->portal_rating) on @endif">★</span>@endfor</span>
                                </td>
                                <td>
                                    @if ($response->comments)
                                        <span class="text-wrap">{{ mb_strimwidth($response->comments, 0, 50, '…') }}</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end muted small">{{ $response->submitted_at->format('M j, Y g:i A') }}</td>
                                <td class="text-end survey-action">
                                    <button type="button" class="btn btn-outline btn-sm survey-view-btn" data-toggle="#response-{{ $response->id }}">View response</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="card-view-list survey-response-list">
                    @foreach ($responses as $response)
                        <article class="survey-response-card">
                            <div>
                                <div class="survey-client-name">{{ $response->user->name }}</div>
                                <div class="survey-client-mail">{{ $response->user->email }}</div>
                            </div>
                            <div class="survey-response-ratings">
                                <span class="survey-rate-row">
                                    <span>Overall</span>
                                    <span class="rating-stars" role="img" aria-label="{{ $response->overall_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->overall_rating) on @endif">★</span>@endfor</span>
                                </span>
                                <span class="survey-rate-row">
                                    <span>Services</span>
                                    <span class="rating-stars" role="img" aria-label="{{ $response->service_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->service_rating) on @endif">★</span>@endfor</span>
                                </span>
                                <span class="survey-rate-row">
                                    <span>Portal</span>
                                    <span class="rating-stars" role="img" aria-label="{{ $response->portal_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->portal_rating) on @endif">★</span>@endfor</span>
                                </span>
                            </div>
                            <p class="survey-response-comment">
                                @if ($response->comments)
                                    {{ mb_strimwidth($response->comments, 0, 80, '…') }}
                                @else
                                    —
                                @endif
                            </p>
                            <div class="survey-response-foot">
                                <span class="muted small">{{ $response->submitted_at->format('M j, Y g:i A') }}</span>
                                <button type="button" class="btn btn-outline btn-sm survey-view-btn" data-toggle="#response-{{ $response->id }}">View response</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <div class="empty-state survey-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>No survey responses yet</h3>
                <p>Responses will appear here once your clients submit the monthly survey.</p>
            </div>
        @endif

        {{ $responses->links('pagination.simple') }}
    </div>

    @foreach ($responses as $response)
        <div id="response-{{ $response->id }}" class="modal hidden">
            <div class="modal-card">
                <h3>{{ $response->user->name }}</h3>
                <p class="modal-meta">
                    @if ($response->user->business_name)
                        {{ $response->user->business_name }} &middot;
                    @endif
                    <a href="mailto:{{ $response->user->email }}" class="contact-link">{{ $response->user->email }}</a>
                    @if ($response->user->client_code)
                        &middot; {{ $response->user->client_code }}
                    @endif
                </p>

                <div class="modal-ratings">
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Overall</span>
                        <span class="rating-stars" role="img" aria-label="{{ $response->overall_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->overall_rating) on @endif">★</span>@endfor</span>
                    </div>
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Services</span>
                        <span class="rating-stars" role="img" aria-label="{{ $response->service_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->service_rating) on @endif">★</span>@endfor</span>
                    </div>
                    <div class="modal-rating-row">
                        <span class="modal-rating-label">Portal</span>
                        <span class="rating-stars" role="img" aria-label="{{ $response->portal_rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<span class="star @if ($i <= $response->portal_rating) on @endif">★</span>@endfor</span>
                    </div>
                </div>

                <div class="modal-comment">
                    <strong>Comments</strong>
                    @if ($response->comments)
                        <p>{{ $response->comments }}</p>
                    @else
                        <p class="muted">No comments left.</p>
                    @endif
                </div>

                <p class="modal-date">Submitted {{ $response->submitted_at->format('F j, Y \a\t g:i A') }}</p>

                <button type="button" class="btn btn-outline btn-block" data-modal-close="#response-{{ $response->id }}">Close</button>
            </div>
        </div>
    @endforeach

    <div class="card card-data">
        <div class="card-head survey-card-head">
            <h3 class="card-title">Clients yet to complete <span class="count-pill">{{ $dueClients->total() }}</span></h3>
            <span class="survey-head-scope">Last 30 days</span>
        </div>

        @if ($dueClients->isEmpty())
            <div class="empty-state survey-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h3>All clients are up to date</h3>
                <p>No clients are due to complete the survey right now.</p>
            </div>
        @else
            <div class="survey-due-grid">
                @foreach ($dueClients as $client)
                    <div class="survey-due-card">
                        <span class="survey-due-avatar">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                        <div class="survey-due-meta">
                            <div class="survey-due-name">{{ $client->name }}</div>
                            @if ($client->client_code)
                                <div class="survey-due-id">{{ $client->client_code }}</div>
                            @endif
                        </div>
                        <span class="badge badge-warn">Pending survey</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{ $dueClients->links('pagination.simple') }}
    </div>
@endsection