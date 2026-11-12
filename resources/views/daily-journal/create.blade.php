@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report')

@php
    $requiresReport = $requiresReport ?? true;
    $isSubmitted = $journal ? $journal->isNotMissing() : false;
    $statusBadge = $journal ? $journal->getStatusBadgeClass() : 'badge-info';
    $statusLabel = $journal ? $journal->getStatusLabel() : 'Not required';
    $statusIcon = $journal ? $journal->getStatusIcon() : '—';

    $dayHeading = match (true) {
        $date->isToday() => 'Today',
        $date->isYesterday() => 'Yesterday',
        default => $date->format('l'),
    };

    $prompts = [
        [
            'name' => 'problems_encountered',
            'label' => 'Problems Encountered',
            'placeholder' => 'Describe any problems, issues, or obstacles you encountered today...',
            'hint' => 'Required. Include anything that delayed your work and why.',
            'example' => 'Example: Client submitted the required documents late, so the filing could not be completed on schedule.',
        ],
        [
            'name' => 'achievements',
            'label' => 'Achievements',
            'placeholder' => 'List your accomplishments, completed tasks, and progress made today...',
            'hint' => 'Required. Name the specific tasks you finished, so your supervisor does not have to guess what you did.',
            'example' => 'Example: Finished monthly bookkeeping for two clients and sent the payroll summary for approval.',
        ],
        [
            'name' => 'suggested_solutions',
            'label' => 'Suggested Solutions',
            'placeholder' => 'Propose solutions for problems encountered, process improvements, or recommendations...',
            'hint' => 'Required. Say what would stop this happening again, or what would make the job easier.',
            'example' => 'Example: Ask clients to send source documents by the 5th so month-end closing is not compressed.',
        ],
    ];
@endphp

@section('content')
<div class="page-head page-head-row">
    <div>
        <h1>Daily Accomplishment Report</h1>
        <p>
            {{ $dayHeading }} &middot; {{ $date->format('l, F j, Y') }}
            @if ($requiresReport)
                @if ($isSubmitted)
                    <span class="badge badge-success ms-2">{{ $statusIcon }} {{ $statusLabel }}</span>
                @elseif ($isFuture)
                    <span class="badge badge-info ms-2">Not due yet</span>
                @elseif ($isPast)
                    <span class="badge badge-danger ms-2">&#9888; Not submitted</span>
                @endif
            @else
                <span class="badge badge-info ms-2">Not required (weekend)</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <a href="{{ route('daily-journal.create', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous Day
        </a>
        <a href="{{ route('daily-journal.create', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next Day
        </a>
        <a href="{{ route('daily-journal.create') }}" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/></svg>
            Today
        </a>
    </div>
</div>

@if ($requiresReport)
    @if ($isSubmitted)
        <div class="alert alert-success mb-4">
            <strong>Report submitted.</strong>
            @if ($journal->submitted_at)
                Sent {{ $journal->submitted_at->format('F j, Y g:i A') }}.
            @endif
            You can read your answers below. To change something, please contact your supervisor.
        </div>
    @endif
    @if ($isFuture)
        <div class="alert alert-info mb-4">
            <strong>Not due yet.</strong> This report covers {{ $date->format('l, F j, Y') }}. Come back on that day to fill it in.
        </div>
    @endif
    @if ($isPast)
        <div class="alert alert-info mb-4">
            <strong>This report is overdue.</strong>
            Nothing was submitted for {{ $date->format('l, F j, Y') }}. Fill it in now and describe what happened.
        </div>
    @endif
@else
    <div class="alert alert-info mb-4">
        <strong>No report required.</strong>
        Daily Accomplishment Reports are only required Monday through Friday.
        {{ $date->format('l, F j, Y') }} falls on a weekend, so nothing is due.
    </div>
@endif

