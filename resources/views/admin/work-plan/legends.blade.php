@extends('layouts.dashboard')

@section('title', 'Activity Legends')

@section('content')
@php
    use Illuminate\Support\Str;

    $totalCount    = $legends->count();
    $activeCount   = $legends->where('is_active', true)->count();
    $inactiveCount = $totalCount - $activeCount;
    $categoryCount = $legends->pluck('category')->filter()->unique()->count();

    // Group by category so the list reads like a reference sheet.
    $grouped = $legends->groupBy(fn ($legend) => trim((string) $legend->category) !== '' ? $legend->category : 'Uncategorized');

    // Keep the add panel open when the last submit failed or the form was refilled.
    $openAdd = $errors->any() || old('code') || old('name');
@endphp

<div class="wl">
    {{-- ===== Header ===== --}}
    <header class="wl-head">
        <div class="wl-head-main">
            <span class="wl-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </span>
            <div>
                <h1>Activity Legends</h1>
                <p>Maintain the A&ndash;T and T1&ndash;T8 task codes used by the weekly work plan.</p>
            </div>
        </div>
        <div class="wl-head-actions">
            <a href="{{ route('work-plan.reference-board') }}" target="_blank" rel="noopener" class="wl-btn wl-btn-ghost">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Reference Board
            </a>
            <a href="{{ route('admin.daily-work-plan.index') }}" class="wl-btn wl-btn-ghost">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Work Plan
            </a>
        </div>
    </header>

    {{-- ===== Flash + errors ===== --}}
    @if (session('status'))
        <div class="wl-alert wl-alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="wl-alert wl-alert-danger" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Summary ===== --}}
    <section class="wl-stats" aria-label="Legend summary">
        <div class="wl-stat">
            <span class="wl-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </span>
            <div><span class="wl-stat-value">{{ $totalCount }}</span><span class="wl-stat-label">{{ $selectedCategory ? 'In this category' : 'Total codes' }}</span></div>
        </div>
        <div class="wl-stat wl-stat--ok">
            <span class="wl-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <div><span class="wl-stat-value">{{ $activeCount }}</span><span class="wl-stat-label">Active</span></div>
        </div>
        <div class="wl-stat wl-stat--muted">
            <span class="wl-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            </span>
            <div><span class="wl-stat-value">{{ $inactiveCount }}</span><span class="wl-stat-label">Inactive</span></div>
        </div>
        <div class="wl-stat">
            <span class="wl-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </span>
            <div><span class="wl-stat-value">{{ $categories->count() }}</span><span class="wl-stat-label">Categories</span></div>
        </div>
    </section>

    {{-- ===== Add activity code (collapsed until needed) ===== --}}
    <details class="wl-card wl-add" @if ($openAdd) open @endif>
        <summary class="wl-card-head">
            <span class="wl-tile" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
            <div class="wl-card-head-text">
                <h2>Add activity code</h2>
                <p>Create a new task code for the weekly work plan.</p>
            </div>
            <span class="wl-chevron" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
        </summary>

        <form method="POST" action="{{ route('admin.work-plan-legends.store') }}" class="wl-form">
            @csrf
            <div class="wl-form-grid">
                <div class="wl-field wl-col-2">
                    <label for="new_code">Code <span class="wl-req">*</span></label>
                    <input type="text" id="new_code" name="code" value="{{ old('code') }}" maxlength="10" placeholder="e.g. A or T9" required>
                </div>
                <div class="wl-field wl-col-6">
                    <label for="new_name">Name <span class="wl-req">*</span></label>
                    <input type="text" id="new_name" name="name" value="{{ old('name') }}" maxlength="255" required>
                </div>
                <div class="wl-field wl-col-3">
                    <label for="new_category">Category</label>
                    <input type="text" id="new_category" name="category" value="{{ old('category') }}" maxlength="50" list="legend-categories" placeholder="Pick or type">
                    <datalist id="legend-categories">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="wl-field wl-col-1">
                    <label for="new_order">Order</label>
                    <input type="number" id="new_order" name="sort_order" value="{{ old('sort_order') }}" min="0" max="9999">
                </div>
                <div class="wl-field wl-col-12">
                    <label for="new_usage">Usage instructions</label>
                    <textarea id="new_usage" name="usage_instructions" rows="2" maxlength="1000" placeholder="When should staff use this code?">{{ old('usage_instructions') }}</textarea>
                </div>
            </div>
            <div class="wl-form-foot">
                <label class="wl-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="new_legend_active" checked>
                    <span class="wl-switch-track" aria-hidden="true"></span>
                    <span>Active</span>
                </label>
                <button type="submit" class="wl-btn wl-btn-primary">Add code</button>
            </div>
        </form>
    </details>

    {{-- ===== Toolbar ===== --}}
    <div class="wl-toolbar">
        <label class="wl-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span class="visually-hidden">Search codes</span>
            <input type="search" placeholder="Search code, name or instructions" data-legend-search autocomplete="off">
        </label>

        <div class="wl-pills" role="group" aria-label="Filter by status">
            <button type="button" class="is-active" data-status-filter="all">All</button>
            <button type="button" data-status-filter="active">Active</button>
            <button type="button" data-status-filter="inactive">Inactive</button>
        </div>

        <form method="GET" action="{{ route('admin.work-plan-legends.index') }}" class="wl-category-form" data-auto-submit>
            <label for="category" class="visually-hidden">Category</label>
            <select name="category" id="category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <button type="submit" class="wl-btn wl-btn-ghost wl-btn-sm" data-apply>Apply</button>
        </form>
    </div>

    <p class="wl-count" aria-live="polite" data-legend-count>
        {{ $totalCount }} {{ Str::plural('code', $totalCount) }}@if ($selectedCategory) in {{ $selectedCategory }}@endif
    </p>

    {{-- ===== Codes, grouped by category ===== --}}
    @forelse ($grouped as $groupName => $items)
        <section class="wl-group" data-group>
            <h2 class="wl-group-title">
                <span>{{ $groupName }}</span>
                <small>{{ $items->count() }}</small>
            </h2>

            <div class="wl-list">
                @foreach ($items as $legend)
                    @php
                        $uses = $taskCounts[$legend->code] ?? 0;
                        $confirm = $uses > 0
                            ? "Delete code {$legend->code}? {$uses} task(s) currently use it."
                            : "Delete code {$legend->code}?";
                    @endphp
                    <details class="wl-item {{ $legend->is_active ? '' : 'is-inactive' }}"
                             data-item
                             data-active="{{ $legend->is_active ? 1 : 0 }}"
                             data-search="{{ Str::lower($legend->code.' '.$legend->name.' '.$legend->category.' '.$legend->usage_instructions) }}">
                        <summary class="wl-row">
                            <span class="wl-code">{{ $legend->code }}</span>
                            <span class="wl-row-main">
                                <strong>{{ $legend->name }}</strong>
                                @if ($legend->usage_instructions)
                                    <small>{{ Str::limit($legend->usage_instructions, 110) }}</small>
                                @endif
                            </span>
                            <span class="wl-row-meta">
                                <span class="wl-pill {{ $legend->is_active ? 'wl-pill--ok' : 'wl-pill--muted' }}">{{ $legend->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="wl-uses" title="Work plan tasks using this code">{{ $uses }} {{ Str::plural('task', $uses) }}</span>
                                <span class="wl-order" title="Sort order">#{{ $legend->sort_order ?? '–' }}</span>
                            </span>
                            <span class="wl-edit-hint">
                                Edit
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </summary>

                        <div class="wl-panel">
                            <form id="legend-form-{{ $legend->id }}" method="POST" action="{{ route('admin.work-plan-legends.update', $legend) }}" class="wl-form-grid">
                                @csrf
                                @method('PUT')
                                <div class="wl-field wl-col-2">
                                    <label for="code_{{ $legend->id }}">Code</label>
                                    <input type="text" id="code_{{ $legend->id }}" name="code" value="{{ $legend->code }}" maxlength="10" required>
                                </div>
                                <div class="wl-field wl-col-6">
                                    <label for="name_{{ $legend->id }}">Name</label>
                                    <input type="text" id="name_{{ $legend->id }}" name="name" value="{{ $legend->name }}" maxlength="255" required>
                                </div>
                                <div class="wl-field wl-col-3">
                                    <label for="category_{{ $legend->id }}">Category</label>
                                    <input type="text" id="category_{{ $legend->id }}" name="category" value="{{ $legend->category }}" maxlength="50" list="legend-categories">
                                </div>
                                <div class="wl-field wl-col-1">
                                    <label for="order_{{ $legend->id }}">Order</label>
                                    <input type="number" id="order_{{ $legend->id }}" name="sort_order" value="{{ $legend->sort_order }}" min="0" max="9999">
                                </div>
                                <div class="wl-field wl-col-12">
                                    <label for="usage_{{ $legend->id }}">Usage instructions</label>
                                    <textarea id="usage_{{ $legend->id }}" name="usage_instructions" rows="2" maxlength="1000">{{ $legend->usage_instructions }}</textarea>
                                </div>
                                <div class="wl-col-12 wl-switch-row">
                                    <label class="wl-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="active_{{ $legend->id }}" @checked($legend->is_active)>
                                        <span class="wl-switch-track" aria-hidden="true"></span>
                                        <span>Active</span>
                                    </label>
                                </div>
                            </form>

                            <div class="wl-panel-foot">
                                <button type="submit" form="legend-form-{{ $legend->id }}" class="wl-btn wl-btn-primary wl-btn-sm">Save changes</button>

                                <form method="POST" action="{{ route('admin.work-plan-legends.destroy', $legend) }}" data-confirm="{{ $confirm }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="wl-btn wl-btn-danger wl-btn-sm">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wl-empty">
            <span class="wl-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </span>
            <h3>No activity legends found</h3>
            <p>Add one above or run the legend seeder.</p>
        </div>
    @endforelse

    {{-- Shown when search or the status filter hides everything --}}
    <div class="wl-empty" data-no-match hidden>
        <span class="wl-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <h3>No codes match</h3>
        <p>Try a different search or clear the status filter.</p>
    </div>
</div>
@endsection

@push('styles')
<style>
.wl {
    --wl-ink: #1b2447;
    --wl-muted: #5b6585;
    --wl-line: #e3e7f3;
    --wl-surface: #ffffff;
    --wl-sunken: #f7f8ff;
    --wl-tint: #eef0fc;
    --wl-brand: #4338ca;
    --wl-brand-dark: #3730a3;
    color: var(--wl-ink);
}
.wl h1, .wl h2, .wl h3, .wl p, .wl ul { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* Header */
.wl-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.wl-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.wl-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--wl-tint); color: var(--wl-brand); }
.wl-head-icon svg { width: 30px; height: 30px; }
.wl-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.wl-head p { margin-top: 2px; color: var(--wl-muted); font-size: .95rem; }
.wl-head-actions { display: flex; flex-wrap: wrap; gap: 8px; }

/* Buttons */
.wl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.wl-btn svg { width: 16px; height: 16px; flex: none; }
.wl-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--wl-brand-dark)); color: #fff; }
.wl-btn-primary:hover { background: var(--wl-brand-dark); }
.wl-btn-ghost { background: var(--wl-surface); border-color: var(--wl-line); color: var(--wl-ink); }
.wl-btn-ghost:hover { background: var(--wl-tint); }
.wl-btn-danger { background: #fff; border-color: #f1b5ae; color: #a93226; }
.wl-btn-danger:hover { background: #fdecea; }
.wl-btn-sm { padding: 8px 14px; font-size: .82rem; }
.wl-btn:focus-visible, .wl-field input:focus-visible, .wl-field textarea:focus-visible, .wl-category-form select:focus-visible, .wl-search input:focus-visible, .wl-pills button:focus-visible, .wl-card-head:focus-visible, .wl-row:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* Alerts */
.wl-alert { margin-bottom: 16px; padding: 12px 16px; border: 1px solid; border-radius: 12px; font-size: .9rem; }
.wl-alert-success { background: #e7f6ee; border-color: #b6e3cb; color: #0b5f37; }
.wl-alert-danger { background: #fdecea; border-color: #f1b5ae; color: #a5281a; }
.wl-alert ul { margin-top: 6px; padding-left: 18px; }

/* Stats */
.wl-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
.wl-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: var(--wl-surface); border: 1px solid var(--wl-line); border-radius: 18px; }
.wl-stat-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: var(--wl-tint); color: var(--wl-brand); }
.wl-stat-icon svg { width: 22px; height: 22px; }
.wl-stat--ok .wl-stat-icon { background: #e7f6ee; color: #0f9d58; }
.wl-stat--muted .wl-stat-icon { background: #eef0f6; color: #64748b; }
.wl-stat div { display: flex; flex-direction: column; }
.wl-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.wl-stat-label { margin-top: 4px; color: var(--wl-muted); font-size: .82rem; font-weight: 700; }

/* Add card */
.wl-card { background: var(--wl-surface); border: 1px solid var(--wl-line); border-radius: 18px; margin-bottom: 20px; overflow: hidden; }
.wl-card > summary { list-style: none; cursor: pointer; }
.wl-card > summary::-webkit-details-marker { display: none; }
.wl-card-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; }
.wl-card[open] > .wl-card-head { border-bottom: 1px solid var(--wl-line); }
.wl-card-head-text { flex: 1; min-width: 0; }
.wl-card-head h2 { font-size: 1.05rem; font-weight: 800; }
.wl-card-head p { margin-top: 2px; color: var(--wl-muted); font-size: .85rem; }
.wl-tile { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--wl-tint); color: var(--wl-brand); }
.wl-tile svg { width: 22px; height: 22px; }
.wl-chevron { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center; color: var(--wl-muted); transition: transform .2s; }
.wl-chevron svg { width: 18px; height: 18px; }
.wl-card[open] .wl-chevron { transform: rotate(180deg); }
.wl-form { padding: 18px 20px 20px; }
.wl-form-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--wl-line); }

