@extends('layouts.dashboard')

@section('title', 'Journal Entry — '. $journal->user->name)

@section('content')
<div class="page-head page-head-row">
    <div>
        <h1>Daily Accomplishment Report</h1>
        <p>{{ $journal->required_date->format('F j, Y') }} — {{ $journal->user->name }} ({{ ucfirst($journal->user->role) }})</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge {{ $journal->getStatusBadgeClass() }} fs-6 px-3 py-2">
            {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
        </span>
        @if (auth()->user()->isAdmin() && $journal->status !== 'missing')
            <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="inline-form" onsubmit="return confirm('Delete this journal entry?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm text-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete
                </button>
            </form>
        @endif
        <a href="{{ auth()->user()->isAdmin() ? route('admin.daily-journal.admin-monitor', ['date' => $journal->required_date->format('Y-m-d')]) : route('admin.daily-journal.supervisor-monitor', ['date' => $journal->required_date->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">Back</a>
    </div>
</div>

<div class="row g-4 journal-report-layout">
    <div class="col-xl-9">
        <div class="card journal-report-card">
            <div class="card-header">
                <h5 class="mb-0">Employee Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 journal-meta-grid">
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Name</div>
                        <p class="journal-meta-value">{{ $journal->user->name }}</p>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Role</div>
                        <p class="journal-meta-value">
                            <span class="badge badge-{{ $journal->user->role === 'supervisor' ? 'info' : 'secondary' }}">
                                {{ ucfirst($journal->user->role) }}
                            </span>
                        </p>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Date</div>
                        <p class="journal-meta-value">{{ $journal->required_date->format('F j, Y') }}</p>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Status</div>
                        <p class="journal-meta-value">
                            <span class="badge journal-status {{ $journal->getStatusBadgeClass() }}">
                                {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
                            </span>
                        </p>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Submitted</div>
                        <p class="journal-meta-value">
                            @if ($journal->submitted_at)
                                {{ $journal->submitted_at->format('F j, Y g:i A') }}
                            @else
                                <span class="text-muted">Not submitted</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="journal-meta-label">Created</div>
                        <p class="journal-meta-value">{{ $journal->created_at->format('F j, Y g:i A') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card journal-report-card">
            <div class="card-header">
                <h5 class="mb-0">Problems Encountered</h5>
            </div>
            <div class="card-body">
                <div class="journal-content">
                    {!! nl2br(e($journal->problems_encountered)) !!}
                </div>
            </div>
        </div>

        <div class="card journal-report-card">
            <div class="card-header">
                <h5 class="mb-0">Achievements</h5>
            </div>
            <div class="card-body">
                <div class="journal-content">
                    {!! nl2br(e($journal->achievements)) !!}
                </div>
            </div>
        </div>

        <div class="card journal-report-card">
            <div class="card-header">
                <h5 class="mb-0">Suggested Solutions</h5>
            </div>
            <div class="card-body">
                <div class="journal-content">
                    {!! nl2br(e($journal->suggested_solutions)) !!}
                </div>
            </div>
        </div>

        @if ($journal->hasEvidence())
            <div class="card journal-report-card">
                <div class="card-header">
                    <h5 class="mb-0">Pictures / Evidence ({{ $journal->evidenceCount() }})</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($journal->evidencePaths() as $path)
                            <a href="{{ Storage::disk('supabase')->temporaryUrl($path, now()->addHours(1)) }}" target="_blank" class="btn btn-outline btn-sm evidence-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                {{ basename($path) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-3">
        <div class="card journal-actions-card sticky-top" style="top: 1.5rem;">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                @if (auth()->user()->isAdmin() && $journal->status !== 'missing')
                    <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="inline-form mb-2" onsubmit="return confirm('Delete this journal entry? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" class="me-2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Delete Entry
                        </button>
                    </form>
                @else
                    <div class="quick-actions-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <p>No actions are available for this report.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.journal-content {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-family: inherit;
    line-height: 1.7;
    min-height: 64px;
    color: var(--navy, #1e3a5f);
}
.journal-report-card { margin-bottom:1rem; overflow:hidden; border-radius:14px; box-shadow:0 8px 24px rgba(15,47,91,.045); }
.journal-report-card .card-header, .journal-actions-card .card-header { padding:1rem 1.25rem; background:#fbfdff; border-bottom-color:var(--border-subtle,#e5e7eb); }.journal-report-card .card-body, .journal-actions-card .card-body { padding:1.25rem; }.journal-meta-label { margin-bottom:.35rem; color:var(--muted,#64748b); font-size:.7rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }.journal-meta-value { margin:0; color:var(--navy,#1e3a5f); font-weight:600; }.journal-status { display:inline-flex; align-items:center; gap:.3rem; padding:.4rem .65rem; border-radius:999px; }.journal-actions-card { border-radius:14px; box-shadow:0 8px 24px rgba(15,47,91,.05); }.quick-actions-empty { display:grid; place-items:center; min-height:116px; padding:1rem; color:var(--muted,#64748b); text-align:center; }.quick-actions-empty svg { width:26px; height:26px; margin-bottom:.6rem; color:var(--primary,#2563eb); }.quick-actions-empty p { margin:0; font-size:.875rem; line-height:1.5; }
.evidence-link {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
</style>
@endpush
