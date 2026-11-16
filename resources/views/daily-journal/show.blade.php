@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Report — '.$journal->user->name)

@section('content')
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $isMissing = $journal->isMissing();

    $backUrl = auth()->user()->isAdmin()
        ? route('admin.daily-journal.admin-monitor', ['date' => $journal->required_date->format('Y-m-d')])
        : route('admin.daily-journal.supervisor-monitor', ['date' => $journal->required_date->format('Y-m-d')]);

    // Deleting is only ever offered for a report that actually holds something.
    $canDelete = auth()->user()->isAdmin() && ! $isMissing;

    // Answers may be stored as plain text or cast to an array; always read them as text.
    $answerText = static function ($value): string {
        if (is_array($value)) {
            return trim(implode(', ', array_filter($value, 'is_scalar')));
        }

        return trim((string) $value);
    };

    // Signed storage links need working credentials. If the disk is unreachable the
    // page should still open, so a failed link is shown as unavailable instead of a 500.
    $evidenceUrl = static function (string $path): ?string {
        try {
            return Storage::disk('supabase')->temporaryUrl($path, now()->addHour());
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    };

    // A journal is three free-text answers plus optional evidence, so completion
    // is simply how many of those answers the employee filled in.
    $sections = [
        [
            'field' => 'achievements',
            'title' => 'Accomplishments',
            'subtitle' => 'What was completed today.',
            'empty' => 'No accomplishments were submitted for this day.',
            'tone' => 'gold',
            'icon' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        ],
        [
            'field' => 'problems_encountered',
            'title' => 'Problems Encountered',
            'subtitle' => 'Issues that came up during the day.',
            'empty' => 'No problems were reported for this day.',
            'tone' => 'violet',
            'icon' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        ],
        [
            'field' => 'suggested_solutions',
            'title' => 'Suggested Solutions',
            'subtitle' => 'How those issues could be handled.',
            'empty' => 'No solutions or actions were submitted for this day.',
            'tone' => 'violet',
            'icon' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/>',
        ],
    ];

    $filled = collect($sections)->filter(fn ($s) => $answerText($journal->{$s['field']}) !== '')->count();
    $completion = (int) round($filled / count($sections) * 100);

    $assessmentLevelSlug = $journal->achievement_level ? Str::slug($journal->achievement_level) : null;

    $levelOptions = $levels ?? collect(config('achievements.levels', []))->pluck('label')->values()->all();
    $selectedLevel = old('level', $journal->achievement_level ?? '');
    $manualErrors = $errors->hasAny(['score', 'category', 'level', 'explanation', 'tags', 'reason']);
    $reviewMode = $manualErrors || $journal->isManuallyAssessed() ? 'manual' : 'automatic';

    $initial = mb_strtoupper(mb_substr($journal->user->name, 0, 1));
@endphp

<div class="djr">
    {{-- ===== Header ===== --}}
    <header class="djr-head">
        <div class="djr-head-main">
            <span class="djr-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
            </span>
            <div>
                <h1>Daily Accomplishment Report</h1>
                <p>{{ $journal->user->name }} &middot; {{ ucfirst($journal->user->role) }} &middot; {{ $journal->required_date->format('l, F j, Y') }}</p>
            </div>
        </div>
        <div class="djr-head-actions">
            <span class="djr-status djr-status--{{ $journal->status }}">
                {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
            </span>
            <a href="{{ $backUrl }}" class="djr-btn djr-btn-ghost">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back
            </a>
        </div>
    </header>

    <div class="djr-grid">
        {{-- ================= MAIN COLUMN ================= --}}
        <div class="djr-main">

            @if ($isMissing)
                <div class="djr-callout" role="status">
                    <span class="djr-callout-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </span>
                    <div>
                        <p class="djr-callout-title">{{ $journal->getStatusLabel() }}</p>
                        <p>This accomplishment report has not been submitted.</p>
                    </div>
                </div>
            @endif

            {{-- Report content: accomplishments first, then problems and solutions side by side --}}
            <div class="djr-content">
                @foreach ($sections as $section)
                    @php $answer = $answerText($journal->{$section['field']}); @endphp
                    <section class="djr-card {{ $loop->first ? 'djr-card--wide' : '' }}" aria-label="{{ $section['title'] }}">
                        <header class="djr-card-head">
                            <span class="djr-tile djr-tile--{{ $section['tone'] }}" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $section['icon'] !!}</svg>
                            </span>
                            <div>
                                <h2>{{ $section['title'] }}</h2>
                                <p>{{ $section['subtitle'] }}</p>
                            </div>
                        </header>
                        <div class="djr-card-body">
                            @if ($answer === '')
                                <p class="djr-empty">{{ $section['empty'] }}</p>
                            @else
                                <div class="djr-prose">{!! nl2br(e($answer)) !!}</div>
                            @endif
                        </div>
                    </section>
                @endforeach

                @if ($journal->hasEvidence())
                    <section class="djr-card djr-card--wide" aria-label="Attachments">
                        <header class="djr-card-head">
                            <span class="djr-tile" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </span>
                            <div>
                                <h2>Attachments</h2>
                                <p>{{ $journal->evidenceCount() }} file{{ $journal->evidenceCount() === 1 ? '' : 's' }} attached to this report.</p>
                            </div>
                        </header>
                        <div class="djr-card-body">
                            <div class="djr-evidence-list">
                                @foreach ($journal->evidencePaths() as $path)
                                    @php $url = $evidenceUrl($path); @endphp
                                    @if ($url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="djr-file" title="{{ basename($path) }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span>{{ basename($path) }}</span>
                                        </a>
                                    @else
                                        <span class="djr-file is-unavailable" title="The file service could not be reached.">
                                            <span>{{ basename($path) }}</span>
                                            <small>Unavailable</small>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            {{-- ===== Achievement assessment ===== --}}
            <section class="djr-card dja" id="assessment" aria-labelledby="dja-title">
                <header class="djr-card-head dja-head">
                    <span class="djr-tile djr-tile--gold" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/></svg>
                    </span>
                    <div class="dja-head-text">
                        <h2 id="dja-title">Achievement Assessment</h2>
                        <p>A provisional score is a recognition aid, not proof of misconduct or a disciplinary finding.</p>
                    </div>
                    @if ($journal->hasAssessment())
                        <div class="dja-head-badges">
                            <span class="dja-mode dja-mode--{{ $journal->assessmentMode() }}">{{ $journal->assessmentModeLabel() }}</span>
                            <span class="dja-status dja-status--{{ $journal->isAssessmentVerified() ? 'verified' : 'provisional' }}">
                                {{ $journal->assessmentStatusLabel() }}
                            </span>
                            <span class="dja-source">{{ $journal->assessmentSourceLabel() }}</span>
                        </div>
                    @endif
                </header>

                <div class="djr-card-body">
                    @if (! $journal->hasAssessment())
                        <div class="dja-empty">
                            <span class="dja-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            </span>
                            <div>
                                <h3>Not assessed yet</h3>
                                <p>This report predates assessments or analysis could not be saved. Opening this page does not call an AI service.</p>
                            </div>
                        </div>
                    @else
                        <div class="dja-scoreblock dja-scoreblock--{{ $assessmentLevelSlug }}">
                            <div class="dja-score-wrap">
                                <span class="dja-score">{{ $journal->achievement_score }}</span>
                                <span class="dja-score-max">/ 100</span>
                            </div>
                            <div class="dja-score-meta">
                                <span class="dja-level dja-level--{{ $assessmentLevelSlug }}">{{ $journal->achievement_level }}</span>
                                <span class="dja-category">{{ $journal->achievement_category }}</span>
                                @if ($journal->assessed_at)
                                    <span class="dja-assessed">Assessed {{ $journal->assessed_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <p class="dja-explanation">{{ $journal->achievement_explanation }}</p>

                        @if (! empty($journal->recognition_tags))
                            <div class="dja-tags" aria-label="Recognition tags">
                                @foreach ($journal->recognition_tags as $tag)
                                    <span class="dja-tag">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($journal->isAssessmentVerified())
                            <div class="dja-verified-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                                <span>
                                    Verified by {{ $journal->reviewer?->name ?? 'a reviewer' }}
                                    @if ($journal->reviewed_at)
                                        on {{ $journal->reviewed_at->format('M j, Y g:i A') }}
                                    @endif.
                                    @if ($journal->review_note)
                                        Reason: {{ $journal->review_note }}
                                    @endif
                                </span>
                            </div>
                        @endif

                        <a href="{{ route('daily-journal.assessment.score-sheet', $journal) }}" class="djr-btn djr-btn-ghost dja-score-sheet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            View full score sheet
                        </a>
                    @endif

                    @if ($canReview)
                        <div class="dja-review">
                            <div class="dja-review-head">
                                <div>
                                    <h3>Assessment mode</h3>
                                    <p>Automatic analysis is the default when a journal is submitted. Use manual review to verify or correct the result. A reason is required when overriding an existing assessment.</p>
                                </div>
                                <span class="dja-mode dja-mode--{{ $journal->hasAssessment() ? $journal->assessmentMode() : 'automatic' }}">
                                    {{ $journal->hasAssessment() ? $journal->assessmentModeLabel() : 'Automatic default' }}
                                </span>
                            </div>

                            <div class="dja-mode-control">
                                <span class="dja-label">Choose mode</span>
                                <div class="dja-mode-options" role="radiogroup" aria-label="Assessment mode">
                                    <input type="radio" id="dja-mode-automatic" name="assessment_ui_mode" value="automatic" @checked($reviewMode === 'automatic')>
                                    <label for="dja-mode-automatic">Automatic</label>
                                    <input type="radio" id="dja-mode-manual" name="assessment_ui_mode" value="manual" @checked($reviewMode === 'manual')>
                                    <label for="dja-mode-manual">Manual</label>
                                </div>
                            </div>

                            <div class="dja-mode-panels">
                                <section class="dja-mode-panel dja-mode-panel--automatic" data-dja-mode-panel="automatic" @if ($reviewMode !== 'automatic') hidden @endif>
                                    <div class="dja-mode-panel-head">
                                        <h4>Automatic analysis</h4>
                                        <p>Re-run AI-assisted or rule-based analysis. The result is saved as provisional and replaces the current assessment.</p>
                                    </div>

                                    @if ($journal->isAssessmentVerified())
                                        <div class="dja-warning">
                                            This journal has a manually verified assessment. Automatic reassessment will replace it with a provisional result, and a reviewer will need to verify it again.
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('daily-journal.assessment.reassess', $journal) }}" class="dja-form">
                                        @csrf
                                        <div class="dja-field">
                                            <label for="reassess_reason">Reason for reassessment (optional)</label>
                                            <input type="text" id="reassess_reason" name="reason" value="{{ old('reason') }}" placeholder="Why is a fresh automatic assessment needed?">
                                        </div>
                                        <div class="dja-form-actions">
                                            <button type="submit" class="djr-btn djr-btn-outline">Run automatic reassessment</button>
                                        </div>
                                    </form>
                                </section>

                                <section class="dja-mode-panel dja-mode-panel--manual" data-dja-mode-panel="manual" @if ($reviewMode !== 'manual') hidden @endif>
                                    <div class="dja-mode-panel-head">
                                        <h4>Manual review</h4>
                                        <p>
                                            @if ($journal->hasAssessment())
                                                Saving this form replaces the current assessment with your verified decision and records a revision.
                                            @else
                                                Saving this form creates a verified manual assessment.
                                            @endif
                                        </p>
                                    </div>

                                    <form method="POST" action="{{ route('daily-journal.assessment.review', $journal) }}" class="dja-form">
                                        @csrf
                                        @method('PATCH')
                                        <div class="dja-form-grid">
                                            <div class="dja-field">
                                                <label for="achievement_score">Score (0–100)</label>
                                                <input type="number" id="achievement_score" name="score" min="0" max="100" value="{{ old('score', $journal->achievement_score) }}" required>
                                                @error('score')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="dja-field">
                                                <label for="achievement_category">Category</label>
                                                <select id="achievement_category" name="category" required>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category }}" @selected(old('category', $journal->achievement_category) === $category)>{{ $category }}</option>
                                                    @endforeach
                                                </select>
                                                @error('category')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="dja-field">
                                                <label for="achievement_level">Level</label>
                                                <select id="achievement_level" name="level">
                                                    <option value="">Derive from score</option>
                                                    @foreach ($levelOptions as $level)
                                                        <option value="{{ $level }}" @selected($selectedLevel === $level)>{{ $level }}</option>
                                                    @endforeach
                                                </select>
                                                @error('level')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="dja-field dja-field--wide">
                                                <label for="achievement_explanation">Explanation</label>
                                                <textarea id="achievement_explanation" name="explanation" rows="3" required>{{ old('explanation', $journal->achievement_explanation) }}</textarea>
                                                @error('explanation')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="dja-field dja-field--wide">
                                                <label for="recognition_tags">Recognition tags (comma separated)</label>
                                                <input type="text" id="recognition_tags" name="tags" value="{{ old('tags', implode(', ', $journal->recognition_tags ?? [])) }}" placeholder="Teamwork, Client Service">
                                                @error('tags')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="dja-field dja-field--wide">
                                                <label for="review_reason">
                                                    Reason for the change
                                                    <span class="dja-label-hint">{{ $journal->hasAssessment() ? 'Required when overriding an existing assessment.' : 'Optional for the first manual assessment.' }}</span>
                                                </label>
                                                <textarea id="review_reason" name="reason" rows="2" @if ($journal->hasAssessment()) required @endif placeholder="Explain what was corrected and why.">{{ old('reason') }}</textarea>
                                                @error('reason')<span class="dja-error">{{ $message }}</span>@enderror
                                            </div>
                                        </div>
                                        <div class="dja-form-actions">
                                            <button type="submit" class="djr-btn djr-btn-primary">Save verified manual assessment</button>
                                        </div>
                                    </form>
                                </section>
                            </div>
                        </div>
                    @endif

                    @if ($journal->assessmentRevisions->isNotEmpty())
                        <div class="dja-history">
                            <h3>Assessment history</h3>
                            <ul>
                                @foreach ($journal->assessmentRevisions->take(5) as $revision)
                                    @php
                                        $actionLabel = match ($revision->action) {
                                            'reassessed' => 'Automatic reassessment',
                                            'corrected' => 'Manual review',
                                            default => ucfirst((string) $revision->action),
                                        };
                                    @endphp
                                    <li>
                                        <div class="dja-history-top">
                                            <strong>{{ $actionLabel }}</strong>
                                            <span>{{ $revision->created_at->format('M j, Y g:i A') }}</span>
                                        </div>
                                        <p>
                                            {{ $revision->reviewer?->name ?? 'Unknown reviewer' }}
                                            changed the assessment from
                                            <strong>{{ $revision->previous_score ?? 'none' }}</strong>
                                            to <strong>{{ $revision->new_score ?? 'none' }}</strong>
                                            via {{ \App\Models\DailyJournal::assessmentSourceLabelFor($revision->new_source) }}
                                            ({{ ucfirst((string) ($revision->new_status ?? 'not assessed')) }}).
                                            @if ($revision->reason)
                                                Reason: {{ $revision->reason }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        {{-- ================= SIDEBAR ================= --}}
        <aside class="djr-side">
            <section class="djr-card djr-summary" aria-label="Report summary">
                <div class="djr-summary-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="12"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="18" y1="20" x2="18" y2="14"/></svg>
                    <h2>Report Summary</h2>
                </div>

                <div class="djr-summary-box">
                    <span class="djr-summary-icon djr-summary-icon--{{ $journal->status }}" aria-hidden="true">
                        @if ($isMissing)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
                        @endif
                    </span>
                    <strong>{{ $journal->getStatusLabel() }}</strong>
                    <p>
                        @if ($journal->submitted_at)
                            Sent {{ $journal->submitted_at->format('M j, Y g:i A') }}
                        @else
                            This report has not been submitted.
                        @endif
                    </p>
                </div>

                <div class="djr-progress" title="{{ $filled }} of {{ count($sections) }} report sections answered">
                    <div class="djr-progress-top">
                        <span>Completion</span>
                        <strong>{{ $completion }}%</strong>
                    </div>
                    <div class="djr-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}">
                        <span style="width: {{ $completion }}%"></span>
                    </div>
                    <small>{{ $filled }} of {{ count($sections) }} sections answered</small>
                </div>

                <dl class="djr-meta">
                    <div>
                        <dt>Date covered</dt>
                        <dd>{{ $journal->required_date->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd>{{ $journal->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    @if ($journal->reminder_count > 0)
                        <div>
                            <dt>Reminders</dt>
                            <dd>
                                {{ $journal->reminder_count }} sent
                                @if ($journal->reminded_at)
                                    <small>Last {{ $journal->reminded_at->diffForHumans() }}</small>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if ($journal->hasAssessment())
                <a href="#assessment" class="djr-card djr-scorelink dja-scoreblock--{{ $assessmentLevelSlug }}">
                    <span class="djr-scorelink-score">{{ $journal->achievement_score }}<small>/100</small></span>
                    <span class="djr-scorelink-text">
                        <span class="dja-level dja-level--{{ $assessmentLevelSlug }}">{{ $journal->achievement_level }}</span>
                        <span>{{ $journal->assessmentStatusLabel() }} &middot; view assessment</span>
                    </span>
                </a>
            @endif

            <section class="djr-card" aria-label="Employee">
                <div class="djr-person">
                    <span class="djr-avatar" aria-hidden="true">{{ $initial }}</span>
                    <div>
                        <strong>{{ $journal->user->name }}</strong>
                        <span>{{ ucfirst($journal->user->role) }}</span>
                    </div>
                </div>
            </section>

            @if ($canDelete)
                <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" data-confirm="Delete this journal entry? This action cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="djr-btn djr-btn-danger djr-btn-block">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete Entry
                    </button>
                </form>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('styles')
<style>
.djr {
    --djr-ink: #1b2447;
    --djr-muted: #5b6585;
    --djr-line: #e3e7f3;
    --djr-surface: #ffffff;
    --djr-sunken: #f7f8ff;
    --djr-tint: #eef0fc;
    --djr-brand: #4338ca;
    --djr-brand-dark: #3730a3;
    --djr-ok: #0b7a45;
    color: var(--djr-ink);
}
.djr h1, .djr h2, .djr h3, .djr h4, .djr p { margin: 0; }

/* Header */
.djr-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.djr-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.djr-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--djr-tint); color: var(--djr-brand); }
.djr-head-icon svg { width: 30px; height: 30px; }
.djr-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.djr-head p { margin-top: 2px; color: var(--djr-muted); font-size: .95rem; }
.djr-head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Status chip */
.djr-status { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid transparent; border-radius: 999px; font-size: .85rem; font-weight: 700; white-space: nowrap; }
.djr-status--submitted { background: #e6f7ee; color: #196f3d; border-color: rgba(25,111,61,.18); }
.djr-status--late { background: #fff3e0; color: #92400e; border-color: rgba(146,64,14,.18); }
.djr-status--missing { background: #fdecea; color: #a93226; border-color: rgba(169,50,38,.16); }

/* Buttons */
.djr-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .9rem; line-height: 1.2; cursor: pointer; text-decoration: none; }
.djr-btn:focus-visible, .djr-card:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }
.djr-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--djr-brand-dark)); color: #fff; }
.djr-btn-primary:hover { background: var(--djr-brand-dark); }
.djr-btn-ghost { background: var(--djr-surface); border-color: var(--djr-line); color: var(--djr-ink); }
.djr-btn-ghost:hover { background: var(--djr-tint); }
.djr-btn-outline { background: var(--djr-surface); border-color: var(--djr-brand); color: var(--djr-brand); }
.djr-btn-outline:hover { background: var(--djr-tint); }
.djr-btn-danger { background: #fff; border-color: #f1b5ae; color: #a93226; }
.djr-btn-danger:hover { background: #fdecea; }
.djr-btn-block { width: 100%; }

/* Layout */
.djr-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
.djr-main { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.djr-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; min-width: 0; }
.djr-content { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: start; }
.djr-card--wide { grid-column: 1 / -1; }

/* Cards */
.djr-card { display: block; background: var(--djr-surface); border: 1px solid var(--djr-line); border-radius: 18px; min-width: 0; overflow: hidden; color: inherit; text-decoration: none; }
.djr-card-head { display: flex; align-items: center; gap: 12px; padding: 18px 20px 12px; }
.djr-card-head h2 { font-size: 1.05rem; font-weight: 800; }
.djr-card-head p { margin-top: 2px; color: var(--djr-muted); font-size: .85rem; }
.djr-card-body { padding: 4px 20px 20px; font-size: .95rem; }
.djr-tile { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--djr-tint); color: var(--djr-brand); }
.djr-tile svg { width: 22px; height: 22px; }
.djr-tile--gold { background: #fff4d6; color: #b7791f; }
.djr-tile--violet { background: #ece9fd; color: #5b3fd1; }
.djr-prose { line-height: 1.7; white-space: pre-wrap; overflow-wrap: anywhere; }
.djr-empty { padding: 12px 14px; border: 1px dashed #c9cff0; border-radius: 12px; background: var(--djr-sunken); color: var(--djr-muted); font-size: .88rem; }

/* Callout */
.djr-callout { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border: 1px solid rgba(169,50,38,.18); border-left: 3px solid #dc4c3f; border-radius: 14px; background: #fdecea; }
.djr-callout-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; background: rgba(231,76,60,.14); color: #dc4c3f; }
.djr-callout-icon svg { width: 20px; height: 20px; }
.djr-callout-title { font-weight: 800; color: #a93226; }
.djr-callout p:not(.djr-callout-title) { font-size: .9rem; margin-top: 2px; }

/* Attachments */
.djr-evidence-list { display: flex; flex-wrap: wrap; gap: 8px; }
.djr-file { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; padding: 8px 12px; border: 1px solid var(--djr-line); border-radius: 10px; background: var(--djr-sunken); color: var(--djr-ink); font-size: .85rem; font-weight: 600; text-decoration: none; }
a.djr-file:hover { border-color: var(--djr-brand); background: var(--djr-tint); }
.djr-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.djr-file svg { flex: none; color: var(--djr-brand); }
.djr-file.is-unavailable { color: var(--djr-muted); border-style: dashed; }
.djr-file small { color: #a93226; font-weight: 700; }

/* Sidebar summary */
.djr-summary { padding: 18px 16px 8px; }
.djr-summary-title { display: flex; align-items: center; gap: 10px; color: var(--djr-brand); margin-bottom: 12px; }
.djr-summary-title h2 { font-size: 1.05rem; font-weight: 800; color: var(--djr-ink); }
.djr-summary-box { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 18px 14px; background: #f0f2fd; border-radius: 14px; }
.djr-summary-icon { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #dfe3fa; color: var(--djr-brand); }
.djr-summary-icon svg { width: 32px; height: 32px; }
.djr-summary-icon--submitted { background: #d6f1e3; color: var(--djr-ok); }
.djr-summary-icon--late { background: #fde9c8; color: #92400e; }
.djr-summary-icon--missing { background: #f9d6d2; color: #a93226; }
.djr-summary-box strong { font-size: 1.2rem; font-weight: 800; margin-top: 4px; }
.djr-summary-box p { font-size: .83rem; color: var(--djr-muted); }
.djr-progress { margin: 14px 0 4px; display: grid; gap: 6px; }
.djr-progress-top { display: flex; justify-content: space-between; font-size: .85rem; }
.djr-progress-top span { color: var(--djr-muted); }
.djr-progress-top strong { color: var(--djr-brand); }
.djr-progress-track { height: 8px; border-radius: 999px; background: #e7e9f7; overflow: hidden; }
.djr-progress-track span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, var(--djr-brand)); }
.djr-progress small { color: var(--djr-muted); font-size: .76rem; }
.djr-meta { display: grid; gap: 12px; margin: 14px 0 10px; }
.djr-meta dt { font-size: .78rem; color: var(--djr-muted); }
.djr-meta dd { margin: 2px 0 0; font-weight: 700; font-size: .92rem; }
.djr-meta dd small { display: block; color: var(--djr-muted); font-weight: 500; font-size: .76rem; }

/* Score shortcut + person */
.djr-scorelink { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-left: 3px solid #2563eb; }
.djr-scorelink:hover { border-color: var(--djr-brand); background: var(--djr-sunken); }
.djr-scorelink-score { font-size: 2rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
.djr-scorelink-score small { font-size: .78rem; font-weight: 600; color: var(--djr-muted); margin-left: 2px; }
.djr-scorelink-text { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; font-size: .78rem; color: var(--djr-muted); }
.djr-person { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
.djr-person div { display: flex; flex-direction: column; min-width: 0; }
.djr-person strong { font-weight: 800; overflow-wrap: anywhere; }
.djr-person span:not(.djr-avatar) { font-size: .82rem; color: var(--djr-muted); }
.djr-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; background: var(--djr-tint); color: var(--djr-brand); font-weight: 800; }

/* ---------- Assessment ---------- */
.dja-head { flex-wrap: wrap; }
.dja-head-text { flex: 1 1 260px; min-width: 0; }
.dja-head-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex: none; }
.dja-status, .dja-mode, .dja-level, .dja-category, .dja-tag { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap; }
.dja-status--verified { background: #e6f7ee; color: #196f3d; }
.dja-status--provisional { background: #fff3e0; color: #92400e; }
.dja-source { font-size: .72rem; color: var(--djr-muted); font-weight: 600; }
.dja-mode--automatic { background: #eaf1fc; color: #1d4ed8; }
.dja-mode--manual { background: #e6f7ee; color: #196f3d; }

.dja-scoreblock { display: flex; align-items: center; gap: 18px; padding: 14px 16px; border: 1px solid var(--djr-line); border-left: 3px solid #2563eb; border-radius: 12px; background: var(--djr-sunken); }
.dja-scoreblock--outstanding { border-left-color: #0f9d58; }
.dja-scoreblock--excellent { border-left-color: #2563eb; }
.dja-scoreblock--very-good { border-left-color: #4f46e5; }
.dja-scoreblock--good { border-left-color: #64748b; }
.dja-scoreblock--needs-further-development { border-left-color: #b45309; }
.dja-score-wrap { display: flex; align-items: baseline; gap: 4px; flex: none; }
.dja-score { font-size: 2.3rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
.dja-score-max { font-size: .82rem; font-weight: 600; color: var(--djr-muted); }
.dja-score-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; min-width: 0; }
.dja-level--outstanding { background: #e6f7ee; color: #196f3d; }
.dja-level--excellent { background: #eaf1fc; color: #1d4ed8; }
.dja-level--very-good { background: #eef2ff; color: #4338ca; }
.dja-level--good { background: #f1f5f9; color: #475569; }
.dja-level--needs-further-development { background: #fff3e0; color: #92400e; }
.dja-category { background: #f1f5f9; color: #475569; font-weight: 600; }
.dja-assessed { font-size: .75rem; color: var(--djr-muted); }
.dja-explanation { margin-top: 12px; line-height: 1.6; font-size: .92rem; }
.dja-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.dja-tag { background: #eef0fc; color: var(--djr-brand); }
.dja-verified-note { display: flex; align-items: flex-start; gap: 8px; margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #e6f7ee; color: #196f3d; font-size: .82rem; line-height: 1.5; }
.dja-verified-note svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }
.dja-score-sheet { margin-top: 14px; }
.dja-empty { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px dashed #c9cff0; border-radius: 12px; background: var(--djr-sunken); }
.dja-empty-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; background: #e7e9f7; color: var(--djr-muted); }
.dja-empty-icon svg { width: 18px; height: 18px; }
.dja-empty h3 { font-size: .9rem; margin-bottom: 2px; }
.dja-empty p { font-size: .82rem; color: var(--djr-muted); line-height: 1.5; }

.dja-review { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--djr-line); }
.dja-review-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.dja-review-head h3 { font-size: .95rem; font-weight: 800; }
.dja-review-head p { margin-top: 2px; font-size: .8rem; color: var(--djr-muted); }
.dja-mode-control { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.dja-label { font-size: .78rem; font-weight: 700; color: var(--djr-muted); }
.dja-label-hint { display: block; margin-top: 2px; font-size: .72rem; font-weight: 500; color: var(--djr-muted); }
.dja-mode-options { display: inline-flex; padding: 3px; border: 1px solid var(--djr-line); border-radius: 999px; background: #f1f2fb; }
.dja-mode-options input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.dja-mode-options label { padding: 6px 14px; border-radius: 999px; font-size: .82rem; font-weight: 700; color: var(--djr-muted); cursor: pointer; }
.dja-mode-options input:checked + label { background: #fff; color: var(--djr-brand); box-shadow: 0 1px 2px rgba(15,23,42,.12); }
.dja-mode-options input:focus-visible + label { outline: 3px solid #a5b4fc; outline-offset: 1px; }
.dja-mode-panels { margin-top: 12px; }
.dja-mode-panel { padding: 14px; border: 1px solid var(--djr-line); border-left: 3px solid #4f46e5; border-radius: 12px; background: var(--djr-sunken); }
.dja-mode-panel[hidden] { display: none; }
.dja-mode-panel--manual { border-left-color: #196f3d; }
.dja-mode-panel-head h4 { font-size: .9rem; font-weight: 800; }
.dja-mode-panel-head p { margin: 2px 0 12px; font-size: .8rem; color: var(--djr-muted); }
.dja-warning { margin-bottom: 12px; padding: 9px 12px; border-radius: 10px; background: #fff3e0; color: #92400e; font-size: .8rem; line-height: 1.5; }
.dja-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.dja-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.dja-field--wide { grid-column: 1 / -1; }
.dja-field label { font-size: .8rem; font-weight: 700; }
.dja-field input, .dja-field select, .dja-field textarea { width: 100%; padding: 9px 11px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; color: var(--djr-ink); font: inherit; font-size: .9rem; box-sizing: border-box; }
.dja-field input:focus-visible, .dja-field select:focus-visible, .dja-field textarea:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 1px; border-color: var(--djr-brand); }
.dja-field textarea { resize: vertical; line-height: 1.5; }
.dja-error { color: #dc2626; font-size: .78rem; }
.dja-form-actions { margin-top: 12px; }

.dja-history { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--djr-line); }
.dja-history h3 { font-size: .92rem; font-weight: 800; margin-bottom: 8px; }
.dja-history ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.dja-history li { padding: 10px 12px; border: 1px solid var(--djr-line); border-radius: 10px; background: var(--djr-sunken); }
.dja-history-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.dja-history-top strong { font-size: .85rem; }
.dja-history-top span { font-size: .75rem; color: var(--djr-muted); }
.dja-history p { margin-top: 3px; font-size: .8rem; line-height: 1.5; color: var(--djr-muted); }

/* Responsive */
@media (max-width: 1100px) {
    .djr-grid { grid-template-columns: minmax(0, 1fr); }
    .djr-side { position: static; }
}
@media (max-width: 700px) {
    .djr-content { grid-template-columns: minmax(0, 1fr); }
    .djr-head h1 { font-size: 1.3rem; }
    .dja-head-badges { align-items: flex-start; }
    .dja-form-grid { grid-template-columns: minmax(0, 1fr); }
    .dja-review-head { flex-direction: column; }
    .dja-scoreblock { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    /* Assessment mode switch (Automatic / Manual) */
    var automatic = document.getElementById('dja-mode-automatic');
    var manual = document.getElementById('dja-mode-manual');

    if (automatic && manual) {
        var sync = function () {
            var mode = manual.checked ? 'manual' : 'automatic';
            Array.prototype.forEach.call(document.querySelectorAll('[data-dja-mode-panel]'), function (panel) {
                panel.hidden = panel.getAttribute('data-dja-mode-panel') !== mode;
            });
        };
        automatic.addEventListener('change', sync);
        manual.addEventListener('change', sync);
        sync();
    }

    /* Confirm before deleting */
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
        form.addEventListener('submit', function (event) {
            if (! window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
        });
    });
})();
</script>
@endpush