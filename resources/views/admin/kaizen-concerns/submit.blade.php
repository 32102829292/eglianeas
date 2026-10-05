@extends('layouts.dashboard')

@section('title', 'Submit an Improvement Idea — Egliane Accounting Services')

@section('content')
    @php
        // Employee identity always comes from the authenticated session, so the
        // submitter never has to type their own name and cannot be spoofed.
        $submitter = auth()->user();
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>Share an Improvement Idea</h1>
            <p>Share workplace ideas, challenges, and improvements that can make our work better.</p>
        </div>
        <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Back to Board</a>
    </div>

    {{-- Employee identity --}}
    <div class="kaizen-identity">
        <span class="badge kaizen-suggestion-tag">Employee Suggestion</span>
        <div class="kaizen-identity-text">
            <span class="kaizen-identity-label">Submitted by</span>
            <span class="kaizen-identity-name">{{ $submitter->name }}</span>
        </div>
        @if ($submitter->position)
            <span class="kaizen-identity-role">{{ $submitter->position }}</span>
        @endif
    </div>

    <div class="card kaizen-submit-card">
        <form method="POST" action="{{ route('admin.kaizen-concerns.submit.store') }}" novalidate>
            @csrf

            {{-- ============ SECTION 1 ============ --}}
            <section class="form-section">
                <span class="form-section-title">
                    <span class="form-section-num">1</span>
                    <span>💡 Improvement Idea</span>
                </span>
                <div class="form-section-note">Tell us what could work better, and how.</div>

                <div class="form-group">
                    <label class="form-label" for="date_identified">Date Identified <span class="text-danger">*</span></label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date"
                           value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="challenge">Challenge / Opportunity for Improvement <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="challenge" name="challenge" rows="5" maxlength="5000" required
                              placeholder="Describe the challenge or opportunity for improvement.">{{ old('challenge') }}</textarea>
                    @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">What is the problem or opportunity?</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="recommended_solution">Recommended Solution <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="5" maxlength="5000" required
                              placeholder="How do you think this could be improved?">{{ old('recommended_solution') }}</textarea>
                    @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">Your suggested way of improving it.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Anything else?</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000"
                              placeholder="Optional extra context or supporting details.">{{ old('notes') }}</textarea>
                    @error('notes')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </section>

            {{-- ============ SECTION 2 ============ --}}
            <section class="form-section kaizen-section-secondary">
                <span class="form-section-title">
                    <span class="form-section-num">2</span>
                    <span>📋 Planning</span>
                </span>
                <div class="form-section-note">Set by the review team after your suggestion is submitted.</div>

                <div class="form-grid three">
                    <div class="form-group">
                        <label class="form-label" for="target_date">Target Date</label>
                        <input class="form-control" id="target_date" name="target_date" type="date" value="{{ old('target_date') }}">
                        @error('target_date')<div class="form-error">{{ $message }}</div>@enderror
                        <div class="form-hint">Optional — roughly when you think this could be done.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kaizen_status_display">Status</label>
                        <input class="form-control" id="kaizen_status_display" type="text" value="Pending" readonly disabled>
                        <div class="form-hint">Your suggestion starts as Pending.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kaizen_assigned_display">Assigned Staff</label>
                        <input class="form-control" id="kaizen_assigned_display" type="text" value="Not assigned yet" readonly disabled>
                        <div class="form-hint">A supervisor or admin assigns this after review.</div>
                    </div>
                </div>
            </section>

            {{-- ============ SECTION 3 ============ --}}
            <section class="form-section kaizen-section-secondary">
                <span class="form-section-title">
                    <span class="form-section-num">3</span>
                    <span>✅ Implementation &amp; Evidence</span>
                </span>
                <div class="form-section-note">Completed by the review team once the improvement is carried out.</div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="kaizen_impl_date_display">Implementation Date</label>
                        <input class="form-control" id="kaizen_impl_date_display" type="text" value="—" readonly disabled>
                        <div class="form-hint">Recorded when the improvement is implemented.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kaizen_impl_status_display">Implementation Status</label>
                        <input class="form-control" id="kaizen_impl_status_display" type="text" value="Not implemented yet" readonly disabled>
                        <div class="form-hint">Only authorized staff can mark it Implemented.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Implementation Evidence</label>
                    <div class="form-note-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                        <span>Add supporting evidence after implementation — a photo, screenshot, PDF or document. You will be able to attach it on the suggestion's own page once it has been implemented.</span>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><path d="M12 19l7-7 3 3"/><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/></svg>
                    Submit Improvement
                </button>
                <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection