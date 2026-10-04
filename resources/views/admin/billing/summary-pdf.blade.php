<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Summary — Egliane Accounting Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 8px; color: #1B1B3A; line-height: 1.3; }

        .report-header { text-align: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid #1B1B3A; }
        .report-header h1 { font-size: 13px; color: #1B1B3A; margin-bottom: 2px; }
        .report-header p { font-size: 8px; color: #6B7280; }

        table.masterlist { width: 100%; table-layout: fixed; border-collapse: collapse; }
        table.masterlist th,
        table.masterlist td { padding: 3px 4px; border: 1px solid #d1d5db; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.masterlist th { background: #1B1B3A; color: #fff; font-size: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; text-align: left; }
        table.masterlist td { font-size: 7px; }
        table.masterlist td.text-right { text-align: right; }
        table.masterlist tbody tr:nth-child(even) td { background: #f3f4f6; }

        .empty-cell { text-align: center; padding: 20px; font-style: italic; color: #6B7280; }

        /* Grouped billing summary (.bsum) and receipt summary (.brsum).
           Mirrors the approved web design; DomPDF has no sticky positioning so
           the header simply repeats on each page. */
        .bsum-title { font-size: 11px; color: #1B1B3A; margin: 12px 0 6px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        table.bsum { width: 100%; table-layout: fixed; border-collapse: collapse; }
        table.bsum th, table.bsum td { padding: 4px 3px; border: 0.5pt solid #c9d2dd; vertical-align: middle; overflow-wrap: anywhere; word-wrap: break-word; }
        table.bsum thead { display: table-header-group; }
        table.bsum thead th { font-size: 6.8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .015em; text-align: center; color: #fff; }
        table.bsum thead tr { page-break-after: avoid; }
        table.bsum tr { page-break-inside: avoid; }

        .bsum-group-remit { background: #1E4E8C; color: #fff; }
        .bsum-group-fee { background: #0F766E; color: #fff; }
        .bsum-sub-remit { background: #1E4E8C; }
        .bsum-sub-fee { background: #0F766E; }
        .bsum-subtotal { font-weight: 800; }
        .bsum-single-total, .bsum-single-grand { background: #111827; color: #fff; }
        .bsum-client-head { background: #111827; color: #fff; text-align: left; }

        table.bsum tbody th.bsum-client { text-align: left; font-size: 7.5pt; font-weight: 600; color: #1B1B3A; }
        table.bsum tbody td { font-size: 7.2pt; }
        .bsum-num { text-align: right; }
        .bsum-cell-remit { background: #EAF1FB; color: #1E4E8C; font-weight: 600; }
        .bsum-cell-fee { background: #E7F3F2; color: #0F766E; font-weight: 600; }
        .bsum-cell-total { font-weight: 600; }
        .bsum-cell-grand { background: #F1F3F7; color: #111827; font-weight: 700; }
        .bsum-orphan { color: #9CA3AF; font-style: italic; }

        table.bsum tfoot th, table.bsum tfoot td { border-top: 1.5pt solid #111827; font-weight: 700; font-size: 7.5pt; }
        .bsum-total-label { text-align: left; text-transform: uppercase; letter-spacing: .03em; color: #111827; }
        .bsum-empty { text-align: center; padding: 14px; font-style: italic; color: #6B7280; }

        .brsum-title { font-size: 8px; color: #1B1B3A; margin: 12px 0 3px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        table.brsum-table { width: 100%; max-width: none; table-layout: fixed; border-collapse: collapse; page-break-inside: avoid; }
        table.brsum-table th, table.brsum-table td { padding: 4px 8px; border: 0.5pt solid #c9d2dd; vertical-align: top; }
        table.brsum-table thead th { font-size: 7px; color: #fff; text-transform: uppercase; letter-spacing: .03em; text-align: left; font-weight: 700; }
        table.brsum-table thead .brsum-remit { background: #1E4E8C; }
        table.brsum-table thead .brsum-fee { background: #0F766E; }
        table.brsum-table thead .brsum-grand { background: #111827; }
        table.brsum-table tbody td { font-size: 7.5pt; }
        table.brsum-table tbody th.brsum-client { text-align: left; font-size: 7.5pt; }
        .brsum-num { text-align: right; }
        .brsum-cell-remit { background: #EAF1FB; color: #1E4E8C; font-weight: 600; }
        .brsum-cell-fee { background: #E7F3F2; color: #0F766E; font-weight: 600; }
        .brsum-cell-grand { background: #F1F3F7; color: #111827; font-weight: 700; }
        table.brsum-table tfoot th, table.brsum-table tfoot td { background: #F1F3F7; color: #111827; font-weight: 700; font-size: 7.5pt; border-top: 0.5pt solid #c9d2dd; }
        table.brsum-table tfoot .brsum-grand-row th, table.brsum-table tfoot .brsum-grand-row td { border-top: 1.5pt solid #111827; }

        @page { size: A3 landscape; margin: 8mm 9mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>{{ $periodLabel }} &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @php
        // The controller passes the shared matrix. Building it from $billings
        // keeps the template renderable on its own (no duplicate arithmetic).
        $summaryMatrix = $matrix ?? \App\Support\BillingSummaryMatrix::make(collect($billings));
    @endphp

    <h2 class="bsum-title">Billing Summary</h2>
    @include('admin.billing.partials.summary-table', ['matrix' => $summaryMatrix, 'variant' => 'pdf'])
</body>
</html>
