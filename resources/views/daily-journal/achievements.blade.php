@extends('layouts.dashboard')

@section('title', 'Achievement Rankings')

@php
    use Carbon\Carbon;

    $periodLabels = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'custom' => 'Custom range',
    ];

    $topScore = $rankings->max('score') ?? 0;
    $topPerformers = $rankings->take(3);
    $rangeLabel = $start->isSameDay($end)
        ? $start->format('F j, Y')
        : $start->format('M j, Y').' – '.$end->format('M j, Y');

    $periodUrl = fn (string $value): string => route('daily-journal.achievements', ['period' => $value]);
@endphp

@section('content')
<div class="page-head page-head-row dar-head">
    <div>
        <nav class="dar-crumbs" aria-label="Breadcrumb">
            <span>Daily Accomplishment</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="is-current">Achievement Rankings</span>
        </nav>
        <h1>Achievement Rankings</h1>
        <p>Recognising completed work, initiative, quality, teamwork, and service across EAS.</p>
    </div>
    <div class="dar-range-chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>{{ $rangeLabel }}</span>
    </div>
</div>

@if ($rankings->isEmpty())
    <section class="dar-empty">
        <span class="dar-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a2 2 0 0 0 0 4h3"/><path d="M17 6h3a2 2 0 0 1 0 4h-3"/></svg>
        </span>
        <h2>No assessed accomplishments for this period</h2>
        <p>
            Rankings appear after employees submit Daily Accomplishment Reports.
            Old reports without an assessment are not counted, and no rankings are invented when data is missing.
        </p>
        <div class="dar-empty-actions">
            <a href="{{ $periodUrl('week') }}" class="btn btn-outline btn-sm">Try this week</a>
            <a href="{{ $periodUrl('month') }}" class="btn btn-outline btn-sm">Try this month</a>
        </div>
    </section>
@else
    <section class="dar-stats" aria-label="Ranking summary">
        <article class="dar-stat">
            <span class="dar-stat-label">Employees ranked</span>
            <span class="dar-stat-value">{{ $employeeCount }}</span>
            <span class="dar-stat-note">Distinct people with assessed work</span>
        </article>
        <article class="dar-stat">
            <span class="dar-stat-label">Top score</span>
            <span class="dar-stat-value">{{ number_format((float) $topScore, 1) }}</span>
            <span class="dar-stat-note">Average quality, not entry volume</span>
        </article>
        <article class="dar-stat dar-stat--verified">
            <span class="dar-stat-label">Manually verified</span>
            <span class="dar-stat-value">{{ $verifiedCount }}</span>
            <span class="dar-stat-note">Reviewed by an admin or supervisor</span>
        </article>
        <article class="dar-stat dar-stat--provisional">
            <span class="dar-stat-label">Automatic provisional</span>
            <span class="dar-stat-value">{{ $provisionalCount }}</span>
            <span class="dar-stat-note">Rule-based or AI-assisted, awaiting review</span>
        </article>
    </section>

    @if ($topPerformers->isNotEmpty())
        <section class="dar-podium" aria-label="Top performers">
            @foreach ($topPerformers as $performer)
                @php $levelSlug = \Illuminate\Support\Str::slug($performer['level']); @endphp
                <article class="dar-podium-card dar-podium-card--{{ $performer['badge'] ?? 'plain' }}">
                    <div class="dar-podium-rank">
                        <span class="dar-medal dar-medal--{{ $performer['badge'] ?? 'plain' }}">#{{ $performer['rank'] }}</span>
                        <span class="dar-podium-label">{{ $performer['badge'] === 'gold' ? 'Top performer' : 'Recognition' }}</span>
                    </div>
                    <div class="dar-podium-person">
                        <span class="dar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($performer['user']->name, 0, 1)) }}</span>
                        <div>
                            <h2>{{ $performer['user']->name }}</h2>
                            <p>{{ ucfirst($performer['user']->role) }} &middot; {{ $performer['submitted_count'] }} assessed {{ \Illuminate\Support\Str::plural('entry', $performer['submitted_count']) }}</p>
                        </div>
                    </div>
                    <div class="dar-podium-score">
                        <span class="dar-score">{{ number_format((float) $performer['score'], 1) }}</span>
                        <span class="dar-level dar-level--{{ $levelSlug }}">{{ $performer['level'] }}</span>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endif