<!-- Work Plan Tasks Panel -->
@if ($requiresReport)
<div class="dg-panel" aria-label="Work Plan Tasks for Today">
    <div class="dg-panel-head">
        <div class="dg-panel-title">
            <span class="dg-panel-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y="9" x2="12" y="9"/><line x1="9" y="21" x2="9" y="21"/></svg>
            </span>
            <div>
                <h2>Work Plan Tasks</h2>
                <p>Your assigned tasks for {{ $date->format('l, F j, Y') }}.</p>
            </div>
        </div>
    </div>

    <div class="dg-goals-container">
        @if ($workPlanTasks->isEmpty())
            <div class="dg-empty-state">
                <span class="dg-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y="9" x2="12" y="9"/></svg>
                </span>
                <div class="dg-empty-text">
                    <strong>No work plan tasks assigned for today.</strong>
                    <p>No work plan tasks have been assigned for {{ $date->format('F j, Y') }}.</p>
                </div>
            </div>
        @else
            <ul class="wp-task-list">
                @foreach ($workPlanTasks as $task)
                    <li class="wp-task-item">
                        <span class="wp-task-title">{{ $task->getLegendLabel() }}</span>
                        @if ($task->description)
                            <span class="wp-task-desc">{{ $task->description }}</span>
                        @endif
                        @if ($task->success_criteria)
                            <span class="wp-task-meta">
                                <span class="wp-criteria-tag">Criteria: {{ $task->success_criteria }}</span>
                            </span>
                        @endif
                        <span class="wp-task-date">{{ $task->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="dg-panel" aria-label="Goals for the Day">
    <div class="dg-panel-head">
        <div class="dg-panel-title">
            <span class="dg-panel-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="12" y2="9"/><line x1="9" y1="21" x2="9" y2="21"/></svg>
            </span>
            <div>
                <h2>Goals for the Day</h2>
                <p>Your targeted goals for {{ $date->format('l, F j, Y') }}. Use these as a reference when writing your Daily Accomplishment Report.</p>
            </div>
        </div>
    </div>

    <div class="dg-goals-container">
        @if ($dailyGoals->isEmpty())
            <div class="dg-empty-state">
                <span class="dg-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </span>
                <div class="dg-empty-text">
                    <strong>No goals set for today.</strong>
                    <p>No targeted goals have been assigned for {{ $date->format('F j, Y') }}. Ask your supervisor to set goals for you.</p>
                </div>
            </div>
        @else
            <ul class="dg-goal-list">
                @foreach ($dailyGoals as $goal)
                    <li class="dg-goal-item">
                        <span class="dg-goal-title">{{ $goal->title }}</span>
                        @if ($goal->description)
                            <span class="dg-goal-desc">{{ $goal->description }}</span>
                        @endif
                        @if ($goal->success_criteria)
                            <span class="dg-goal-meta">
                                <span class="dg-criteria-tag">Success: {{ $goal->success_criteria }}</span>
                            </span>
                        @endif
                        <span class="dg-goal-date">{{ $goal->target_date?->format('D, M j') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
<form method="POST" action="{{ route('daily-journal.store') }}" enctype="multipart/form-data" class="daily-journal-form" data-journal-form>
    @csrf
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Report Details</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <span class="k">Name</span>
                    <p class="form-control-static">{{ $user->name }}</p>
                </div>
                <div class="col-md-6">
                    <span class="k">Role</span>
                    <p class="form-control-static">{{ ucfirst($user->role) }}</p>
                </div>
                <div class="col-md-6">
                    <span class="k">Date Covered</span>
                    <p class="form-control-static">{{ $date->format('F j, Y') }}</p>
                </div>
                <div class="col-md-6">
                    <span class="k">Status</span>
                    <p class="form-control-static">
                        <span class="badge {{ $statusBadge }}">{{ $statusIcon }} {{ $statusLabel }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    @unless ($isSubmitted || $isFuture)
        <div class="card journal-readiness">
            <div class="card-head d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h2 class="card-title mb-0">Before you submit</h2>
                <span class="badge badge-info" data-readiness-count>0 of 3 answered</span>
            </div>
            <div class="card-body">
                <ul class="journal-checklist mb-0">
                    @foreach ($prompts as $prompt)
                        <li data-check="{{ $prompt['name'] }}">
                            <svg class="journal-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>{{ $prompt['label'] }} answered</span>
                        </li>
                    @endforeach
                </ul>
                <p class="form-hint mb-0 mt-2">All three answers are required. Attachments are optional.</p>
            </div>
        </div>
    @endunless

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Your Report</h2>
        </div>
        <div class="card-body">
            @foreach ($prompts as $prompt)
                @php $field = $prompt['name']; @endphp
                <div class="form-group journal-field">
                    <label class="form-label fw-bold" for="{{ $field }}">
                        {{ $prompt['label'] }} <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="{{ $field }}"
                        name="{{ $field }}"
                        class="form-control journal-textarea @error($field) is-invalid @enderror"
                        rows="5"
                        required
                        @if ($isSubmitted) readonly @endif
                        @if ($isFuture) disabled @endif
                        placeholder="{{ $prompt['placeholder'] }}"
                        aria-describedby="{{ $field }}-hint @error($field) {{ $field }}-error @enderror"
                        data-journal-input>{{ old($field, $journal->{$field}) }}</textarea>
                    <div class="form-hint" id="{{ $field }}-hint">{{ $prompt['hint'] }}</div>
                    @unless ($isSubmitted || $isFuture)
                        <div class="journal-example">{{ $prompt['example'] }}</div>
                    @endunless
                    @error($field)
                        <div class="form-error" id="{{ $field }}-error">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Attachments <span class="badge badge-info">Optional</span></h2>
        </div>
        <div class="card-body">
            <p class="form-hint">
                Add a screenshot, photo, or document that shows the work you described. This is what your supervisor
                uses to verify your accomplishments. Maximum 5 MB per file. JPG, PNG, PDF, DOC, DOCX.
            </p>
            <input
                type="file"
                id="evidence"
                name="evidence[]"
                class="form-control @error('evidence') is-invalid @enderror @error('evidence.*') is-invalid @enderror"
                accept="image/*,.pdf,.doc,.docx"
                multiple
                @if ($isSubmitted || $isFuture) disabled @endif
                aria-describedby="evidence-hint"
                @error('evidence') aria-invalid="true" @enderror
                @error('evidence.*') aria-invalid="true" @enderror
                data-journal-files>
            <div class="form-hint" id="evidence-hint">You can attach more than one file.</div>
            @error('evidence')<div class="form-error">{{ $message }}</div>@enderror
            @error('evidence.*')<div class="form-error">{{ $message }}</div>@enderror

            <ul class="journal-file-list" data-file-list hidden></ul>

            @if ($journal->hasEvidence())
                <div class="evidence-preview mt-3">
                    <strong>Attached to this report:</strong>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach ($journal->evidencePaths() as $path)
                            <a href="{{ Storage::disk('supabase')->temporaryUrl($path, now()->addHours(1)) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                {{ basename($path) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="journal-actions">
        @if (! $isSubmitted)
            <button type="submit" class="btn btn-primary btn-lg" @disabled($isFuture)>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" class="me-2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Submit Daily Report
            </button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-lg">Cancel</a>
        @else
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-lg">Back to Dashboard</a>
        @endif
    </div>
</form>
@endif
@endsection

@push('styles')
<style>
.daily-journal-form {
    max-width: 900px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.daily-journal-form .journal-textarea {
    font-family: inherit;
    line-height: 1.6;
    resize: vertical;
    min-height: 120px;
}
.daily-journal-form .card-body .journal-field:last-child {
    margin-bottom: 0;
}
.journal-field + .journal-field {
    border-top: 1px solid var(--border-subtle, #e5e7eb);
    padding-top: 20px;
    margin-top: 20px;
}
.form-control-static {
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
    color: var(--text, #1f2937);
    margin-bottom: 0;
}
.daily-journal-form .k {
    display: block;
    font-size: var(--text-xs, 11px);
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--muted, #6b7280);
    font-weight: 600;
    margin-bottom: 2px;
}
.journal-checklist {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.journal-checklist li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: var(--text-base, 14px);
    color: var(--muted, #6b7280);
}
.journal-check-icon {
    flex: 0 0 auto;
    border-radius: 50%;
    padding: 2px;
    box-sizing: content-box;
    background: var(--border-subtle, #e5e7eb);
    color: #fff;
    stroke: none;
    transition: background var(--transition-fast, .15s ease);
}
.journal-checklist li.is-done {
    color: var(--text, #1f2937);
}
.journal-checklist li.is-done .journal-check-icon {
    background: var(--success, #27AE60);
}
.journal-readiness .badge-info.is-ready {
    background: var(--success-soft, rgba(39,174,96,.12));
    color: var(--success, #27AE60);
}
.journal-example {
    margin-top: 6px;
    padding: 8px 12px;
    border-left: 3px solid var(--border-subtle, #e5e7eb);
    background: var(--surface-sunken, #F6F8FB);
    border-radius: 0 var(--radius-sm, 12px) var(--radius-sm, 12px) 0;
    font-size: var(--text-sm, 12.5px);
    color: var(--muted, #6b7280);
}
.journal-file-list {
    list-style: none;
    padding: 0;
    margin: 12px 0 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.journal-file-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: var(--text-sm, 12.5px);
    padding: 8px 12px;
    background: var(--surface-sunken, #F6F8FB);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: var(--radius-sm, 12px);
}
.journal-file-list .jf-name {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}
.journal-file-list .jf-size {
    flex: 0 0 auto;
    color: var(--muted, #6b7280);
    font-variant-numeric: tabular-nums;
}
.evidence-preview a {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 250px;
}
.journal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 4px;
}
@media (max-width: 640px) {
    .daily-journal-form { gap: 16px; }
    .journal-actions .btn { width: 100%; }
    .daily-journal-form .journal-textarea { min-height: 96px; }
    .evidence-preview a { max-width: 100%; }
}

/* ---------- Goals for the Day Panel (scoped to .dg-*) ---------- */
.dg-panel { margin-bottom: 1.25rem; }
.dg-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px 12px 0 0; border-bottom: none; }
.dg-panel-title { display: flex; align-items: center; gap: .75rem; }
.dg-panel-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: none; border-radius: 10px; background: #eef4fd; color: #2563eb; }
.dg-panel-icon svg { width: 20px; height: 20px; flex: none; }
.dg-panel-title h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.dg-panel-title p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.dg-goals-container { padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 12px 12px; border-top: none; }
.dg-empty-state { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 2.5rem 1rem; text-align: center; color: var(--muted, #64748b); }
.dg-empty-icon { width: 48px; height: 48px; border-radius: 14px; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; }
.dg-empty-icon svg { width: 24px; height: 24px; flex: none; }
.dg-empty-text strong { color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; display: block; margin-bottom: .25rem; }
.dg-empty-text p { margin: 0; font-size: .85rem; }
.dg-goal-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .5rem; padding: .75rem 1rem; }
.dg-goal-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .75rem; background: #f8fafc; border-radius: 8px; font-size: .88rem; flex-wrap: wrap; }
.dg-goal-title { font-weight: 700; color: var(--text, #1f2937); overflow-wrap: anywhere; }
.dg-goal-desc { color: var(--muted, #64748b); font-size: .82rem; overflow-wrap: anywhere; }
.dg-goal-meta { display: inline-flex; gap: .3rem; align-items: center; }
.dg-criteria-tag { font-size: .72rem; font-weight: 700; color: #2563eb; background: #eef4fd; padding: .15rem .5rem; border-radius: 6px; white-space: nowrap; }
.dg-goal-date { font-size: .75rem; font-weight: 700; color: #6b7280; white-space: nowrap; }

/* ---------- Work Plan Tasks Panel (scoped to .wp-task-*) ---------- */
.wp-task-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .5rem; padding: .75rem 1rem; }
.wp-task-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .75rem; background: #f8fafc; border-radius: 8px; font-size: .88rem; flex-wrap: wrap; }
.wp-task-title { font-weight: 700; color: var(--text, #1f2937); overflow-wrap: anywhere; }
.wp-task-desc { color: var(--muted, #64748b); font-size: .82rem; overflow-wrap: anywhere; }
.wp-task-meta { display: inline-flex; gap: .3rem; align-items: center; }
.wp-criteria-tag { font-size: .72rem; font-weight: 700; color: #2563eb; background: #eef4fd; padding: .15rem .5rem; border-radius: 6px; white-space: nowrap; }
.wp-task-date { font-size: .75rem; font-weight: 700; color: #6b7280; white-space: nowrap; }

@media (max-width: 640px) {
    .dg-goal-item { flex-direction: column; align-items: flex-start; gap: .4rem; }
    .dg-goal-date { margin-top: .25rem; }
    .wp-task-item { flex-direction: column; align-items: flex-start; gap: .4rem; }
    .wp-task-date { margin-top: .25rem; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-journal-form]');
    if (! form) { return; }

    var inputs = Array.prototype.slice.call(form.querySelectorAll('[data-journal-input]'));
    var items = Array.prototype.slice.call(form.querySelectorAll('[data-check]'));
    var counter = document.querySelector('[data-readiness-count]');

    function sync() {
        var done = 0;
        items.forEach(function (item) {
            var field = form.querySelector('[name="' + item.getAttribute('data-check') + '"]');
            var filled = !! (field && field.value.trim().length > 0);
            item.classList.toggle('is-done', filled);
            if (filled) { done++; }
        });

        if (counter) {
            counter.textContent = done + ' of ' + items.length + ' answered';
            counter.classList.toggle('is-ready', done === items.length);
        }
    }

    inputs.forEach(function (input) {
        input.addEventListener('input', sync);
    });
    sync();

    // Show what is queued for upload before the form is sent.
    var fileInput = form.querySelector('[data-journal-files]');
    var fileList = form.querySelector('[data-file-list]');

    if (fileInput && fileList) {
        var render = function () {
            var files = fileInput.files;
            fileList.innerHTML = '';

            if (! files || files.length === 0) {
                fileList.hidden = true;
                return;
            }

            Array.prototype.forEach.call(files, function (file) {
                var li = document.createElement('li');

                var name = document.createElement('span');
                name.className = 'jf-name';
                name.textContent = file.name;

                var size = document.createElement('span');
                size.className = 'jf-size';
                size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

                li.appendChild(name);
                li.appendChild(size);
                fileList.appendChild(li);
            });

            fileList.hidden = false;
        };

        fileInput.addEventListener('change', render);
        render();
    }
})();
</script>

@endpush