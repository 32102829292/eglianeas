@php
    $grouped = $billing->lineItems->groupBy('category');
    $remittances = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE, collect());
    $professionalFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE, collect());
    $bookkeepingFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE, collect());
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
            <div class="statement-block-title">BOOKKEEPING / POST-CLOSING TRIAL BALANCE</div>
            @foreach ($bookkeepingFees as $item)
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
</div>
