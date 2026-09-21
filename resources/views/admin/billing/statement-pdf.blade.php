@php
    $grouped = $billing->lineItems->groupBy('category');
    $categories = [
        \App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE => 'BIR REMITTANCES',
        \App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE => 'PROFESSIONAL FEES (FILING)',
        \App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE => 'BOOKKEEPING',
        \App\Models\BillingLineItem::CATEGORY_POST_CLOSING_TB => 'POST-CLOSING TRIAL BALANCE',
        \App\Models\BillingLineItem::CATEGORY_INVENTORY_LIST => 'INVENTORY LIST (NOTARIZED)',
        \App\Models\BillingLineItem::CATEGORY_OTHER_ATTACHMENT => 'OTHER ATTACHMENT',
        \App\Models\BillingLineItem::CATEGORY_DATA_ENTRY => 'DATA ENTRY',
    ];

    $payments = $payments ?? \App\Support\BillingPaymentDetails::forPdf();
    $gcashNumber = (string) ($payments['gcash_number'] ?? '');
    $gcashQr = $payments['gcash_qr'] ?? null;
    $bankAccounts = $payments['banks'] ?? [];

    // DomPDF core fonts cannot render the peso glyph; use Php notation.
    $peso = fn ($value) => 'Php '.number_format((float) ($value ?? 0), 2);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Billing Statement — {{ $billing->period_label }}</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 16mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        table { width: 100%; border-collapse: collapse; }

        .head { text-align: center; border-bottom: 2pt solid #1B1B3A; padding-bottom: 10pt; margin-bottom: 12pt; }
        .brand { font-size: 14pt; font-weight: bold; color: #1B1B3A; letter-spacing: .8pt; }
        .period { font-size: 10.5pt; color: #555; margin-top: 2pt; }

        .client { font-weight: bold; font-size: 12.5pt; color: #1B1B3A; text-transform: uppercase; margin-bottom: 14pt; }

        .paid-stamp {
            float: right;
            border: 2.4pt solid #c0392b;
            color: #c0392b;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 1.5pt;
            padding: 3pt 9pt;
        }

        .section-title { font-size: 8pt; font-weight: bold; color: #777; letter-spacing: .8pt; padding-top: 8pt; padding-bottom: 2pt; }
        .item-row td { padding: 3pt 0; border-bottom: .5pt dotted #d5dade; }
        .amount { text-align: right; white-space: nowrap; width: 90pt; font-weight: 600; }

        .total-row td { border-top: 2pt solid #1B1B3A; margin-top: 6pt; padding-top: 7pt; font-weight: bold; font-size: 12pt; color: #1B1B3A; }
        .spacer-row td { height: 10pt; }

        .signer { font-weight: bold; color: #1B1B3A; margin-top: 26pt; }
        .note { font-size: 9pt; color: #666; margin-top: 4pt; }

        .payments { border-top: 1pt solid #d5dade; margin-top: 20pt; padding-top: 8pt; }
        .payments .section-title { padding-top: 0; }
        .pay-method { width: 100%; border-collapse: collapse; margin-top: 4pt; }
        .pay-method td { padding: 4pt 0 6pt; border-bottom: .5pt dotted #d5dade; vertical-align: middle; }
        .pay-info { padding-right: 8pt; text-align: left; }
        .pay-label { font-size: 9.5pt; font-weight: bold; color: #1B1B3A; }
        .pay-detail { font-size: 9pt; color: #444; padding-top: 1pt; }
        .pay-number { font-size: 9pt; color: #333; padding-top: 1pt; }
        .pay-qr-cell { width: 62pt; text-align: right; }
        .pay-qr { width: 58pt; height: 58pt; }
    </style>
</head>
<body>

    <div class="head">
        <div class="brand">EGLIANE ACCOUNTING SERVICES</div>
        <div class="period">BILLING STATEMENT · {{ mb_strtoupper($billing->period_label) }}</div>
    </div>

    @if ($billing->isPaid())
        <span class="paid-stamp">PAID</span>
    @endif

    <div class="client">{{ $billing->client?->business_name ?: $billing->client?->name }}</div>

    <table>
        @foreach ($categories as $category => $title)
            @php($items = $grouped->get($category, collect())->filter(fn ($i) => (float) $i->amount != 0.0))
            @if ($items->isNotEmpty())
                <tr><td colspan="2" class="section-title">{{ $title }}</td></tr>
                @foreach ($items as $item)
                    <tr class="item-row">
                        <td>{{ $item->label }}</td>
                        <td class="amount">{{ $peso($item->amount) }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        <tr class="spacer-row"><td colspan="2"></td></tr>
        <tr class="total-row">
            <td>TOTAL AMOUNT</td>
            <td class="amount">{{ $peso($billing->total) }}</td>
        </tr>
    </table>

    <div class="signer">HARRIS EGLIANE, CPA</div>
    @if ($billing->isPaid())
        <div class="note">Date paid: {{ $billing->paid_at?->format('F j, Y') ?? '—' }} · Ref #{{ str_pad((string) $billing->id, 5, '0', STR_PAD_LEFT) }}</div>
    @elseif ($billing->due_date)
        <div class="note">Due date: {{ $billing->due_date->format('F j, Y') }} · Ref #{{ str_pad((string) $billing->id, 5, '0', STR_PAD_LEFT) }}</div>
    @endif

    @if ($payments['has'])
        <div class="payments">
            <div class="section-title">PAYMENT DETAILS</div>

            @if ($gcashNumber !== '' || $gcashQr !== null)
                <table class="pay-method">
                    <tr>
                        <td class="pay-info">
                            <div class="pay-label">GCash</div>
                            @if ($gcashNumber !== '')
                                <div class="pay-number">{{ $gcashNumber }}</div>
                            @endif
                        </td>
                        <td class="pay-qr-cell">
                            @if ($gcashQr !== null)
                                <img src="{{ $gcashQr }}" class="pay-qr" alt="GCash QR Code">
                            @endif
                        </td>
                    </tr>
                </table>
            @endif

            @foreach ($bankAccounts as $bank)
                <table class="pay-method">
                    <tr>
                        <td class="pay-info">
                            @if (! empty($bank['bank_name']))
                                <div class="pay-label">{{ $bank['bank_name'] }}</div>
                            @endif
                            @if (! empty($bank['account_name']))
                                <div class="pay-detail">{{ $bank['account_name'] }}</div>
                            @endif
                            @if (! empty($bank['account_number']))
                                <div class="pay-number">{{ $bank['account_number'] }}</div>
                            @endif
                        </td>
                        <td class="pay-qr-cell">
                            @if (! empty($bank['qr']))
                                <img src="{{ $bank['qr'] }}" class="pay-qr" alt="{{ $bank['bank_name'] ?: 'Bank' }} QR Code">
                            @endif
                        </td>
                    </tr>
                </table>
            @endforeach
        </div>
    @endif

</body>
</html>
