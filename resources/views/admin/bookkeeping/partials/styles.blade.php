{{-- Shared styling for the monthly / quarterly bookkeeping screens.

     The same declarations the weekly tracker uses, kept here so both share one
     source of truth. The selectors carry the `wk-` prefix because the markup in
     admin/bookkeeping/index.blade.php uses those names, exactly as the weekly
     tracker does; only the period data (month, quarter) differs between them. --}}
<style>
    /* ---------- Header + period navigator ---------- */
    .wk-head { margin-bottom: 14px; }
    .wk-head-main h1 { text-transform: uppercase; letter-spacing: .01em; }
    .wk-head-main p { font-size: var(--text-md); }

    .wk-weekbar {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 10px 12px; margin-bottom: 14px;
        background: var(--surface); border: 1px solid var(--border-subtle);
        border-radius: var(--radius-sm);
    }
    .wk-weeknav {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; flex: 0 0 auto;
        color: var(--navy); background: var(--surface-sunken);
        border: 1px solid var(--border-subtle); border-radius: 9px; text-decoration: none;
    }
    .wk-weeknav:hover { background: var(--sky-soft); border-color: #BFDBFE; }
    .wk-weeklabel { display: flex; flex-direction: column; line-height: 1.25; padding: 0 4px; }
    .wk-weeklabel b { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); white-space: nowrap; }
    .wk-weeklabel span { font-size: var(--text-xs); color: var(--muted-text); }
    .wk-week-today {
        padding: 5px 10px; font-size: var(--text-xs); font-weight: 700;
        color: var(--navy); background: var(--sky-soft);
        border: 1px solid #BFDBFE; border-radius: 999px; text-decoration: none;
    }
    .wk-weekbar-spacer { flex: 1 1 auto; }

    .wk-quick { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .wk-quick a {
        padding: 5px 10px; font-size: var(--text-xs); font-weight: 600;
        color: var(--muted-text); text-decoration: none; border-radius: 999px;
    }
    .wk-quick a:hover { color: var(--navy); background: var(--sky-light); }

    /* ---------- Collapsible filters ---------- */
    .wk-filters {
        margin-bottom: 16px; background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
    }
    .wk-filters-summary {
        display: flex; align-items: center; gap: 8px; cursor: pointer;
        padding: 10px 14px; font-size: var(--text-sm); font-weight: 700; color: var(--navy);
        list-style: none; min-height: 44px;
    }
    .wk-filters-summary::-webkit-details-marker { display: none; }
    .wk-filters-summary::marker { content: ''; }
    .wk-filters-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
        font-size: 10.5px; font-weight: 800; color: #fff; background: var(--sky-deep);
    }
    .wk-filters-active { margin-left: auto; font-size: var(--text-xs); font-weight: 600; color: var(--sky-deep); }

    .wk-filters-form {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;
        padding: 14px 14px 14px; border-top: 1px solid var(--border-subtle);
    }
    .wk-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .wk-field > span { font-size: var(--text-xs); color: var(--muted-text); font-weight: 600; }
    .wk-field-grow { flex: 1 1 190px; }
    .wk-field select, .wk-field input {
        min-height: 36px; padding: 0 10px; font-size: var(--text-sm);
        color: var(--text); background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: 8px;
    }
    .wk-filters-actions { display: flex; align-items: center; gap: 8px; }

    /* ---------- Section blocks ---------- */
    .wk-section-block { margin-bottom: 20px; }
    .wk-section-title {
        font-family: var(--font-head); font-size: var(--text-sm); font-weight: 700;
        text-transform: uppercase; letter-spacing: .06em; color: var(--navy); margin: 0;
    }
    .wk-section-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; flex-wrap: wrap; margin-bottom: 10px;
    }
    .wk-section-block > .wk-section-title { display: block; margin-bottom: 10px; }
    .wk-section-note { font-size: var(--text-xs); color: var(--muted-text); }

    /* ---------- Summary ---------- */
    .stat-grid.wk-summary { grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 0; }
    .stat-grid.wk-summary .stat-card { padding: 14px; }
    .stat-grid.wk-summary .stat-value { font-size: 28px; }

    /* ---------- Clear / empty state ---------- */
    /* Bootstrap sets .card to flex-direction: column, so the direction is stated
       here rather than inherited. The icon chip is pinned to one size on every
       axis, and :not() keeps the text wrapper rule below off it. */
    .wk-clear {
        display: flex; flex-direction: row; align-items: center;
        gap: 12px; flex-wrap: wrap; padding: 14px 16px;
    }
    .wk-clear-icon {
        display: inline-flex; align-items: center; justify-content: center;
        flex: 0 0 56px; align-self: center;
        width: 56px; height: 56px; min-width: 56px; min-height: 56px;
        border-radius: 50%; color: var(--success); background: var(--success-soft);
    }
    .wk-clear-icon svg { width: 24px; height: 24px; flex: none; }
    .wk-clear > div:not(.wk-clear-icon) { flex: 1 1 200px; min-width: 0; }
    .wk-clear b { display: block; font-size: var(--text-base); color: var(--navy); }
    .wk-clear span { font-size: var(--text-sm); color: var(--muted-text); }

    /* ---------- Today's priorities ---------- */
    .wk-today-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .wk-today-item {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 10px 14px; background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
    }
    .wk-today-main { flex: 1 1 220px; min-width: 0; }
    .wk-today-main b { display: block; font-size: var(--text-base); color: var(--navy); }
    .wk-today-main span { font-size: var(--text-xs); color: var(--muted-text); }
    .wk-today-open { font-size: var(--text-xs); font-weight: 700; color: var(--sky-deep); text-decoration: none; }
    .wk-today-open:hover { text-decoration: underline; }
    .wk-more { margin: 8px 0 0; font-size: var(--text-xs); color: var(--muted-text); }

    /* ---------- Status dots / pills / badges ---------- */
    .wk-dot { width: 9px; height: 9px; flex: 0 0 auto; border-radius: 50%; background: var(--border-subtle); }
    .wk-dot-completed { background: var(--success); }
    .wk-dot-in_progress, .wk-dot-active { background: var(--sky-deep); }
    .wk-dot-attention { background: var(--danger); }
    .wk-dot-pending { background: #EAB308; }

    .wk-pill {
        display: inline-flex; align-items: center; padding: 3px 9px;
        border-radius: 999px; font-size: 10.5px; font-weight: 800;
        letter-spacing: .02em; white-space: nowrap;
    }
    .wk-pill-completed { background: #DCFCE7; color: #166534; }
    .wk-pill-in_progress, .wk-pill-active { background: #DBEAFE; color: #1E40AF; }
    .wk-pill-attention { background: #FEE2E2; color: #991B1B; }
    .wk-pill-pending { background: #FEF3C7; color: #92400E; }

    .wk-badge {
        display: inline-flex; align-items: center; padding: 3px 9px;
        border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap;
    }
    .wk-badge-attention { background: var(--danger-soft); color: #991B1B; }
    .wk-badge-ok { background: var(--success-soft); color: #166534; }

    /* ---------- Client cards ---------- */
    .wk-clients { display: flex; flex-direction: column; gap: 10px; }
    .wk-client {
        background: var(--surface); border: 1px solid var(--border-subtle);
        border-radius: var(--radius-sm); overflow: hidden;
    }
    .wk-client[open] { box-shadow: var(--shadow-sm); }
    .wk-client-head {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 12px 14px; cursor: pointer; list-style: none; min-height: 48px;
    }
    .wk-client-head::-webkit-details-marker { display: none; }
    .wk-client-head::marker { content: ''; }
    .wk-client-caret {
        width: 0; height: 0; flex: 0 0 auto;
        border-left: 6px solid var(--muted-text);
        border-top: 5px solid transparent; border-bottom: 5px solid transparent;
        transition: transform var(--transition-fast);
    }
    .wk-client[open] .wk-client-caret { transform: rotate(90deg); }
    .wk-client-name { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); }
    .wk-client-count { margin-left: auto; font-size: var(--text-xs); color: var(--muted-text); white-space: nowrap; }

    .wk-mini {
        display: block; width: 84px; height: 6px; flex: 0 0 auto;
        background: var(--surface-sunken); border-radius: 999px; overflow: hidden;
    }
    .wk-mini-bar { display: block; height: 100%; background: var(--success); border-radius: 999px; }

    .wk-tasks { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--border-subtle); }
    .wk-task {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 10px 14px; border-bottom: 1px solid var(--border-subtle);
    }
    .wk-task:last-child { border-bottom: 0; }
    .wk-task-attention { background: rgba(231, 76, 60, .035); }
    .wk-task-main { flex: 1 1 240px; min-width: 0; }
    .wk-task-main b { display: block; font-size: var(--text-sm); font-weight: 700; color: var(--text-strong); }
    .wk-task-meta { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: var(--text-xs); color: var(--muted-text); }
    .wk-task-meta .is-overdue { color: #991B1B; font-weight: 700; }
    /* Assigned staff sits in its own column so the role can sit under the name
       instead of running on after it in a single line. */
    .wk-task-staff { flex: 0 1 168px; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
    .wk-task-staff .wk-person-name { font-size: var(--text-xs); }

    .wk-task-actions { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; }
    .wk-task-actions .btn { min-height: 32px; }

    /* ---------- Progress ---------- */
    .wk-progress-card { padding: 16px; }
    .wk-progress-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .wk-progress-head b { font-family: var(--font-head); font-size: 24px; font-weight: 800; color: var(--navy); }
    .wk-progress-head span { margin-left: 6px; font-size: var(--text-sm); color: var(--muted-text); }
    .wk-bar { height: 9px; background: var(--surface-sunken); border-radius: 999px; overflow: hidden; }
    .wk-bar-sm { height: 6px; }
    .wk-bar-fill { display: block; height: 100%; border-radius: 999px; transition: width var(--transition); }
    .wk-bar-fill.is-ok { background: var(--success); }
    .wk-bar-fill.is-warn { background: var(--warning); }

    .wk-types {
        list-style: none; margin: 14px 0 0; padding: 0;
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px;
    }
    .wk-type-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 5px; }
    .wk-type-head span { font-size: var(--text-xs); color: var(--muted-text); }
    .wk-type-head b { font-size: var(--text-xs); color: var(--navy); }

    /* ---------- Detailed report ---------- */
    .wk-report { padding: 0; }
    /* Wide workbook grids scroll inside their own card so the page itself never
       gains a horizontal scrollbar. */
    .wk-report .table-scroll,
    .wk-section-block .table-scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

    .wk-report-summary {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 14px 16px; cursor: pointer; list-style: none; min-height: 52px;
    }
    .wk-report-summary::-webkit-details-marker { display: none; }
    .wk-report-summary::marker { content: ''; }
    .wk-report-toggle {
        margin-left: auto; width: 0; height: 0; flex: 0 0 auto;
        border-top: 6px solid var(--muted-text);
        border-left: 5px solid transparent; border-right: 5px solid transparent;
        transition: transform var(--transition-fast);
    }
    .wk-report[open] .wk-report-toggle { transform: rotate(180deg); }
    .wk-report > *:not(.wk-report-summary) { margin-left: 16px; margin-right: 16px; }
    .wk-report > .table-scroll { margin-left: 0; margin-right: 0; }
    .wk-report-hint { margin-top: 0; padding-top: 4px; font-size: var(--text-xs); color: var(--muted-text); }
    .wk-report .empty-state { padding: 18px 16px; }

    /* ---------- Comparison grid ---------- */
    .wk-grid { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
    .wk-grid th, .wk-grid td { padding: 7px 10px; border-bottom: 1px solid var(--border-subtle); text-align: left; vertical-align: middle; }
    .wk-grid thead th {
        font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .03em;
        color: var(--muted-text); background: var(--surface-raised); white-space: nowrap;
    }
    .wk-row:hover { background: var(--sky-light); }
    .wk-day { display: block; font-weight: 700; }
    .wk-datefull { display: block; font-size: 10.5px; color: var(--muted-text); }
    .wk-task { font-weight: 600; white-space: nowrap; }
    .wk-client { white-space: nowrap; }
    .wk-staff { color: var(--muted-text); white-space: nowrap; }

    .cmp-none { color: #94A3B8; }
    .cmp-cell {
        display: inline-flex; flex-direction: column; gap: 1px; padding: 2px 7px;
        border-radius: 5px; text-decoration: none; line-height: 1.2; min-width: 62px;
    }
    .cmp-cell-text { font-size: var(--text-xs); font-weight: 700; }
    .cmp-cell-sub { font-size: 10px; opacity: .85; }
    .cmp-done   { background: #DCFCE7; color: #166534; }
    .cmp-late   { background: #FEF3C7; color: #92400E; }
    .cmp-active { background: #DBEAFE; color: #1E40AF; }
    .cmp-overdue{ background: #FEE2E2; color: #991B1B; }
    .cmp-todo   { background: var(--surface-raised); color: var(--muted-text); }

    .wk-side { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; padding: 16px 0; }
    .wk-side-card h3 { font-size: var(--text-sm); margin: 0 0 8px; color: var(--navy); }
    .wk-side-empty { font-size: var(--text-xs); color: var(--muted-text); margin: 0; }
    .wk-side-list { list-style: none; margin: 0; padding: 0; }
    .wk-side-list li { border-bottom: 1px solid var(--border-subtle); }
    .wk-side-list a { display: flex; align-items: center; gap: 6px; padding: 6px 0; text-decoration: none; color: inherit; font-size: var(--text-xs); }
    .wk-side-list a b { flex: 0 0 auto; }
    .wk-side-list a span { color: var(--muted-text); }
    .wk-side-status { margin-left: auto; font-style: normal; font-weight: 700; }
    .wk-side-status.is-bad  { color: #991B1B; }
    .wk-side-status.is-warn { color: #92400E; }

    /* ---------- Plan identity strip (period + status + owner) ----------
       Shared by the weekly tracker and the monthly / quarterly workbook so the
       three areas open with the same block. Tone classes only change colour;
       the label text always states the status, never colour alone. */
    .wk-plan {
        display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        padding: 14px 16px; margin-bottom: 14px;
        background: var(--surface); border: 1px solid var(--border-subtle);
        border-radius: var(--radius-sm); box-shadow: var(--shadow-sm);
    }
    .wk-plan-icon {
        display: inline-flex; align-items: center; justify-content: center;
        flex: 0 0 40px; width: 40px; height: 40px;
        border-radius: 11px; background: var(--sky-soft); color: var(--sky-deep);
    }
    .wk-plan-icon svg { width: 20px; height: 20px; flex: none; }
    .wk-plan-main { flex: 1 1 260px; min-width: 0; }
    .wk-plan-main b { display: block; font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); }
    .wk-plan-main span { font-size: var(--text-xs); color: var(--muted-text); }
    .wk-plan-facts { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; }
    .wk-fact { display: flex; flex-direction: column; gap: 1px; padding: 0 12px; border-left: 1px solid var(--border-subtle); }
    .wk-fact:first-child { border-left: 0; padding-left: 0; }
    .wk-fact-label {
        font-size: 10px; font-weight: 800; letter-spacing: .07em;
        text-transform: uppercase; color: var(--muted-text);
    }
    .wk-fact-value { font-size: var(--text-sm); font-weight: 700; color: var(--navy); white-space: nowrap; }
    .wk-fact-sub { font-size: 10.5px; color: var(--muted-text); white-space: nowrap; }

    /* ---------- Target vs actual metric tiles ----------
       Reads the counts the controller already computed in $stats; nothing here
       recalculates progress. */
    .wk-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(132px, 1fr)); gap: 10px; margin-top: 12px; }
    .wk-tile {
        display: flex; align-items: center; gap: 9px;
        padding: 10px 12px; background: var(--surface-sunken);
        border: 1px solid var(--border-subtle); border-radius: 10px;
    }
    .wk-tile-dot { width: 8px; height: 8px; flex: 0 0 auto; border-radius: 50%; background: var(--border-subtle); }
    .wk-tile-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
    .wk-tile b { font-size: var(--text-base); font-weight: 800; color: var(--navy); }
    .wk-tile span { font-size: 10.5px; font-weight: 600; color: var(--muted-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wk-tile.is-ok    .wk-tile-dot { background: var(--success); }
    .wk-tile.is-warn  .wk-tile-dot { background: var(--warning); }
    .wk-tile.is-bad   .wk-tile-dot { background: var(--danger); }
    .wk-tile.is-info  .wk-tile-dot { background: var(--sky-deep); }
    .wk-tile.is-idle  .wk-tile-dot { background: #C8CEDA; }

    /* Completion read-out: large percentage plus a labelled track. */
    /* flex-direction is set explicitly: .card is a column flex box elsewhere in
       the dashboard, which would otherwise stretch the bar to its flex-basis. */
    .wk-done { display: flex; flex-direction: row; align-items: center; gap: 16px; flex-wrap: wrap; }

    .wk-done-figure { display: flex; align-items: baseline; gap: 6px; }
    .wk-done-figure b { font-family: var(--font-head); font-size: 34px; font-weight: 800; color: var(--navy); line-height: 1; }
    .wk-done-figure span { font-size: var(--text-sm); color: var(--muted-text); }
    .wk-done-track { flex: 1 1 200px; min-width: 160px; }

    /* ---------- Person hierarchy (client / staff) ----------
       Name on the first line, the supporting fact already present in the data
       underneath it, so repeating names read as a hierarchy not as noise. */
    .wk-person { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
    .wk-person-name { display: block; font-weight: 700; color: var(--navy); }
    .wk-person-sub {
        display: block; font-size: 10.5px; color: var(--muted-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .wk-person-sub.is-empty { color: #94A3B8; font-style: italic; }

    /* ---------- Evidence ---------- */
    .wk-evi { display: inline-flex; align-items: center; gap: 5px; max-width: 100%; }
    .wk-evi svg { width: 13px; height: 13px; flex: none; }
    .wk-evi-none { font-size: var(--text-xs); color: #94A3B8; }
    .wk-evi-has {
        font-size: var(--text-xs); font-weight: 700; color: #166534;
        display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
    }
    .wk-evi-has:hover { text-decoration: underline; }

    /* ---- Unified sheet extras (shared by Weekly, Monthly and Quarterly) --- */

    /* Payment-date and Actions columns on the workbook sheet. */
    .wk-col-paymentdate { white-space: nowrap; }
    .wk-paydate { white-space: nowrap; vertical-align: middle; }
    .wk-col-actions { white-space: nowrap; text-align: right; }

    /* Plain-language "what next" hint above the sheet, mirroring the weekly one. */
    .wk-nextstep {
        display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;
        margin: 0 0 16px; padding: 10px 14px;
        font-size: var(--text-sm); color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        border-left: 3px solid var(--sky-deep);
        border-radius: var(--radius-sm);
    }
    .wk-nextstep b { color: var(--navy); }
    .wk-nextstep a { color: var(--sky-deep); font-weight: 600; }
    .wk-nextstep.is-warn { border-left-color: var(--warning, #F59E0B); }
    .wk-nextstep.is-ok { border-left-color: var(--success, #10B981); }

    /* Collapsible secondary detail, so the sheet stays the primary view. */
    .wk-glance {
        margin-top: 16px; background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
    }
    .wk-glance > .wk-glance-summary {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap; cursor: pointer;
        padding: 12px 14px; list-style: none; min-height: 44px;
    }
    .wk-glance > .wk-glance-summary::-webkit-details-marker { display: none; }
    .wk-glance > .wk-glance-summary::marker { content: ''; }
    .wk-glance > .wk-glance-summary .wk-section-title { margin: 0; }
    .wk-glance[open] > .wk-glance-summary { border-bottom: 1px solid var(--border-subtle); }
    .wk-glance-body { padding: 14px; }

    /* ---------- Responsive ---------- */
    @media (max-width: 1100px) {
        .stat-grid.wk-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .wk-types { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
        .stat-grid.wk-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .stat-grid.wk-summary .stat-value { font-size: 24px; }

        .wk-weekbar { gap: 6px; padding: 10px; }
        .wk-weeklabel { order: -1; width: 100%; padding: 0; }
        .wk-weekbar-spacer { display: none; }
        .wk-quick { width: 100%; overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
        .wk-quick a { white-space: nowrap; min-height: 34px; display: inline-flex; align-items: center; }

        .wk-filters-form { flex-direction: column; align-items: stretch; gap: 12px; }
        .wk-field, .wk-field-grow { flex: 1 1 auto; width: 100%; }
        .wk-filters-actions { width: 100%; }
        .wk-filters-actions .btn { flex: 1 1 auto; min-height: 44px; }

        .wk-client-head { gap: 8px; }
        .wk-client-count { margin-left: 0; width: 100%; }
        .wk-mini { width: 100%; }

        .wk-task { align-items: flex-start; padding: 12px 14px; }
        .wk-task .wk-dot { margin-top: 6px; }
        /* Staff and evidence each take a full row on narrow screens so neither
           name is truncated to nothing. */
        .wk-task-staff { flex: 1 1 100%; padding-left: 19px; }
        .wk-evi { padding-left: 19px; }
        .wk-task-actions { width: 100%; padding-left: 19px; }
        .wk-task-actions .btn { flex: 1 1 auto; min-height: 44px; }

        .wk-today-item { padding: 12px 14px; }
        .wk-today-open { width: 100%; padding: 10px 0 0; min-height: 40px; display: flex; align-items: center; }

        .wk-types { grid-template-columns: 1fr; }
        .wk-report > *:not(.wk-report-summary) { margin-left: 12px; margin-right: 12px; }
        .wk-report-summary { padding: 14px 12px; }

        .wk-plan { gap: 10px; padding: 12px 14px; }
        .wk-plan-icon { flex-basis: 34px; width: 34px; height: 34px; }
        .wk-plan-main { flex: 1 1 100%; }
        .wk-plan-facts { margin-left: 0; width: 100%; gap: 8px; }
        .wk-fact { flex: 1 1 auto; padding: 0 10px 0 0; border-left: 0; }
        .wk-done-figure b { font-size: 28px; }
        .wk-done { gap: 10px; }

        .wk-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .wk-tile { padding: 9px 10px; }
    }

    /* ---- Balance and remarks chips in the tracker rows -------------------- */
    .schedule-seed-hint { display: block; margin-top: 3px; font-size: 11px; color: var(--muted, #6b7280); }

    .wk-badge-balance {
        background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00);
        font-weight: 600;
    }
    .wk-badge-remark {
        background: var(--bg, #f3f4f6); color: var(--muted, #6b7280);
        font-weight: 500;
    }

    /* ---- Target schedule -------------------------------------------------- */
    .schedule-card { margin-bottom: 16px; }
    .schedule-error { margin: 0 16px 12px; }
    .schedule-empty { padding: 14px 16px; margin: 0; }

    /* One collapsed row per client, so a plan with many clients stays scannable. */
    .schedule-client { border-top: 1px solid var(--border, #e5e7eb); }
    .schedule-client:first-of-type { border-top: 0; }
    .schedule-summary {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 12px 16px; cursor: pointer; list-style: none;
    }
    .schedule-summary::-webkit-details-marker { display: none; }
    .schedule-summary::before {
        content: '▸'; color: var(--muted, #6b7280); font-size: 12px; flex: 0 0 auto;
    }
    .schedule-client[open] > .schedule-summary::before { content: '▾'; }
    .schedule-client-name { font-weight: 600; flex: 0 0 auto; }
    .schedule-chain { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 1 1 auto; }
    .schedule-chain-step {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 12px; color: var(--muted, #6b7280);
        background: var(--bg, #f9fafb); border: 1px solid var(--border, #e5e7eb);
        border-radius: 999px; padding: 2px 9px; white-space: nowrap;
    }
    .schedule-chain-step strong { color: var(--text, #111827); font-weight: 600; }
    .schedule-summary-meta { display: flex; gap: 6px; flex: 0 0 auto; }
    .schedule-chip { font-size: 11px; }

    .schedule-stages {
        display: grid; gap: 12px; padding: 0 16px 16px;
        grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
    }
    .schedule-stage {
        border: 1px solid var(--border, #e5e7eb); border-radius: 8px; padding: 12px;
        background: var(--bg, #f9fafb); min-width: 0;
    }
    .schedule-stage-head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
    .schedule-stage-name { font-weight: 600; font-size: 13px; }
    .schedule-stage-offset { font-size: 11px; color: var(--muted, #6b7280); width: 100%; }

    .schedule-form { display: flex; flex-direction: column; gap: 4px; }
    .schedule-label { font-size: 11px; font-weight: 600; color: var(--muted, #6b7280); margin-top: 6px; }
    .schedule-input { width: 100%; max-width: 100%; box-sizing: border-box; }
    .schedule-textarea { resize: vertical; min-height: 42px; }
    .schedule-hint { font-size: 11px; color: var(--muted, #6b7280); }
    .schedule-hint-auto { color: var(--info, #3b82f6); }
    .schedule-save { align-self: flex-start; margin-top: 10px; }

    .schedule-readonly { display: flex; flex-direction: column; gap: 2px; }
    .schedule-pair { display: flex; gap: 8px; align-items: baseline; font-size: 12px; }
    .schedule-pair .schedule-label { flex: 0 0 84px; margin-top: 0; }
    .schedule-value { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }

    @media (max-width: 640px) {
        .schedule-summary { padding: 10px 12px; gap: 8px; }
        .schedule-stages { padding: 0 12px 12px; grid-template-columns: 1fr; }
        .schedule-pair { flex-direction: column; gap: 0; }
        .schedule-pair .schedule-label { flex: 0 0 auto; }
    }

    /* ---- Shared tracker bits: status, remarks, evidence, performed by ------- */
    /* Used by the weekly, monthly and quarterly Target vs Actual tables so all
       three read the same way. Presentation only — the status values themselves
       still come from each target model's effectiveStatus(). */

    /* Status: yellow pending, blue in progress, green completed, red missed.
       A task that finished late keeps its own tone so "done" and "done late"
       never read as the same thing. */
    .bk-status {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 11.5px; font-weight: 700; letter-spacing: 0.01em;
        border: 1px solid transparent; white-space: nowrap;
    }
    .bk-status::before {
        content: ''; width: 7px; height: 7px; border-radius: 999px;
        background: currentColor; opacity: 0.8; flex: 0 0 auto;
    }
    .bk-status-pending { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .bk-status-active  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .bk-status-done    { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .bk-status-late    { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .bk-status-overdue { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

    /* Remarks wrap instead of truncating: a remark nobody can finish reading is
       no better than a hidden one. */
    .bk-remarks { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
    .bk-remarks-text {
        font-size: 13px; line-height: 1.55; color: var(--text, #1e293b);
        white-space: pre-wrap; overflow-wrap: anywhere; min-width: 15ch;
    }
    .bk-remarks-none { font-size: 12px; color: var(--muted, #6b7280); }
    .bk-remarks-edit { display: inline-flex; align-items: center; gap: 5px; }
    .bk-remarks-target {
        font-size: 13px; font-weight: 600; color: var(--text, #0f172a);
        padding: 8px 12px; border-radius: 8px;
        background: var(--surface-2, #f8fafc); border: 1px solid var(--border, #e5e7eb);
    }

    /* Evidence */
    .bk-evidence { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
    .bk-evidence-pill {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 11.5px; font-weight: 700; color: #0369a1;
        background: #f0f9ff; border: 1px solid #bae6fd;
        border-radius: 999px; padding: 2px 9px;
    }
    .bk-evidence-links { display: inline-flex; gap: 10px; font-size: 12.5px; }
    .bk-evidence-links a { color: var(--primary, #6366f1); text-decoration: none; }
    .bk-evidence-links a:hover { text-decoration: underline; }
    .bk-evidence-name {
        display: block; max-width: 22ch; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap;
        font-size: 11.5px; color: var(--muted, #6b7280);
    }
    .bk-evidence-empty {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 12px; color: var(--muted, #6b7280);
    }

    /* Performed by */
    .bk-performed-name { font-size: 13px; font-weight: 600; color: var(--text, #0f172a); }
    .bk-performed-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 3px; }
    .bk-performed-time { font-size: 11.5px; color: var(--muted, #6b7280); }
    .bk-role {
        display: inline-flex; align-items: center;
        font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px;
    }
    .bk-role-staff { background: #eff6ff; color: #1d4ed8; }
    .bk-role-supervisor { background: #fffbeb; color: #b45309; }
    .bk-role-admin { background: #eef2ff; color: #4338ca; }

    /* Wide Target vs Actual tables scroll rather than shrink: the remarks and
       evidence columns keep a readable width and the actions stay clickable. */
    .bk-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .bk-target-table { min-width: 1120px; }
    .bk-target-table th, .bk-target-table td { vertical-align: top; }
    .bk-target-table .bk-col-remarks { min-width: 240px; }

    /* The at-a-glance strip at the top of each target page. A finished week or
       period flips the whole strip green so completion reads without hunting. */
    .bk-plan-strip {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
        gap: 16px; margin-bottom: 16px; padding: 16px 18px;
        background: #fff; border: 1px solid var(--border, #e2e8f0);
        border-radius: var(--radius, 10px); border-left: 4px solid var(--sky-deep, #0369a1);
    }
    .bk-plan-strip.is-finished { border-left-color: var(--success, #16a34a); background: #f0fdf4; }
    .bk-plan-strip-main { min-width: 220px; flex: 1 1 260px; }
    .bk-plan-strip-label {
        font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
        color: var(--muted, #64748b);
    }
    .bk-plan-strip-value {
        font-size: 26px; font-weight: 800; line-height: 1.15; color: var(--text, #0f172a);
        margin-top: 2px;
    }
    .bk-plan-strip-pct { font-size: 15px; font-weight: 700; color: var(--muted, #64748b); }
    .bk-plan-bar {
        height: 8px; margin-top: 9px; border-radius: 999px;
        background: var(--surface-sunken, #e2e8f0); overflow: hidden;
    }
    .bk-plan-bar span { display: block; height: 100%; background: var(--sky-deep, #0369a1); }
    .bk-plan-strip.is-finished .bk-plan-bar span { background: var(--success, #16a34a); }
    .bk-plan-strip-counts { display: flex; flex-wrap: wrap; gap: 8px; }
    .bk-count {
        font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 4px 11px;
        white-space: nowrap;
    }
    .bk-count-done { background: #dcfce7; color: #166534; }
    .bk-count-active { background: #dbeafe; color: #1e40af; }
    .bk-count-pending { background: #fef3c7; color: #92400e; }
    .bk-count-overdue { background: #fee2e2; color: #991b1b; }

    @media (max-width: 720px) {
        .bk-target-table { min-width: 0; }
        .bk-remarks-text { min-width: 0; }
        .bk-evidence-name { max-width: 100%; }
        .bk-plan-strip { flex-direction: column; align-items: stretch; }
    }

    /* ===================================================================
       Compact spreadsheet sheet, shared by the Weekly, Monthly and Quarterly
       bookkeeping screens so all three read as one worksheet: dense rows, thin
       borders, day/period bands, task-type subheaders and a compact Open
       button. Spacing is genuinely reduced (never transform/zoom). This block
       is the single source of truth — the weekly view no longer declares its
       own copy.
       =================================================================== */

    /* Header / period navigator / plan strip / hint: tighter vertical rhythm. */
    .wk-head { margin-bottom: 10px; }
    .wk-head-main h1 { font-size: 22px; line-height: 1.2; }
    .wk-head-main p { font-size: var(--text-xs); }

    .wk-weekbar { padding: 6px 10px; gap: 6px; margin-bottom: 10px; }
    .wk-weeknav { width: 28px; height: 28px; border-radius: 7px; }
    .wk-weeknav svg { width: 14px; height: 14px; }
    .wk-weeklabel { padding: 0 2px; }
    .wk-weeklabel b { font-size: var(--text-sm); }
    .wk-weeklabel span { font-size: 10.5px; }
    .wk-week-today { padding: 4px 9px; font-size: 10.5px; }
    .wk-quick a { padding: 4px 9px; font-size: 10.5px; }

    .wk-plan { padding: 8px 12px; gap: 10px; margin-bottom: 10px; box-shadow: none; }
    .wk-plan-icon { flex-basis: 30px; width: 30px; height: 30px; border-radius: 8px; }
    .wk-plan-icon svg { width: 15px; height: 15px; }
    .wk-plan-main b { font-size: var(--text-sm); }
    .wk-plan-main span { font-size: 10.5px; }
    .wk-fact { gap: 0; padding: 0 10px; }
    .wk-fact-value { font-size: var(--text-xs); }
    .wk-fact-sub { font-size: 10px; }

    .wk-nextstep { margin-bottom: 10px; padding: 7px 12px; font-size: var(--text-xs); }

    /* Collapsible filters: smaller trigger and fields. */
    .wk-filters { margin-bottom: 10px; }
    .wk-filters-summary { padding: 7px 12px; min-height: 34px; font-size: var(--text-xs); }
    .wk-filters-form { padding: 10px 12px; gap: 8px; }
    .wk-field > span { font-size: 10.5px; }
    .wk-field select, .wk-field input { min-height: 30px; font-size: var(--text-xs); border-radius: 6px; }

    /* Section heads: the sheet header line. */
    .wk-section-block { margin-bottom: 14px; }
    .wk-section-head { margin-bottom: 8px; }

    /* -------- The comparison sheet itself -------- */
    .wk-grid-dense {
        width: 100%; table-layout: fixed; font-size: 12.5px;
        border: 1px solid #e5e7eb; border-radius: 6px;
    }
    /* Column plan: Target 8%, Task 9%, Client 18%, Staff 13%, four stages 9%
       each, Payment 8%, Actions 8%. Client takes the generous share; stage
       columns stay narrow since they hold one short cell. */
    .wk-grid-dense .wk-col-date     { width: 8%; }
    .wk-grid-dense .wk-col-task     { width: 9%; }
    .wk-grid-dense .wk-col-client   { width: 18%; }
    .wk-grid-dense .wk-col-staff    { width: 13%; }
    .wk-grid-dense .wk-col-stage    { width: 9%; }
    .wk-grid-dense .wk-col-paymentdate { width: 8%; }
    .wk-grid-dense .wk-col-actions  { width: 8%; }

    .wk-grid-dense th,
    .wk-grid-dense td {
        padding: 4px 8px; border-bottom: 1px solid #e5e7eb;
        line-height: 1.3; vertical-align: middle;
    }
    .wk-grid-dense thead th {
        height: 32px; padding: 0 8px;
        font-size: 11px; font-weight: 800; letter-spacing: .04em;
        text-transform: uppercase; color: #6b7280;
        background: #f3f4f6; border-bottom: 1px solid #d1d5db;
        white-space: nowrap;
    }
    .wk-grid-dense thead th.wk-col-stage { text-align: center; }

    /* Day / period bands: a full-width light-blue strip before each date's
       rows. No border on the band row itself so it reads as a divider. */
    .wk-dayband { border-top: 1px solid #d1d5db; }
    .wk-dayband tr.wk-dayhead td {
        padding: 3px 8px; background: #eff6ff; border-bottom: 1px solid #dbeafe;
        font-size: 11px; font-weight: 800; letter-spacing: .04em;
        text-transform: uppercase; color: #1e40af; white-space: nowrap;
    }
    .wk-dayhead-name { margin-right: 8px; }
    .wk-dayhead-count { color: #3b82f6; font-weight: 700; text-transform: none; letter-spacing: 0; }

    /* Task-type subheader inside a day/period band: the workbook's second level
       (Pick-Up, Record, Return / Collect, Billing / Payment). Small and flush,
       so the task type reads as a group label, not a big card. */
    .wk-dayband tr.wk-taskhead td {
        padding: 2px 8px 2px 16px; background: #f8fafc;
        border-bottom: 1px solid #eef2f7;
        font-size: 10.5px; font-weight: 800; letter-spacing: .05em;
        text-transform: uppercase; color: #475569; white-space: nowrap;
    }
    .wk-taskhead-name { margin-right: 8px; }
    .wk-taskhead-count { color: #94a3b8; font-weight: 700; text-transform: none; letter-spacing: 0; }

    /* Compact rows. The 30px height keeps each data row in the 28-34px band. */
    .wk-grid-dense .wk-row > td { height: 30px; }
    .wk-grid-dense .wk-row { background: #fff; }
    .wk-grid-dense .wk-row:hover { background: #f0f7ff; }
    .wk-grid-dense .wk-row:last-child > td { border-bottom: 0; }

    .wk-grid-dense .wk-day { font-size: 12.5px; font-weight: 700; line-height: 1.15; }
    .wk-grid-dense .wk-datefull { font-size: 10px; line-height: 1.15; }
    .wk-grid-dense .wk-task { font-size: 12px; font-weight: 600; white-space: normal; }

    /* Client names stay on one line; a long name ellipsises (title carries the
       full value so it is never lost). */
    .wk-grid-dense .wk-client {
        max-width: 0; white-space: nowrap; overflow: hidden;
        text-overflow: ellipsis; font-weight: 600; color: var(--navy);
    }
    .wk-grid-dense .wk-staff { white-space: normal; }
    .wk-grid-dense .wk-person-name { font-size: 12px; font-weight: 700; line-height: 1.2; }
    .wk-grid-dense .wk-person-sub { font-size: 10px; line-height: 1.2; }

    /* Stage cells: compact pill that stays a link (hover/active states
       retained), just smaller and filling its narrow column. */
    .wk-grid-dense .cmp-cell {
        padding: 2px 6px; border-radius: 6px; min-width: 0;
        gap: 0; line-height: 1.15; max-width: 100%; width: 100%;
    }
    .wk-grid-dense .cmp-cell-text { font-size: 10.5px; font-weight: 700; }
    .wk-grid-dense .cmp-cell-sub { font-size: 9.5px; }
    .wk-grid-dense .cmp-none { font-size: 12px; }

    .wk-grid-dense .wk-paydate .wk-day { font-size: 12px; }

    /* Open button: keep the word "Open", always visible, far right. */
    .wk-grid-dense .wk-col-actions { text-align: right; }
    .wk-open {
        display: inline-flex; align-items: center; justify-content: center;
        min-height: 24px; height: 24px; padding: 0 9px;
        font-size: 10.5px; font-weight: 700; line-height: 1;
        border-radius: 5px; border: 1px solid #cbd5e1;
        color: var(--navy); background: #fff; text-decoration: none;
    }
    .wk-open:hover { background: var(--sky-soft); border-color: #bfdbfe; color: var(--navy); }

    /* Compact bottom summary blocks (This Week / Period / Progress). */
    .stat-grid.wk-summary .stat-card { padding: 10px 12px; }
    .stat-grid.wk-summary .stat-value { font-size: 22px; }
    .stat-grid.wk-summary .stat-label { font-size: 10.5px; }
    .stat-grid.wk-summary .stat-meta { font-size: 10px; }
    .wk-done { padding: 10px 12px; gap: 12px; }
    .wk-done-figure b { font-size: 26px; }
    .wk-tiles { gap: 8px; margin-top: 10px; }
    .wk-tile { padding: 8px 10px; }

    @media (max-width: 1100px) {
        .wk-grid-dense { table-layout: auto; }
        .wk-grid-dense .wk-client { max-width: none; }
    }
</style>
