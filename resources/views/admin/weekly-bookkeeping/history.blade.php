@extends('layouts.dashboard')

@section('title')
    Weekly Target History — {{ $bookkeeping->week_start?->format('M j, Y') }} — Egliane Accounting Services
@endsection

@section('content')
    @php
        /*
         * Presentation-only helpers. Everything is read from $history, which the
         * controller has already scoped to the signed-in user, so nothing here
         * can show an event the person is not allowed to see.
         */
        $eventLabel = fn ($action) => $eventLabels[$action] ?? \Illuminate\Support\Str::headline((string) $action);

        $historyByDay = $history->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        $dayLabel = function ($date) {
            if ($date->isToday()) {
                return 'Today';
            }
            if ($date->isYesterday()) {
                return 'Yesterday';
            }

            return $date->format('l, M j, Y');
        };

        // Event types present in this trail, for the filter.
        $eventOptions = $history->map(fn ($log) => $eventLabel($log->action))->unique()->sort()->values();

        $peopleCount = $history->map(fn ($log) => $log->user?->id ?? 'system')->unique()->count();
        $latestLog = $history->sortByDesc('created_at')->first();
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>Weekly Target History</h1>
            <p>
                <span class="title-week">{{ $bookkeeping->week_start?->format('M j, Y') }} – {{ $bookkeeping->week_end?->format('M j, Y') }}</span>
                <span class="title-sep">·</span>
                <span class="title-client">{{ $bookkeeping->displayOwnerName() }}</span>
                <span class="title-sep">·</span>
                <span class="badge {{ $badgeClasses[$bookkeeping->status] ?? 'badge-neutral' }}">{{ $bookkeeping->statusLabel() }}</span>
            </p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.weekly-bookkeeping.show', $bookkeeping) }}" class="btn btn-outline btn-sm">Back to weekly target</a>
            <a href="{{ route('admin.weekly-bookkeeping.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    {{--
        $history is the trail the controller already scoped to the signed-in
        user: plain staff only see entries about their own tasks, while
        supervisors and admins see the whole week. Rendering the relation
        directly would leak other people's events, so always iterate $history.
    --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Accountability Timeline <span class="card-sub">{{ $history->count() }} {{ \Illuminate\Support\Str::plural('event', $history->count()) }}</span></h2>
        </div>

        @if ($history->isNotEmpty())
            <p class="timeline-summary">
                {{ $peopleCount }} {{ \Illuminate\Support\Str::plural('person', $peopleCount) }} involved
                @if ($latestLog)
                    &middot; Last activity {{ $latestLog->created_at->diffForHumans() }}
                @endif
            </p>

            {{-- Only worth showing once the trail is long enough to need it. --}}
            @if ($history->count() > 4)
                <div class="timeline-filters">
                    <label class="timeline-filter">
                        <span>Event</span>
                        <select id="tl-event">
                            <option value="">All events</option>
                            @foreach ($eventOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="timeline-filter timeline-filter-grow">
                        <span>Search</span>
                        <input type="search" id="tl-search" placeholder="Person or detail…" autocomplete="off">
                    </label>
                    <span class="timeline-filter-count" id="tl-count" aria-live="polite"></span>
                </div>
            @endif

            <div class="timeline" role="list">
                @foreach ($historyByDay as $day => $logs)
                    {{-- A day heading sits between the items, so the items stay
                         direct children of .timeline exactly as before. --}}
                    <div class="timeline-day-title" role="presentation">
                        {{ $dayLabel(\Illuminate\Support\Carbon::parse($day)) }}
                        <span class="timeline-day-count">{{ $logs->count() }}</span>
                    </div>

                    @foreach ($logs as $log)
                        @php
                            $user = $log->user;
                            $isSystem = $user === null;
                            $userName = $isSystem ? 'System' : (trim((string) $user->name) !== '' ? trim((string) $user->name) : 'Unknown user');
                            $userRole = $isSystem ? 'system' : ($user->role ?: 'unknown');
                            $roleLabel = match ($userRole) {
                                'admin' => 'Admin',
                                'supervisor' => 'Supervisor',
                                'staff' => 'Staff',
                                'client' => 'Client',
                                'system' => 'Automated',
                                default => ucfirst((string) $userRole),
                            };
                            $nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
                            $initials = mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 1).mb_substr($nameParts[1] ?? '', 0, 1));
                            $label = $eventLabel($log->action);
                        @endphp
                        <div class="timeline-item" role="listitem"
                             data-event="{{ $label }}"
                             data-search="{{ \Illuminate\Support\Str::lower($userName.' '.$label.' '.$log->description) }}">
                            <div class="timeline-top">
                                <span class="timeline-action">{{ $label }}</span>
                                <time class="timeline-when" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->diffForHumans() }}">{{ $log->created_at->format('M j, Y · g:i A') }}</time>
                            </div>
                            <div class="timeline-user">
                                <span class="timeline-user-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                {{ $userName }}
                                <span class="timeline-role timeline-role-{{ $userRole }}">{{ $roleLabel }}</span>
                            </div>
                            <div class="timeline-desc">{{ $log->description }}</div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="empty-state compact timeline-hidden" id="tl-empty">No events match your filter.</div>
        @else
            <div class="empty-state compact">
                No history recorded yet for this weekly target.
                <a href="{{ route('admin.weekly-bookkeeping.show', $bookkeeping) }}">Back to the weekly target</a>.
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var eventSelect = document.getElementById('tl-event');
    var searchInput = document.getElementById('tl-search');
    var countNote = document.getElementById('tl-count');
    var emptyNote = document.getElementById('tl-empty');
    var timeline = document.querySelector('.timeline');

    // The filter only exists on a long trail; nothing to do otherwise.
    if (!timeline || !eventSelect || !searchInput) {
        return;
    }

    var items = timeline.querySelectorAll('.timeline-item');

    function applyFilter() {
        var eventValue = eventSelect.value;
        var query = searchInput.value.trim().toLowerCase();
        var shown = 0;

        items.forEach(function (item) {
            var matches = (eventValue === '' || item.getAttribute('data-event') === eventValue)
                && (query === '' || item.getAttribute('data-search').indexOf(query) !== -1);

            item.classList.toggle('timeline-hidden', !matches);
            if (matches) {
                shown++;
            }
        });

        // A day heading is hidden when none of its events are showing.
        timeline.querySelectorAll('.timeline-day-title').forEach(function (heading) {
            var next = heading.nextElementSibling;
            var any = false;

            while (next && !next.classList.contains('timeline-day-title')) {
                if (!next.classList.contains('timeline-hidden')) {
                    any = true;
                    break;
                }
                next = next.nextElementSibling;
            }

            heading.classList.toggle('timeline-hidden', !any);
        });

        var filtering = eventValue !== '' || query !== '';
        countNote.textContent = filtering ? shown + ' of ' + items.length + ' events' : '';

        if (emptyNote) {
            emptyNote.classList.toggle('timeline-hidden', shown !== 0);
        }
    }

    eventSelect.addEventListener('change', applyFilter);
    searchInput.addEventListener('input', applyFilter);
});
</script>
@endpush

