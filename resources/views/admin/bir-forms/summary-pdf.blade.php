<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BIR Forms Summary — Egliane Accounting Services</title>
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
        table.masterlist td.text-center { text-align: center; }
        table.masterlist td.text-right { text-align: right; }
        table.masterlist tbody tr:nth-child(even) td { background: #f3f4f6; }

        .check { color: #16a34a; font-weight: 700; }
        .empty-cell { text-align: center; padding: 20px; font-style: italic; color: #6B7280; }

        @page { size: A4 landscape; margin: 10mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>BIR Forms Summary &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <table class="masterlist">
        <colgroup>
            <col width="4.5%">
            <col width="5.5%">
            <col width="6.5%">
            <col width="5%">
            <col width="6%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
            <col width="3.5%">
        </colgroup>
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Client Name</th>
                <th>Business Name</th>
                <th>Business Type</th>
                <th>Line of Business</th>
                @foreach ($formTypes as $ft)
                    <th class="text-center">{{ $ft }}</th>
                @endforeach
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                @php
                    $client = $entry['user'];
                    $p = $entry['profile'];
                    $statuses = $entry['statuses'];
                @endphp
                <tr>
                    <td>{{ $client->client_code ?? '' }}</td>
                    <td>{{ $client->name }}</td>
                    <td>{{ $client->business_name ?? '' }}</td>
                    <td>{{ $p?->business_type ?? '' }}</td>
                    <td>{{ $p?->line_of_business ?? '' }}</td>
                    @foreach ($formTypes as $ft)
                        <td class="text-center">
                            @if ($statuses[$ft] ?? false)
                                <span class="check">&#10003;</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center">{{ $entry['applicableCount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + count($formTypes) + 1 }}" class="empty-cell">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