/* Form grid */
.wl-form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 14px; }
.wl-col-1 { grid-column: span 1; } .wl-col-2 { grid-column: span 2; } .wl-col-3 { grid-column: span 3; }
.wl-col-6 { grid-column: span 6; } .wl-col-12 { grid-column: 1 / -1; }
.wl-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.wl-field label { font-size: .82rem; font-weight: 700; }
.wl-req { color: #dc2626; }
.wl-field input, .wl-field textarea { width: 100%; padding: 9px 12px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; color: var(--wl-ink); font: inherit; font-size: .9rem; box-sizing: border-box; }
.wl-field textarea { resize: vertical; line-height: 1.5; }

/* Switch */
.wl-switch { position: relative; display: inline-flex; align-items: center; gap: 10px; font-size: .88rem; font-weight: 700; cursor: pointer; }
.wl-switch input[type="checkbox"] { position: absolute; opacity: 0; width: 1px; height: 1px; }
.wl-switch-track { position: relative; width: 40px; height: 22px; border-radius: 999px; background: #cdd3ea; transition: background .15s; }
.wl-switch-track::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.25); transition: transform .15s; }
.wl-switch input:checked ~ .wl-switch-track { background: #0f9d58; }
.wl-switch input:checked ~ .wl-switch-track::after { transform: translateX(18px); }
.wl-switch input:focus-visible ~ .wl-switch-track { outline: 3px solid #a5b4fc; outline-offset: 2px; }
.wl-switch-row { padding-top: 2px; }

/* Toolbar */
.wl-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-bottom: 8px; }
.wl-search { position: relative; flex: 1 1 260px; display: block; margin: 0; }
.wl-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.wl-search input { width: 100%; min-height: 42px; padding: 8px 12px 8px 36px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; font: inherit; font-size: .9rem; color: var(--wl-ink); box-sizing: border-box; }
.wl-pills { display: inline-flex; padding: 3px; border: 1px solid var(--wl-line); border-radius: 999px; background: #f1f2fb; }
.wl-pills button { padding: 7px 14px; border: 0; border-radius: 999px; background: transparent; color: var(--wl-muted); font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.wl-pills button.is-active { background: #fff; color: var(--wl-brand); box-shadow: 0 1px 2px rgba(15,23,42,.12); }
.wl-category-form { display: flex; gap: 8px; margin: 0; }
.wl-category-form select { min-height: 42px; padding: 8px 12px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; color: var(--wl-ink); font: inherit; font-size: .9rem; }
.wl-category-form [data-apply][hidden] { display: none; }
.wl-count { margin: 4px 2px 14px; color: var(--wl-muted); font-size: .85rem; }

/* Groups and rows */
.wl-group { margin-bottom: 20px; }
.wl-group[hidden] { display: none; }
.wl-group-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--wl-muted); }
.wl-group-title small { padding: 1px 8px; border-radius: 999px; background: var(--wl-tint); color: var(--wl-brand); font-size: .72rem; letter-spacing: 0; }
.wl-list { display: flex; flex-direction: column; gap: 8px; }
.wl-item { background: var(--wl-surface); border: 1px solid var(--wl-line); border-radius: 14px; overflow: hidden; }
.wl-item[hidden] { display: none; }
.wl-item[open] { border-color: #b8bfee; }
.wl-item.is-inactive .wl-row { opacity: .7; }
.wl-item > summary { list-style: none; cursor: pointer; }
.wl-item > summary::-webkit-details-marker { display: none; }
.wl-row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: 14px; padding: 12px 16px; }
.wl-row:hover { background: var(--wl-sunken); }
.wl-code { display: inline-flex; align-items: center; justify-content: center; min-width: 46px; padding: 5px 12px; border-radius: 999px; background: #e4e6fb; color: var(--wl-brand); font-weight: 800; }
.wl-row-main { display: flex; flex-direction: column; min-width: 0; line-height: 1.35; }
.wl-row-main strong { font-size: .95rem; }
.wl-row-main small { color: var(--wl-muted); font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wl-row-meta { display: flex; align-items: center; gap: 12px; }
.wl-pill { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: .74rem; font-weight: 700; }
.wl-pill--ok { background: #e7f6ee; color: #0b7a45; }
.wl-pill--muted { background: #eef0f6; color: #64748b; }
.wl-uses, .wl-order { color: var(--wl-muted); font-size: .8rem; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
.wl-edit-hint { display: inline-flex; align-items: center; gap: 4px; color: var(--wl-brand); font-size: .82rem; font-weight: 700; }
.wl-edit-hint svg { width: 16px; height: 16px; transition: transform .2s; }
.wl-item[open] .wl-edit-hint svg { transform: rotate(180deg); }
.wl-panel { padding: 16px; border-top: 1px solid var(--wl-line); background: var(--wl-sunken); }
.wl-panel-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--wl-line); }
.wl-panel-foot form { margin: 0; }

/* Empty state */
.wl-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 36px 20px; text-align: center; border: 1px dashed #c9cff0; border-radius: 16px; background: var(--wl-sunken); }
.wl-empty[hidden] { display: none; }
.wl-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--wl-muted); }
.wl-empty-icon svg { width: 24px; height: 24px; }
.wl-empty h3 { font-size: 1rem; font-weight: 800; }
.wl-empty p { color: var(--wl-muted); font-size: .88rem; }

/* Responsive */
@media (max-width: 1000px) {
    .wl-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .wl-col-2 { grid-column: span 4; } .wl-col-6 { grid-column: span 8; }
    .wl-col-3 { grid-column: span 8; } .wl-col-1 { grid-column: span 4; }
}
@media (max-width: 760px) {
    .wl-row { grid-template-columns: auto minmax(0, 1fr) auto; }
    .wl-row-meta { grid-column: 2 / -1; grid-row: 2; flex-wrap: wrap; gap: 8px 12px; }
}
@media (max-width: 560px) {
    .wl-head h1 { font-size: 1.3rem; }
    .wl-form-grid > * { grid-column: 1 / -1 !important; }
    .wl-stats { gap: 10px; }
    .wl-pills { width: 100%; }
    .wl-pills button { flex: 1; }
    .wl-category-form { width: 100%; }
    .wl-category-form select { flex: 1; }
    .wl-edit-hint { font-size: 0; gap: 0; }
    .wl-panel-foot, .wl-form-foot { flex-wrap: wrap; }
}
@media (prefers-reduced-motion: reduce) {
    .wl-chevron, .wl-edit-hint svg, .wl-switch-track, .wl-switch-track::after { transition: none; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    /* Category filter applies as soon as it changes; the Apply button is the no-JS fallback. */
    var catForm = document.querySelector('form[data-auto-submit]');
    if (catForm) {
        var apply = catForm.querySelector('[data-apply]');
        if (apply) { apply.hidden = true; }
        var select = catForm.querySelector('select');
        if (select) { select.addEventListener('change', function () { catForm.submit(); }); }
    }

    /* Confirm before deleting */
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
        form.addEventListener('submit', function (event) {
            if (! window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
        });
    });

    /* Search + status filter over the rows already on the page */
    var search = document.querySelector('[data-legend-search]');
    var pills = Array.prototype.slice.call(document.querySelectorAll('[data-status-filter]'));
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-item]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-group]'));
    var noMatch = document.querySelector('[data-no-match]');
    var count = document.querySelector('[data-legend-count]');
    var originalCount = count ? count.textContent : '';
    var status = 'all';

    function apply() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var shown = 0;

        items.forEach(function (item) {
            var matchesText = term === '' || item.getAttribute('data-search').indexOf(term) !== -1;
            var isActive = item.getAttribute('data-active') === '1';
            var matchesStatus = status === 'all' || (status === 'active' ? isActive : ! isActive);
            var visible = matchesText && matchesStatus;
            item.hidden = ! visible;
            if (visible) { shown++; }
        });

        groups.forEach(function (group) {
            var any = group.querySelector('[data-item]:not([hidden])');
            group.hidden = ! any;
        });

        if (noMatch) { noMatch.hidden = shown !== 0 || items.length === 0; }
        if (count) {
            count.textContent = (term === '' && status === 'all')
                ? originalCount
                : 'Showing ' + shown + ' of ' + items.length + ' codes';
        }
    }

    if (search) { search.addEventListener('input', apply); }
    pills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            status = pill.getAttribute('data-status-filter');
            pills.forEach(function (p) { p.classList.toggle('is-active', p === pill); });
            apply();
        });
    });
})();
</script>
@endpush