@push('styles')
<style>
.timeline-role {
    display: inline-block;
    margin-left: 8px;
    padding: 1px 6px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-radius: 9999px;
    background: var(--muted-bg, #f3f4f6);
    color: var(--muted, #6b7280);
}
.timeline-role-admin { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); }
.timeline-role-supervisor { background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00); }
.timeline-role-staff { background: var(--info-bg, #dbeafe); color: var(--info, #3b82f6); }
.timeline-role-client { background: var(--success-bg, #d1fae5); color: var(--success, #10b981); }
/* Actions with no signed-in person, such as the scheduled weekly copy. */
.timeline-role-system { background: var(--muted-bg, #f3f4f6); color: var(--muted, #6b7280); }

.timeline-summary { margin: 0 0 12px; font-size: 13px; color: var(--muted, #6b7280); }

.timeline-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin: 0 0 14px; }
.timeline-filter { display: flex; flex-direction: column; gap: 4px; font-size: 12px; min-width: 180px; }
.timeline-filter span { color: var(--muted, #6b7280); text-transform: uppercase; letter-spacing: .03em; }
.timeline-filter-grow { flex: 1 1 220px; }
.timeline-filter select, .timeline-filter input[type="search"] {
    height: 34px; box-sizing: border-box; padding: 0 10px; font: inherit; font-size: 14px; color: inherit;
    background-color: transparent; border: 1px solid rgba(128,128,128,.4); border-radius: 6px;
}
.timeline-filter select:focus, .timeline-filter input[type="search"]:focus {
    outline: none; border-color: var(--primary, #6366f1); box-shadow: 0 0 0 3px rgba(99, 102, 241, .18);
}
.timeline-filter-count { font-size: 13px; color: var(--muted, #6b7280); }

/* One heading per day, so a long trail is easy to scan. */
.timeline-day-title {
    margin: 16px 0 6px;
    font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: var(--muted, #6b7280);
}
.timeline-day-title:first-child { margin-top: 0; }
.timeline-day-count { margin-left: 6px; font-weight: 600; letter-spacing: 0; }

.timeline-desc { white-space: pre-line; overflow-wrap: anywhere; }
.timeline-hidden { display: none !important; }
</style>
@endpush