<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Masterlist — Egliane Accounting Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 9px; color: #1B1B3A; line-height: 1.4; }

        .report-header { text-align: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #1B1B3A; }
        .report-header h1 { font-size: 14px; color: #1B1B3A; margin-bottom: 2px; }
        .report-header p { font-size: 9px; color: #6B7280; }

        .client-card { page-break-after: always; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid #d1d5db; }
        .client-card:last-child { page-break-after: avoid; border-bottom: none; }

        .client-name { font-size: 12px; font-weight: 700; color: #1B1B3A; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }

        .section { margin-bottom: 10px; }
        .section-title { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #6B7280; margin-bottom: 4px; padding-bottom: 2px; border-bottom: 1px solid #e5e7eb; }

        .field-grid { width: 100%; border-collapse: collapse; }
        .field-grid td { padding: 3px 6px; vertical-align: top; border: 1px solid #e5e7eb; }
        .field-grid td.label { width: 18%; font-weight: 700; font-size: 8px; color: #374151; background: #f9fafb; white-space: nowrap; }
        .field-grid td.value { width: 32%; font-size: 9px; }

        .empty-msg { text-align: center; padding: 24px; font-style: italic; color: #6B7280; }

        @page { size: A4 landscape; margin: 12mm; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Egliane Accounting Services</h1>
        <p>Client Masterlist &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @forelse ($clients as $client)
        @php($p = $client->profile)
        <div class="client-card">
            <div class="client-name">{{ $client->business_name ?: $client->name }} &mdash; {{ $client->client_code ?? 'N/A' }}</div>

            <div class="section">
                <div class="section-title">Business Information</div>
                <table class="field-grid">
                    <tr>
                        <td class="label">Client ID</td>
                        <td class="value">{{ $client->client_code ?? '' }}</td>
                        <td class="label">Business Name</td>
                        <td class="value">{{ $client->business_name ?? '' }}</td>
                        <td class="label">Business Type</td>
                        <td class="value">{{ $p?->business_type ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Line of Business</td>
                        <td class="value">{{ $p?->line_of_business ?? '' }}</td>
                        <td class="label">BIR Registration Type</td>
                        <td class="value">{{ $p?->bir_registration_type ?? '' }}</td>
                        <td class="label">Status</td>
                        <td class="value">{{ $p?->statusLabel() ?? '' }}</td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <div class="section-title">Contact &amp; Address</div>
                <table class="field-grid">
                    <tr>
                        <td class="label">Business Address</td>
                        <td class="value">{{ $p?->business_address ?? '' }}</td>
                        <td class="label">Contact No.</td>
                        <td class="value">{{ $p?->contact_no ?? '' }}</td>
                        <td class="label">Email Address</td>
                        <td class="value">{{ $client->email }}</td>
                    </tr>
                    <tr>
                        <td class="label">2nd Person Contact No.</td>
                        <td class="value">{{ $p?->second_contact_no ?? '' }}</td>
                        <td class="label">2nd Person Email</td>
                        <td class="value">{{ $p?->second_email ?? '' }}</td>
                        <td class="label"></td>
                        <td class="value"></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <div class="section-title">Personal &amp; Tax Information</div>
                <table class="field-grid">
                    <tr>
                        <td class="label">Birth Date</td>
                        <td class="value">{{ $p?->birth_date?->format('m/d/Y') ?? '' }}</td>
                        <td class="label">TIN No.</td>
                        <td class="value">{{ $p?->tin_no ?? '' }}</td>
                        <td class="label">Payment Status</td>
                        <td class="value">{{ $p?->paymentStatusLabel() ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Mother's Maiden Name</td>
                        <td class="value">{{ $p?->mother_maiden_name ?? '' }}</td>
                        <td class="label">Father's Name</td>
                        <td class="value">{{ $p?->father_name ?? '' }}</td>
                        <td class="label">Date Started</td>
                        <td class="value">{{ $p?->date_started?->format('m/d/Y') ?? '' }}</td>
                    </tr>
                </table>
            </div>

            @if ($p?->remarks)
                <div class="section">
                    <div class="section-title">Remarks</div>
                    <table class="field-grid">
                        <tr>
                            <td class="label">Remarks</td>
                            <td class="value" colspan="5">{{ $p->remarks }}</td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="empty-msg">No clients found.</div>
    @endforelse
</body>
</html>
