<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Masterlist — Egliane Accounting Services</title>
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
        table.masterlist tbody tr:nth-child(even) td { background: #f3f4f6; }

        .empty-cell { text-align: center; padding: 20px; font-style: italic; color: #6B7280; }

        @page { size: A4 landscape; margin: 10mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>Client Masterlist &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <table class="masterlist">
        <colgroup>
            <col width="4.5%">
            <col width="5.5%">
            <col width="6.5%">
            <col width="5.5%">
            <col width="6%">
            <col width="5%">
            <col width="7.5%">
            <col width="4.5%">
            <col width="7%">
            <col width="4.5%">
            <col width="6%">
            <col width="4.5%">
            <col width="4.5%">
            <col width="5.5%">
            <col width="5%">
            <col width="4%">
            <col width="4.5%">
            <col width="4.5%">
            <col width="5%">
        </colgroup>
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Client Name</th>
                <th>Business Name</th>
                <th>Business Type</th>
                <th>Line of Business</th>
                <th>BIR Reg. Type</th>
                <th>Business Address</th>
                <th>Contact No.</th>
                <th>Email Address</th>
                <th>2nd Contact No.</th>
                <th>2nd Email</th>
                <th>Birth Date</th>
                <th>TIN No.</th>
                <th>Mother's Maiden</th>
                <th>Father's Name</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date Started</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $client)
                @php($p = $client->profile)
                <tr>
                    <td>{{ $client->client_code ?? '' }}</td>
                    <td>{{ $client->name }}</td>
                    <td>{{ $client->business_name ?? '' }}</td>
                    <td>{{ $p?->business_type ?? '' }}</td>
                    <td>{{ $p?->line_of_business ?? '' }}</td>
                    <td>{{ $p?->bir_registration_type ?? '' }}</td>
                    <td>{{ $p?->business_address ?? '' }}</td>
                    <td>{{ $p?->contact_no ?? '' }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $p?->second_contact_no ?? '' }}</td>
                    <td>{{ $p?->second_email ?? '' }}</td>
                    <td>{{ $p?->birth_date?->format('m/d/Y') ?? '' }}</td>
                    <td>{{ $p?->tin_no ?? '' }}</td>
                    <td>{{ $p?->mother_maiden_name ?? '' }}</td>
                    <td>{{ $p?->father_name ?? '' }}</td>
                    <td>{{ $p?->statusLabel() ?? '' }}</td>
                    <td>{{ $p?->paymentStatusLabel() ?? '' }}</td>
                    <td>{{ $p?->date_started?->format('m/d/Y') ?? '' }}</td>
                    <td>{{ $p?->remarks ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="19" class="empty-cell">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
