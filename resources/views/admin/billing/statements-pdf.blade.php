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
            margin: 8mm 11mm 17mm 11mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7.6pt; color: #111; }
        table { width: 100%; border-collapse: collapse; }

        .stmt { page-break-inside: avoid; width: 100%; padding: 4pt 0 5pt; }
        .stmt + .stmt { border-top: 1px dashed #9aa3ad; }
        .page-break { page-break-after: always; }

        .head-row td { vertical-align: top; padding-bottom: 2pt; }
        .brand { font-size: 8.4pt; font-weight: bold; color: #1B1B3A; letter-spacing: .5pt; }
        .period { font-size: 7.2pt; color: #555; }
        .client { font-weight: bold; font-size: 8.6pt; color: #1B1B3A; text-transform: uppercase; }
        .paid-stamp {
            display: inline-block;
            border: 1.6pt solid #c0392b;
            color: #c0392b;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 1pt;
            padding: 1pt 5pt;
            transform: rotate(-8deg);
        }

        .section-title { font-size: 6.4pt; font-weight: bold; color: #777; letter-spacing: .6pt; padding-top: 2pt; }
        .item-row td { padding: .8pt 0; border-bottom: .3pt dotted #d5dade; }
        .amount { text-align: right; white-space: nowrap; width: 62pt; }

        .total-row td { border-top: 1.2pt solid #1B1B3A; padding-top: 2.5pt; font-weight: bold; font-size: 8.6pt; color: #1B1B3A; }

        .sign-row td { padding-top: 3pt; color: #333; }
        .signer { font-weight: bold; color: #1B1B3A; }
        .note { font-size: 6.8pt; color: #666; }

        .batch-footer {
            position: fixed;
            bottom: -0.5mm;
            left: -11mm;
            right: -11mm;
            font-size: 6.6pt;
            color: #444;
            border-top: 1.2pt solid #1B1B3A;
            padding: 2.5mm 11mm 0;
            background: #fff;
        }
        .batch-footer b { color: #1B1B3A; letter-spacing: .5pt; }
    </style>
</head>
<body>

    @forelse ($billings as $billing)
        <table class="stmt {{ ($loop->iteration + 1) % 6 === 0 ? 'page-break' : '' }}">
            <tr class="head-row">
                <td>
                    <span class="brand">EGLIANE ACCOUNTING SERVICES</span><br>
                    <span class="client">{{ $billing->client?->business_name ?: $billing->client?->name }}</span>
                </td>
                <td class="amount">
                    @if ($billing->isPaid())
                        <span class="paid-stamp">PAID</span><br>
                    @endif
                    <span class="period">BILLING STATEMENT<br>{{ mb_strtoupper($billing->period_label) }}</span>
                </td>
            </tr>
            @foreach ($categories as $category => $title)
                @php($items = $billing->lineItems->where('category', $category)->filter(fn ($i) => (float) $i->amount != 0.0))
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
            <tr class="total-row">
                <td>TOTAL AMOUNT</td>
                <td class="amount">{{ $peso($billing->total) }}</td>
            </tr>
            <tr class="sign-row">
                <td>
                    <span class="signer">HARRIS EGLIANE, CPA</span>
                    @if ($billing->isPaid())
                        <span class="note"> &middot; Paid {{ $billing->paid_at?->format('M j, Y') }}</span>
                    @elseif ($billing->due_date)
                        <span class="note"> &middot; Due {{ $billing->due_date->format('M j, Y') }}</span>
                    @endif
                </td>
                <td class="amount note">Ref #{{ str_pad((string) $billing->id, 5, '0', STR_PAD_LEFT) }}</td>
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
