@extends('layouts.dashboard')

@section('title', $billing->periodTitle().' — Receipt — Egliane Accounting Services')

@section('content')
    @php($client = $billing->client)
    @php($gcashNumber = \App\Models\Setting::get('gcash_number', ''))
    @php($clientPortalUrl = route('client.billing.show', $billing))

    <a href="{{ route('admin.billing.show', $client) }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to {{ $client?->business_name ?: $client?->name }}
    </a>

    <div class="page-head page-head-row no-print">
        <div>
            <h1>Billing statement</h1>
            <p>Period: {{ $billing->period_label }}</p>
        </div>
        <div class="btn-row">
            <form method="POST" action="{{ route('admin.billing.sendEmail', $billing) }}" class="inline-form" id="emailBillingForm">
                @csrf
                <button type="submit" class="btn btn-outline" title="Send to {{ $client?->email }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Send via Email
                </button>
            </form>
            <button type="button" class="btn btn-outline" id="shareBillingBtn"
                data-client="{{ $client?->business_name ?: $client?->name }}"
                data-period="{{ $billing->period_label }}"
                data-total="{{ $billing->money($billing->total) }}"
                data-due="{{ $billing->due_date && ! $billing->isPaid() ? $billing->due_date->format('F j, Y') : '' }}"
                data-paid="{{ $billing->isPaid() ? ($billing->paid_at?->format('F j, Y') ?? '') : '' }}"
                data-url="{{ $clientPortalUrl }}"
                data-gcash="{{ $gcashNumber }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                Share to&hellip;
            </button>
            <a href="{{ route('admin.billing.csv', $billing) }}" class="btn btn-outline">Download CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Download as PDF</button>
        </div>
    </div>

    <div class="statement-wrap">
        @include('partials.billing-statement')
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('shareBillingBtn');
            if (!btn) return;

            function buildMessage() {
                var lines = [
                    'Hi ' + btn.getAttribute('data-client') + ',',
                    '',
                    'Your billing statement for ' + btn.getAttribute('data-period') + ' is ready.',
                    'Total: ' + btn.getAttribute('data-total')
                ];
                var paid = btn.getAttribute('data-paid');
                var due = btn.getAttribute('data-due');
                if (paid) {
                    lines.push('Status: PAID (paid on ' + paid + ') \u2014 thank you!');
                } else if (due) {
                    lines.push('Due date: ' + due);
                }
                var gcash = btn.getAttribute('data-gcash');
                if (gcash) {
                    lines.push('');
                    lines.push('You may pay via GCash: ' + gcash);
                }
                lines.push('');
                lines.push('View your statement anytime in your client portal:');
                lines.push(btn.getAttribute('data-url'));
                lines.push('');
                lines.push('Thank you!');
                lines.push('\u2014 Egliane Accounting Services');
                return lines.join('\n');
            }

            function copyFallback(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        window.alert('Message copied to clipboard. Paste it into Messenger, Viber, SMS, or any app to send it.');
                    }, function () {
                        window.prompt('Copy this message and paste it into your messaging app:', text);
                    });
                    return;
                }
                window.prompt('Copy this message and paste it into your messaging app:', text);
            }

            btn.addEventListener('click', function () {
                var text = buildMessage();
                if (navigator.share) {
                    navigator.share({ title: 'Billing Statement', text: text }).catch(function (err) {
                        if (err && err.name === 'AbortError') return;
                        copyFallback(text);
                    });
                    return;
                }
                copyFallback(text);
            });

            var emailForm = document.getElementById('emailBillingForm');
            if (emailForm) {
                emailForm.addEventListener('submit', function (e) {
                    var recipient = {{ json_encode($client?->email) }};
                    var ok = window.confirm('Send this billing statement to ' + (recipient || 'the client') + '?');
                    if (!ok) e.preventDefault();
                });
            }
        })();
    </script>
@endpush
