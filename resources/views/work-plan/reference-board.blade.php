@extends('layouts.dashboard')

@section('title', 'Activity Legend Reference Board')

@section('content')
@php
    use Illuminate\Support\Str;

    $totalCodes = $groups->sum(fn ($legends) => $legends->count());
@endphp

<div class="wlb">
    {{-- ===== Header ===== --}}
    <header class="wlb-head">
        <div class="wlb-head-main">
            <span class="wlb-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </span>
            <div>
                <h1>Activity Legend Reference Board</h1>
                <p>Use these codes when planning work and recording daily accomplishments.</p>
            </div>
        </div>
        <div class="wlb-head-actions">
            <button type="button" class="wlb-btn wlb-btn-ghost" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print
            </button>
            <a href="{{ route('daily-journal.create') }}" class="wlb-btn wlb-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
                Daily Accomplishment
            </a>
        </div>
    </header>

    <p class="wlb-print-title">Activity Legend Reference Board</p>

    @if ($groups->isEmpty())
        <div class="wlb-empty">
            <span class="wlb-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </span>
            <h3>No activity legends yet</h3>
            <p>No active activity legends are configured yet.</p>
        </div>
    @else
        {{-- ===== Toolbar: search + jump to category ===== --}}
        <div class="wlb-toolbar">
            <label class="wlb-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span class="visually-hidden">Search codes</span>
                <input type="search" placeholder="Search a code, task or instruction" data-board-search autocomplete="off">
            </label>

            @if ($groups->count() > 1)
                <nav class="wlb-jump" aria-label="Jump to category">
                    @foreach ($groups as $category => $legends)
                        <a href="#cat-{{ Str::slug($category) }}">{{ $category }} <small>{{ $legends->count() }}</small></a>
                    @endforeach
                </nav>
            @endif
        </div>

        <p class="wlb-count" aria-live="polite" data-board-count>
            {{ $totalCodes }} {{ Str::plural('code', $totalCodes) }} in {{ $groups->count() }} {{ Str::plural('category', $groups->count()) }}
        </p>

        {{-- ===== Groups ===== --}}
        @foreach ($groups as $category => $legends)
            <section class="wlb-group" id="cat-{{ Str::slug($category) }}" data-group>
                <h2 class="wlb-group-title">
                    <span>{{ $category }}</span>
                    <small>{{ $legends->count() }} {{ Str::plural('code', $legends->count()) }}</small>
                </h2>

                <div class="wlb-grid">
                    @foreach ($legends as $legend)
                        <article class="wlb-card" data-card data-search="{{ Str::lower($legend->code.' '.$legend->name.' '.$legend->usage_instructions) }}">
                            <span class="wlb-code">{{ $legend->code }}</span>
                            <div class="wlb-card-body">
                                <h3>{{ $legend->name }}</h3>
                                @if ($legend->usage_instructions)
                                    <p>{{ $legend->usage_instructions }}</p>
                                @else
                                    <p class="is-muted">No usage instructions.</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="wlb-empty" data-no-match hidden>
            <span class="wlb-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <h3>No codes match</h3>
            <p>Try a different code, task name, or keyword.</p>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.wlb {
    --wlb-ink: #1b2447;
    --wlb-muted: #5b6585;
    --wlb-line: #e3e7f3;
    --wlb-surface: #ffffff;
    --wlb-sunken: #f7f8ff;
    --wlb-tint: #eef0fc;
    --wlb-brand: #4338ca;
    --wlb-brand-dark: #3730a3;
    color: var(--wlb-ink);
}
.wlb h1, .wlb h2, .wlb h3, .wlb p { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.wlb-print-title { display: none; }

/* Header */
.wlb-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.wlb-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.wlb-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--wlb-tint); color: var(--wlb-brand); }
.wlb-head-icon svg { width: 30px; height: 30px; }
.wlb-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.wlb-head p { margin-top: 2px; color: var(--wlb-muted); font-size: .95rem; }
.wlb-head-actions { display: flex; flex-wrap: wrap; gap: 8px; }

