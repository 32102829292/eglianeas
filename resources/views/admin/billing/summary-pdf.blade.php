<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Summary — Egliane Accounting Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 7px; color: #1B1B3A; line-height: 1.25; }

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

        @page { size: A4 landscape; margin: 10mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>{{ $periodLabel }} &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <table class="masterlist">
        <colgroup>
            <col width="12%">
            <col width="8%">
            <col width="8%">
            <col width="8%">
            <col width="8%">
            <col width="6%">
            <col width="8%">
            <col width="8%">
            <col width="8%">
            <col width="12%">
            <col width="8%">
        </colgroup>
        <thead>
            <tr>
                <th>Name</th>
                <th>Sales</th>
                <th>2551Q (BIR)</th>
                <th>1701Q (BIR)</th>
                <th>Cash In</th>
                <th>Rate</th>
                <th>Fees</th>
                <th>2551Q &mdash; Amount</th>
                <th>1701Q &mdash; Amount</th>
                <th>Bookkeeping / Post-Closing TB</th>
                <th>Amount (Total)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($billings as $billing)
                @php
                    $client = $billing->client;
                    $rate = (float) $billing->rate_2551q;
                    $fees = (float) $billing->fee_2551q + (float) $billing->fee_1701q;
                @endphp
                <tr>
                    <td>{{ $client?->business_name ?: $client?->name ?? '' }}</td>
                    <td class="text-right">{{ number_format($billing->sales, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->tax_2551q, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->tax_1701q, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->cash_in, 2) }}</td>
                    <td class="text-right">{{ $rate > 0 ? $rate . '%' : '' }}</td>
                    <td class="text-right">{{ number_format($fees, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->fee_2551q, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->fee_1701q, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->fee_bookkeeping, 2) }}</td>
                    <td class="text-right">{{ number_format($billing->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty-cell">No billing records found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
