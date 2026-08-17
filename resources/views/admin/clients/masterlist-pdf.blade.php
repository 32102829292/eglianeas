<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Masterlist — Egliane Accounting Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 8px; color: #1B1B3A; line-height: 1.3; }
        .report-header { text-align: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #1B1B3A; }
        .report-header h1 { font-size: 14px; color: #1B1B3A; margin-bottom: 2px; }
        .report-header p { font-size: 9px; color: #6B7280; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1B1B3A; color: #fff; padding: 4px 5px; text-align: left; font-weight: 700; font-size: 7px; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; border: 1px solid #2A2A55; }
        td { padding: 3px 5px; border: 1px solid #d1d5db; vertical-align: top; font-size: 8px; }
        tr:nth-child(even) td { background: #f3f4f6; }
        .empty-cell { text-align: center; padding: 16px; font-style: italic; color: #6B7280; }
        @page { size: A4 landscape; margin: 10mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>Client Masterlist &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Client Name</th>
                <th>Business Name</th>
                <th>Business Type</th>
                <th>Line of Business</th>
                <th>BIR Registration Type</th>
                <th>Business Address</th>
                <th>Contact No.</th>
                <th>Email Address</th>
                <th>2nd Person Contact No.</th>
                <th>2nd Person Email Address</th>
                <th>Birth Date</th>
                <th>TIN No.</th>
                <th>Mother's Maiden Name</th>
                <th>Father's Name</th>
                <th>Status</th>
                <th>Payment Status</th>
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