<section class="dar-filters" aria-labelledby="dar-filter-title">
    <div class="dar-filters-head">
        <div>
            <h2 id="dar-filter-title">Ranking period</h2>
            <p>Compare employees within the same period. Custom ranges are limited to one year.</p>
        </div>
    </div>

    <div class="dar-period-tabs" role="group" aria-label="Ranking period">
        @foreach ($periodLabels as $value => $label)
            <a href="{{ $periodUrl($value) }}" class="dar-period-tab {{ $period === $value ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('daily-journal.achievements') }}" class="dar-custom-form">
        <input type="hidden" name="period" value="custom">
        <div class="dar-field">
            <label for="start_date">Start date</label>
            <input type="date" id="start_date" name="start_date" value="{{ $start->format('Y-m-d') }}" required>
        </div>
        <div class="dar-field">
            <label for="end_date">End date</label>
            <input type="date" id="end_date" name="end_date" value="{{ $end->format('Y-m-d') }}" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Apply range</button>
    </form>
</section>

@if ($rankings->isNotEmpty())
    <section class="dar-card" aria-labelledby="dar-table-title">
        <div class="dar-card-head">
            <div>
                <h2 id="dar-table-title">Ranking table</h2>
                <p>{{ $rankings->count() }} {{ \Illuminate\Support\Str::plural('employee', $rankings->count()) }} ranked for {{ $rangeLabel }}.</p>
            </div>
            <span class="dar-provisional-key">
                <span class="dar-dot dar-dot--verified"></span> Manual verified
                <span class="dar-dot dar-dot--provisional"></span> Automatic provisional
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover dar-table">
                <thead>
                    <tr>
                        <th scope="col">Rank</th>
                        <th scope="col">Employee</th>
                        <th scope="col">Achievement score</th>
                        <th scope="col">Level</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Manual verified</th>
                        <th scope="col">Recognition tags</th>
                        <th scope="col">Assessment mode</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rankings as $ranking)
                        @php
                            $levelSlug = \Illuminate\Support\Str::slug($ranking['level']);
                            $statusLabel = match ($ranking['verification_status']) {
                                'verified' => 'Manual verified',
                                'partial' => 'Partially verified',
                                default => 'Automatic provisional',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="dar-rank dar-rank--{{ $ranking['badge'] ?? 'plain' }}">
                                    @if ($ranking['badge'])
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>
                                    @endif
                                    {{ $ranking['rank'] }}
                                </span>
                            </td>
                            <td>
                                <div class="dar-person">
                                    <span class="dar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($ranking['user']->name, 0, 1)) }}</span>
                                    <div class="dar-person-text">
                                        <span class="dar-person-name">{{ $ranking['user']->name }}</span>
                                        <span class="dar-person-role">{{ ucfirst($ranking['user']->role) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="dar-score">{{ number_format((float) $ranking['score'], 1) }}</span></td>
                            <td><span class="dar-level dar-level--{{ $levelSlug }}">{{ $ranking['level'] }}</span></td>
                            <td><span class="dar-count">{{ $ranking['submitted_count'] }}</span></td>
                            <td>
                                <span class="dar-count">{{ $ranking['verified_count'] }}</span>
                                @if ($ranking['provisional_count'] > 0)
                                    <span class="dar-count-note">+{{ $ranking['provisional_count'] }} provisional</span>
                                @endif
                            </td>
                            <td>
                                <div class="dar-tags">
                                    @forelse ($ranking['tags'] as $tag)
                                        <span class="dar-tag">{{ $tag }}</span>
                                    @empty
                                        <span class="dar-none">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <span class="dar-status dar-status--{{ $ranking['verification_status'] }}">
                                    <span class="dar-dot dar-dot--{{ $ranking['verification_status'] === 'verified' ? 'verified' : 'provisional' }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dar-notes" aria-label="How rankings work">
        <div>
            <h2>How this ranking works</h2>
            <p>
                Employees are ordered by their average assessed quality across distinct accomplishment text, not by the
                number of reports submitted. Repeated or copy-pasted entries are counted once. Differences in role are
                shown so work is read in context rather than assumed to be identical.
            </p>
        </div>
        <div>
            <h2>Automatic vs manual</h2>
            <p>
                An automatic provisional score comes from the rule-based fallback or an optional AI service. It is not a
                finding of misconduct and is never the sole basis for discipline. Once an admin or supervisor reviews
                the entry in manual mode, the achievement is marked manually verified and the change is recorded in its
                audit history.
            </p>
        </div>
    </section>
@endif
@endsection

@push('styles')
<style>
/* Scoped to .dar-* so shared dashboard styles stay untouched. */
.dar-head { align-items: flex-start; gap: 1rem; }
.dar-crumbs { display: flex; align-items: center; gap: .3rem; margin-bottom: .4rem; color: var(--muted, #64748b); font-size: .75rem; font-weight: 600; letter-spacing: .02em; }
.dar-crumbs svg { width: 12px; height: 12px; flex: none; opacity: .6; }
.dar-crumbs .is-current { color: var(--navy, #1e3a5f); }
.dar-head h1 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1.55rem; font-weight: 800; letter-spacing: -.015em; line-height: 1.2; }
.dar-head p { margin: .35rem 0 0; color: var(--muted, #64748b); font-size: .9rem; }
.dar-range-chip { display: inline-flex; align-items: center; gap: .5rem; flex: none; padding: .55rem .85rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; color: var(--navy, #1e3a5f); font-size: .82rem; font-weight: 700; box-shadow: 0 4px 14px rgba(15, 47, 91, .05); }
.dar-range-chip svg { width: 16px; height: 16px; flex: none; color: #2563eb; }

.dar-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.1rem; }
.dar-stat { display: flex; flex-direction: column; gap: .2rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 6px 18px rgba(15, 47, 91, .05); }
.dar-stat-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.dar-stat-value { color: var(--navy, #1e3a5f); font-size: 1.8rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
.dar-stat-note { color: var(--muted, #64748b); font-size: .74rem; }
.dar-stat--verified { border-left: 3px solid #0f9d58; }
.dar-stat--provisional { border-left: 3px solid #d97706; }

.dar-podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.dar-podium-card { display: flex; flex-direction: column; gap: .75rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .06); }
.dar-podium-card--gold { border-color: #e6c66b; background: linear-gradient(180deg, #fffdf4, #fff); }
.dar-podium-card--silver { border-color: #cbd5e1; }
.dar-podium-card--bronze { border-color: #d9b08c; }
.dar-podium-rank { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.dar-podium-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.dar-medal { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 .5rem; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.dar-medal--gold { background: #fef3c7; color: #92400e; }
.dar-medal--silver { background: #e2e8f0; color: #475569; }
.dar-medal--bronze { background: #fde8d7; color: #9a3412; }
.dar-podium-person { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.dar-podium-person h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: .98rem; font-weight: 800; overflow-wrap: anywhere; }
.dar-podium-person p { margin: .1rem 0 0; color: var(--muted, #64748b); font-size: .75rem; }
.dar-podium-score { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: auto; }
.dar-score { color: var(--navy, #1e3a5f); font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.dar-podium-score .dar-score { font-size: 1.6rem; }

.dar-filters { margin-bottom: 1.25rem; padding: 1.1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); }
.dar-filters-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.dar-filters-head p { margin: .2rem 0 .9rem; color: var(--muted, #64748b); font-size: .8rem; }
.dar-period-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
.dar-period-tab { display: inline-flex; align-items: center; justify-content: center; padding: .48rem .85rem; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 999px; background: var(--surface, #fff); color: var(--muted, #64748b); font-size: .8rem; font-weight: 700; text-decoration: none; }
.dar-period-tab:hover { border-color: #bfd4f5; color: #1d4ed8; }
.dar-period-tab.is-active { background: #eef4fd; border-color: #bfd4f5; color: #1d4ed8; }
.dar-custom-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .7rem; padding-top: .9rem; border-top: 1px solid var(--border-subtle, #e5e7eb); }
.dar-field { display: flex; flex-direction: column; gap: .3rem; min-width: 150px; }
.dar-field label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.dar-field input { min-height: 2.4rem; padding: .4rem .6rem; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 9px; background: var(--surface, #fff); color: var(--text, #1f2937); font: inherit; font-size: .85rem; }

.dar-card { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); overflow: hidden; }
.dar-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.05rem 1.25rem; border-bottom: 1px solid var(--border-subtle, #e5e7eb); }
.dar-card-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.dar-card-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.dar-provisional-key { display: inline-flex; align-items: center; gap: .35rem; flex: none; color: var(--muted, #64748b); font-size: .72rem; font-weight: 600; }

.dar-table { margin: 0; min-width: 940px; }
.dar-table thead th { padding: .75rem 1.1rem; background: #f8fafc; border-bottom: 1px solid var(--border-subtle, #e5e7eb); color: var(--muted, #64748b); font-size: .66rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
.dar-table tbody td { padding: .8rem 1.1rem; vertical-align: middle; }
.dar-rank { display: inline-flex; align-items: center; gap: .25rem; min-width: 2rem; height: 2rem; padding: 0 .5rem; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: .8rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.dar-rank svg { width: 14px; height: 14px; flex: none; }
.dar-rank--gold { background: #fef3c7; color: #92400e; }
.dar-rank--silver { background: #e2e8f0; color: #475569; }
.dar-rank--bronze { background: #fde8d7; color: #9a3412; }

.dar-person { display: flex; align-items: center; gap: .6rem; min-width: 0; }
.dar-avatar { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: none; border-radius: 50%; background: #eef4fd; color: #2563eb; font-size: .8rem; font-weight: 800; }
.dar-person-text { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.dar-person-name { color: var(--navy, #1e3a5f); font-weight: 700; white-space: nowrap; }
.dar-person-role { color: var(--muted, #64748b); font-size: .74rem; }
.dar-count { color: var(--navy, #1e3a5f); font-weight: 700; font-variant-numeric: tabular-nums; }
.dar-count-note { display: block; color: #b45309; font-size: .68rem; font-weight: 600; }

.dar-level { display: inline-flex; align-items: center; padding: .25rem .6rem; border: 1px solid transparent; border-radius: 999px; font-size: .7rem; font-weight: 700; line-height: 1.25; white-space: nowrap; }
.dar-level--outstanding { background: #ecfdf3; border-color: #bbf7d0; color: #047857; }
.dar-level--excellent { background: #eef4fd; border-color: #bfd4f5; color: #1d4ed8; }
.dar-level--very-good { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.dar-level--good { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.dar-level--needs-further-development { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }

.dar-tags { display: flex; flex-wrap: wrap; gap: .3rem; max-width: 220px; }
.dar-tag { display: inline-flex; padding: .22rem .5rem; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: .68rem; font-weight: 600; white-space: nowrap; }
.dar-none { color: #94a3b8; }
.dar-status { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
.dar-status--verified { background: #e7f6ee; color: #0b7a45; }
.dar-status--partial { background: #fdf1e2; color: #92400e; }
.dar-status--provisional { background: #fff7ed; color: #b45309; }
.dar-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex: none; }
.dar-dot--verified { background: #0f9d58; }
.dar-dot--provisional { background: #d97706; }

.dar-empty { display: flex; flex-direction: column; align-items: center; gap: .55rem; margin-bottom: 1.25rem; padding: 3rem 1.5rem; text-align: center; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); }
.dar-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex: none; border-radius: 14px; background: #f1f5f9; color: #64748b; }
.dar-empty-icon svg { width: 24px; height: 24px; flex: none; }
.dar-empty h2 { margin: .35rem 0 0; color: var(--navy, #1e3a5f); font-size: 1.05rem; font-weight: 700; }
.dar-empty p { max-width: 34rem; margin: 0; color: var(--muted, #64748b); font-size: .87rem; line-height: 1.55; }
.dar-empty-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .4rem; }

.dar-notes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1.25rem; }
.dar-notes > div { padding: 1rem 1.15rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.dar-notes h2 { margin: 0 0 .3rem; color: var(--navy, #1e3a5f); font-size: .88rem; font-weight: 700; }
.dar-notes p { margin: 0; color: var(--muted, #64748b); font-size: .8rem; line-height: 1.55; }

@media (max-width: 1199px) {
    .dar-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dar-podium { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .dar-head { flex-direction: column; align-items: stretch; }
    .dar-notes { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
    .dar-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .dar-stat { padding: .85rem .9rem; }
    .dar-stat-value { font-size: 1.5rem; }
    .dar-podium { grid-template-columns: minmax(0, 1fr); }
    .dar-period-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dar-custom-form { display: grid; grid-template-columns: minmax(0, 1fr); }
    .dar-field { min-width: 0; }
    .dar-card-head { flex-direction: column; }
    .dar-head h1 { font-size: 1.35rem; }
}
</style>
@endpush
