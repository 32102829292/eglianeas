@php
    $categories = [
        \App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE => 'BIR REMITTANCES',
        \App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE => 'PROFESSIONAL FEES',
        \App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE => 'BOOKKEEPING',
        \App\Models\BillingLineItem::CATEGORY_POST_CLOSING_TB => 'POST-CLOSING TB',
        \App\Models\BillingLineItem::CATEGORY_INVENTORY_LIST => 'INVENTORY LIST',
        \App\Models\BillingLineItem::CATEGORY_OTHER_ATTACHMENT => 'OTHER ATTACHMENT',
        \App\Models\BillingLineItem::CATEGORY_DATA_ENTRY => 'DATA ENTRY',
    ];

    $payments = $payments ?? \App\Support\BillingPaymentDetails::forPdf();
    $gcashNumber = (string) ($payments['gcash_number'] ?? '');
    $gcashQr = $payments['gcash_qr'] ?? null;
    $bankAccounts = $payments['banks'] ?? [];

    // DomPDF core fonts cannot render the peso glyph; use Php notation.
    $peso = fn ($value) => 'Php '.number_format((float) ($value ?? 0), 2);

    $paperSize = ($paperSize ?? 'a4') === 'letter' ? 'letter' : 'a4';
    $rowsPerPage = 4;
    // Fixed equal-height slots (label-sheet grid). Computed by the controller
    // from the selected paper size: page height minus @page margins, divided
    // by rows per page. Payment details render inside each cell.
    $rowSlotMm = $rowSlotMm ?? round(([297.0, 279.4][$paperSize === 'letter' ? 1 : 0] - 20) / $rowsPerPage, 2);
    $density = $density ?? 'normal';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Billing Statements</title>
    <style>
        @page {
            /* Must mirror the controller's paper selection — an explicit size here
               would override DomPDF's setPaper(). */
            size: {{ $paperSize === 'letter' ? 'Letter' : 'A4' }} portrait;
            margin: 10mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* DomPDF starts stacked block content ~6.5mm above the declared @page
           margin; pad the body so the grid begins at the full 10mm margin. */
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #111; padding-top: 10.2mm; }
        table { width: 100%; border-collapse: collapse; }

        /* Label-sheet grid: each statement renders as one row of two bordered,
           exactly equal cells (Taxpayer's Copy + Egliane's Copy). Four rows per
           page = 8 receipts. Cells are fixed-height slots; borders on the tds
           form the grid lines (border-collapse merges the centre divider).
           Spacing must live INSIDE cells — DomPDF carries bottom margins across
           forced page breaks, which pushed following content off-page. */
        .pair-wrap {
            width: 100%;
            page-break-inside: avoid;
        }
        .pair-wrap.new-page { page-break-before: always; }
        .pair {
            width: 100%;
        }

        .cell {
            width: 50%;
            border: 1pt solid #333;
            padding: 1.5mm 2mm;
            vertical-align: top;
        }

        /* Fixed-height content window. The height lives here, NOT on the td:
           DomPDF treats td height as a minimum, so oversized statements grew
           the row (+~3.5mm per row, cascading) and spilled past the borders.
           A block div with overflow:hidden cannot exceed its box. */
        .slot {
            height: {{ round($rowSlotMm - 3.7, 2) }}mm;
            overflow: hidden;
        }

        .copy-tag {
            text-align: right;
            font-size: 5.8pt;
            font-weight: bold;
            letter-spacing: .6pt;
            text-transform: uppercase;
            color: #999;
            padding-bottom: 1.5pt;
        }

        .head-row td { vertical-align: top; padding-bottom: 2pt; }
        .brand { font-size: 7.6pt; font-weight: bold; color: #1B1B3A; letter-spacing: .4pt; }
        .period { font-size: 6.4pt; color: #555; }
        .client { font-weight: bold; font-size: 7.8pt; color: #1B1B3A; text-transform: uppercase; }
        .paid-stamp {
            display: inline-block;
            border: 1.4pt solid #c0392b;
            color: #c0392b;
            font-size: 7.2pt;
            font-weight: bold;
            letter-spacing: 1pt;
            padding: 1pt 4pt;
            transform: rotate(-8deg);
        }

        .section-title { font-size: 5.9pt; font-weight: bold; color: #777; letter-spacing: .5pt; padding-top: 2pt; }
        .item-row td { padding: .7pt 0; border-bottom: .3pt dotted #d5dade; }
        .amount { text-align: right; white-space: nowrap; width: 48pt; }

        .total-row td { border-top: 1.2pt solid #1B1B3A; padding-top: 2.5pt; font-weight: bold; font-size: 7.8pt; color: #1B1B3A; }

        .sign-row td { padding-top: 3pt; color: #333; }
        .signer { font-weight: bold; color: #1B1B3A; }
        .note { font-size: 6.2pt; color: #666; }

        /* Density tiers applied uniformly to the whole sheet when statements
           would not fit their fixed slots at full size. */
        .compact { font-size: 6.4pt; }
        .compact .brand { font-size: 6.9pt; }
        .compact .client { font-size: 7pt; }
        .compact .period { font-size: 5.8pt; }
        .compact .section-title { font-size: 5.3pt; padding-top: 1.5pt; }
        .compact .item-row td { padding: .5pt 0; }
        .compact .total-row td { font-size: 7pt; padding-top: 2pt; }
        .compact .sign-row td { padding-top: 2pt; }
        .compact .note { font-size: 5.6pt; }

        .tiny { font-size: 5.7pt; }
        .tiny .brand { font-size: 6.2pt; }
        .tiny .client { font-size: 6.4pt; }
        .tiny .period { font-size: 5.2pt; }
        .tiny .copy-tag { font-size: 5pt; }
        .tiny .section-title { font-size: 4.8pt; padding-top: 1pt; }
        .tiny .item-row td { padding: .3pt 0; }
        .tiny .amount { width: 40pt; }
        .tiny .total-row td { font-size: 6.3pt; padding-top: 1.5pt; }
        .tiny .sign-row td { padding-top: 1.5pt; }
        .tiny .note { font-size: 5pt; }

        /* Payment details rendered INSIDE each receipt cell (once per copy),
           compact so it fits the fixed slot. QRs sit on the right edge of the
           cell, text lines on the left, so nothing crosses the centre divider. */
        .cell-payments { border-top: .6pt solid #d5dade; margin-top: 1.6mm; padding-top: 1.2mm; }
        .cell-payments-title {
            font-weight: bold;
            color: #1B1B3A;
            letter-spacing: .5pt;
            text-transform: uppercase;
            font-size: 6pt;
            padding-bottom: .8pt;
        }
        .cell-pmethod { width: 100%; border-collapse: collapse; }
        .cell-pmethod td { vertical-align: middle; }
        .cell-pinfo { text-align: left; padding-right: 2mm; }
        .cell-pline { font-size: 6.2pt; color: #444; padding: .6pt 0; }
        .cell-pline b { color: #1B1B3A; letter-spacing: .4pt; }
        .cell-pqr { text-align: right; white-space: nowrap; }
        .cell-pqr img { width: 8mm; height: 8mm; margin-left: 1.6mm; }
        .cell-oversize { font-size: 5.6pt; color: #c0392b; font-weight: bold; padding-top: .8mm; }
    </style>
</head>
<body>

    @forelse ($billings as $billing)
        <div class="pair-wrap {{ ! $loop->first && ($loop->iteration - 1) % $rowsPerPage === 0 ? 'new-page' : '' }}">
        <table class="pair">
            <tr>
                <td class="cell">
                    <div class="slot">
                        @include('admin.billing.partials.statement-cell', [
                            'copyLabel' => "Taxpayer's Copy",
                            'payments' => $payments,
                            'gcashNumber' => $gcashNumber,
                            'gcashQr' => $gcashQr,
                            'bankAccounts' => $bankAccounts,
                            'overflowIds' => $overflowIds ?? [],
                        ])
                    </div>
                </td>
                <td class="cell">
                    <div class="slot">
                        @include('admin.billing.partials.statement-cell', [
                            'copyLabel' => "Egliane Accounting Services' Copy",
                            'payments' => $payments,
                            'gcashNumber' => $gcashNumber,
                            'gcashQr' => $gcashQr,
                            'bankAccounts' => $bankAccounts,
                            'overflowIds' => $overflowIds ?? [],
                        ])
                    </div>
                </td>
            </tr>
        </table>
        </div>
    @empty
        <p>No statements selected.</p>
    @endforelse

</body>
</html>
