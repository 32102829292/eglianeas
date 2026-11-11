@extends('layouts.dashboard')

@section('title', \App\Models\KaizenConcern::TYPES[$concern->type] ?? 'Employee Suggestion' .' #'. $concern->id .' — Egliane Accounting Services')

@push('styles')
<style>
    /* ---------- My Performance Evaluation Card --*/
    .eval-card {
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
    }

    .eval-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-subtle);
    }

    .eval-card-title {
        font-family: var(--font-head);
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        margin: 0;
    }

    .eval-badge-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .eval-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .eval-badge.finalized {
        background: #E6F7EE;
        color: #196F3D;
        border: 1px solid rgba(25, 111, 61, .18);
    }

    .eval-badge.grade {
        background: #E3F2FD;
        color: #1A5276;
        border: 1px solid rgba(26, 82, 118, .15);
    }

    .eval-badge.pending {
        background: #FFF3E0;
        color: #92400E;
        border: 1px solid rgba(146, 64, 14, .18);
    }

    .eval-badge.review {
        background: var(--sky-soft);
        color: #1A5276;
        border: 1px solid rgba(26, 82, 118, .15);
    }

    .eval-badge.reversed {
        background: #FDECEA;
        color: #A93226;
        border: 1px solid rgba(169, 50, 38, .16);
    }

    .eval-badge.not-added {
        background: #EEF1F5;
        color: #4A5568;
        border: 1px solid rgba(74, 85, 104, .14);
    }

    .eval-badge.pending-integration {
        background: #FFF3E0;
        color: #92400E;
        border: 1px solid rgba(146, 64, 14, .18);
    }

    /* ---------- Evaluation body ---------- */
    .eval-card-body {
        padding: 24px;
    }

    /* Row 1: Score Summary + Score Sheet Integration */
    .eval-row-1 {
        display: grid;
        grid-template-columns: 2fr 3fr;
        gap: 24px;
        margin-bottom: 24px;
        align-items: start;
    }

    .eval-score-panel {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        padding: 24px;
        background: var(--surface-raised);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
    }

    .evaluation-score-summary {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .score-circle-wrapper {
        width: 120px;
        height: 120px;
    }

    .eval-score-circle-svg {
        width: 120px;
        height: 120px;
        display: block;
    }

    .score-value-label {
        font-size: 18px;
        color: var(--muted-text);
        font-weight: 600;
    }

    .score-classification {
        font-size: 30px;
        font-weight: 800;
        color: var(--navy);
        margin: 0;
    }

    .score-integration-status {
        font-size: 14px;
        font-weight: 600;
        color: var(--muted-text);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .score-assessment-method {
        font-size: 12px;
        color: var(--muted-text);
        margin-top: 2px;
    }

    /* ---------- Score Sheet integration panel ---------- */
    .eval-sheet-panel {
        padding: 24px;
        background: var(--surface-raised);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
    }

    .eval-sheet-head {
        margin-bottom: 16px;
    }

    .eval-sheet-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted-text);
        margin: 0 0 4px 0;
    }

    .eval-sheet-period {
        font-size: 14px;
        color: var(--text);
        margin: 0;
    }

    .eval-sheet-note {
        font-size: 13px;
        line-height: 1.5;
        padding: 12px 16px;
        border-radius: var(--radius-sm);
        margin: 0;
    }

    .eval-sheet-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: var(--radius-sm);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-right: 8px;
    }

    .eval-sheet-note--added {
        background: #E6F7EE;
        color: #196F3D;
        border: 1px solid rgba(25, 111, 61, .18);
    }

    .eval-sheet-note--reversed {
        background: #FDECEA;
        color: #A93226;
        border: 1px solid rgba(169, 50, 38, .16);
    }

    .eval-sheet-note--pending {
        background: #FFF3E0;
        color: #92400E;
        border: 1px solid rgba(146, 64, 14, .18);
    }

    .badge-score-added {
        background: #196F3D;
        color: #fff;
    }

    .badge-score-reversed {
        background: #A93226;
        color: #fff;
    }

    .badge-score-review {
        background: #1A5276;
        color: #fff;
    }

    .badge-score-pending {
        background: #92400E;
        color: #fff;
    }

    /* ---------- Evaluation dates ---------- */
    .eval-dates {
        display: flex;
        gap: 24px;
        margin-bottom: 24px;
    }

    .eval-date-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
        text-align: center;
    }

    .eval-date-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted-text);
        margin: 0;
    }

    .eval-date-value {
        font-size: 14px;
        color: var(--text);
        margin: 0;
    }

    /* ---------- Criterion breakdown table ---------- */
    .eval-criterion-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 24px;
    }

    .eval-criterion-table th {
        text-align: left;
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted-text);
        border-bottom: 1px solid var(--border-subtle);
    }

    .eval-criterion-table th.text-end {
        text-align: right;
    }

    .eval-criterion-table th:first-child {
        width: 38%;
        min-width: 180px;
        text-align: left;
    }

    .eval-criterion-table th:nth-child(2),
    .eval-criterion-table th:nth-child(3),
    .eval-criterion-table th:nth-child(4) {
        width: 16%;
        text-align: right;
    }

    .eval-criterion-table td {
        padding: 10px 12px;
        font-size: 13px;
        color: var(--text);
        border-bottom: 1px solid var(--border-subtle);
    }

    .eval-criterion-table td.text-end {
        text-align: right;
    }

    .eval-criterion-table td:first-child {
        text-align: left;
    }

    .eval-criterion-table td.na {
        color: var(--muted-text);
    }

    .eval-criterion-table tr.total-row {
        font-weight: 700;
        background: var(--surface-raised);
    }

    .eval-criterion-table tr.total-row td {
        border-top: 2px solid var(--border-subtle);
    }

    @media (max-width: 640px) {
        .eval-criterion-table {
            font-size: 11px;
        }
        .eval-criterion-table th,
        .eval-criterion-table td {
            padding: 6px 8px;
        }
        .eval-criterion-table th:first-child,
        .eval-criterion-table td:first-child {
            width: 40%;
            min-width: auto;
        }
    }

    /* ---------- Assessment notes ---------- */
    .pri-eval-note {
        font-size: 13px;
        color: var(--muted-text);
        margin: 12px 0;
        padding: 12px 16px;
        background: var(--surface-raised);
        border-radius: var(--radius-sm);
        border-left: 3px solid var(--border-subtle);
    }

    /* ---------- Admin actions ---------- */
    .pri-eval-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* =========================================================
       Employee Suggestion detail page — scoped redesign
       All rules are scoped under .kaizen-detail-page so they cannot
       leak into the Admin Concerns board or any other dashboard page.
       ========================================================= */

    .kaizen-detail-page {
        --kaizen-accent: var(--navy);
        --kaizen-success: #196F3D;
    }

    /* ---------- Back bar ---------- */
    .kaizen-detail-backbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .kaizen-detail-backbar .btn {
        font-size: 12.5px;
        padding: 6px 14px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .kaizen-detail-backbar .btn svg { width: 14px; height: 14px; }

    /* ---------- Page header ---------- */
    .kaizen-detail-page .page-head {
        margin-bottom: 16px;
    }

    .kaizen-detail-page .page-head h1 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-family: var(--font-head);
        font-size: 24px;
        font-weight: 800;
        color: var(--navy);
        letter-spacing: -0.02em;
    }

    .kaizen-detail-page .page-head .badge.kaizen-suggestion-tag {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .kaizen-detail-page .page-head .record-id {
        font-size: 13px;
        font-weight: 700;
        color: var(--navy);
        background: var(--sky-soft);
        color: #1A5276;
        border: 1px solid rgba(26, 82, 118, .18);
        padding: 2px 10px;
        border-radius: 10px;
        white-space: nowrap;
        line-height: 1;
    }

    .kaizen-detail-page .page-head p {
        margin: 4px 0 0;
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .kaizen-detail-page .page-head p strong {
        color: var(--text);
        font-weight: 700;
    }

    .kaizen-detail-page .page-head-row {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
    }

    @media (min-width: 700px) {
        .kaizen-detail-page .page-head-row {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .kaizen-detail-page .kaizen-hero-status {
        align-items: flex-start;
    }

    .kaizen-detail-page .kaizen-hero-status-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .kaizen-detail-page .kaizen-hero-status .kaizen-status {
        font-size: 14px;
        padding: 6px 14px;
        gap: 8px;
    }

    /* ---------- Flow tracker ---------- */
    .kaizen-flow {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 18px;
        padding: 14px 18px;
        border-radius: 14px;
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        box-shadow: var(--shadow-card);
    }

    .kaizen-flow-step {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1 1 auto;
        min-width: 0;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--surface);
        font-size: 12.5px;
        font-weight: 700;
        color: var(--text-muted);
    }

    .kaizen-flow-step:not(:last-child)::after {
        content: "\203A";
        margin-left: 2px;
        color: var(--border);
        font-size: 15px;
        line-height: 1;
    }

    .kaizen-flow-step.is-done {
        background: var(--sky-soft);
        border-color: rgba(26, 82, 118, .16);
        color: #1A5276;
    }

    .kaizen-flow-step.is-current {
        border-color: rgba(26, 82, 118, .34);
        border-style: dashed;
        color: var(--navy);
        background: #FFFFFF;
        box-shadow: inset 0 0 0 1px rgba(26, 82, 118, .12);
    }

    .kaizen-flow-step.is-pending {
        background: #F2F5F9;
        border-color: rgba(74, 85, 104, .14);
        color: var(--text-muted);
    }

    .kaizen-flow-marker {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--surface);
        border: 1.5px solid currentColor;
        color: inherit;
    }

    .kaizen-flow-step.is-done .kaizen-flow-marker {
        background: #1A5276;
        border-color: #1A5276;
        color: #fff;
    }

    .kaizen-flow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }

    .kaizen-flow-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    /* ---------- Implemented / Not Implemented banners ---------- */
    .kaizen-implemented-banner {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 18px;
        padding: 18px 22px;
        border-radius: 14px;
        background: linear-gradient(135deg, #E6F7EE 0%, #F2FBF6 100%);
        border: 1px solid rgba(25, 111, 61, .28);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .05);
    }

    .kaizen-implemented-icon {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #196F3D;
        color: #fff;
    }

    .kaizen-implemented-body { min-width: 0; }

    .kaizen-implemented-title {
        font-weight: 800;
        letter-spacing: 0.06em;
        color: #196F3D;
        font-size: 1.05rem;
        text-transform: uppercase;
    }

    .kaizen-implemented-date { color: #2F3B45; font-size: .92rem; }

    .kaizen-implemented-note { color: #4A5568; font-size: .85rem; }

    .kaizen-not-implemented-banner {
        background: linear-gradient(135deg, #EEF1F5 0%, #F7F9FB 100%);
        border-color: rgba(74, 85, 104, .25);
    }

    .kaizen-not-implemented-banner .kaizen-implemented-icon { background: #4A5568; }

    .kaizen-not-implemented-banner .kaizen-implemented-title { color: #4A5568; }

    /* ---------- Details / Challenge grid ---------- */
    .kaizen-detail-page .grid-2 {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    @media (min-width: 900px) {
        .kaizen-detail-page .grid-2 {
            grid-template-columns: 1fr 1fr;
        }
    }

    .kaizen-detail-page .card {
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .kaizen-detail-page .card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border-subtle);
        background: #FAFCFE;
    }

    .kaizen-detail-page .card-head h2.card-title,
    .kaizen-detail-page .card-head h2.card-title.mb-0 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        font-family: var(--font-head);
        color: var(--navy);
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .kaizen-detail-page .card-head h2.card-title svg,
    .kaizen-detail-page .card-head h2.card-title.mb-0 svg {
        width: 17px;
        height: 17px;
        flex: 0 0 auto;
    }

    .kaizen-detail-page .card-data {
        padding: 0;
        flex: 1;
    }

    /* ---------- Details list ---------- */
    .kaizen-detail-page .kaizen-detail-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .kaizen-detail-page .kaizen-detail-list li {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 12px 20px;
        padding: 12px 22px;
        border-bottom: 1px solid rgba(27, 27, 58, .06);
        font-size: 14px;
        align-items: baseline;
    }

    .kaizen-detail-page .kaizen-detail-list li:last-child {
        border-bottom: none;
    }

    .kaizen-detail-page .kaizen-detail-list .k {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .kaizen-detail-page .kaizen-detail-list .v {
        font-size: 14px;
        color: var(--text);
        font-weight: 600;
        text-align: left;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        min-width: 0;
    }

    .kaizen-detail-page .kaizen-detail-list .badge {
        font-size: 11.5px;
        padding: 3px 10px;
        border-radius: 10px;
        display: inline-block;
    }

    /* ---------- Prose (challenge / solution / notes) ---------- */
    .kaizen-prose {
        padding: 20px 22px;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: 14.5px;
        line-height: 1.75;
        color: #2F3B45;
        margin: 0;
    }

    .kaizen-prose.muted {
        color: var(--text-muted);
        font-style: italic;
    }

    /* ---------- Solution card ---------- */
    .kaizen-solution-card {
        border-left: 3px solid var(--sky);
        background: linear-gradient(180deg, #FAFCFE 0%, var(--surface) 55%);
    }

    .kaizen-solution-card .card-head { background: #FAFCFE; }

    .kaizen-solution-card .card-title { color: #1A5276; }

    /* ---------- Section heading (checklist / evidence) ---------- */
    .kaizen-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .kaizen-section-head svg {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        color: var(--navy);
    }

    .kaizen-section-title {
        font-size: 15px;
        font-weight: 800;
        font-family: var(--font-head);
        color: var(--navy);
        letter-spacing: -0.01em;
        margin: 0;
    }

    .kaizen-section-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .kaizen-section-hint {
        margin: 4px 0 0;
        font-size: 12.5px;
        color: var(--text-muted);
    }

    /* ---------- Checklist progress section ---------- */
    .kaizen-checklist-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 32px 22px;
        text-align: center;
        color: var(--text-muted);
    }

    .kaizen-checklist-empty .kaizen-checklist-empty-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #F2F5F9;
        color: #4A5568;
        display: grid;
        place-items: center;
        font-size: 26px;
    }

    .kaizen-checklist-empty h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: var(--navy);
        font-family: var(--font-head);
    }

    .kaizen-checklist-empty p {
        margin: 2px 0 0;
        font-size: 13px;
        line-height: 1.5;
    }

    .kaizen-checklist-summary {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 22px;
        background: #F2F5F9;
        border-bottom: 1px solid var(--border-subtle);
        font-size: 13px;
        color: var(--text-muted);
    }

    .kaizen-checklist-summary strong {
        color: var(--text);
        font-weight: 800;
    }

    .kaizen-checklist-table-wrap {
        padding: 0 22px 22px;
    }

    .kaizen-checklist-table-wrap table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: 13px;
    }

    .kaizen-checklist-table-wrap th {
        text-align: left;
        padding: 10px 12px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-subtle);
        white-space: nowrap;
    }

    .kaizen-checklist-table-wrap td {
        padding: 12px;
        border-bottom: 1px solid rgba(27, 27, 58, .06);
        color: var(--text);
        vertical-align: middle;
    }

    .kaizen-checklist-table-wrap tr:last-child td { border-bottom: none; }

    .kaizen-checklist-table-wrap tr.completed td {
        background: #E6F7EE;
    }

    .kaizen-checklist-table-wrap .kaizen-checklist-title {
        font-weight: 700;
        color: var(--text);
        min-width: 0;
    }

    .kaizen-checklist-table-wrap .kaizen-checklist-title.completed {
        text-decoration: line-through;
        color: var(--text-muted);
    }

    .kaizen-checklist-table-wrap .kaizen-checklist-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .kaizen-checklist-progress {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 22px;
        background: #F2F5F9;
        border-bottom: 1px solid var(--border-subtle);
    }

    .kaizen-checklist-progress .kaizen-checklist-progress-bar {
        flex: 1;
        height: 8px;
        border-radius: 999px;
        background: #D7DEE7;
        overflow: hidden;
    }

    .kaizen-checklist-progress .kaizen-checklist-progress-bar > span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: var(--kaizen-success);
        transition: width 0.25s ease;
    }

    .kaizen-checklist-progress .kaizen-checklist-label {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
        white-space: nowrap;
    }

    .kaizen-checklist-ready {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 22px;
        padding: 11px 16px;
        border-radius: 12px;
        background: #E6F7EE;
        border: 1px solid rgba(25, 111, 61, .22);
    }

    .kaizen-checklist-ready-icon {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #196F3D;
        color: #fff;
    }

    .kaizen-checklist-ready-body { display: flex; flex-direction: column; min-width: 0; }

    .kaizen-checklist-ready-body strong { color: #196F3D; font-size: 13.5px; }
    .kaizen-checklist-ready-body .kaizen-checklist-ready-note { color: #2F3B45; font-size: 12.5px; }

    /* ---------- Evidence panel ---------- */
    .kaizen-evidence-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-subtle);
        background: #FAFCFE;
        flex-wrap: wrap;
    }

    .kaizen-evidence-head .kaizen-evidence-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        font-family: var(--font-head);
        color: var(--navy);
        letter-spacing: -0.01em;
    }

    .kaizen-evidence-head .kaizen-evidence-title svg {
        width: 18px;
        height: 18px;
        color: var(--navy);
    }

    .kaizen-evidence-head .kaizen-evidence-description {
        font-size: 12.5px;
        color: var(--text-muted);
        margin: 0;
        margin-left: auto;
        max-width: 420px;
        line-height: 1.5;
    }

    .kaizen-evidence-head .kaizen-evidence-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .kaizen-evidence-head .kaizen-evidence-count {
        margin-right: auto;
        padding: 4px 11px;
        border-radius: 999px;
        background: var(--sky-soft);
        color: #1A5276;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .kaizen-evidence-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
        padding: 18px 22px 22px;
    }

    .kaizen-evidence-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 16px 18px;
        border-radius: 12px;
        background: var(--surface);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-card);
    }

    .kaizen-evidence-card .kaizen-evidence-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--sky-soft);
        color: #1A5276;
        display: grid;
        place-items: center;
        font-size: 19px;
        flex: 0 0 auto;
    }

    .kaizen-evidence-card .kaizen-evidence-name {
        font-weight: 800;
        color: var(--navy);
        font-size: 14px;
        overflow-wrap: anywhere;
        line-height: 1.35;
    }

    .kaizen-evidence-card .kaizen-evidence-sub {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
        flex-wrap: wrap;
    }

    .kaizen-evidence-card .kaizen-evidence-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .kaizen-evidence-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 34px 22px;
        text-align: center;
        color: var(--text-muted);
        border-top: 1px solid var(--border-subtle);
    }

    .kaizen-evidence-empty .kaizen-evidence-empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #F2F5F9;
        color: #4A5568;
        display: grid;
        place-items: center;
        font-size: 30px;
    }

    .kaizen-evidence-empty h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: var(--navy);
        font-family: var(--font-head);
    }

    .kaizen-evidence-empty p {
        margin: 2px 0 0;
        font-size: 13px;
        line-height: 1.5;
        max-width: 360px;
    }

    .kaizen-evidence-implemented {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 22px 0;
        padding: 11px 16px;
        border-radius: 12px;
        background: #E6F7EE;
        border: 1px solid rgba(25, 111, 61, .22);
        color: #196F3D;
        font-size: 13.5px;
    }

    .kaizen-evidence-implemented-icon {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #196F3D;
        color: #fff;
    }

    /* ---------- Implementation confirmation ---------- */
    .kaizen-confirm {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin: 14px 22px 22px;
        padding: 14px 18px;
        border-radius: 12px;
        background: var(--surface-sunken);
        border: 1px dashed var(--border);
    }

    .kaizen-confirm-text { flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; }
    .kaizen-confirm-hint { margin-top: 2px; font-size: 12.5px; color: var(--text-muted); }
    .kaizen-confirm .btn-success svg { margin-right: 6px; }

    .kaizen-confirm-done {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 14px 22px 22px;
        padding: 13px 18px;
        border-radius: 12px;
        background: #E6F7EE;
        border: 1px solid rgba(25, 111, 61, .22);
        color: #196F3D;
        font-size: 13.5px;
    }

    .kaizen-confirm-done-icon {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #196F3D;
        color: #fff;
    }

    /* ---------- Completion summary ---------- */
    .kaizen-complete-card {
        margin-top: 20px;
        padding: 20px 22px;
        border: 1px solid rgba(25, 111, 61, .26);
        background: linear-gradient(135deg, #E9F8F0 0%, #F6FCF9 100%);
    }

    .kaizen-complete-head { display: flex; align-items: center; gap: 14px; }
    .kaizen-complete-icon {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #196F3D;
        color: #fff;
    }

    .kaizen-complete-title {
        font-family: var(--font-head);
        font-size: 16px;
        font-weight: 800;
        color: #196F3D;
    }
    .kaizen-complete-note { margin-top: 2px; font-size: 13.5px; color: #2F3B45; }
    .kaizen-complete-facts {
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        gap: 10px 28px;
        margin: 16px 0 0;
        padding: 14px 0 0;
        border-top: 1px solid rgba(25, 111, 61, .18);
    }
    .kaizen-complete-facts li { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .kaizen-complete-facts span {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #4A7A61;
    }
    .kaizen-complete-facts strong { font-size: 14px; color: #196F3D; overflow-wrap: anywhere; }

    /* ---------- Responsive ---------- */
    @media (max-width: 640px) {
        .kaizen-detail-page .kaizen-detail-list li {
            grid-template-columns: 1fr;
            gap: 6px 0;
        }
        .kaizen-evidence-grid {
            grid-template-columns: 1fr;
        }
        .kaizen-flow-step {
            flex: 1 1 100%;
        }
    }

    /* ---------- Achievement Ranking Contribution (scoped) ---------- */
    .kaizen-detail-page .kaizen-contribution-card {
        border-top: 2px solid var(--navy);
    }
    .kaizen-detail-page .kaizen-contribution-card .card-head {
        background: #FAFCFE;
    }
    .kaizen-detail-page .kaizen-contribution-card .card-title {
        color: var(--navy);
    }
    .kaizen-detail-page .kaizen-contribution-card .card-title svg {
        color: var(--navy);
    }
    .kaizen-detail-page .kaizen-contribution-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 22px;
        border-bottom: 1px solid rgba(27, 27, 58, .06);
        font-size: 13.5px;
        flex-wrap: wrap;
    }
    .kaizen-detail-page .kaizen-contribution-row:last-child {
        border-bottom: none;
    }
    .kaizen-detail-page .kaizen-contribution-label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-muted);
        min-width: 150px;
    }
    .kaizen-detail-page .kaizen-contribution-value {
        font-size: 14px;
        color: var(--text);
        font-weight: 700;
        text-align: left;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        min-width: 0;
    }
    .kaizen-detail-page .kaizen-contribution-note {
        font-size: 12.5px;
        color: var(--text-muted);
        margin-top: 4px;
        display: block;
        overflow-wrap: anywhere;
    }
    .kaizen-detail-page .kaizen-contribution-divider {
        height: 1px;
        background: var(--border-subtle);
        margin: 8px 0;
        border: none;
    }
    .kaizen-detail-page .kaizen-contribution-link {
        color: var(--sky-deep);
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .kaizen-detail-page .kaizen-contribution-link:hover {
        text-decoration: underline;
    }
    .kaizen-detail-page .kaizen-contribution-card .badge {
        font-size: 11.5px;
        padding: 3px 10px;
        border-radius: 10px;
        display: inline-block;
    }

    /* ---------- Evidence delete notification ---------- */
    .kaizen-evidence-notifications {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1060;
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 320px;
    }
    .kaizen-evidence-notification {
        font-size: 13px;
        padding: 10px 14px;
        border-radius: 10px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(16, 24, 40, 0.15);
    }
    .kaizen-evidence-notification.alert-success {
        background: #196F3D;
        border: 1px solid rgba(25, 111, 61, 0.2);
    }
    .kaizen-evidence-notification.alert-danger {
        background: #A93226;
        border: 1px solid rgba(169, 50, 38, 0.2);
    }
    .kaizen-evidence-notification .btn-close {
        filter: invert(1);
        opacity: 0.8;
        margin-left: auto;
    }
    @keyframes kaizen-spin {
        to { transform: rotate(360deg); }
    }
    .kaizen-evidence-delete-btn .loading-spinner {
        animation: kaizen-spin 0.8s linear infinite;
    }
</style>
@endpush

@section('content')
    <div class="kaizen-detail-page">
    @php
        /* Admin Concerns and Employee Suggestions share this screen, so the
           label follows the record's own type. A record from before the `type`
           column existed reads as an Employee Suggestion, which is how the board
           has always treated it. */
        $recordTypeLabel = \App\Models\KaizenConcern::TYPES[$concern->type] ?? 'Employee Suggestion';

        $isAdminConcern = $concern->isAdminConcern();
        $isResolved = $concern->isResolved();
        $isClosed = $concern->isClosed();

        $isImplemented = $concern->isImplemented();
        $isNotImplemented = $concern->isNotImplemented();

        $checklistTotal = $concern->checklistItems->count();
        $checklistDone = $concern->checklistItems->where('completed', true)->count();
        $checklistComplete = $checklistTotal > 0 && $checklistDone === $checklistTotal;
        $checklistPercent = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;

        $evidenceCount = $concern->evidences->count();

        // The long date is used in the headline states, where there is room to
        // read it, and the fallback only covers the legacy records that were
        // finalised before an implementation date was recorded.
        $implementedOn = $concern->implementation_date?->format('F j, Y')
            ?? $concern->updated_at?->format('F j, Y');

        // Implementation confirmation is the existing admin-only edit step, so
        // it carries exactly the same guard. Employees and supervisors can
        // still work the checklist but cannot finalise the suggestion.
        $canConfirmImplementation = auth()->user()->isAdmin() && ! $isImplemented;

        // Finishing the checklist only means the work is ready to be confirmed.
        // The parent status is never changed by checklist progress.
        $showReadyPrompt = $checklistComplete && ! $isImplemented;
        $showCompletedSummary = $isImplemented && $checklistComplete;

        // Evidence step state comes from actual evidence records, not from the
        // implementation status. Implemented does not mean evidence exists.
        $evidenceStepState = $evidenceCount > 0 ? 'done' : 'pending';

        // Achievement Ranking Contribution: read the existing evaluation for
        // this suggestion without creating or fabricating any score.
        $contributionEvaluation = null;
        $contributionResponsible = null;
        $contributionPeriod = null;
        $contributionIntegration = null;
        $contributionTimeliness = null;
        $contributionResponsiveness = null;
        $contributionWeighted = null;
        $contributionOverall = null;

        if (! $isAdminConcern) {
            $contributionEvaluation = \App\Models\StaffTaskEvaluation::forSource(
                \App\Models\StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN,
                $concern->id
            )->first();

            if ($contributionEvaluation) {
                $contributionResponsible = $contributionEvaluation->staff;
                $contributionPeriod = $contributionEvaluation->evaluationPeriod;
                $contributionIntegration = $contributionEvaluation->integrationBadge();
                $contributionTimeliness = $contributionEvaluation->timeliness_score;
                $contributionResponsiveness = $contributionEvaluation->immediate_responsiveness_score;
                $contributionWeighted = $contributionEvaluation->score_sheet_weighted_points;
                $contributionOverall = $contributionEvaluation->overall_score;
            } else {
                // No evaluation exists yet: fall back to the staff who would be
                // responsible for this suggestion (assigned staff, else creator).
                $contributionResponsible = $concern->assignedStaff ?? $concern->creator;
            }
        }
    @endphp

    {{-- Back navigation --}}
    @if (isset($backUrl))
    <div class="kaizen-detail-backbar">
        <a href="{{ $backUrl }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            {{ $backLabel ?? 'Back' }}
        </a>
    </div>
    @endif

    {{-- Top header --}}
    <div class="page-head page-head-row">
        <div>
            <h1>
                <span class="badge kaizen-suggestion-tag">{{ $recordTypeLabel }}</span>
                <span class="record-id">#{{ $concern->id }}</span>
            </h1>
            <p>Submitted by: <strong>{{ $concern->creator->name ?? '—' }}</strong></p>
        </div>
        <div class="kaizen-hero-status">
            <span class="kaizen-hero-status-label">Current Status</span>
            @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
        </div>
    </div>

    {{-- Employee Suggestion → Progress → Implementation → Evidence --}}
    @php
        /* Both the flow and the badge follow the derived status, so a record that
           has an implementation date always reads as Implemented even if the
           stored status column has not caught up yet. */
        $effectiveStatus = $concern->effectiveStatus();
    @endphp
    <div class="kaizen-flow">
        @foreach ([
            [$recordTypeLabel, 'done'],
            ['Progress', $effectiveStatus === \App\Models\KaizenConcern::STATUS_PENDING ? 'current' : 'done'],
            ['Implementation', $isImplemented ? 'done' : 'current'],
            ['Evidence', $evidenceStepState],
        ] as [$stageLabel, $stageState])
            <div class="kaizen-flow-step is-{{ $stageState }}">
                <span class="kaizen-flow-marker">
                    @if ($stageState === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="11" height="11" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <span class="kaizen-flow-dot" aria-hidden="true"></span>
                    @endif
                </span>
                <span class="kaizen-flow-label">{{ $stageLabel }}</span>
            </div>
        @endforeach
    </div>

    {{-- Implemented state, shown at the top of the page --}}
    @if ($isImplemented)
        <div class="kaizen-implemented-banner">
            <div class="kaizen-implemented-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="30" height="30">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="kaizen-implemented-body">
                <div class="kaizen-implemented-title">&#10003; IMPLEMENTED</div>
                <div class="kaizen-implemented-date">
                    Implemented on:
                    <strong>{{ $implementedOn }}</strong>
                </div>
                <div class="kaizen-implemented-note">This improvement has been implemented.</div>
            </div>
        </div>
    @elseif ($isNotImplemented)
        <div class="kaizen-implemented-banner kaizen-not-implemented-banner">
            <div class="kaizen-implemented-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="30" height="30">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="8" y1="8" x2="16" y2="16"/>
                </svg>
            </div>
            <div class="kaizen-implemented-body">
                <div class="kaizen-implemented-title">NOT IMPLEMENTED</div>
                <div class="kaizen-implemented-note">This improvement suggestion was not implemented.</div>
            </div>
        </div>
    @endif

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">{{ $isAdminConcern ? 'Concern Overview' : 'Details' }}</h2>
            </div>
            <ul class="detail-list kaizen-detail-list">
                <li><span class="k">Date Identified</span><span class="v">{{ $concern->date_identified?->format('F j, Y') ?? '—' }}</span></li>
                <li><span class="k">Target Date</span><span class="v">{{ $concern->target_date?->format('F j, Y') ?? '—' }}</span></li>
                <li class="kaizen-detail-impl {{ $isImplemented ? 'is-implemented' : '' }}">
                    <span class="k">{{ $isImplemented ? 'Implemented on' : 'Implementation Date' }}</span>
                    <span class="v">
                        @if ($isImplemented)
                            <strong>{{ $implementedOn }}</strong>
                        @else
                            —
                        @endif
                    </span>
                </li>
                <li>
                    <span class="k">{{ $concern->isAdminConcern() ? 'Assigned To' : 'Assigned Staff' }}</span>
                    <span class="v">
                        @if ($concern->assignedStaff)
                            {{ $concern->assignedStaff->name }}
                        @elseif ($concern->isAdminConcern())
                            <span class="badge badge-info">Unassigned</span>
                        @else
                            <span class="badge badge-info">Unassigned (visible to all staff &amp; supervisors)</span>
                        @endif
                    </span>
                </li>
                @if ($concern->canAssignStaff(auth()->user()))
                    <li>
                        <span class="k">Change Assignment</span>
                        <div class="v">
                            @include('admin.kaizen-concerns.partials.assign-form', ['concern' => $concern])
                        </div>
                    </li>
                @endif
                <li>
                    <span class="k">Status</span>
                    <span class="v">
                        @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
                    </span>
                </li>
                <li><span class="k">Submitted By</span><span class="v">{{ $concern->creator->name ?? '—' }}</span></li>
                <li><span class="k">Created At</span><span class="v">{{ $concern->created_at?->format('F j, Y g:i A') }}</span></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Challenge / Opportunity</h2>
            </div>
            <div class="card-data">
                <div class="kaizen-prose">{{ $concern->challenge }}</div>
            </div>
        </div>

        <div class="card kaizen-solution-card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0018 8 6 6 0 006 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 018.91 14"/></svg>
                    {{ $isAdminConcern ? 'Suggested Resolution' : 'Suggested Solution' }}
                </h2>
            </div>
            <div class="card-data">
                @if ($concern->recommended_solution)
                    <div class="kaizen-prose">{{ $concern->recommended_solution }}</div>
                @else
                    <div class="kaizen-prose muted">— No solution recorded —</div>
                @endif
            </div>
        </div>

        @if ($concern->notes)
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">Notes</h2>
            </div>
            <div class="card-data">
                <div class="kaizen-prose">{{ $concern->notes }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Progress Updates (Admin Concerns only) --}}
    @if ($isAdminConcern)
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">Progress Updates</h2>
        </div>
        <div class="card-data">
            @if ($concern->progressUpdates->isNotEmpty())
                <div class="kaizen-progress-update-list">
                    @foreach ($concern->progressUpdates as $update)
                    <div class="kaizen-progress-update-item">
                        <div class="kaizen-progress-update-header">
                            <span class="kaizen-progress-update-submitter">{{ $update->submitter->name ?? '—' }}</span>
                            <span class="kaizen-progress-update-date">{{ $update->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
                        </div>
                        <div class="kaizen-progress-update-body">{{ $update->update_text }}</div>
                        @if ($update->evidences->isNotEmpty())
                        <div class="kaizen-progress-update-evidence">
                            @foreach ($update->evidences as $evidence)
                            <span class="kaizen-evidence-item">
                                <span class="kaizen-evidence-icon">📎</span>
                                <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="kaizen-evidence-name">{{ $evidence->original_name }}</a>
                            </span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 muted">
                    <p class="mb-1">No progress updates yet.</p>
                    <p class="mb-0">Progress updates will appear here as staff submit them.</p>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Implementation Progress (the existing checklist) --}}
    @if (!$isAdminConcern)
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <div class="kaizen-section-head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                <div>
                    <h2 class="card-title mb-0">Implementation Progress</h2>
                    <p class="kaizen-section-hint" style="margin:0;">Track the implementation steps for this suggestion.</p>
                </div>
            </div>
            <div class="kaizen-section-actions">
                @if ($concern->canManageChecklist(auth()->user()))
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Item
                    </button>
                @endif
            </div>
        </div>

        @if ($checklistTotal > 0)
            <div class="kaizen-checklist-progress">
                <div class="kaizen-checklist-progress-bar">
                    <span style="width: {{ $checklistPercent }}%"></span>
                </div>
                <span class="kaizen-checklist-label">{{ $checklistDone }} of {{ $checklistTotal }} complete</span>
            </div>
        @endif

        @if ($checklistComplete)
            <div class="kaizen-checklist-ready">
                <span class="kaizen-checklist-ready-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span class="kaizen-checklist-ready-body">
                    <strong>&#10003; All checklist items completed</strong>
                    <span class="kaizen-checklist-ready-note">
                        @if ($isImplemented)
                            This suggestion has been confirmed as implemented.
                        @else
                            Ready for Implementation Confirmation
                        @endif
                    </span>
                </span>
            </div>
        @endif

        @if ($concern->checklistItems->isNotEmpty())
            <div class="kaizen-checklist-table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-muted">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Item</th>
                            <th class="text-center" style="width: 100px;">Status</th>
                            <th class="text-center" style="width: 140px;">Completed By</th>
                            <th class="text-center" style="width: 140px;">Completed At</th>
                            <th class="text-end" style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($concern->checklistItems->sortBy('sort_order') as $index => $item)
                            <tr class="{{ $item->completed ? 'completed' : '' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input" {{ $item->completed ? 'checked' : '' }} data-checklist-item="{{ $item->id }}" data-url="{{ route('admin.kaizen-concerns.checklist.toggle', $concern) }}">
                                        <span class="kaizen-checklist-title {{ $item->completed ? 'completed' : '' }}">{{ $item->title }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $item->completed ? 'badge-success' : 'badge-warn' }}">
                                        {{ $item->completed ? 'Done' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $item->completer->name ?? '—' }}</td>
                                <td class="text-center">{{ $item->completed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($concern->canManageChecklist(auth()->user()))
                                        <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editChecklistModal"
                                                data-checklist-id="{{ $item->id }}"
                                                data-checklist-title="{{ $item->title }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.destroy', $concern) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this checklist item?', message: 'This item will be permanently removed.', danger: true, confirmLabel: 'Delete' });">
                                            @csrf
                                            <input type="hidden" name="checklist_item_id" value="{{ $item->id }}">
                                            <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            @if ($concern->canManageChecklist(auth()->user()))
                <div class="kaizen-checklist-empty">
                    <span class="kaizen-checklist-empty-icon">&#10003;</span>
                    <h3>No checklist items yet</h3>
                    <p>Implementation steps will appear here once they are added.</p>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Item
                    </button>
                </div>
            @else
                <div class="kaizen-checklist-empty">
                    <span class="kaizen-checklist-empty-icon">&#10003;</span>
                    <h3>No checklist items yet</h3>
                    <p>Checklist items will appear here once they are assigned.</p>
                </div>
            @endif
        @endif
    </div>
    @endif

    {{-- Add Checklist Item Modal --}}
    @if ($concern->canManageChecklist(auth()->user()))
    <div class="modal fade" id="addChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.add', $concern) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Checklist Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="checklist_title">Item Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="checklist_title" name="title" type="text" maxlength="500" required placeholder="Enter checklist item...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Checklist Item Modal --}}
    @if ($concern->canManageChecklist(auth()->user()))
    <div class="modal fade" id="editChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.update', $concern) }}">
                    @csrf
                    <input type="hidden" name="checklist_item_id" id="edit_checklist_item_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Checklist Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="edit_checklist_title">Item Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="edit_checklist_title" name="title" type="text" maxlength="500" required placeholder="Enter checklist item...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Completed summary: checklist complete AND officially implemented --}}
    @if ($showCompletedSummary)
        <div class="card kaizen-complete-card">
            <div class="kaizen-complete-head">
                <span class="kaizen-complete-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22" height="22"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <div>
                    <div class="kaizen-complete-title">&#10003; Improvement Implemented</div>
                    <div class="kaizen-complete-note">This employee suggestion has been successfully implemented.</div>
                </div>
            </div>
            <ul class="kaizen-complete-facts">
                <li><span>Implemented on</span><strong>{{ $implementedOn }}</strong></li>
                <li><span>Evidence</span><strong>{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }}</strong></li>
                <li><span>Submitted by</span><strong>{{ $concern->creator->name ?? '—' }}</strong></li>
            </ul>
        </div>
    @endif

    {{-- Implementation Evidence --}}
    <div class="card" id="evidence" style="margin-top: 20px;">
        <div class="kaizen-evidence-head">
            <div class="kaizen-evidence-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true" style="vertical-align:-3px"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                {{ $isAdminConcern ? 'Supporting Evidence' : 'Implementation Evidence' }}
            </div>
            <p class="kaizen-evidence-description">{{ $isAdminConcern ? 'Attach supporting evidence for this Admin Concern.' : 'Attach proof that this improvement was implemented.' }}</p>
            @if ($evidenceCount > 0)
                <span class="kaizen-evidence-count">{{ $evidenceCount }} Evidence {{ Str::plural('File', $evidenceCount) }}</span>
            @endif
            <div class="kaizen-evidence-actions">
                @if ($concern->canUploadEvidence(auth()->user()))
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Evidence
                    </button>
                @endif
            </div>
        </div>

        {{-- The Implemented state is repeated here so it is also visible next to the evidence it produced. --}}
        @if ($isImplemented)
            <div class="kaizen-evidence-implemented">
                <span class="kaizen-evidence-implemented-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span>
                    <strong>&#10003; IMPLEMENTED</strong> &middot; Implemented on:
                    <strong>{{ $implementedOn }}</strong>
                </span>
            </div>
        @endif

        {{-- Evidence delete notifications (AJAX) --}}
        <div id="kaizen-evidence-notifications" class="kaizen-evidence-notifications"></div>

        @if ($concern->evidences->isNotEmpty())
            <div class="kaizen-evidence-grid">
                @foreach ($concern->evidences as $evidence)
                    @php
                        $icon = str_starts_with((string) $evidence->mime_type, 'image/')
                            ? '&#128444;'
                            : (str_contains((string) $evidence->mime_type, 'pdf') ? '&#128213;' : '&#128206;');
                    @endphp
                    <div class="kaizen-evidence-card kaizen-evidence-item">
                        <span class="kaizen-evidence-icon" aria-hidden="true">{!! $icon !!}</span>
                        <div class="kaizen-evidence-name">{{ $evidence->original_name }}</div>
                        <div class="kaizen-evidence-sub">
                            Added by {{ $evidence->uploader->name ?? '—' }} &middot; {{ $evidence->created_at?->format('M j, Y') }}
                            @if ($evidence->size)
                                <span>&#183;</span> {{ number_format($evidence->size / 1024, 0) }} KB
                            @endif
                        </div>
                        <div class="kaizen-evidence-actions">
                            <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                            <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.download', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm">Download</a>
                            @if (auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.kaizen-concerns.evidence.delete', [$concern, $evidence]) }}" class="d-inline kaizen-evidence-delete-form" data-evidence-id="{{ $evidence->id }}" data-confirm-title="Remove this evidence?" data-confirm-message="This file will be permanently removed." data-confirm-label="Remove" data-confirm-danger="true">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline danger btn-sm kaizen-evidence-delete-btn">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="kaizen-evidence-empty">
                <span class="kaizen-evidence-empty-icon">📎</span>
                <h3>{{ $isAdminConcern ? 'No supporting evidence attached yet.' : 'No implementation evidence attached yet.' }}</h3>
                <p>{{ $isAdminConcern ? 'Click "Add Evidence" to attach a photo, screenshot, PDF or document.' : 'Click "Add Evidence" to attach a photo, screenshot, PDF or document.' }}</p>
                @if ($concern->canUploadEvidence(auth()->user()))
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Evidence
                    </button>
                @endif
            </div>
        @endif

        {{-- Implementation confirmation (Employee Suggestions only) --}}
        @if (!$isAdminConcern)
        @if ($isImplemented)
            <div class="kaizen-confirm-done">
                <span class="kaizen-confirm-done-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="13" height="13" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span>Confirmed as implemented on <strong>{{ $implementedOn }}</strong>.</span>
            </div>
        @elseif ($canConfirmImplementation)
            <div class="kaizen-confirm">
                <div class="kaizen-confirm-text">
                    <strong>Confirm implementation</strong>
                    @if ($checklistComplete)
                        <span class="kaizen-confirm-hint">All implementation tasks are complete. Confirm to record the official Implemented status.</span>
                    @else
                        <span class="kaizen-confirm-hint">Records the official Implemented status. Checklist progress is tracked separately and does not change this status.</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.kaizen-concerns.implement', $concern) }}"
                      onsubmit="return egliane.confirm.form(this, { title: 'Mark as Implemented?', message: 'This records the improvement as officially implemented and stamps the implementation date.', confirmLabel: 'Mark as Implemented' });">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="15" height="15" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg>
                        Mark as Implemented
                    </button>
                </form>
            </div>
        @endif
        @endif
    </div>

    {{-- Achievement Ranking Contribution (Employee Suggestions) --}}
    @if (! $isAdminConcern)
    <div class="card kaizen-contribution-card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17V7M12 3V7M9 20H6M18 20H15"/></svg>
                Achievement Ranking Contribution
            </h2>
        </div>
        <div class="card-data">
            <div class="kaizen-contribution-row">
                <span class="kaizen-contribution-label">Responsible employee</span>
                <span class="kaizen-contribution-value">{{ $contributionResponsible->name ?? '—' }}</span>
            </div>
            <div class="kaizen-contribution-row">
                <span class="kaizen-contribution-label">Assessment period</span>
                <span class="kaizen-contribution-value">{{ $contributionPeriod?->name ?? 'No evaluation period' }}</span>
            </div>
            <div class="kaizen-contribution-row kaizen-contribution-row--status">
                <span class="kaizen-contribution-label">Integration status</span>
                <span class="kaizen-contribution-value">
                    @if ($contributionEvaluation)
                        <span class="badge {{ $contributionIntegration['class'] }}">{{ $contributionIntegration['label'] }}</span>
                    @else
                        <span class="badge badge-info">PENDING</span>
                    @endif
                </span>
            </div>

            @if ($contributionEvaluation && $contributionEvaluation->overall_score !== null)
            <div class="kaizen-contribution-row kaizen-contribution-row--overall">
                <span class="kaizen-contribution-label">Record-level score</span>
                <span class="kaizen-contribution-value">{{ number_format((float) $contributionEvaluation->overall_score, 1) }} / 100</span>
                <span class="kaizen-contribution-note">Score for this suggestion only, not the employee&rsquo;s overall ranking.</span>
            </div>
            @endif

            <div class="kaizen-contribution-divider"></div>

            <div class="kaizen-contribution-row">
                <span class="kaizen-contribution-label">Timeliness</span>
                <span class="kaizen-contribution-value">
                    @if ($contributionEvaluation && $contributionTimeliness !== null)
                        {{ number_format((float) $contributionTimeliness, 1) }} / 100
                    @else
                        <span class="text-muted">Not evaluated</span>
                    @endif
                </span>
                <span class="kaizen-contribution-note">
                    @if ($contributionEvaluation && $contributionTimeliness !== null)
                        Evaluated against the target deadline.
                    @elseif ($concern->target_date === null)
                        Not evaluated — no target date was provided.
                    @else
                        Awaiting evaluation.
                    @endif
                </span>
            </div>

            <div class="kaizen-contribution-row">
                <span class="kaizen-contribution-label">Immediate Responsiveness</span>
                <span class="kaizen-contribution-value">
                    @if ($contributionEvaluation && $contributionResponsiveness !== null)
                        {{ number_format((float) $contributionResponsiveness, 1) }} / 100
                    @else
                        <span class="text-muted">Not evaluated</span>
                    @endif
                </span>
                <span class="kaizen-contribution-note">
                    @if ($contributionEvaluation && $contributionResponsiveness !== null)
                        Evaluated against the first response timestamp.
                    @else
                        Awaiting verification of assignment and first response timestamps.
                    @endif
                </span>
            </div>

            @if ($contributionEvaluation && $contributionWeighted !== null && $contributionEvaluation->isIncludedInScoreSheet())
            <div class="kaizen-contribution-row kaizen-contribution-row--contribution">
                <span class="kaizen-contribution-label">Score contribution</span>
                <span class="kaizen-contribution-value">{{ number_format((float) $contributionWeighted, 2) }} weighted points</span>
                <span class="kaizen-contribution-note">Contributed to the {{ $contributionEvaluation->criterionLabel() }} criterion of the Score Sheet.</span>
            </div>
            @endif

            @if ($contributionEvaluation && $contributionEvaluation->taskReferenceUrl())
            <div class="kaizen-contribution-row kaizen-contribution-row--link">
                <span class="kaizen-contribution-label">Source record</span>
                <a href="{{ $contributionEvaluation->taskReferenceUrl() }}" class="kaizen-contribution-link">
                    {{ $contributionEvaluation->taskTitle() }} · View source
                </a>
            </div>
            @endif

            <div class="kaizen-contribution-row kaizen-contribution-row--sheet">
                <span class="kaizen-contribution-label">Employee score sheet</span>
                <a href="{{ route('daily-journal.achievements') }}" class="kaizen-contribution-link">
                    View Achievement Ranking
                </a>
            </div>

        </div>
    </div>
    @endif

    {{-- Add Evidence Modal --}}
    @if ($concern->canUploadEvidence(auth()->user()))
    <div class="modal fade" id="addEvidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.evidence.add', $concern) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Evidence of Implementation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="evidence_file">File <span class="text-danger">*</span></label>
                            <input class="form-control" id="evidence_file" name="evidence" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted">Accepted: images, PDF, Word, Excel. Maximum 20 MB.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Resolution Details (Admin Concerns only) --}}
    @if ($isAdminConcern)
    <div class="card" style="margin-top: 20px;">
        <div class="card-head">
            <h2 class="card-title mb-0">Resolution Details</h2>
        </div>
        <div class="card-data">
            @if ($isResolved || $concern->resolution_notes)
                <ul class="detail-list kaizen-detail-list">
                    @if ($concern->resolution_notes)
                    <li><span class="k">Resolution Notes</span><span class="v">{{ $concern->resolution_notes }}</span></li>
                    @endif
                    @if ($concern->resolved_at)
                    <li><span class="k">Resolved At</span><span class="v">{{ $concern->resolved_at->format('F j, Y g:i A') }}</span></li>
                    @endif
                    @if ($concern->resolvedBy)
                    <li><span class="k">Resolved By</span><span class="v">{{ $concern->resolvedBy->name ?? '—' }}</span></li>
                    @endif
                    @if ($concern->isClosed() && !$isResolved)
                    <li><span class="k">Closed</span><span class="v">This Admin Concern has been closed without resolution.</span></li>
                    @endif
                </ul>
            @else
                <div class="text-center py-4 muted">
                    <p class="mb-1">No resolution details recorded yet.</p>
                    <p class="mb-0">Resolution details will appear once the concern is resolved or closed.</p>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Performance Evaluation --}}
    @php
        $canAdminAssess = auth()->user()->isAdmin();
        $isAdminConcern = $concern->isAdminConcern();
        $isResolved = $concern->isResolved();
        $isClosed = $concern->isClosed();
        $evaluation = isset($evaluation) ? $evaluation : null;
        $evalScore = $evaluation ? $evaluation->overall_score : null;
        $evalGrade = $evaluation ? $evaluation->performance_grade : null;
        $evalStatus = $evaluation ? $evaluation->evaluation_status : null;
        $assessmentBadge = $evaluation ? $evaluation->aiAssessmentBadge() : null;

        $scorePercent = $evalScore !== null ? round($evalScore) : 0;
        $strokeDasharray = 326.7;
        $strokeDashoffset = $scorePercent !== 0 ? round(326.7 - (326.7 * $scorePercent / 100), 1) : 326.7;
        $strokeColor = $evalScore !== null ? ($evalScore >= 75 ? '#22c55e' : ($evalScore >= 50 ? '#f59e0b' : '#ef4444')) : '#e2e8f0';
    @endphp

    @if ($evaluation && ($isEvaluationOwner || $canAdminAssess))
    <div class="eval-card" id="performance-evaluation">
        <div class="eval-card-head">
            <h2 class="eval-card-title">
                {{ $isEvaluationOwner ? 'My Performance Evaluation' : 'Performance Evaluation' }}
            </h2>
            <div class="eval-badge-group">
                @if ($assessmentBadge)
                <span class="eval-badge {{ $assessmentBadge['class'] }}">{{ $assessmentBadge['label'] }}</span>
                @endif
                @if ($evalGrade)
                <span class="eval-badge grade">{{ $evalGrade }}</span>
                @endif
            </div>
        </div>
        <div class="eval-card-body">

            {{-- Row 1: Score Summary + Score Sheet Integration --}}
            <div class="eval-row-1">
                {{-- Left: Score Summary --}}
                <div class="eval-score-panel">
                    <div class="evaluation-score-summary">
                        <div class="score-circle-wrapper">
                            <svg class="eval-score-circle-svg" width="120" height="120" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10"/>
                                <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $strokeColor }}" stroke-width="10" stroke-dasharray="{{ $strokeDasharray }}" stroke-dashoffset="{{ $strokeDashoffset }}" stroke-linecap="round"/>
                                <text x="60" y="66" style="font-family: var(--font-head);" font-size="24" font-weight="800" text-anchor="middle" fill="var(--navy)">{{ number_format($evalScore, 1) }}</text>
                            </svg>
                        </div>
                        <div class="score-value-label">/100</div>
                        <div class="score-classification">{{ $evalGrade }}</div>
                        <div class="score-integration-status">{{ $assessmentBadge ? $assessmentBadge['label'] : ucfirst(str_replace('_', ' ', $evalStatus)) }}</div>
                        @if ($evaluation->assessment_source)
                        <div class="score-assessment-method">{{ \App\Models\StaffTaskEvaluation::ASSESSMENT_SOURCES[$evaluation->assessment_source] ?? $evaluation->assessment_source }}</div>
                        @endif
                    </div>
                </div>

                {{-- Score Sheet integration panel --}}
                <div class="eval-sheet-panel">
                        <div class="eval-sheet-head">
                            <p class="eval-sheet-label">Score Sheet Integration</p>
                            <p class="eval-sheet-period">{{ $evaluation->evaluationPeriod?->name ?? 'No evaluation period' }}</p>
                        </div>

                        @if ($evaluation->isIncludedInScoreSheet())
                        <p class="eval-sheet-note eval-sheet-note--added">
                            <span class="eval-sheet-badge badge-score-added">ADDED TO SCORE SHEET</span>
                            Added to {{ $evaluation->sourceLabel() }} #{{ $evaluation->source_id }} Score Sheet
                            on {{ $evaluation->score_sheet_added_at?->format('F j, Y') }}.
                        </p>
                        @elseif ($evaluation->evaluation_status === \App\Models\StaffTaskEvaluation::STATUS_REVERSED)
                        <p class="eval-sheet-note eval-sheet-note--reversed">
                            <span class="eval-sheet-badge badge-score-reversed">REVERSED / EXCLUDED</span>
                            Excluded from the Score Sheet.
                            @if ($evaluation->score_sheet_reversal_reason)
                                Reason: {{ $evaluation->score_sheet_reversal_reason }}
                            @endif
                        </p>
                        @elseif ($evaluation->isAwaitingAdminReview())
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-review">FOR ADMIN REVIEW</span>
                            Awaiting admin review. Use the actions below to accept, adjust, or finalize this assessment.
                        </p>
                        @elseif ($evaluation->overall_score !== null)
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-pending">PENDING INTEGRATION</span>
                            Evaluation is finalized but not yet added to the Score Sheet.
                        </p>
                        @else
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-pending">EVALUATION PENDING</span>
                            Not yet added to the Score Sheet.
                        </p>
                        @endif
                    </div>
            </div>

            {{-- Evaluation dates --}}
            <div class="eval-dates">
                <div class="eval-date-item">
                    <p class="eval-date-label">Submitted</p>
                    <p class="eval-date-value">{{ $evaluation->submitted_at?->format('F j, Y') ?? '—' }}</p>
                </div>
                <div class="eval-date-item">
                    <p class="eval-date-label">Deadline</p>
                    <p class="eval-date-value">{{ $evaluation->deadline_at?->format('F j, Y') ?? '—' }}</p>
                </div>
                <div class="eval-date-item">
                    <p class="eval-date-label">Completed</p>
                    <p class="eval-date-value">{{ $evaluation->completed_at?->format('F j, Y') ?? '—' }}</p>
                </div>
            </div>

            {{-- Criterion breakdown table --}}
            <table class="eval-criterion-table">
                <thead>
                    <tr>
                        <th>Criterion</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">Weight</th>
                        <th class="text-end">Weighted Points</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="criterion-name">Accomplishment Quality</td>
                        <td class="text-end">{{ $evaluation->accomplishment_quality_score ? number_format($evaluation->accomplishment_quality_score, 2) : '—' }}/100</td>
                        <td class="text-end">50%</td>
                        <td class="text-end">{{ $evaluation->accomplishment_quality_score ? number_format($evaluation->accomplishment_quality_score * 0.50, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Timeliness</td>
                        <td class="text-end">{{ $evaluation->timeliness_score ? number_format($evaluation->timeliness_score, 2) : '—' }}/100</td>
                        <td class="text-end">20%</td>
                        <td class="text-end">{{ $evaluation->timeliness_score ? number_format($evaluation->timeliness_score * 0.20, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Immediate Responsiveness</td>
                        <td class="text-end">{{ $evaluation->immediate_responsiveness_score ? number_format($evaluation->immediate_responsiveness_score, 2) : '—' }}/100</td>
                        <td class="text-end">20%</td>
                        <td class="text-end">{{ $evaluation->immediate_responsiveness_score ? number_format($evaluation->immediate_responsiveness_score * 0.20, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Client Feedback</td>
                        <td class="text-end na">N/A</td>
                        <td class="text-end">10%</td>
                        <td class="text-end na">N/A</td>
                    </tr>
                    <tr class="total-row">
                        <td class="criterion-name">Overall</td>
                        <td class="text-end">{{ $evalScore ? number_format($evalScore, 2) : '—' }}/100</td>
                        <td class="text-end">100%</td>
                        <td class="text-end">{{ $evalScore ? number_format($evalScore, 2) : '—' }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Assessment explanation --}}
            @if ($evaluation->assessment_explanation)
            <div class="pri-eval-note">
                <strong>Assessment:</strong> {{ $evaluation->assessment_explanation }}
            </div>
            @endif

            @if ($evaluation->adjustment_reason)
            <div class="pri-eval-note">
                <strong>Admin note:</strong> {{ $evaluation->adjustment_reason }}
            </div>
            @endif

            {{-- Admin actions --}}
            @if ($canAdminAssess)
            <div class="pri-eval-actions" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-subtle);">

                @if ($evaluation->isAwaitingAdminReview() || $evalScore === null)
                    {{-- Generate / Re-assess --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.store', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Generate AI Assessment?', message: 'This will run an AI-assisted assessment of the Admin Concern and store it as a provisional evaluation pending your review.', confirmLabel: 'Generate Assessment' });">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6M4 9h4M16 9h4"/></svg>
                            Generate AI Assessment
                        </button>
                    </form>

                @elseif ($evaluation->isProvisionalAssessment())
                    {{-- Adjust and Finalize --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.adjust', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Adjust Assessment?', message: 'Changing scores will mark this evaluation as adjusted and re-synchronize it to the Score Sheet.', confirmLabel: 'Adjust Assessment' });">
                        @csrf
                        @method('PATCH')
                        <div class="form-group d-inline" style="margin-right: 12px;">
                            <label class="form-label" style="margin-bottom: 4px;">Adjusted Score</label>
                            <input type="number" name="overall_score" class="form-control form-control-sm" step="0.01" min="0" max="100" value="{{ number_format($evalScore, 2) }}" style="width: 90px; display: inline-block;">
                        </div>
                        <div class="form-group d-inline" style="margin-right: 12px;">
                            <label class="form-label" style="margin-bottom: 4px;">Reason</label>
                            <input type="text" name="adjustment_reason" class="form-control form-control-sm" placeholder="Reason for adjustment" style="width: 200px; display: inline-block;" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">Adjust Score</button>
                    </form>

                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.finalize', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Finalize Assessment?', message: 'This will finalize the evaluation and add it to the staff Score Sheet. The score cannot be adjusted afterward without reversal.', confirmLabel: 'Finalize Assessment' });">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Finalize Assessment</button>
                    </form>

                @elseif ($evalStatus === \App\Models\StaffTaskEvaluation::STATUS_FINALIZED)
                    {{-- Reverse finalized --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.evaluation.reverse', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Reverse Evaluation?', message: 'This will remove the evaluation from the Score Sheet and mark it as REVERSED / EXCLUDED. The evaluation history will be kept.', confirmLabel: 'Reverse Evaluation', danger: true });">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Reverse Evaluation</button>
                    </form>
                @endif

                {{-- Always allow reverse if not reversed --}}
                @if ($evalStatus !== \App\Models\StaffTaskEvaluation::STATUS_REVERSED
                    && $evalStatus !== \App\Models\StaffTaskEvaluation::STATUS_AWAITING_REVIEW)
                    <form method="POST" action="{{ route('admin.kaizen-concerns.evaluation.reverse', $concern) }}"
                          class="d-inline"
                          style="margin-left: 12px;"
                          onsubmit="return egliane.confirm.form(this, { title: 'Reverse / Exclude from Score Sheet?', message: 'This will remove the evaluation from the Score Sheet. The evaluation history will be kept.', confirmLabel: 'Reverse', danger: true });">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Reverse</button>
                    </form>
                @endif
            </div>
            @endif

        </div>
    </div>
    @endif

    @if (!$evaluation && $canAdminAssess && $isAdminConcern)
    {{-- Admin can generate an assessment even if none exists --}}
    <div class="eval-card" id="performance-evaluation">
        <div class="eval-card-head">
            <h2 class="eval-card-title">Performance Evaluation</h2>
            <span class="eval-badge pending">EVALUATION PENDING</span>
        </div>
        <div class="eval-card-body">
            <p class="muted">No performance evaluation has been generated for this Admin Concern yet.</p>
            <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.store', $concern) }}"
                  onsubmit="return egliane.confirm.form(this, { title: 'Generate AI Assessment?', message: 'This will run an AI-assisted assessment of the Admin Concern based on its challenge, progress updates, and evidence.', confirmLabel: 'Generate Assessment' });">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6M4 9h4M16 9h4"/></svg>
                    Generate AI Assessment
                </button>
            </form>
        </div>
    </div>
    @endif
    </div>

    {{-- Confirmation modal for delete actions --}}
    @include('components.confirm-modal')
@endsection

@push('scripts')
<script>
    // Handle checklist item toggle via AJAX
    document.addEventListener('change', function(e) {
        if (e.target.matches('[data-checklist-item]')) {
            const checkbox = e.target;
            const itemId = checkbox.dataset.checklistItem;
            const url = checkbox.dataset.url;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ checklist_item_id: itemId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Unable to update the checklist item.');
                }

                return response.json();
            })
            .then(data => {
                if (!data.status) {
                    throw new Error(data.message || 'Unable to update the checklist item.');
                }

                // Reload after the persisted update so every status/detail cell stays in sync.
                window.location.reload();
            })
            .catch(err => {
                console.error('Error toggling checklist:', err);
                checkbox.checked = !checkbox.checked; // revert on error
            });
        }
    });

    @if ($concern->canManageChecklist(auth()->user()))
    // Populate the edit checklist modal with the row that was clicked.
    document.addEventListener('show.bs.modal', function (event) {
        if (! event.target || event.target.id !== 'editChecklistModal') {
            return;
        }

        const button = event.relatedTarget;
        const idField = document.getElementById('edit_checklist_item_id');
        const titleField = document.getElementById('edit_checklist_title');

        if (button && idField) {
            idField.value = button.dataset.checklistId || '';
        }
        if (button && titleField) {
            titleField.value = button.dataset.checklistTitle || '';
        }
    });
    @endif

    // Set the resolution form action and open the confirm dialog for the
    // chosen outcome (Resolved or Closed Without Resolution).
    function setResolutionFormAction(btn, actionUrl, title, message, confirmLabel, danger) {
        var form = document.getElementById('resolution-action-form');
        if (!form) return;
        form.action = actionUrl;
        egliane.confirm.form(form, {
            title: title,
            message: message,
            confirmLabel: confirmLabel,
            danger: danger,
        });
    }

    // AJAX delete for evidence cards. Confirms via the shared modal, then
    // submits via fetch and removes the card from the DOM on success.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList.contains('kaizen-evidence-delete-form')) {
            return;
        }
        e.preventDefault();

        var btn = form.querySelector('.kaizen-evidence-delete-btn');
        var card = form.closest('.kaizen-evidence-card');
        var notifications = document.getElementById('kaizen-evidence-notifications');

        // Show loading state
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner" style="width:14px;height:14px;border:2px solid #ffffff;border-radius:50%;border-top-color:#transparent;border-top-color:#ffffff;animation:spin 0.8s linear infinite;display:inline-block;margin-right:6px;"></span>Deleting...';

        // Confirm via the shared modal, then submit via AJAX
        egliane.confirm.action({
            title: form.dataset.confirmTitle || 'Remove this evidence?',
            message: form.dataset.confirmMessage || 'This file will be permanently removed.',
            confirmLabel: form.dataset.confirmLabel || 'Remove',
            danger: form.dataset.confirmDanger === 'true'
        }, function () {
            var formData = new FormData(form);

            fetch(form.action, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                // Success: remove the card and show a notification
                if (card) {
                    card.style.opacity = '0';
                    setTimeout(function () { card.remove(); }, 300);
                }
                showEvidenceNotification(data.message || 'Evidence removed.', 'success');
            })
            .catch(function (error) {
                console.error('Evidence delete failed:', error);
                showEvidenceNotification('Failed to delete evidence. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Delete';
            });
        });
    });

    function showEvidenceNotification(message, type) {
        var container = document.getElementById('kaizen-evidence-notifications');
        if (!container) return;

        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? '&#10003;' : '&#10007;';

        var el = document.createElement('div');
        el.className = 'alert ' + alertClass + ' alert-dismissible fade show kaizen-evidence-notification';
        el.role = 'alert';
        el.innerHTML = '<span aria-hidden="true">' + icon + '</span>&nbsp;' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';

        container.appendChild(el);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 5000);
    }
</script>
@endpush
