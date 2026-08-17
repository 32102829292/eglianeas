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

    <div class="statement-block">
        <div class="statement-row statement-sales"><span>SALES</span><span>{{ $billing->money($billing->sales) }}</span></div>
    </div>

    <div class="statement-block">
        <div class="statement-block-title">BIR REMITTANCES</div>
        <div class="statement-row"><span>2551Q ({{ $billing->rate_2551q }}%)</span><span>{{ $billing->money($billing->tax_2551q) }}</span></div>
        <div class="statement-row"><span>1701Q</span><span>{{ $billing->money($billing->tax_1701q) }}</span></div>
        <div class="statement-row"><span>CASH IN</span><span>{{ $billing->money($billing->cash_in) }}</span></div>
    </div>

    <div class="statement-block">
        <div class="statement-block-title">RATE FEES (PROFESSIONAL / FILING)</div>
        <div class="statement-row"><span>2551Q</span><span>{{ $billing->money($billing->fee_2551q) }}</span></div>
        <div class="statement-row"><span>1701Q</span><span>{{ $billing->money($billing->fee_1701q) }}</span></div>
        <div class="statement-row"><span>BOOKKEEPING / POST CLOSING TRIAL BALANCE</span><span>{{ $billing->money($billing->fee_bookkeeping) }}</span></div>
    </div>

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
