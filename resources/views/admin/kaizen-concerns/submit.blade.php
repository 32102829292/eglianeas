@extends('layouts.dashboard')

@section('title', 'Submit an Improvement Idea — Egliane Accounting Services')

@section('content')
    @php
        // Employee identity always comes from the authenticated session, so the
        // submitter never has to type their own name and cannot be spoofed.
        $submitter = auth()->user();
    @endphp

    <!-- Page Header -->
    <header class="eas-page-head ks-submit-head">
        <div class="eas-head-main">
            <div class="eas-head-left">
                <span class="eas-eyebrow">Employee Improvement Board</span>
                <h1 class="eas-head-title">Submit an Improvement Idea</h1>
                <p class="eas-head-sub">Share an idea that could improve the way we work.</p>
            </div>
        </div>
        <div class="eas-head-actions">
            <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline eas-btn-outline">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Board
            </a>
        </div>
    </header>

    <!-- Employee identity -->
    <div class="ks-identity">
        <span class="ks-identity-label">Submitted by</span>
        <span class="ks-identity-name">{{ $submitter->name }}</span>
        @if ($submitter->position)
            <span class="ks-identity-role">{{ $submitter->position }}</span>
        @endif
    </div>

    <div class="ks-form-card">
        <form id="kaizenSubmitForm" method="POST" action="{{ route('admin.kaizen-concerns.submit.store') }}" novalidate>
            @csrf

            {{-- SECTION 1: completed by the employee --}}
            <section class="form-section ks-section-one">
                <div class="form-section-head">
                    <span class="form-section-title">
                        <span class="ks-section-num">1</span>
                        <span>Improvement Suggestion</span>
                    </span>
                    <span class="ks-owner-tag ks-owner-you">Completed by you</span>
                </div>
                <div class="form-section-note">Describe the situation and the improvement you have in mind. This is the only section you need to fill in.</div>

                <div class="form-group">
                    <label class="form-label" for="date_identified">Date Identified <span class="text-danger">*</span></label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date"
                           value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">When did you first notice this opportunity for improvement?</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="challenge">Challenge / Current Situation <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="challenge" name="challenge" rows="5" maxlength="5000" required placeholder="Describe the challenge or current situation...">{{ old('challenge') }}</textarea>
                    @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">What is the problem, and what impact does it have?</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="recommended_solution">Proposed Improvement / Recommended Solution <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="5" maxlength="5000" required placeholder="Describe your proposed improvement or recommended solution...">{{ old('recommended_solution') }}</textarea>
                    @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">How do you think this could be improved?</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Additional Details <span class="form-label-optional">(optional)</span></label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Any extra context or supporting details...">{{ old('notes') }}</textarea>
                    @error('notes')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </section>

            {{-- SECTION 2: managed by the review team (read-only here) --}}
            <section class="form-section ks-section-review">
                <div class="form-section-head">
                    <span class="form-section-title">
                        <span class="ks-section-num">2</span>
                        <span>Review and Planning</span>
                    </span>
                    <span class="ks-owner-tag ks-owner-pending">Pending review</span>
                </div>
                <div class="form-section-note">An Admin or Supervisor sets these after you submit. They are shown here for reference and cannot be edited on this form.</div>

                <div class="ks-info-grid ks-info-grid-three">
                    <div class="ks-info-item">
                        <span class="ks-info-label">Target Date</span>
                        <span class="ks-info-value">—</span>
                        <span class="ks-info-sub">Set by an Admin or Supervisor after review</span>
                    </div>
                    <div class="ks-info-item">
                        <span class="ks-info-label">Status</span>
                        <span class="ks-info-value ks-info-status ks-info-status-pending">Pending</span>
                        <span class="ks-info-sub">Initial status for every new suggestion</span>
                    </div>
                    <div class="ks-info-item">
                        <span class="ks-info-label">Assigned Employee</span>
                        <span class="ks-info-value">—</span>
                        <span class="ks-info-sub">Assigned by an Admin or Supervisor after review</span>
                    </div>
                </div>
            </section>

            {{-- SECTION 3: implementation, managed by the review team --}}
            <section class="form-section ks-section-review">
                <div class="form-section-head">
                    <span class="form-section-title">
                        <span class="ks-section-num">3</span>
                        <span>Implementation and Evidence</span>
                    </span>
                    <span class="ks-owner-tag ks-owner-pending">After implementation</span>
                </div>
                <div class="form-section-note">Recorded after the suggestion has been reviewed and implemented. You do not need to fill anything in here.</div>

                <div class="ks-info-grid ks-info-grid-two">
                    <div class="ks-info-item">
                        <span class="ks-info-label">Implementation Date</span>
                        <span class="ks-info-value">—</span>
                        <span class="ks-info-sub">Recorded when the improvement is implemented</span>
                    </div>
                    <div class="ks-info-item">
                        <span class="ks-info-label">Implementation Status</span>
                        <span class="ks-info-value ks-info-status ks-info-status-not-implemented">Not implemented yet</span>
                        <span class="ks-info-sub">Only authorized staff can mark it Implemented</span>
                    </div>
                </div>

                <div class="form-group ks-evidence-note">
                    <label class="form-label">Implementation Evidence</label>
                    <div class="form-note-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                        <span>Supporting evidence (a photo, screenshot, PDF or document) is attached on the suggestion's own page once it has been implemented.</span>
                    </div>
                </div>
            </section>

            <div class="form-actions ks-form-actions">
                <button type="submit" class="btn btn-primary" data-submit-btn>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><path d="M12 19l7-7 3 3"/><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/></svg>
                    <span data-submit-label>Submit Improvement</span>
                </button>
                <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('styles')
