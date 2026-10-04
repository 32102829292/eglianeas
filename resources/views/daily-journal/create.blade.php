@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report')

@php
    $isSubmitted = $journal->isNotMissing();
    $statusBadge = $journal->getStatusBadgeClass();
    $statusLabel = $journal->getStatusLabel();
    $statusIcon = $journal->getStatusIcon();
@endphp

@section('content')
<div class="page-head page-head-row">
    <div>
        <h1>Daily Accomplishment Report</h1>
        <p>
            {{ $date->format('l, F j, Y') }}
            @if ($isSubmitted)
                <span class="badge {{ $statusBadge }} ms-2">{{ $statusIcon }} {{ $statusLabel }}</span>
            @else
                <span class="badge badge-danger ms-2">⚠ Missing</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('daily-journal.create', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm" @if ($isFuture) disabled @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous Day
        </a>
        <a href="{{ route('daily-journal.create', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm" @if ($isFuture) disabled @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next Day
        </a>
        <a href="{{ route('daily-journal.create') }}" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/></svg>
            Today
        </a>
    </div>
</div>

@if ($isSubmitted)
    <div class="alert alert-success mb-4">
        <strong>Journal already submitted.</strong> You can review your entry below. To make changes, contact your supervisor.
    </div>
@endif

<form method="POST" action="{{ route('daily-journal.store') }}" enctype="multipart/form-data" class="daily-journal-form" @if ($isSubmitted) style="pointer-events: none; opacity: 0.6;" @endif>
    @csrf
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

    <div class="form-section">
        <h3>Employee Information</h3>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Name</label>
                <p class="form-control-static">{{ $user->name }}</p>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Role</label>
                <p class="form-control-static">{{ ucfirst($user->role) }}</p>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Date</label>
                <p class="form-control-static">{{ $date->format('F j, Y') }}</p>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Status</label>
                <p class="form-control-static">
                    <span class="badge {{ $statusBadge }}">{{ $statusIcon }} {{ $statusLabel }}</span>
                    @if ($journal->submitted_at)
                        <small class="ms-2 text-muted">Submitted {{ $journal->submitted_at->format('F j, Y g:i A') }}</small>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="form-section">
        <h3>Required Fields</h3>

        <div class="mb-4">
            <label for="problems_encountered" class="form-label fw-bold">Problems Encountered <span class="text-danger">*</span></label>
            <textarea id="problems_encountered" name="problems_encountered" class="form-control journal-textarea" rows="6" required
                @if ($isSubmitted) readonly @endif
                placeholder="Describe any problems, issues, or obstacles you encountered today...">{{ old('problems_encountered', $journal->problems_encountered) }}</textarea>
            <div class="form-text">Required. Document any challenges, blockers, client issues, system problems, or anything that hindered your work.</div>
        </div>

        <div class="mb-4">
            <label for="achievements" class="form-label fw-bold">Achievements <span class="text-danger">*</span></label>
            <textarea id="achievements" name="achievements" class="form-control journal-textarea" rows="6" required
                @if ($isSubmitted) readonly @endif
                placeholder="List your accomplishments, completed tasks, and progress made today...">{{ old('achievements', $journal->achievements) }}</textarea>
            <div class="form-text">Required. Include completed tasks, milestones reached, client deliverables, process improvements, etc.</div>
        </div>

        <div class="mb-4">
            <label for="suggested_solutions" class="form-label fw-bold">Suggested Solutions <span class="text-danger">*</span></label>
            <textarea id="suggested_solutions" name="suggested_solutions" class="form-control journal-textarea" rows="6" required
                @if ($isSubmitted) readonly @endif
                placeholder="Propose solutions for problems encountered, process improvements, or recommendations...">{{ old('suggested_solutions', $journal->suggested_solutions) }}</textarea>
            <div class="form-text">Required. Suggest fixes for problems, process improvements, tool requests, training needs, or escalation paths.</div>
        </div>
    </div>

    <hr class="my-4">

    <div class="form-section">
        <h3>Pictures / Evidence (Optional)</h3>
        <div class="mb-3">
            <label for="evidence" class="form-label">Attach supporting documents, screenshots, or photos</label>
            <input type="file" id="evidence" name="evidence[]" class="form-control" accept="image/*,.pdf,.doc,.docx" multiple @if ($isSubmitted) disabled @endif>
            <div class="form-text">Optional. Max 5MB per file. Supported: JPG, PNG, PDF, DOC, DOCX.</div>
        </div>

        @if ($journal->hasEvidence())
            <div class="evidence-preview mt-3">
                <strong>Current attachments:</strong>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($journal->evidencePaths() as $path)
                        <a href="{{ Storage::disk('supabase')->temporaryUrl($path, now()->addHours(1)) }}" target="_blank" class="btn btn-outline btn-sm">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            {{ basename($path) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if (! $isSubmitted)
        <div class="d-flex gap-2 mt-4 pt-3 border-top">
            <button type="submit" class="btn btn-primary btn-lg">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" class="me-2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Submit Daily Journal
            </button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-lg">Cancel</a>
        </div>
    @else
        <div class="d-flex gap-2 mt-4 pt-3 border-top">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-lg">Back to Dashboard</a>
        </div>
    @endif
</form>
@endsection

@push('styles')
<style>
.journal-textarea {
    font-family: inherit;
    line-height: 1.6;
    resize: vertical;
    min-height: 120px;
}
.form-section h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--navy, #1e2a4a);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-subtle, #e5e7eb);
}
.form-control-static {
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
    color: var(--text, #1f2937);
}
.evidence-preview a {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 250px;
}
.daily-journal-form {
    max-width: 900px;
}
</style>
@endpush