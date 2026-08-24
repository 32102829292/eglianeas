@php
    $categories = [
        \App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE => 'BIR REMITTANCES',
        \App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE => 'PROFESSIONAL FEES',
        \App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE => 'BOOKKEEPING',
    ];

    $paymentBits = [];
    if ($gcashNumber) {
        $paymentBits[] = 'GCash: '.$gcashNumber;
    }
    foreach (($bankAccounts ?? []) as $bank) {
        $bits = array_filter([
            $bank['bank_name'] ?? '',
            ($bank['account_name'] ?? '') ? '('.$bank['account_name'].')' : '',
            $bank['account_number'] ?? '',
        ]);
        if ($bits) {
            $paymentBits[] = implode(' ', $bits);
        }
    }

    // DomPDF core fonts cannot render the peso glyph; use Php notation.
    $peso = fn ($value) => 'Php '.number_format((float) ($value ?? 0), 2);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Billing Statements</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #111; }
        table { width: 100%; border-collapse: collapse; }

        /* Each statement renders as one row of two receipt cells (Taxpayer's + Egliane's copy).
           Three rows per page = 6 receipts; DomPDF has no CSS Grid, so the 2-column
           grid is built with a table, which it paginates reliably. */
        .pair {
            width: 100%;
            page-break-inside: avoid;
            margin-bottom: 5pt;
        }
        .pair.new-page { page-break-before: always; }

        .cell { width: 50%; vertical-align: top; padding: 0 3mm; }
        .cell.first { padding-left: 0; }
        .cell.second {
            padding-right: 0;
            border-left: 1pt dashed #9aa3ad; /* cutting guide between copies */
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

        .batch-footer {
            position: fixed;
            bottom: -0.5mm;
            left: -10mm;
            right: -10mm;
            font-size: 6.4pt;
            color: #444;
            border-top: 1.2pt solid #1B1B3A;
            padding: 2mm 10mm 0;
            background: #fff;
        }
        .batch-footer b { color: #1B1B3A; letter-spacing: .5pt; }
    </style>
</head>
<body>

    @forelse ($billings as $billing)
        <table class="pair {{ ! $loop->first && ($loop->iteration - 1) % 3 === 0 ? 'new-page' : '' }}">
            <tr>
                <td class="cell first">
                    @include('admin.billing.partials.statement-cell', ['copyLabel' => "Taxpayer's Copy"])
                </td>
                <td class="cell second">
                    @include('admin.billing.partials.statement-cell', ['copyLabel' => "Egliane Accounting Services' Copy"])
                </td>
            </tr>
        </table>
    @empty
        <p>No statements selected.</p>
    @endforelse

    @if (! empty($paymentBits))
        <div class="batch-footer">
            <b>PAYMENT DETAILS</b> &nbsp;&mdash;&nbsp; {{ implode(' &nbsp;&middot;&nbsp; ', $paymentBits) }}
        </div>
    @endif

</body>
</html>
