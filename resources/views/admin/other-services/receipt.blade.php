@extends('layouts.dashboard')

@section('title'){{ $service->serviceName() }} — Receipt — Other Services — Egliane Accounting Services @endsection

@section('content')
    @php
        $client = $service->client;
        $gcashNumber = \App\Models\Setting::get('gcash_number', '');
        $gcashQrCode = \App\Models\Setting::get('gcash_qr_code', '');
        $bankAccounts = \App\Models\Setting::get('bank_accounts', []);
        $hasPaymentInfo = $gcashNumber || $gcashQrCode || count($bankAccounts) > 0;
    @endphp

    <a href="{{ route('admin.other-services.billing') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Other Services
    </a>

    <div class="page-head page-head-row no-print">
        <div>
            <h1>Service receipt</h1>
            <p>{{ $service->serviceName() }} &mdash; {{ $client?->business_name ?: $client?->name }}</p>
        </div>
        <div class="btn-row">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    <div class="statement-wrap">
        <div class="statement">
            @if ($service->isPaid())
                <div class="paid-stamp" aria-hidden="true">
                    <div class="paid-stamp-line1">PAID</div>
                    <div class="paid-stamp-line2">{{ $service->money() }}</div>
                </div>
            @endif

            <div class="statement-head">
                <div class="statement-brand">EGLIANE ACCOUNTING SERVICES</div>
                <div class="statement-period">OTHER SERVICES</div>
            </div>

            <div class="statement-client">
                {{ $client?->business_name ?: $client?->name }}
            </div>

            <div class="statement-block">
                <div class="statement-block-title">SERVICE DETAILS</div>
                <div class="statement-row">
                    <span>Service type</span>
                    <span>{{ $service->serviceName() }}</span>
                </div>
                <div class="statement-row">
                    <span>Date requested</span>
                    <span>{{ $service->requested_at?->format('F j, Y') ?? '—' }}</span>
                </div>
                @if ($service->notes)
                    <div class="statement-row">
                        <span>Notes</span>
                        <span>{{ $service->notes }}</span>
                    </div>
                @endif
            </div>

            <div class="statement-total">
                <span>AMOUNT DUE:</span>
                <span>{{ $service->money() }}</span>
            </div>

            <div class="statement-sign">HARRIS EGLIANE, CPA</div>

            @if ($service->due_date && ! $service->isPaid())
                <div class="statement-paid-note">Due date: {{ $service->due_date->format('F j, Y') }}</div>
            @endif

            @if ($service->isPaid())
                <div class="statement-paid-note">Date paid: {{ $service->paid_at?->format('F j, Y') ?? '—' }}</div>
            @endif

            @if ($hasPaymentInfo)
                <div class="statement-divider"></div>
                <div class="statement-block-title">PAYMENT DETAILS</div>

                @if ($gcashNumber || $gcashQrCode)
                    <div class="payment-method">
                        <div class="payment-method-info">
                            <div class="payment-method-label">GCash</div>
                            @if ($gcashNumber)
                                <div class="payment-method-number"><a href="tel:{{ $gcashNumber }}" class="contact-link">{{ $gcashNumber }}</a></div>
                            @endif
                        </div>
                        @if ($gcashQrCode)
                            <img src="{{ route('payment.image', 'gcash') }}" alt="GCash QR Code" class="payment-qr">
                        @endif
                    </div>
                @endif

                @foreach ($bankAccounts as $i => $bank)
                    @if (! empty($bank['bank_name']) || ! empty($bank['account_number']))
                        <div class="payment-method">
                            <div class="payment-method-info">
                                @if (! empty($bank['bank_name']))
                                    <div class="payment-method-label">{{ $bank['bank_name'] }}</div>
                                @endif
                                @if (! empty($bank['account_name']))
                                    <div class="payment-method-detail">{{ $bank['account_name'] }}</div>
                                @endif
                                @if (! empty($bank['account_number']))
                                    <div class="payment-method-number">{{ $bank['account_number'] }}</div>
                                @endif
                            </div>
                            @if (! empty($bank['bank_qr_code']))
                                <img src="{{ route('payment.image', ['type' => 'bank', 'index' => $i]) }}" alt="{{ $bank['bank_name'] ?? 'Bank' }} QR Code" class="payment-qr">
                            @endif
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
@endsection