/* Buttons */
.wlb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.wlb-btn svg { width: 16px; height: 16px; flex: none; }
.wlb-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--wlb-brand-dark)); color: #fff; }
.wlb-btn-primary:hover { background: var(--wlb-brand-dark); color: #fff; }
.wlb-btn-ghost { background: var(--wlb-surface); border-color: var(--wlb-line); color: var(--wlb-ink); }
.wlb-btn-ghost:hover { background: var(--wlb-tint); }
.wlb-btn:focus-visible, .wlb-search input:focus-visible, .wlb-jump a:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* Toolbar */
.wlb-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; margin-bottom: 8px; }
.wlb-search { position: relative; flex: 1 1 280px; display: block; margin: 0; max-width: 420px; }
.wlb-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.wlb-search input { width: 100%; min-height: 42px; padding: 8px 12px 8px 36px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; font: inherit; font-size: .9rem; color: var(--wlb-ink); box-sizing: border-box; }
.wlb-jump { display: flex; flex-wrap: wrap; gap: 6px; }
.wlb-jump a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: 1px solid var(--wlb-line); border-radius: 999px; background: var(--wlb-surface); color: var(--wlb-ink); font-size: .82rem; font-weight: 700; text-decoration: none; }
.wlb-jump a:hover { background: var(--wlb-tint); border-color: #c9cff0; }
.wlb-jump small { padding: 0 7px; border-radius: 999px; background: var(--wlb-tint); color: var(--wlb-brand); font-size: .72rem; font-weight: 700; }
.wlb-count { margin: 4px 2px 16px; color: var(--wlb-muted); font-size: .85rem; }

/* Groups and cards */
.wlb-group { margin-bottom: 24px; scroll-margin-top: 16px; }
.wlb-group[hidden] { display: none; }
.wlb-group-title { display: flex; align-items: baseline; gap: 10px; margin-bottom: 12px; font-size: 1.05rem; font-weight: 800; }
.wlb-group-title small { color: var(--wlb-muted); font-size: .8rem; font-weight: 600; }
.wlb-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.wlb-card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; background: var(--wlb-surface); border: 1px solid var(--wlb-line); border-radius: 16px; }
.wlb-card[hidden] { display: none; }
.wlb-code { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; min-width: 48px; height: 48px; padding: 0 12px; border-radius: 14px; background: #e4e6fb; color: var(--wlb-brand); font-size: 1.15rem; font-weight: 800; box-sizing: border-box; }
.wlb-card-body { min-width: 0; }
.wlb-card h3 { font-size: .98rem; font-weight: 800; line-height: 1.3; overflow-wrap: anywhere; }
.wlb-card p { margin-top: 4px; color: var(--wlb-muted); font-size: .85rem; line-height: 1.55; overflow-wrap: anywhere; }
.wlb-card p.is-muted { font-style: italic; opacity: .7; }

/* Empty */
.wlb-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 36px 20px; text-align: center; border: 1px dashed #c9cff0; border-radius: 16px; background: var(--wlb-sunken); }
.wlb-empty[hidden] { display: none; }
.wlb-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--wlb-muted); }
.wlb-empty-icon svg { width: 24px; height: 24px; }
.wlb-empty h3 { font-size: 1rem; font-weight: 800; }
.wlb-empty p { color: var(--wlb-muted); font-size: .88rem; }

/* Responsive */
@media (max-width: 1200px) { .wlb-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) {
    .wlb-grid { grid-template-columns: minmax(0, 1fr); }
    .wlb-head h1 { font-size: 1.3rem; }
    .wlb-search { max-width: none; }
}

/* Print: a clean two-column sheet with no controls */
@media print {
    .wlb-head-actions, .wlb-toolbar, .wlb-count, .wlb-head-icon { display: none !important; }
    .wlb-head { margin-bottom: 8px; }
    .wlb-head h1 { font-size: 20pt; }
    .wlb-head p { font-size: 10pt; }
    .wlb-print-title { display: none; }
    .wlb-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .wlb-group { margin-bottom: 14px; break-inside: auto; }
    .wlb-group-title { break-after: avoid; font-size: 13pt; }
    .wlb-card { break-inside: avoid; padding: 10px; border-color: #bbb; border-radius: 8px; }
    .wlb-code { height: 36px; min-width: 38px; font-size: 12pt; background: #eee !important; color: #000; border: 1px solid #999; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .wlb-card h3 { font-size: 10.5pt; }
    .wlb-card p { font-size: 9pt; color: #333; }
    .wlb-card[hidden], .wlb-group[hidden] { display: none !important; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var search = document.querySelector('[data-board-search]');
    if (! search) { return; }

    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-card]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-group]'));
    var noMatch = document.querySelector('[data-no-match]');
    var count = document.querySelector('[data-board-count]');
    var originalCount = count ? count.textContent : '';

    search.addEventListener('input', function () {
        var term = search.value.trim().toLowerCase();
        var shown = 0;

        cards.forEach(function (card) {
            var match = term === '' || card.getAttribute('data-search').indexOf(term) !== -1;
            card.hidden = ! match;
            if (match) { shown++; }
        });

        groups.forEach(function (group) {
            group.hidden = ! group.querySelector('[data-card]:not([hidden])');
        });

        if (noMatch) { noMatch.hidden = shown !== 0; }
        if (count) {
            count.textContent = term === ''
                ? originalCount
                : 'Showing ' + shown + ' of ' + cards.length + ' codes';
        }
    });
})();
</script>
@endpush