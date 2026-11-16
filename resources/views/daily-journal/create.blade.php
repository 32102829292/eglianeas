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
@endphp

{{-- Form wraps the whole page so the summary sidebar buttons can submit it. --}}
{{-- Adjust the route name below to whatever your controller uses to save. --}}
<form method="POST"
      action="{{ route('daily-journal.store') }}"
      enctype="multipart/form-data"
      class="dar"
      data-journal-form>
    @csrf
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>{{ $date->format('l, F j, Y') }}</span>
            </div>
            <a href="{{ route('daily-journal.create', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
               class="dar-nav-btn" aria-label="Next day">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            <a href="{{ route('daily-journal.create') }}" class="dar-today-btn">Today</a>
        </div>
    </header>

    {{-- ===== Status banners ===== --}}
    @if ($requiresReport)
        @if ($isSubmitted)
            <div class="dar-alert dar-alert-success">
                <strong>Report submitted.</strong>
                @if ($journal->submitted_at) Sent {{ $journal->submitted_at->format('F j, Y g:i A') }}. @endif
                To change something, please contact your supervisor.
            </div>
        @elseif ($isFuture)
            <div class="dar-alert dar-alert-info">
                <strong>Not due yet.</strong> This report covers {{ $date->format('l, F j, Y') }}. Come back on that day to fill it in.
            </div>
        @elseif ($isPast)
            <div class="dar-alert dar-alert-warn">
                <strong>This report is overdue.</strong> Nothing was submitted for {{ $date->format('l, F j, Y') }}. Fill it in now and describe what happened.
            </div>
        @endif
    @else
        <div class="dar-alert dar-alert-info">
            <strong>No report required.</strong> Daily Accomplishment Reports are only required Monday through Friday.
        </div>
    @endif

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
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            Add Urgent Task
                        </button>
                    @endif
                </div>

                @if ($workPlanTasks->isEmpty())
                    <div class="dar-empty">
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
                    </div>

                    <div id="urgent-form" class="dar-urgent-form" hidden>
                        <div class="dar-field">
                            <label for="urgent-desc">Task</label>
                            <input type="text" id="urgent-desc" class="dar-input" placeholder="What did you work on?">
                        </div>
                        <div class="dar-field">
                            <label for="urgent-reason">Reason it was urgent</label>
                            <input type="text" id="urgent-reason" class="dar-input" placeholder="Why couldn't it wait?">
                        </div>
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
                    </div>

                    @if ($achievementLegends && $achievementLegends->isNotEmpty())
                        <div class="dar-legend-grid">
                            @foreach ($achievementLegends as $legend)
                                <label class="dar-legend">
                                    <input type="checkbox"
                                           name="achievement_codes[]"
                                           value="{{ $legend->code }}"
                                           @checked(in_array($legend->code, $checkedCodes, true))
                                           @disabled($locked)>
                                    <span><strong>{{ $legend->code }}</strong> &ndash; {{ $legend->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="dar-empty">
                            <strong>No achievement legend codes available.</strong>
                            <p>Codes will appear once activity legends are configured.</p>
                        </div>
                    @endif

                    <div class="dar-field dar-field-pad">
                        <label for="achievements">Achievement details</label>
                        <textarea id="achievements"
                                  name="achievements"
                                  class="dar-input dar-textarea @error('achievements') is-invalid @enderror"
                                  rows="3"
                                  required
                                  @readonly($isSubmitted)
                                  @disabled($isFuture)
                                  placeholder="Describe or list completed activities…"
                                  data-journal-input>{{ old('achievements', $journal?->achievements) }}</textarea>
                        @if (! empty($prompt['achievements']['hint']))
                            <div class="dar-hint">{{ $prompt['achievements']['hint'] }}</div>
                        @endif
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
                            <h2>Problems Encountered <span class="dar-req" title="Required">*</span></h2>
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
                        @if ($requiresReport && ! empty($prompt['problems_encountered']['hint']))
                            <div class="dar-hint">{{ $prompt['problems_encountered']['hint'] }}</div>
                        @endif
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
                            <h2>Suggested Solutions <span class="dar-req" title="Required">*</span></h2>
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
                        @if ($requiresReport && ! empty($prompt['suggested_solutions']['hint']))
                            <div class="dar-hint">{{ $prompt['suggested_solutions']['hint'] }}</div>
                        @endif
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
                    <p class="dar-progress" data-readiness-count aria-live="polite"></p>
                </div>

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
                    <button type="submit" name="action" value="submit" class="dar-btn dar-btn-primary dar-btn-lg" @disabled($isFuture)>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M22 2 11 13" stroke="currentColor" stroke-width="2" fill="none"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                        Submit Report
                    </button>
                    <button type="submit" name="action" value="draft" class="dar-btn dar-btn-outline dar-btn-lg" formnovalidate @disabled($isFuture)>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Draft
                    </button>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="dar-btn dar-btn-outline dar-btn-lg">Back to Dashboard</a>
                @endif
            </div>

            <div class="dar-tip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <p>You can review and edit your report before submitting.</p>
            </div>
        </aside>
    </div>
</form>

@push('styles')
<style>
.dar {
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
.dar h1, .dar h2, .dar h3, .dar p { margin: 0; }

/* Header */
.dar-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
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

/* Alerts */
.dar-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: .92rem; border: 1px solid; }
.dar-alert-success { background: #e7f6ee; border-color: #b6e3cb; color: #0b5f37; }
.dar-alert-info { background: #eaf1ff; border-color: #c4d6fb; color: #1e40af; }
.dar-alert-warn { background: var(--dar-warn-bg); border-color: var(--dar-warn-line); color: var(--dar-warn-ink); }

/* Two-column layout */
.dar-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
.dar-main { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.dar-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; }
.dar-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Cards */
.dar-card { background: var(--dar-surface); border: 1px solid var(--dar-line); border-radius: 18px; padding-bottom: 4px; min-width: 0; }
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

/* Buttons */
.dar-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 12px; border: 1px solid transparent; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; line-height: 1.2; }
.dar-btn:focus-visible, .dar-nav-btn:focus-visible, .dar-input:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }
.dar-btn:disabled { opacity: .5; cursor: not-allowed; }
.dar-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--dar-brand-dark)); color: #fff; }
.dar-btn-primary:hover:not(:disabled) { background: var(--dar-brand-dark); }
.dar-btn-outline { background: var(--dar-surface); border-color: var(--dar-brand); color: var(--dar-brand); }
.dar-btn-outline:hover:not(:disabled) { background: var(--dar-tint); }
.dar-btn-ghost { background: var(--dar-surface); border-color: var(--dar-line); color: var(--dar-ink); }
.dar-btn-sm { padding: 7px 12px; font-size: .82rem; }
.dar-btn-lg { width: 100%; padding: 15px 18px; font-size: 1rem; border-radius: 14px; }

