@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report')

@section('content')
@php
    $locked        = $isSubmitted || $isFuture;
    $checkedCodes  = array_filter(array_merge(
        (array) old('achievement_codes', []),
        explode(',', (string) ($journal?->achievements ?? ''))
    ));
    $showUrgent    = $requiresReport && ! auth()->user()->isAdmin();
    $weekStart     = $date->copy()->startOfWeek();
    $evidenceCount = $journal?->hasEvidence() ? count($journal->evidencePaths()) : 0;
    $draftKey      = 'dar-draft-' . auth()->id() . '-' . $date->format('Y-m-d');
    $backUrl       = auth()->user()->isAdmin() ? route('admin.dashboard') : url()->previous();
@endphp

{{-- The form wraps the whole page so the sidebar and mobile bar buttons can submit it. --}}
<form method="POST"
      action="{{ route('daily-journal.store') }}"
      enctype="multipart/form-data"
      class="dar"
      id="dar-form"
      data-journal-form
      data-locked="{{ $locked ? '1' : '0' }}"
      data-draft-key="{{ $draftKey }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
    {{-- The script sets this right before every real submit, so "action" is always posted
         even in browsers that do not report the clicked button (e.submitter). --}}
    <input type="hidden" name="action" id="dar-action" value="draft">

    {{-- ===== Page header ===== --}}
    <header class="dar-head">
        <div class="dar-head-main">
            <span class="dar-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
            </span>
            <div>
                <h1>Daily Accomplishment Report</h1>
                <p>Track your daily work activities and accomplishments.</p>
            </div>
        </div>

        <div class="dar-date-nav">
            <a href="{{ route('daily-journal.create', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
               class="dar-nav-btn" aria-label="Previous day">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div class="dar-date-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>{{ $date->format('l, F j, Y') }}</span>
            </div>
            <a href="{{ route('daily-journal.create', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
               class="dar-nav-btn" aria-label="Next day">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            <a href="{{ route('daily-journal.create') }}" class="dar-today-btn">Today</a>
        </div>
    </header>

    {{-- ===== Week strip ===== --}}
    <nav class="dar-week" aria-label="Jump to a day this week">
        @for ($i = 0; $i < 5; $i++)
            @php $day = $weekStart->copy()->addDays($i); @endphp
            <a href="{{ route('daily-journal.create', ['date' => $day->format('Y-m-d')]) }}"
               class="dar-day {{ $day->isSameDay($date) ? 'is-active' : '' }} {{ $day->isToday() ? 'is-today' : '' }}"
               @if ($day->isSameDay($date)) aria-current="date" @endif>
                <span class="dar-day-name">{{ $day->format('D') }}</span>
                <span class="dar-day-num">{{ $day->format('j') }}</span>
                @if ($day->isToday())<span class="dar-day-flag">Today</span>@endif
            </a>
        @endfor
    </nav>

    {{-- ===== Status banners ===== --}}
    @if ($requiresReport)
        @if ($isSubmitted)
            <div class="dar-alert dar-alert-success" role="status">
                <strong>Report submitted.</strong>
                @if ($journal->submitted_at) Sent {{ $journal->submitted_at->format('F j, Y g:i A') }}. @endif
                To change something, please contact your supervisor.
            </div>
        @elseif ($isFuture)
            <div class="dar-alert dar-alert-info" role="status">
                <strong>Not due yet.</strong> This report covers {{ $date->format('l, F j, Y') }}. Come back on that day to fill it in.
            </div>
        @elseif ($isPast)
            <div class="dar-alert dar-alert-warn" role="status">
                <strong>This report is overdue.</strong> Nothing was submitted for {{ $date->format('l, F j, Y') }}. Fill it in now and describe what happened.
            </div>
        @endif
    @else
        <div class="dar-alert dar-alert-info" role="status">
            <strong>No report required.</strong> Daily Accomplishment Reports are only required Monday through Friday.
        </div>
    @endif

    @if ($errors->any())
        <div class="dar-alert dar-alert-error" role="alert">
            <strong>Your report could not be saved.</strong> Please fix the highlighted fields below.
        </div>
    @endif

    {{-- Shown by script when an unsaved draft is found on this device. --}}
    <div class="dar-alert dar-alert-info dar-restore" data-restore hidden role="status">
        <span>Restored an unsaved draft from this device.</span>
        <button type="button" class="dar-link-btn" data-restore-discard>Discard it</button>
    </div>

    <div class="dar-grid">
        {{-- ================= MAIN COLUMN ================= --}}
        <div class="dar-main">

            {{-- Goals + urgent tasks --}}
            <section class="dar-card" aria-label="Goals for the Day">
                <div class="dar-card-head">
                    <div class="dar-title">
                        <span class="dar-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        </span>
                        <div>
                            <h2>Goals for the Day</h2>
                            <p>Your targeted work activities for {{ $date->format('l, F j, Y') }}.</p>
                        </div>
                    </div>

                    @if ($showUrgent && ! $locked)
                        <button type="button" class="dar-btn dar-btn-primary" data-urgent-toggle aria-expanded="false" aria-controls="urgent-form">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            Add Urgent Task
                        </button>
                    @endif
                </div>

                @if ($workPlanTasks->isEmpty())
                    <div class="dar-empty">
                        <span class="dar-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </span>
                        <strong>No work plan activities assigned for this date.</strong>
                        <p>Nothing is scheduled in the Weekly Work Plan for {{ $date->format('F j, Y') }}. You can add an urgent task if needed.</p>
                    </div>
                @else
                    <div class="dar-table-wrap">
                        <table class="dar-table">
                            <thead>
                                <tr>
                                    <th scope="col">Activity Code</th>
                                    <th scope="col">Assigned Task</th>
                                    <th scope="col" class="dar-th-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($workPlanTasks as $activity)
                                    <tr>
                                        <td><span class="dar-code">{{ $activity->legend_code ?? '·' }}</span></td>
                                        <td>{{ $activity->getLegendLabel() }}</td>
                                        <td class="dar-td-status">
                                            @if ($activity->isCompleted())
                                                <span class="dar-status ok">Completed</span>
                                            @elseif ($activity->isInProgress())
                                                <span class="dar-status warn">In Progress</span>
                                            @elseif ($activity->isOnHold())
                                                <span class="dar-status warn">On Hold</span>
                                            @elseif ($activity->isCancelled())
                                                <span class="dar-status danger">Cancelled</span>
                                            @else
                                                <span class="dar-status pending">Scheduled</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($showUrgent)
                    <div class="dar-subhead">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <h3>Additional Urgent Tasks</h3>
                        <span class="dar-count-badge" data-urgent-count>{{ count($urgentTasks ?? []) }}</span>
                    </div>

                    <div id="urgent-form" class="dar-urgent-form" hidden>
                        <div class="dar-field">
                            <label for="urgent-desc">Task</label>
                            <input type="text" id="urgent-desc" class="dar-input" placeholder="What did you work on?" maxlength="255">
                        </div>
                        <div class="dar-field">
                            <label for="urgent-reason">Reason it was urgent</label>
                            <input type="text" id="urgent-reason" class="dar-input" placeholder="Why couldn't it wait?" maxlength="255">
                        </div>
                        <p class="dar-form-error" data-urgent-error hidden role="alert">Please fill in both the task and the reason.</p>
                        <div class="dar-urgent-actions">
                            <button type="button" class="dar-btn dar-btn-primary dar-btn-sm" data-urgent-add>Add task</button>
                            <button type="button" class="dar-btn dar-btn-ghost dar-btn-sm" data-urgent-cancel>Cancel</button>
                        </div>
                    </div>

                    <div class="dar-notice" data-urgent-empty @if (! empty($urgentTasks)) hidden @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        No additional urgent tasks yet.
                    </div>

                    <ul class="dar-urgent-list" data-urgent-list>
                        @foreach ($urgentTasks ?? [] as $i => $task)
                            <li>
                                <span class="dar-urgent-tag">Urgent</span>
                                <span class="dar-urgent-desc">{{ $task['description'] }}</span>
                                <span class="dar-urgent-reason">Reason: {{ $task['reason'] }}</span>
                                @unless ($locked)
                                    <button type="button" class="dar-urgent-remove">Remove</button>
                                @endunless
                                <input type="hidden" name="urgent_tasks[{{ $i }}][description]" value="{{ $task['description'] }}">
                                <input type="hidden" name="urgent_tasks[{{ $i }}][reason]" value="{{ $task['reason'] }}">
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Achievements --}}
            @if ($requiresReport)
                <section class="dar-card" aria-label="Achievements">
                    <div class="dar-card-head">
                        <div class="dar-title">
                            <span class="dar-title-icon dar-title-icon-gold" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                            </span>
                            <div>
                                <h2>Achievements</h2>
                                <p>Select the Activity Legend Codes for the work you completed.</p>
                            </div>
                        </div>
                        @if ($achievementLegends && $achievementLegends->isNotEmpty())
                            <span class="dar-count-badge dar-count-badge--wide"><span data-legend-count>0</span> selected</span>
                        @endif
                    </div>

                    @if ($achievementLegends && $achievementLegends->isNotEmpty())
                        @if ($achievementLegends->count() > 8)
                            <div class="dar-legend-tools">
                                <label class="dar-search">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <span class="dar-sr">Filter activity codes</span>
                                    <input type="search" class="dar-input" placeholder="Filter codes…" data-legend-filter autocomplete="off">
                                </label>
                                @unless ($locked)
                                    <button type="button" class="dar-link-btn" data-legend-clear>Clear selection</button>
                                @endunless
                            </div>
                        @endif
                        <div class="dar-legend-grid">
                            @foreach ($achievementLegends as $legend)
                                <label class="dar-legend" data-search="{{ \Illuminate\Support\Str::lower($legend->code . ' ' . $legend->name) }}">
                                    <input type="checkbox"
                                           name="achievement_codes[]"
                                           value="{{ $legend->code }}"
                                           @checked(in_array($legend->code, $checkedCodes, true))
                                           @disabled($locked)
                                           data-legend-box>
                                    <span><strong>{{ $legend->code }}</strong> &ndash; {{ $legend->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="dar-hint dar-legend-none" data-legend-none hidden>No codes match your filter.</p>
                    @else
                        <div class="dar-empty">
                            <span class="dar-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/><path d="M4 22h16"/></svg>
                            </span>
                            <strong>No achievement legend codes available.</strong>
                            <p>Codes will appear once activity legends are configured.</p>
                        </div>
                    @endif

                    <div class="dar-field dar-field-pad">
                        <label for="achievements">Achievement details <span class="dar-req" title="Required">*</span></label>
                        <textarea id="achievements"
                                  name="achievements"
                                  class="dar-input dar-textarea @error('achievements') is-invalid @enderror"
                                  rows="3"
                                  required
                                  @readonly($isSubmitted)
                                  @disabled($isFuture)
                                  placeholder="Describe or list completed activities…"
                                  data-journal-input>{{ old('achievements', $journal?->achievements) }}</textarea>
                        <div class="dar-field-foot">
                            @if (! empty($prompt['achievements']['hint']))
                                <div class="dar-hint">{{ $prompt['achievements']['hint'] }}</div>
                            @endif
                            <span class="dar-chars" data-chars-for="achievements"></span>
                        </div>
                        @error('achievements')<div class="dar-error">{{ $message }}</div>@enderror
                    </div>
                </section>
            @endif

            {{-- Problems + Solutions side by side --}}
            <div class="dar-pair">
                <section class="dar-card" aria-label="Problems Encountered">
                    <div class="dar-card-head dar-card-head-tight">
                        <div class="dar-title">
                            <span class="dar-title-icon dar-title-icon-violet" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </span>
                            <h2><label for="problems_encountered">Problems Encountered</label> <span class="dar-req" title="Required">*</span></h2>
                        </div>
                    </div>
                    <div class="dar-card-pad">
                        <textarea id="problems_encountered"
                                  name="problems_encountered"
                                  class="dar-input dar-textarea @error('problems_encountered') is-invalid @enderror"
                                  rows="5"
                                  required
                                  @readonly($isSubmitted)
                                  @disabled($isFuture)
                                  placeholder="Describe any problems, issues, or obstacles you encountered today…"
                                  data-journal-input>{{ old('problems_encountered', $journal?->problems_encountered) }}</textarea>
                        <div class="dar-field-foot">
                            @if ($requiresReport && ! empty($prompt['problems_encountered']['hint']))
                                <div class="dar-hint">{{ $prompt['problems_encountered']['hint'] }}</div>
                            @endif
                            <span class="dar-chars" data-chars-for="problems_encountered"></span>
                        </div>
                        @if ($requiresReport && ! $locked && ! empty($prompt['problems_encountered']['example']))
                            <div class="dar-example">{{ $prompt['problems_encountered']['example'] }}</div>
                        @endif
                        @error('problems_encountered')<div class="dar-error">{{ $message }}</div>@enderror
                    </div>
                </section>

                <section class="dar-card" aria-label="Suggested Solutions">
                    <div class="dar-card-head dar-card-head-tight">
                        <div class="dar-title">
                            <span class="dar-title-icon dar-title-icon-violet" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/></svg>
                            </span>
                            <h2><label for="suggested_solutions">Suggested Solutions</label> <span class="dar-req" title="Required">*</span></h2>
                        </div>
                    </div>
                    <div class="dar-card-pad">
                        <textarea id="suggested_solutions"
                                  name="suggested_solutions"
                                  class="dar-input dar-textarea @error('suggested_solutions') is-invalid @enderror"
                                  rows="5"
                                  required
                                  @readonly($isSubmitted)
                                  @disabled($isFuture)
                                  placeholder="Propose solutions for problems encountered, process improvements…"
                                  data-journal-input>{{ old('suggested_solutions', $journal?->suggested_solutions) }}</textarea>
                        <div class="dar-field-foot">
                            @if ($requiresReport && ! empty($prompt['suggested_solutions']['hint']))
                                <div class="dar-hint">{{ $prompt['suggested_solutions']['hint'] }}</div>
                            @endif
                            <span class="dar-chars" data-chars-for="suggested_solutions"></span>
                        </div>
                        @if ($requiresReport && ! $locked && ! empty($prompt['suggested_solutions']['example']))
                            <div class="dar-example">{{ $prompt['suggested_solutions']['example'] }}</div>
                        @endif
                        @error('suggested_solutions')<div class="dar-error">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            {{-- Attachments --}}
            <section class="dar-card" aria-label="Attachments">
                <div class="dar-card-head dar-card-head-tight">
                    <div class="dar-title">
                        <span class="dar-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </span>
                        <h2>Attachments <span class="dar-optional">Optional</span></h2>
                    </div>
                </div>
                <div class="dar-card-pad">
                    <p class="dar-hint dar-hint-top">
                        Add a screenshot, photo, or document that shows the work you described. Your supervisor uses it to verify your accomplishments.
                        Up to 5 MB per file: JPG, PNG, PDF, DOC, DOCX.
                    </p>
                    <label class="dar-drop @error('evidence') is-invalid @enderror @error('evidence.*') is-invalid @enderror @if ($locked) is-disabled @endif" data-drop>
                        <input type="file"
                               id="evidence"
                               name="evidence[]"
                               class="dar-drop-input"
                               accept="image/*,.pdf,.doc,.docx"
                               multiple
                               @disabled($locked)
                               data-journal-files>
                        <span class="dar-drop-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </span>
                        <span class="dar-drop-text">
                            <strong>Choose files</strong> or drag them here
                            <small>JPG, PNG, PDF, DOC, DOCX &middot; up to 5 MB each</small>
                        </span>
                    </label>
                    @error('evidence')<div class="dar-error">{{ $message }}</div>@enderror
                    @error('evidence.*')<div class="dar-error">{{ $message }}</div>@enderror

                    <ul class="dar-file-errors" data-file-errors hidden role="alert"></ul>
                    <ul class="dar-file-list" data-file-list hidden></ul>

                    @if ($journal?->hasEvidence())
                        <div class="dar-evidence">
                            <strong>Attached to this report</strong>
                            <div class="dar-evidence-links">
                                @foreach ($journal->evidencePaths() as $path)
                                    <a href="{{ Storage::disk('supabase')->temporaryUrl($path, now()->addHours(1)) }}"
                                       target="_blank" rel="noopener" class="dar-btn dar-btn-ghost dar-btn-sm">
                                        {{ basename($path) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        {{-- ================= SIDEBAR ================= --}}
        <aside class="dar-side">
            <section class="dar-card dar-summary" aria-label="Report Summary">
                <div class="dar-summary-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="12"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="18" y1="20" x2="18" y2="14"/></svg>
                    <h2>Report Summary</h2>
                </div>

                <div class="dar-summary-box">
                    <span class="dar-summary-icon {{ $isSubmitted ? 'is-done' : '' }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
                    </span>
                    <strong class="dar-summary-state">
                        @if ($isSubmitted) {{ $statusLabel }}
                        @elseif ($requiresReport) Not Yet Submitted
                        @else Not Required
                        @endif
                    </strong>
                    <p>
                        @if ($isSubmitted) Your report has been sent to your supervisor.
                        @elseif ($requiresReport) Please complete all sections and submit your report.
                        @else Reports are only needed Monday through Friday.
                        @endif
                    </p>

                    @if (! $isSubmitted && $requiresReport)
                        <div class="dar-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress-bar>
                            <span data-progress-fill></span>
                        </div>
                        <p class="dar-progress" data-readiness-count aria-live="polite"></p>
                    @endif
                </div>

                @if (! $isSubmitted && $requiresReport)
                    <ul class="dar-checklist" aria-label="Report checklist">
                        <li class="is-done is-static">
                            <span class="dar-check" aria-hidden="true"></span>
                            Goals reviewed <small>{{ $workPlanTasks->count() }} assigned</small>
                        </li>
                        <li data-check="achievements"><span class="dar-check" aria-hidden="true"></span>Achievements <small>required</small></li>
                        <li data-check="problems_encountered"><span class="dar-check" aria-hidden="true"></span>Problems encountered <small>required</small></li>
                        <li data-check="suggested_solutions"><span class="dar-check" aria-hidden="true"></span>Suggested solutions <small>required</small></li>
                        <li data-check-files data-existing="{{ $evidenceCount }}"><span class="dar-check" aria-hidden="true"></span>Attachments <small data-files-note>optional</small></li>
                    </ul>
                @endif

                <dl class="dar-meta">
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <div>
                            <dt>Submission Deadline</dt>
                            <dd class="dar-deadline">{{ $date->format('F j, Y') }}</dd>
                        </div>
                    </div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <div>
                            <dt>Last Updated</dt>
                            <dd>{{ $journal?->updated_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                        </div>
                    </div>
                </dl>
            </section>

            <div class="dar-actions">
                @if (! $isSubmitted)
                    {{-- No name="action" here: the hidden #dar-action input carries the value,
                         so a duplicate field can never override it. --}}
                    <button type="submit" value="submit" class="dar-btn dar-btn-primary dar-btn-lg" data-action="submit" @disabled($isFuture)>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M22 2 11 13" stroke="currentColor" stroke-width="2" fill="none"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                        <span>Submit Report</span>
                    </button>
                    <button type="submit" value="draft" class="dar-btn dar-btn-outline dar-btn-lg" data-action="draft" formnovalidate @disabled($isFuture)>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Save Draft</span>
                    </button>
                    <p class="dar-kbd-hint">Tip: press <kbd>Ctrl</kbd> + <kbd>S</kbd> to save a draft.</p>
                @else
                    <a href="{{ $backUrl }}" class="dar-btn dar-btn-outline dar-btn-lg">Back to Dashboard</a>
                @endif
            </div>

            <div class="dar-tip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <p>You can review and edit your report before submitting. Drafts are also kept on this device while you type.</p>
            </div>
        </aside>
    </div>

    {{-- Compact action bar for small screens (buttons point at the form by id). --}}
    @if (! $isSubmitted && $requiresReport)
        <div class="dar-mobile-bar" data-mobile-bar>
            <span class="dar-mobile-progress" data-mobile-progress></span>
            <button type="submit" form="dar-form" value="draft" class="dar-btn dar-btn-outline dar-btn-sm" data-action="draft" formnovalidate @disabled($isFuture)>Save Draft</button>
            <button type="submit" form="dar-form" value="submit" class="dar-btn dar-btn-primary dar-btn-sm" data-action="submit" @disabled($isFuture)>Submit</button>
        </div>
    @endif
</form>

{{-- Confirmation dialog lives outside the main form so its own method="dialog" form is valid. --}}
@if (! $isSubmitted && $requiresReport)
<dialog class="dar-dialog" id="dar-confirm" aria-labelledby="dar-confirm-title">
    <form method="dialog">
        <h3 id="dar-confirm-title">Submit your report?</h3>
        <p>Once submitted, you can no longer edit this report. Contact your supervisor if something needs to change afterwards.</p>
        <ul class="dar-dialog-list" data-confirm-summary></ul>
        <div class="dar-dialog-actions">
            <button value="cancel" class="dar-btn dar-btn-ghost">Keep editing</button>
            <button value="confirm" class="dar-btn dar-btn-primary" autofocus>Yes, submit report</button>
        </div>
    </form>
</dialog>
@endif

@push('styles')
<style>
.dar, .dar-dialog {
    --dar-ink: #1b2447;
    --dar-muted: #5b6585;
    --dar-line: #e3e7f3;
    --dar-surface: #ffffff;
    --dar-tint: #eef0fc;
    --dar-brand: #4338ca;
    --dar-brand-dark: #3730a3;
    --dar-ok: #0b7a45;
    --dar-warn-bg: #fff3dc;
    --dar-warn-line: #f4c56a;
    --dar-warn-ink: #8a5300;
    color: var(--dar-ink);
}
.dar [hidden], #dar-confirm[hidden] { display: none !important; }
.dar h1, .dar h2, .dar h3, .dar p { margin: 0; }
.dar h1, .dar h2, .dar h3 { color: var(--dar-ink); }
.dar h2 label { font: inherit; color: inherit; cursor: pointer; margin: 0; }
.dar-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

/* Header */
.dar-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.dar-head-main { display: flex; align-items: center; gap: 14px; }
.dar-head-icon { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 16px; background: var(--dar-tint); color: var(--dar-brand); flex: none; }
.dar-head-icon svg { width: 30px; height: 30px; }
.dar-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.dar-head p { color: var(--dar-muted); font-size: .95rem; margin-top: 2px; }
.dar-date-nav { display: flex; align-items: center; gap: 8px; }
.dar-date-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--dar-surface); border: 1px solid var(--dar-line); border-radius: 12px; font-weight: 600; font-size: .95rem; }
.dar-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--dar-surface); border: 1px solid var(--dar-line); border-radius: 12px; color: var(--dar-ink); text-decoration: none; }
.dar-nav-btn:hover { background: var(--dar-tint); }
.dar-today-btn { padding: 10px 16px; border-radius: 12px; background: var(--dar-ink); color: #fff; font-weight: 700; font-size: .9rem; text-decoration: none; }

/* Week strip */
.dar-week { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; margin-bottom: 18px; }
.dar-day { position: relative; display: flex; flex-direction: column; align-items: center; gap: 1px; padding: 9px 6px; border: 1px solid var(--dar-line); border-radius: 12px; background: var(--dar-surface); color: var(--dar-muted); text-decoration: none; transition: background .15s, border-color .15s, transform .15s; }
.dar-day:hover { background: var(--dar-tint); border-color: #b8bfee; transform: translateY(-1px); }
.dar-day-name { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.dar-day-num { font-size: 1.15rem; font-weight: 800; color: var(--dar-ink); }
.dar-day-flag { position: absolute; top: -8px; right: 8px; padding: 1px 7px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: .62rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.dar-day.is-active { background: linear-gradient(180deg, #4f46e5, var(--dar-brand-dark)); border-color: transparent; color: #c7d2fe; box-shadow: 0 8px 18px rgba(67, 56, 202, .28); }
.dar-day.is-active .dar-day-num { color: #fff; }

/* Alerts */
.dar-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: .92rem; border: 1px solid; }
.dar-alert-success { background: #e7f6ee; border-color: #b6e3cb; color: #0b5f37; }
.dar-alert-info { background: #eaf1ff; border-color: #c4d6fb; color: #1e40af; }
.dar-alert-warn { background: var(--dar-warn-bg); border-color: var(--dar-warn-line); color: var(--dar-warn-ink); }
.dar-alert-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.dar-restore { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.dar-link-btn { background: none; border: 0; padding: 0; font: inherit; font-size: .85rem; font-weight: 700; color: var(--dar-brand); text-decoration: underline; cursor: pointer; }
.dar-link-btn:hover { color: var(--dar-brand-dark); }

/* Two-column layout */
.dar-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
.dar-main { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.dar-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; }
.dar-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Cards */
.dar-card { background: var(--dar-surface); border: 1px solid var(--dar-line); border-radius: 18px; padding-bottom: 4px; min-width: 0; box-shadow: 0 6px 20px rgba(27, 36, 71, .04); }
.dar-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 18px 20px 12px; }
.dar-card-head-tight { padding-bottom: 8px; }
.dar-card-pad { padding: 0 20px 16px; }
.dar-title { display: flex; align-items: center; gap: 12px; }
.dar-title h2 { font-size: 1.05rem; font-weight: 800; }
.dar-title p { color: var(--dar-muted); font-size: .85rem; margin-top: 2px; }
.dar-title-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: var(--dar-tint); color: var(--dar-brand); flex: none; }
.dar-title-icon svg { width: 22px; height: 22px; }
.dar-title-icon-gold { background: #fff4d6; color: #b7791f; }
.dar-title-icon-violet { background: #ece9fd; color: #5b3fd1; }
.dar-req { color: #dc2626; }
.dar-optional { font-size: .72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--dar-tint); color: var(--dar-brand); margin-left: 6px; vertical-align: middle; }
.dar-count-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; padding: 2px 8px; border-radius: 999px; background: var(--dar-tint); color: var(--dar-brand); font-size: .78rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.dar-count-badge--wide { padding: 4px 12px; gap: 4px; }

/* Buttons */
.dar-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 12px; border: 1px solid transparent; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; line-height: 1.2; transition: background .15s, transform .15s; }
.dar-btn:hover:not(:disabled) { transform: translateY(-1px); }
.dar-btn:focus-visible, .dar-nav-btn:focus-visible, .dar-input:focus-visible, .dar-day:focus-visible, .dar-link-btn:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }
.dar-btn:disabled { opacity: .5; cursor: not-allowed; }
.dar-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--dar-brand-dark)); color: #fff; }
.dar-btn-primary:hover:not(:disabled) { background: var(--dar-brand-dark); }
.dar-btn-outline { background: var(--dar-surface); border-color: var(--dar-brand); color: var(--dar-brand); }
.dar-btn-outline:hover:not(:disabled) { background: var(--dar-tint); }
.dar-btn-ghost { background: var(--dar-surface); border-color: var(--dar-line); color: var(--dar-ink); }
.dar-btn-sm { padding: 7px 12px; font-size: .82rem; }
.dar-btn-lg { width: 100%; padding: 15px 18px; font-size: 1rem; border-radius: 14px; }
.dar-btn.is-busy { pointer-events: none; opacity: .75; }

/* Empty state */
.dar-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; margin: 4px 20px 16px; padding: 28px 20px; text-align: center; background: #f7f8ff; border: 1px dashed #b8bfee; border-radius: 14px; }
.dar-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; margin-bottom: 4px; border-radius: 14px; background: #e4e6fb; color: var(--dar-brand); }
.dar-empty-icon svg { width: 24px; height: 24px; }
.dar-empty strong { font-size: 1rem; font-weight: 800; }
.dar-empty p { max-width: 44ch; font-size: .88rem; line-height: 1.55; color: var(--dar-muted); }

/* Goals table */
.dar-table-wrap { margin: 4px 20px 8px; border: 1px solid var(--dar-line); border-radius: 12px; overflow: hidden; overflow-x: auto; }
.dar-table { width: 100%; border-collapse: collapse; font-size: .95rem; }
.dar-table thead th { background: #e9ebfb; text-align: left; padding: 11px 16px; font-weight: 700; font-size: .9rem; }
.dar-table tbody td { padding: 10px 16px; border-top: 1px solid var(--dar-line); vertical-align: middle; }
.dar-table tbody tr:hover { background: #fafbff; }
.dar-th-status, .dar-td-status { text-align: right !important; white-space: nowrap; }
.dar-code { display: inline-flex; align-items: center; justify-content: center; min-width: 46px; padding: 4px 12px; border-radius: 999px; background: #e4e6fb; color: var(--dar-brand); font-weight: 800; }
.dar-status { font-weight: 600; font-size: .9rem; display: inline-flex; align-items: center; gap: 6px; }
.dar-status::before { content: ""; width: 14px; height: 14px; border-radius: 50%; border: 2px solid currentColor; box-sizing: border-box; }
.dar-status.pending { color: #1d4ed8; }
.dar-status.ok { color: var(--dar-ok); }
.dar-status.ok::before { background: currentColor; }
.dar-status.warn { color: #92400e; }
.dar-status.danger { color: #a5281a; }

/* Urgent tasks */
.dar-subhead { display: flex; align-items: center; gap: 10px; padding: 14px 20px 8px; color: var(--dar-brand); }
.dar-subhead h3 { font-size: 1.02rem; font-weight: 800; }
.dar-notice { display: flex; align-items: center; gap: 10px; margin: 0 20px 14px; padding: 10px 14px; background: var(--dar-warn-bg); border: 1px solid var(--dar-warn-line); border-radius: 10px; color: var(--dar-warn-ink); font-size: .92rem; }
.dar-urgent-form { margin: 0 20px 12px; padding: 14px; border: 1px dashed #b8bfee; border-radius: 12px; background: #f7f8ff; display: grid; gap: 10px; }
.dar-form-error { color: #dc2626; font-size: .82rem; }
.dar-urgent-actions { display: flex; gap: 8px; }
.dar-urgent-list { list-style: none; margin: 0; padding: 0 20px 14px; display: flex; flex-direction: column; gap: 8px; }
.dar-urgent-list li { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 10px 14px; background: #fffaf0; border: 1px solid var(--dar-warn-line); border-radius: 10px; font-size: .92rem; }
.dar-urgent-tag { font-weight: 800; color: #b45309; }
.dar-urgent-desc { flex: 1 1 200px; overflow-wrap: anywhere; }
.dar-urgent-reason { color: var(--dar-muted); font-size: .85rem; }
.dar-urgent-remove { background: none; border: 0; color: #a5281a; font-weight: 700; cursor: pointer; font-size: .82rem; margin-left: auto; }
.dar-urgent-remove:hover { text-decoration: underline; }

/* Achievements */
.dar-legend-tools { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 0 20px 8px; }
.dar-search { position: relative; display: block; flex: 1 1 220px; max-width: 320px; margin: 0; }
.dar-search svg { position: absolute; left: 11px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: var(--dar-muted); pointer-events: none; }
.dar-search .dar-input { padding-left: 34px; }
.dar-legend-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; padding: 4px 20px 8px; }
.dar-legend { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--dar-line); border-radius: 10px; background: #fafbff; font-size: .88rem; cursor: pointer; transition: background .15s, border-color .15s; }
.dar-legend:hover { border-color: #b8bfee; }
.dar-legend input { width: 18px; height: 18px; flex: none; accent-color: var(--dar-brand); }
.dar-legend:has(input:checked) { background: var(--dar-tint); border-color: var(--dar-brand); }
.dar-legend:has(input:disabled) { cursor: default; }
.dar-legend-none { padding: 0 20px 8px; }

/* Fields */
.dar-field { display: grid; gap: 6px; }
.dar-field label { font-weight: 700; font-size: .88rem; }
.dar-field-pad { padding: 8px 20px 16px; }
.dar-field-foot { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-top: 6px; }
.dar-field-foot .dar-hint { margin-top: 0; }
.dar-chars { margin-left: auto; flex: none; font-size: .75rem; font-weight: 700; color: var(--dar-muted); font-variant-numeric: tabular-nums; }
.dar-input { width: 100%; padding: 10px 12px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; font: inherit; color: var(--dar-ink); box-sizing: border-box; transition: border-color .15s, box-shadow .15s; }
.dar-input:focus { border-color: var(--dar-brand); box-shadow: 0 0 0 3px rgba(67, 56, 202, .12); }
.dar-textarea { min-height: 120px; line-height: 1.55; resize: vertical; }
.dar-input.is-invalid { border-color: #dc2626; }
.dar-input:disabled, .dar-input[readonly] { background: #f4f5fa; color: var(--dar-muted); }
.dar-hint { margin-top: 6px; font-size: .8rem; color: var(--dar-muted); }
.dar-hint-top { margin: 0 0 10px; }
.dar-example { margin-top: 8px; padding: 8px 12px; border-left: 3px solid #b8bfee; background: #f7f8ff; border-radius: 0 10px 10px 0; font-size: .82rem; color: var(--dar-muted); }
.dar-error { margin-top: 6px; color: #dc2626; font-size: .82rem; }

/* Drop zone */
.dar-drop { position: relative; display: flex; align-items: center; gap: 14px; padding: 18px 20px; border: 2px dashed #b8bfee; border-radius: 14px; background: #f7f8ff; cursor: pointer; transition: background .15s, border-color .15s; }
.dar-drop:hover, .dar-drop.is-over { background: var(--dar-tint); border-color: var(--dar-brand); }
.dar-drop:focus-within { outline: 3px solid #a5b4fc; outline-offset: 2px; }
.dar-drop.is-invalid { border-color: #dc2626; }
.dar-drop.is-disabled { opacity: .55; cursor: not-allowed; }
.dar-drop-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.dar-drop.is-disabled .dar-drop-input { cursor: not-allowed; }
.dar-drop-icon { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background: #e4e6fb; color: var(--dar-brand); flex: none; }
.dar-drop-icon svg { width: 22px; height: 22px; }
.dar-drop-text { display: flex; flex-direction: column; gap: 2px; font-size: .95rem; }
.dar-drop-text strong { color: var(--dar-brand); }
.dar-drop-text small { color: var(--dar-muted); font-size: .8rem; }

/* Files */
.dar-file-errors { list-style: none; margin: 10px 0 0; padding: 10px 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; color: #991b1b; font-size: .82rem; display: flex; flex-direction: column; gap: 2px; }
.dar-file-list { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.dar-file-list li { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #f7f8ff; border: 1px solid var(--dar-line); border-radius: 10px; font-size: .84rem; }
.dar-file-list .jf-badge { flex: none; min-width: 38px; padding: 2px 6px; border-radius: 6px; background: #e4e6fb; color: var(--dar-brand); font-size: .68rem; font-weight: 800; text-align: center; text-transform: uppercase; }
.dar-file-list .jf-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dar-file-list .jf-size { color: var(--dar-muted); font-variant-numeric: tabular-nums; }
.dar-file-list .jf-remove { flex: none; width: 26px; height: 26px; border: 0; border-radius: 8px; background: transparent; color: #a5281a; font-size: 1.1rem; line-height: 1; cursor: pointer; }
.dar-file-list .jf-remove:hover { background: #fee2e2; }
.dar-evidence { margin-top: 14px; font-size: .9rem; }
.dar-evidence-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.dar-evidence-links a { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Sidebar */
.dar-summary { padding: 18px 16px 8px; }
.dar-summary-title { display: flex; align-items: center; gap: 10px; color: var(--dar-brand); margin-bottom: 12px; }
.dar-summary-title h2 { font-size: 1.05rem; font-weight: 800; }
.dar-summary-box { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 18px 14px; background: #f0f2fd; border-radius: 14px; }
.dar-summary-icon { display: inline-flex; align-items: center; justify-content: center; width: 70px; height: 70px; border-radius: 50%; background: #dfe3fa; color: var(--dar-brand); }
.dar-summary-icon svg { width: 36px; height: 36px; }
.dar-summary-icon.is-done { background: #d6f1e3; color: var(--dar-ok); }
.dar-summary-state { font-size: 1.25rem; font-weight: 800; margin-top: 4px; }
.dar-summary-box p { font-size: .85rem; color: var(--dar-muted); }
.dar-bar { width: 100%; height: 8px; margin-top: 8px; border-radius: 999px; background: #dfe3fa; overflow: hidden; }
.dar-bar span { display: block; width: 0; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, #4338ca); transition: width .35s ease; }
.dar-bar.is-ready span { background: linear-gradient(90deg, #34d399, #0b7a45); }
.dar-progress { font-weight: 700; color: var(--dar-brand) !important; }
.dar-progress.is-ready { color: var(--dar-ok) !important; }

.dar-checklist { list-style: none; margin: 14px 0 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.dar-checklist li { display: flex; align-items: center; gap: 10px; padding: 8px 6px; font-size: .88rem; font-weight: 600; border-radius: 8px; }
.dar-checklist li small { margin-left: auto; font-size: .72rem; font-weight: 600; color: var(--dar-muted); }
.dar-check { position: relative; display: inline-block; width: 20px; height: 20px; flex: none; border: 2px solid #c3c9e6; border-radius: 50%; background: #fff; transition: background .2s, border-color .2s; }
.dar-checklist li.is-done .dar-check { background: var(--dar-ok); border-color: var(--dar-ok); }
.dar-checklist li.is-done .dar-check::after { content: ""; position: absolute; left: 5px; top: 1px; width: 5px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.dar-checklist li.is-done { color: var(--dar-ok); }

.dar-meta { margin: 14px 0 6px; display: grid; gap: 14px; }
.dar-meta > div { display: flex; gap: 12px; align-items: flex-start; color: var(--dar-brand); }
.dar-meta dt { font-size: .82rem; color: var(--dar-muted); }
.dar-meta dd { margin: 2px 0 0; font-weight: 600; color: var(--dar-ink); }
.dar-meta .dar-deadline { font-size: 1.05rem; font-weight: 800; color: var(--dar-brand); }
.dar-actions { display: grid; gap: 12px; }
.dar-kbd-hint { text-align: center; font-size: .75rem; color: var(--dar-muted); }
.dar-kbd-hint kbd { padding: 1px 6px; border: 1px solid var(--dar-line); border-bottom-width: 2px; border-radius: 5px; background: #fff; font: inherit; font-size: .72rem; font-weight: 700; }
.dar-tip { display: flex; gap: 10px; padding: 14px; background: #eef0fc; border-radius: 14px; color: var(--dar-brand); font-size: .85rem; }
.dar-tip svg { flex: none; }
.dar-tip p { color: var(--dar-ink); }

/* Mobile action bar */
.dar-mobile-bar { display: none; }

/* Confirmation dialog */
.dar-dialog { width: min(440px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 18px; color: var(--dar-ink, #1b2447); box-shadow: 0 30px 80px rgba(15, 23, 42, .35); }
.dar-dialog::backdrop { background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px); }
.dar-dialog form { display: flex; flex-direction: column; gap: 12px; padding: 24px; }
.dar-dialog h3 { margin: 0; font-size: 1.2rem; font-weight: 800; }
.dar-dialog p { margin: 0; font-size: .9rem; line-height: 1.55; color: #5b6585; }
.dar-dialog-list { list-style: none; margin: 0; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; background: #f0f2fd; border-radius: 12px; font-size: .88rem; font-weight: 600; }
.dar-dialog-list li { display: flex; justify-content: space-between; gap: 12px; }
.dar-dialog-list span:last-child { color: #4338ca; font-weight: 800; }
.dar-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; flex-wrap: wrap; }

/* Responsive */
@media (max-width: 1100px) {
    .dar-grid { grid-template-columns: 1fr; }
    .dar-side { position: static; }
    .dar-legend-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dar-actions { display: none; }
    .dar .dar-mobile-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; align-items: center; gap: 10px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: rgba(255, 255, 255, .94); backdrop-filter: blur(10px); border-top: 1px solid var(--dar-line); box-shadow: 0 -8px 24px rgba(27, 36, 71, .08); }
    .dar-mobile-progress { flex: 1; font-size: .8rem; font-weight: 700; color: var(--dar-brand); }
    .dar { padding-bottom: 72px; }
}
@media (max-width: 700px) {
    .dar-pair { grid-template-columns: 1fr; }
    .dar-date-nav { flex-wrap: wrap; }
    .dar-head h1 { font-size: 1.3rem; }
    .dar-day-flag { display: none; }
}
@media (max-width: 480px) {
    .dar-legend-grid { grid-template-columns: 1fr; }
}
@media (prefers-reduced-motion: reduce) {
    .dar *, .dar *::before, .dar *::after { transition: none !important; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.getElementById('dar-form');
    if (! form) { return; }

    var locked = form.getAttribute('data-locked') === '1';
    var draftKey = form.getAttribute('data-draft-key');
    var actionInput = document.getElementById('dar-action');
    var submitting = false;
    var dirty = false;

    function store(fn) { try { return fn(window.localStorage); } catch (e) { return null; } }
    function $(sel, root) { return (root || form).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }

    var required = $$('[data-journal-input]');
    var legendBoxes = $$('[data-legend-box]');

    /* ---------------- Progress, checklist, counters ---------------- */
    var fileState = []; // File objects queued for upload
    var counter = $('[data-readiness-count]');
    var bar = $('[data-progress-bar]');
    var barFill = $('[data-progress-fill]');
    var mobileProgress = document.querySelector('[data-mobile-progress]');
    var existingFiles = parseInt(($('[data-check-files]') || { getAttribute: function () { return '0'; } }).getAttribute('data-existing') || '0', 10);

    function answered(el) { return el.value.trim().length > 0; }

    function syncProgress() {
        var done = required.filter(answered).length;
        var total = required.length;
        var pct = total ? Math.round((done / total) * 100) : 0;
        var ready = total > 0 && done === total;

        if (counter) {
            counter.textContent = done + ' of ' + total + ' required sections answered';
            counter.classList.toggle('is-ready', ready);
        }
        if (bar && barFill) {
            barFill.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', String(pct));
            bar.classList.toggle('is-ready', ready);
        }
        if (mobileProgress) { mobileProgress.textContent = done + ' / ' + total + ' sections'; }

        $$('[data-check]').forEach(function (li) {
            var el = document.getElementById(li.getAttribute('data-check'));
            li.classList.toggle('is-done', !! el && answered(el));
        });

        var filesLi = $('[data-check-files]');
        if (filesLi) {
            var count = fileState.length + existingFiles;
            filesLi.classList.toggle('is-done', count > 0);
            var note = $('[data-files-note]', filesLi);
            if (note) { note.textContent = count > 0 ? count + (count === 1 ? ' file' : ' files') : 'optional'; }
        }
    }

    function syncChars() {
        required.forEach(function (el) {
            var out = $('[data-chars-for="' + el.id + '"]');
            if (out) { out.textContent = el.value.length ? el.value.length.toLocaleString() + ' characters' : ''; }
        });
    }

    required.forEach(function (el) {
        el.addEventListener('input', function () { syncProgress(); syncChars(); queueDraft(); dirty = true; });
    });

    /* ---------------- Activity codes ---------------- */
    var legendCount = $('[data-legend-count]');
    function syncLegend() {
        if (legendCount) { legendCount.textContent = String(legendBoxes.filter(function (b) { return b.checked; }).length); }
    }
    legendBoxes.forEach(function (box) {
        box.addEventListener('change', function () { syncLegend(); queueDraft(); dirty = true; });
    });

    var legendFilter = $('[data-legend-filter]');
    var legendNone = $('[data-legend-none]');
    if (legendFilter) {
        legendFilter.addEventListener('input', function () {
            var term = legendFilter.value.trim().toLowerCase();
            var visible = 0;
            $$('.dar-legend').forEach(function (label) {
                var match = term === '' || (label.getAttribute('data-search') || '').indexOf(term) !== -1;
                label.hidden = ! match;
                if (match) { visible++; }
            });
            if (legendNone) { legendNone.hidden = visible !== 0; }
        });
        legendFilter.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
    }
    var legendClear = $('[data-legend-clear]');
    if (legendClear) {
        legendClear.addEventListener('click', function () {
            legendBoxes.forEach(function (b) { b.checked = false; });
            syncLegend(); queueDraft(); dirty = true;
        });
    }

    /* ---------------- Attachments (validate, queue, remove) ---------------- */
    var fileInput = $('[data-journal-files]');
    var fileList = $('[data-file-list]');
    var fileErrors = $('[data-file-errors]');
    var MAX_BYTES = 5 * 1024 * 1024;
    var ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];

    function ext(name) { var p = name.split('.'); return p.length > 1 ? p.pop().toLowerCase() : ''; }
    function formatSize(bytes) { return bytes < 1024 * 1024 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : (bytes / 1024 / 1024).toFixed(2) + ' MB'; }

    function pushInputFiles() {
        if (! fileInput || typeof DataTransfer === 'undefined') { return; }
        var dt = new DataTransfer();
        fileState.forEach(function (f) { dt.items.add(f); });
        fileInput.files = dt.files;
    }

    function renderFiles() {
        if (! fileList) { return; }
        fileList.innerHTML = '';
        if (fileState.length === 0) { fileList.hidden = true; return; }

        fileState.forEach(function (file, index) {
            var li = document.createElement('li');

            var badge = document.createElement('span');
            badge.className = 'jf-badge';
            badge.textContent = ext(file.name) || 'file';

            var name = document.createElement('span');
            name.className = 'jf-name';
            name.textContent = file.name;
            name.title = file.name;

            var size = document.createElement('span');
            size.className = 'jf-size';
            size.textContent = formatSize(file.size);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'jf-remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.textContent = '×';
            remove.addEventListener('click', function () {
                fileState.splice(index, 1);
                pushInputFiles(); renderFiles(); syncProgress(); dirty = true;
            });

            li.appendChild(badge); li.appendChild(name); li.appendChild(size); li.appendChild(remove);
            fileList.appendChild(li);
        });
        fileList.hidden = false;
    }

    if (fileInput && fileList) {
        fileInput.addEventListener('change', function () {
            var problems = [];
            Array.prototype.forEach.call(fileInput.files, function (file) {
                var isImage = file.type && file.type.indexOf('image/') === 0;
                if (! isImage && ALLOWED_EXT.indexOf(ext(file.name)) === -1) {
                    problems.push(file.name + ' is not an allowed file type.'); return;
                }
                if (file.size > MAX_BYTES) {
                    problems.push(file.name + ' is ' + formatSize(file.size) + ' — the limit is 5 MB.'); return;
                }
                var duplicate = fileState.some(function (f) { return f.name === file.name && f.size === file.size; });
                if (! duplicate) { fileState.push(file); }
            });

            if (fileErrors) {
                fileErrors.innerHTML = '';
                problems.forEach(function (msg) { var li = document.createElement('li'); li.textContent = msg; fileErrors.appendChild(li); });
                fileErrors.hidden = problems.length === 0;
            }
            pushInputFiles(); renderFiles(); syncProgress(); dirty = true;
        });

        var drop = $('[data-drop]');
        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) { fileInput.addEventListener(ev, function () { drop.classList.add('is-over'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { fileInput.addEventListener(ev, function () { drop.classList.remove('is-over'); }); });
        }
    }

    /* ---------------- Urgent tasks ---------------- */
    var toggle = $('[data-urgent-toggle]');
    var panel = document.getElementById('urgent-form');
    var list = $('[data-urgent-list]');
    var empty = $('[data-urgent-empty]');
    var urgentCount = $('[data-urgent-count]');
    var descIn = document.getElementById('urgent-desc');
    var reasonIn = document.getElementById('urgent-reason');
    var urgentError = $('[data-urgent-error]');

    if (list) {
        var nextIndex = list.querySelectorAll('li').length;
        // Server-rendered rows may use any index, so continue from the highest one.
        $$('input[name^="urgent_tasks["]', list).forEach(function (input) {
            var m = input.name.match(/urgent_tasks\[(\d+)\]/);
            if (m) { nextIndex = Math.max(nextIndex, parseInt(m[1], 10) + 1); }
        });

        var refreshUrgent = function () {
            var n = list.querySelectorAll('li').length;
            if (empty) { empty.hidden = n > 0; }
            if (urgentCount) { urgentCount.textContent = String(n); }
        };
        var setOpen = function (open) {
            if (! panel || ! toggle) { return; }
            panel.hidden = ! open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (urgentError) { urgentError.hidden = true; }
            if (open && descIn) { descIn.focus(); }
        };
        var makeHidden = function (name, value) {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            return input;
        };
        var makeSpan = function (cls, text) {
            var s = document.createElement('span'); s.className = cls; s.textContent = text; return s;
        };
        var addUrgent = function () {
            var desc = descIn.value.trim();
            var reason = reasonIn.value.trim();
            if (! desc || ! reason) {
                if (urgentError) { urgentError.hidden = false; }
                (desc ? reasonIn : descIn).focus();
                return;
            }
            var li = document.createElement('li');
            li.appendChild(makeSpan('dar-urgent-tag', 'Urgent'));
            li.appendChild(makeSpan('dar-urgent-desc', desc));
            li.appendChild(makeSpan('dar-urgent-reason', 'Reason: ' + reason));

            var remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'dar-urgent-remove'; remove.textContent = 'Remove';
            li.appendChild(remove);

            li.appendChild(makeHidden('urgent_tasks[' + nextIndex + '][description]', desc));
            li.appendChild(makeHidden('urgent_tasks[' + nextIndex + '][reason]', reason));
            nextIndex++;

            list.appendChild(li);
            descIn.value = ''; reasonIn.value = '';
            refreshUrgent(); setOpen(false); dirty = true;
        };

        // One delegated listener handles removing both server-rendered and new rows.
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('.dar-urgent-remove');
            if (! btn) { return; }
            btn.closest('li').remove();
            refreshUrgent(); dirty = true;
        });

        if (toggle && panel) {
            toggle.addEventListener('click', function () { setOpen(panel.hidden); });
            $('[data-urgent-cancel]').addEventListener('click', function () { setOpen(false); });
            $('[data-urgent-add]').addEventListener('click', addUrgent);
            // Enter inside these inputs adds the task instead of submitting the whole report.
            [descIn, reasonIn].forEach(function (input) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); addUrgent(); }
                    if (e.key === 'Escape') { setOpen(false); toggle.focus(); }
                });
            });
        }
        refreshUrgent();
    }

    /* ---------------- Local draft (this device only) ---------------- */
    var restoreBanner = $('[data-restore]');
    var draftTimer = null;

    function snapshot() {
        var data = { fields: {}, codes: [] };
        required.forEach(function (el) { data.fields[el.id] = el.value; });
        legendBoxes.forEach(function (b) { if (b.checked) { data.codes.push(b.value); } });
        return data;
    }
    function queueDraft() {
        if (locked || ! draftKey) { return; }
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function () {
            store(function (s) { s.setItem(draftKey, JSON.stringify({ at: Date.now(), data: snapshot() })); });
        }, 500);
    }

    if (! locked && draftKey) {
        var saved = store(function (s) { return s.getItem(draftKey); });
        var allEmpty = required.length > 0 && required.every(function (el) { return ! answered(el); });
        if (saved && allEmpty) {
            try {
                var parsed = JSON.parse(saved);
                var hasText = Object.keys(parsed.data.fields).some(function (id) { return (parsed.data.fields[id] || '').trim() !== ''; });
                if (hasText) {
                    required.forEach(function (el) { if (parsed.data.fields[el.id] != null) { el.value = parsed.data.fields[el.id]; } });
                    if (legendBoxes.every(function (b) { return ! b.checked; })) {
                        legendBoxes.forEach(function (b) { b.checked = parsed.data.codes.indexOf(b.value) !== -1; });
                    }
                    if (restoreBanner) { restoreBanner.hidden = false; }
                }
            } catch (e) { /* ignore a corrupt draft */ }
        }
    }
    var discard = $('[data-restore-discard]');
    if (discard) {
        discard.addEventListener('click', function () {
            required.forEach(function (el) { el.value = ''; });
            legendBoxes.forEach(function (b) { b.checked = false; });
            store(function (s) { s.removeItem(draftKey); });
            restoreBanner.hidden = true;
            syncProgress(); syncChars(); syncLegend();
        });
    }

    /* ---------------- Submit confirmation + busy state ---------------- */
    var dialog = document.getElementById('dar-confirm');
    var summaryList = dialog ? dialog.querySelector('[data-confirm-summary]') : null;
    var confirmed = false;
    var pendingSubmitter = null;

    function summaryRow(label, value) {
        var li = document.createElement('li');
        var a = document.createElement('span'); a.textContent = label;
        var b = document.createElement('span'); b.textContent = value;
        li.appendChild(a); li.appendChild(b);
        return li;
    }
    function fillSummary() {
        if (! summaryList) { return; }
        summaryList.innerHTML = '';
        summaryList.appendChild(summaryRow('Activity codes selected', String(legendBoxes.filter(function (b) { return b.checked; }).length)));
        summaryList.appendChild(summaryRow('Urgent tasks added', String(list ? list.querySelectorAll('li').length : 0)));
        summaryList.appendChild(summaryRow('New attachments', String(fileState.length)));
    }

    // Which action does a button stand for? data-action is the source of truth;
    // value is only a fallback. No submitter at all (Enter key in an old browser) means draft.
    function actionOf(btn) {
        if (! btn) { return 'draft'; }
        return btn.getAttribute('data-action') || btn.value || 'draft';
    }

    // Submit the form with an explicit action, independent of browser submitter support.
    function submitWith(action, btn) {
        actionInput.value = action;
        if (typeof form.requestSubmit === 'function') {
            try { btn ? form.requestSubmit(btn) : form.requestSubmit(); return; } catch (err) { /* fall through */ }
        }
        // Very old browsers: submit() skips the submit event, so run the cleanup ourselves.
        submitting = true;
        store(function (s) { s.removeItem(draftKey); });
        form.submit();
    }

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter || pendingSubmitter;
        var action = actionOf(submitter);

        if (action === 'submit' && ! confirmed) {
            e.preventDefault();
            if (! form.reportValidity()) { return; }
            pendingSubmitter = submitter;
            fillSummary();
            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else if (window.confirm('Submit your report? You will not be able to edit it afterwards.')) {
                confirmed = true;
                submitWith('submit', submitter);
            }
            return;
        }

        // Always post the action, whatever the browser reports as the submitter.
        actionInput.value = action;
        confirmed = false;
        pendingSubmitter = null;

        submitting = true;
        store(function (s) { s.removeItem(draftKey); });
        // Wait a tick so the form data (including the action) is captured before the UI changes.
        setTimeout(function () {
            $$('[data-action]').concat($$('[data-action]', document.querySelector('[data-mobile-bar]') || form)).forEach(function (btn) {
                btn.classList.add('is-busy');
                btn.setAttribute('aria-disabled', 'true');
            });
            var label = submitter && submitter.querySelector && submitter.querySelector('span');
            if (label) { label.textContent = action === 'submit' ? 'Submitting…' : 'Saving…'; }
            else if (submitter) { submitter.textContent = action === 'submit' ? 'Submitting…' : 'Saving…'; }
        }, 0);
    });

    if (dialog) {
        dialog.addEventListener('close', function () {
            if (dialog.returnValue === 'confirm' && pendingSubmitter) {
                confirmed = true;
                submitWith('submit', pendingSubmitter);
            } else {
                // Cancelled or dismissed (Esc): forget the pending submit.
                confirmed = false;
                pendingSubmitter = null;
                actionInput.value = 'draft';
            }
            dialog.returnValue = '';
        });
    }

    /* ---------------- Shortcuts + leave warning ---------------- */
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            var draftBtn = form.querySelector('.dar-actions [data-action="draft"]') || form.querySelector('[data-action="draft"]');
            if (draftBtn && ! draftBtn.disabled && ! locked) {
                e.preventDefault();
                submitWith('draft', draftBtn);
            }
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty && ! submitting && ! locked) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    syncProgress(); syncChars(); syncLegend();
})();
</script>
@endpush
@endsection