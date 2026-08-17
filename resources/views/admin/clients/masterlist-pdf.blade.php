@extends('layouts.dashboard')

@section('title', 'Client Masterlist — Egliane Accounting Services')

@section('content')
<style>
    .masterlist-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    .masterlist-table th { background: #1B1B3A; color: #fff; padding: 6px 8px; text-align: left; font-weight: 700; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; border: 1px solid #2A2A55; }
    .masterlist-table td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; font-size: 9px; }
    .masterlist-table tr:nth-child(even) td { background: #f8fafc; }
    .masterlist-header { margin-bottom: 20px; text-align: center; }
    .masterlist-header h1 { font-size: 16px; color: #1B1B3A; margin-bottom: 4px; }
    .masterlist-header p { font-size: 11px; color: #6B7280; }
</style>

<div class="masterlist-header">
    <h1>Egliane Accounting Services</h1>
    <p>Client Masterlist &mdash; Generated {{ now()->format('F j, Y \a\t g:i A') }}</p>
</div>

<table class="masterlist-table">
    <thead>
        <tr>
            <th>Client ID</th>
            <th>Client Name</th>
            <th>Business Name</th>
            <th>Business Type</th>
            <th>Line of Business</th>
            <th>BIR Registration Type</th>
            <th>Business Address</th>
            <th>Contact Information</th>
            <th>Contact No.</th>
            <th>Email Address</th>
            <th>Second Contact Information</th>
            <th>2nd Person Contact No.</th>
            <th>2nd Person Email Address</th>
            <th>Birth Date</th>
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
                <td>{{ $p?->contact_no ?? '' }}</td>
                <td>{{ $client->email }}</td>
                <td>{{ $p?->second_contact_no ?? '' }}</td>
                <td>{{ $p?->second_contact_no ?? '' }}</td>
                <td>{{ $p?->second_email ?? '' }}</td>
                <td>{{ $p?->birth_date?->format('m/d/Y') ?? '' }}</td>
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
            <tr><td colspan="22" style="text-align:center; padding:20px;">No clients found.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
