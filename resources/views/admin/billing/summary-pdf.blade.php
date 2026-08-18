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

    @php
        $allFormTypes = $allFormTypes ?? [];
        $totalCols = 1 + count($allFormTypes) + 1 + count($allFormTypes) + 1 + 1;
        $colWidth = round(100 / $totalCols, 1);
    @endphp

    <table class="masterlist">
        <colgroup>
            <col width="{{ $colWidth * 1.5 }}%">
            @foreach ($allFormTypes as $ft)
                <col width="{{ $colWidth }}%">
            @endforeach
            <col width="{{ $colWidth }}%">
            @foreach ($allFormTypes as $ft)
                <col width="{{ $colWidth }}%">
            @endforeach
            <col width="{{ $colWidth }}%">
            <col width="{{ $colWidth }}%">
        </colgroup>
        <thead>
            <tr>
                <th>Client</th>
                @foreach ($allFormTypes as $ft)
                    <th>{{ $ft }} (BIR)</th>
                @endforeach
                <th>Cash In</th>
                @foreach ($allFormTypes as $ft)
                    <th>Fee — {{ $ft }}</th>
                @endforeach
                <th>Bookkeeping</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($billings as $billing)
                @php
                    $client = $billing->client;
                    $lineItems = $billing->lineItems;
                @endphp
                <tr>
                    <td>{{ $client?->business_name ?: $client?->name ?? '' }}</td>
                    @foreach ($allFormTypes as $ft)
                        @php $item = $lineItems->where('category', \App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE)->where('form_type', $ft)->first(); @endphp
                        <td class="text-right">{{ $item ? number_format($item->amount, 2) : '—' }}</td>
                    @endphp
                    @php $cashIn = $lineItems->where('category', \App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE)->whereNull('form_type')->first(); @endphp
                    <td class="text-right">{{ $cashIn ? number_format($cashIn->amount, 2) : '—' }}</td>
                    @foreach ($allFormTypes as $ft)
                        @php $item = $lineItems->where('category', \App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE)->where('form_type', $ft)->first(); @endphp
                        <td class="text-right">{{ $item ? number_format($item->amount, 2) : '—' }}</td>
                    @endforeach
                    @php $book = $lineItems->where('category', \App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE)->first(); @endphp
                    <td class="text-right">{{ $book ? number_format($book->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ number_format($billing->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $totalCols }}" class="empty-cell">No billing records found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