<style>
/* ============================================================
   Submit Improvement Idea - Kaizen Strategy
   ============================================================ */

/* Shared EAS page header (these classes are defined per-page elsewhere). */
.eas-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-head-main {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    flex-wrap: wrap;
}

.eas-head-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.eas-eyebrow {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-head-title {
    margin: 0 0 6px;
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.eas-head-sub {
    margin: 0;
    font-size: 14px;
    color: var(--muted-text);
}

.eas-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.eas-head-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ks-submit-head {
    margin-bottom: 20px;
}

.ks-identity {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 10px 14px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    background: var(--surface-raised);
}

.ks-identity-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.ks-identity-name {
    font-weight: 700;
    color: var(--navy);
}

.ks-identity-role {
    font-size: 12px;
    color: var(--muted-text);
}

.ks-form-card {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    background: var(--surface);
    padding: 20px;
}

.ks-form-card .form-section {
    background: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: 12px;
    padding: 18px 18px 6px;
    margin-bottom: 16px;
}

.ks-form-card .form-section:last-of-type {
    margin-bottom: 0;
}

/* Cancel the global adjacent-section divider: these are their own cards. */
.ks-form-card .form-section + .form-section {
    border-top: 1px solid var(--border-subtle);
    margin-top: 0;
    padding-top: 18px;
}

.form-section-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-subtle);
}

.form-section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -.01em;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ks-section-num {
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
    border-radius: 6px;
    background: var(--navy);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
}

.ks-owner-tag {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 999px;
    white-space: nowrap;
}

.ks-owner-you {
    background: #EFF6FF;
    color: #1D4ED8;
}

.ks-owner-pending {
    background: #FEF3E2;
    color: #92400E;
}

.form-section-note {
    font-size: 12.5px;
    color: var(--muted-text);
    margin-bottom: 14px;
}

.ks-info-grid {
    display: grid;
    gap: 12px;
    margin-bottom: 14px;
}

.ks-info-grid-three {
    grid-template-columns: repeat(3, 1fr);
}

.ks-info-grid-two {
    grid-template-columns: repeat(2, 1fr);
}

.ks-info-item {
    border: 1px solid var(--border-subtle);
    border-radius: 10px;
    padding: 10px 12px;
    background: var(--surface);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ks-info-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.ks-info-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    overflow-wrap: anywhere;
}

.ks-info-sub {
    font-size: 11.5px;
    color: var(--muted-text);
}

.ks-info-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.ks-info-status-pending {
    background: #FEF3E2;
    color: #92400E;
}

.ks-info-status-not-implemented {
    background: #F9FAFB;
    color: #6B7280;
}

.ks-evidence-note {
    margin-top: 6px;
}

.ks-form-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid var(--border-subtle);
}

.ks-form-actions .btn-primary {
    min-width: 160px;
}

.ks-form-actions .btn-primary[disabled] {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (max-width: 700px) {
    .ks-info-grid-three,
    .ks-info-grid-two {
        grid-template-columns: 1fr;
    }

    .ks-form-actions {
        flex-direction: column;
    }

    .ks-form-actions .btn {
        width: 100%;
    }

    .eas-page-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .eas-head-main {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .eas-head-title {
        font-size: 22px;
    }

    .ks-form-card {
        padding: 14px;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kaizenSubmitForm');
    if (!form) {
        return;
    }

    // Prevent accidental double clicks from firing two submissions.
    form.addEventListener('submit', function () {
        var button = form.querySelector('[data-submit-btn]');
        if (!button) {
            return;
        }

        button.disabled = true;
        var label = button.querySelector('[data-submit-label]');
        if (label) {
            label.textContent = 'Submitting...';
        }
    });
});
</script>
@endpush
