@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report')

@php
    $isSubmitted = $journal->isNotMissing();
    $statusBadge = $journal->getStatusBadgeClass();
    $statusLabel = $journal->getStatusLabel();
    $statusIcon = $journal->getStatusIcon();

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
            @if ($isSubmitted)
                <span class="badge {{ $statusBadge }} ms-2">{{ $statusIcon }} {{ $statusLabel }}</span>
            @elseif ($isFuture)
                <span class="badge badge-info ms-2">Not due yet</span>
            @else
                <span class="badge badge-danger ms-2">&#9888; Not submitted</span>
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

@if ($isSubmitted)
    <div class="alert alert-success mb-4">
        <strong>Report submitted.</strong>
        @if ($journal->submitted_at)
            Sent {{ $journal->submitted_at->format('F j, Y g:i A') }}.
        @endif
        You can read your answers below. To change something, please contact your supervisor.
    </div>
@elseif ($isFuture)
    <div class="alert alert-info mb-4">
        <strong>Not due yet.</strong> This report covers {{ $date->format('l, F j, Y') }}. Come back on that day to fill it in.
    </div>
@elseif ($isPast)
    <div class="alert alert-info mb-4">
        <strong>This report is overdue.</strong>
        Nothing was submitted for {{ $date->format('l, F j, Y') }}. Fill it in now and describe what happened.
    </div>
@endif

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
                        @error($field) aria-invalid="true" @enderror
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
    white-space: nowrap;
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