/* Goals table */
.dar-table-wrap { margin: 4px 20px 8px; border: 1px solid var(--dar-line); border-radius: 12px; overflow: hidden; overflow-x: auto; }
.dar-table { width: 100%; border-collapse: collapse; font-size: .95rem; }
.dar-table thead th { background: #e9ebfb; text-align: left; padding: 11px 16px; font-weight: 700; font-size: .9rem; }
.dar-table tbody td { padding: 10px 16px; border-top: 1px solid var(--dar-line); vertical-align: middle; }
.dar-th-status, .dar-td-status { text-align: right; white-space: nowrap; }
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
.dar-subhead h3 { font-size: 1.02rem; font-weight: 800; color: var(--dar-ink); }
.dar-notice { display: flex; align-items: center; gap: 10px; margin: 0 20px 14px; padding: 10px 14px; background: var(--dar-warn-bg); border: 1px solid var(--dar-warn-line); border-radius: 10px; color: var(--dar-warn-ink); font-size: .92rem; }
.dar-urgent-form { margin: 0 20px 12px; padding: 14px; border: 1px dashed #b8bfee; border-radius: 12px; background: #f7f8ff; display: grid; gap: 10px; }
.dar-urgent-actions { display: flex; gap: 8px; }
.dar-urgent-list { list-style: none; margin: 0; padding: 0 20px 14px; display: flex; flex-direction: column; gap: 8px; }
.dar-urgent-list li { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 10px 14px; background: #fffaf0; border: 1px solid var(--dar-warn-line); border-radius: 10px; font-size: .92rem; }
.dar-urgent-tag { font-weight: 800; color: #b45309; }
.dar-urgent-desc { flex: 1 1 200px; overflow-wrap: anywhere; }
.dar-urgent-reason { color: var(--dar-muted); font-size: .85rem; }
.dar-urgent-remove { background: none; border: 0; color: #a5281a; font-weight: 700; cursor: pointer; font-size: .82rem; }

/* Achievements */
.dar-legend-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; padding: 4px 20px 8px; }
.dar-legend { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--dar-line); border-radius: 10px; background: #fafbff; font-size: .88rem; cursor: pointer; }
.dar-legend:hover { border-color: #b8bfee; }
.dar-legend input { width: 18px; height: 18px; flex: none; accent-color: var(--dar-brand); }
.dar-legend:has(input:checked) { background: var(--dar-tint); border-color: var(--dar-brand); }

/* Fields */
.dar-field { display: grid; gap: 6px; }
.dar-field label { font-weight: 700; font-size: .88rem; }
.dar-field-pad { padding: 8px 20px 16px; }
.dar-input { width: 100%; padding: 10px 12px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; font: inherit; color: var(--dar-ink); box-sizing: border-box; }
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
.dar-file-list { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.dar-file-list li { display: flex; gap: 8px; padding: 8px 12px; background: #f7f8ff; border: 1px solid var(--dar-line); border-radius: 10px; font-size: .84rem; }
.dar-file-list .jf-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dar-file-list .jf-size { color: var(--dar-muted); font-variant-numeric: tabular-nums; }
.dar-evidence { margin-top: 14px; font-size: .9rem; }
.dar-evidence-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.dar-evidence-links a { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Sidebar */
.dar-summary { padding: 18px 16px 8px; }
.dar-summary-title { display: flex; align-items: center; gap: 10px; color: var(--dar-brand); margin-bottom: 12px; }
.dar-summary-title h2 { font-size: 1.05rem; font-weight: 800; color: var(--dar-ink); }
.dar-summary-box { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 18px 14px; background: #f0f2fd; border-radius: 14px; }
.dar-summary-icon { display: inline-flex; align-items: center; justify-content: center; width: 70px; height: 70px; border-radius: 50%; background: #dfe3fa; color: var(--dar-brand); }
.dar-summary-icon svg { width: 36px; height: 36px; }
.dar-summary-icon.is-done { background: #d6f1e3; color: var(--dar-ok); }
.dar-summary-state { font-size: 1.25rem; font-weight: 800; margin-top: 4px; }
.dar-summary-box p { font-size: .85rem; color: var(--dar-muted); }
.dar-progress { font-weight: 700; color: var(--dar-brand) !important; }
.dar-progress.is-ready { color: var(--dar-ok) !important; }
.dar-meta { margin: 14px 0 6px; display: grid; gap: 14px; }
.dar-meta > div { display: flex; gap: 12px; align-items: flex-start; color: var(--dar-brand); }
.dar-meta dt { font-size: .82rem; color: var(--dar-muted); }
.dar-meta dd { margin: 2px 0 0; font-weight: 600; color: var(--dar-ink); }
.dar-meta .dar-deadline { font-size: 1.05rem; font-weight: 800; color: var(--dar-brand); }
.dar-actions { display: grid; gap: 12px; }
.dar-tip { display: flex; gap: 10px; padding: 14px; background: #eef0fc; border-radius: 14px; color: var(--dar-brand); font-size: .85rem; }
.dar-tip p { color: var(--dar-ink); }

/* Responsive */
@media (max-width: 1100px) {
    .dar-grid { grid-template-columns: 1fr; }
    .dar-side { position: static; }
    .dar-legend-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
    .dar-pair { grid-template-columns: 1fr; }
    .dar-date-nav { flex-wrap: wrap; }
    .dar-head h1 { font-size: 1.3rem; }
}
@media (max-width: 480px) {
    .dar-legend-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-journal-form]');
    if (! form) { return; }

    /* ---- Progress counter: how many required fields are filled ---- */
    var required = Array.prototype.slice.call(form.querySelectorAll('[data-journal-input]'));
    var counter  = form.querySelector('[data-readiness-count]');

    function sync() {
        if (! counter || required.length === 0) { return; }
        var done = required.filter(function (el) { return el.value.trim().length > 0; }).length;
        counter.textContent = done + ' of ' + required.length + ' sections answered';
        counter.classList.toggle('is-ready', done === required.length);
    }
    required.forEach(function (el) { el.addEventListener('input', sync); });
    sync();

    /* ---- Queued file preview ---- */
    var fileInput = form.querySelector('[data-journal-files]');
    var fileList  = form.querySelector('[data-file-list]');

    if (fileInput && fileList) {
        var render = function () {
            var files = fileInput.files;
            fileList.innerHTML = '';
            if (! files || files.length === 0) { fileList.hidden = true; return; }

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

        var drop = form.querySelector('[data-drop]');
        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) {
                fileInput.addEventListener(ev, function () { drop.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                fileInput.addEventListener(ev, function () { drop.classList.remove('is-over'); });
            });
        }
    }

    /* ---- Add Urgent Task ---- */
    var toggle  = form.querySelector('[data-urgent-toggle]');
    var panel   = document.getElementById('urgent-form');
    var list    = form.querySelector('[data-urgent-list]');
    var empty   = form.querySelector('[data-urgent-empty]');
    var descIn  = document.getElementById('urgent-desc');
    var reasonIn = document.getElementById('urgent-reason');

    if (toggle && panel && list) {
        var nextIndex = list.querySelectorAll('li').length;

        function setOpen(open) {
            panel.hidden = ! open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { descIn.focus(); }
        }

        function refreshEmpty() {
            if (empty) { empty.hidden = list.querySelectorAll('li').length > 0; }
        }

        function hidden(name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            return input;
        }

        function span(cls, text) {
            var s = document.createElement('span');
            s.className = cls;
            s.textContent = text;
            return s;
        }

        toggle.addEventListener('click', function () { setOpen(panel.hidden); });
        form.querySelector('[data-urgent-cancel]').addEventListener('click', function () { setOpen(false); });

        form.querySelector('[data-urgent-add]').addEventListener('click', function () {
            var desc = descIn.value.trim();
            var reason = reasonIn.value.trim();
            if (! desc || ! reason) {
                (desc ? reasonIn : descIn).focus();
                return;
            }

            var li = document.createElement('li');
            li.appendChild(span('dar-urgent-tag', 'Urgent'));
            li.appendChild(span('dar-urgent-desc', desc));
            li.appendChild(span('dar-urgent-reason', 'Reason: ' + reason));

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'dar-urgent-remove';
            remove.textContent = 'Remove';
            remove.addEventListener('click', function () { li.remove(); refreshEmpty(); });
            li.appendChild(remove);

            li.appendChild(hidden('urgent_tasks[' + nextIndex + '][description]', desc));
            li.appendChild(hidden('urgent_tasks[' + nextIndex + '][reason]', reason));
            nextIndex++;

            list.appendChild(li);
            descIn.value = '';
            reasonIn.value = '';
            refreshEmpty();
            setOpen(false);
        });
    }
})();
</script>
@endpush
@endsection