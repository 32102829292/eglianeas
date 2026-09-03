@php
    $grouped = $billing->lineItems->groupBy('category');
    $remittances = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE, collect());
    $professionalFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE, collect());
    $bookkeepingFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE, collect());
    $postClosingTb = $grouped->get(\App\Models\BillingLineItem::CATEGORY_POST_CLOSING_TB, collect());
    $inventoryList = $grouped->get(\App\Models\BillingLineItem::CATEGORY_INVENTORY_LIST, collect());
    $otherAttachment = $grouped->get(\App\Models\BillingLineItem::CATEGORY_OTHER_ATTACHMENT, collect());
    $dataEntry = $grouped->get(\App\Models\BillingLineItem::CATEGORY_DATA_ENTRY, collect());
    $gcashNumber = \App\Models\Setting::get('gcash_number', '');
    $gcashQrCode = \App\Models\Setting::get('gcash_qr_code', '');
    $bankAccounts = \App\Models\Setting::get('bank_accounts', []);
    $hasPaymentInfo = $gcashNumber || $gcashQrCode || count($bankAccounts) > 0;
@endphp

<div class="statement">
    @if ($billing->isPaid())
        <div class="paid-stamp" aria-hidden="true">
            <div class="paid-stamp-line1">PAID</div>
            <div class="paid-stamp-line2">{{ $billing->money($billing->total) }}</div>
        </div>
    @endif

    <div class="statement-head">
        <div class="statement-brand">EGLIANE ACCOUNTING SERVICES</div>
        <div class="statement-period">{{ $billing->period_label }}</div>
    </div>

    <div class="statement-client">
        {{ $billing->client?->business_name ?: $billing->client?->name }}
    </div>

    @if ($remittances->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">BIR REMITTANCES</div>
            @foreach ($remittances as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($professionalFees->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">PROFESSIONAL FEES (FILING)</div>
            @foreach ($professionalFees as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($bookkeepingFees->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">BOOKKEEPING</div>
            @foreach ($bookkeepingFees as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($postClosingTb->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">POST-CLOSING TRIAL BALANCE</div>
            @foreach ($postClosingTb as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($inventoryList->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">INVENTORY LIST (NOTARIZED)</div>
            @foreach ($inventoryList as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($otherAttachment->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">OTHER ATTACHMENT</div>
            @foreach ($otherAttachment as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($dataEntry->isNotEmpty())
        <div class="statement-block">
            <div class="statement-block-title">DATA ENTRY</div>
            @foreach ($dataEntry as $item)
                <div class="statement-row">
                    <span>{{ $item->label }}</span>
                    <span>{{ $billing->money($item->amount) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="statement-total">
        <span>TOTAL AMOUNT:</span>
        <span>{{ $billing->money($billing->total) }}</span>
    </div>

    <div class="statement-sign">HARRIS EGLIANE, CPA</div>

    @if ($billing->due_date && ! $billing->isPaid())
        <div class="statement-paid-note">Due date: {{ $billing->due_date->format('F j, Y') }}</div>
    @endif

    @if ($billing->isPaid())
        <div class="statement-paid-note">Date paid: {{ $billing->paid_at?->format('F j, Y') ?? '—' }}</div>
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
