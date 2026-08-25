@php($clientName = $billing->client?->business_name ?: $billing->client?->name)
<div class="copy-tag">{{ $copyLabel }}</div>
<table class="stmt {{ $density ?? 'normal' }}">
    <tr class="head-row">
        <td>
            <span class="brand">EGLIANE ACCOUNTING SERVICES</span><br>
            <span class="client">{{ $clientName }}</span>
        </td>
        <td class="amount">
            @if ($billing->isPaid())
                <span class="paid-stamp">PAID</span><br>
            @endif
            <span class="period">BILLING STATEMENT<br>{{ mb_strtoupper($billing->period_label) }}</span>
        </td>
    </tr>
    @foreach ($categories as $category => $title)
        @php($items = $billing->lineItems->where('category', $category)->filter(fn ($i) => (float) $i->amount != 0.0))
        @if ($items->isNotEmpty())
            <tr><td colspan="2" class="section-title">{{ $title }}</td></tr>
            @foreach ($items as $item)
                <tr class="item-row">
                    <td>{{ $item->label }}</td>
                    <td class="amount">{{ $peso($item->amount) }}</td>
                </tr>
            @endforeach
        @endif
    @endforeach
    <tr class="total-row">
        <td>TOTAL AMOUNT</td>
        <td class="amount">{{ $peso($billing->total) }}</td>
    </tr>
    <tr class="sign-row">
        <td>
            <span class="signer">HARRIS EGLIANE, CPA</span>
            @if ($billing->isPaid())
                <span class="note"> &middot; Paid {{ $billing->paid_at?->format('M j, Y') }}</span>
            @elseif ($billing->due_date)
                <span class="note"> &middot; Due {{ $billing->due_date->format('M j, Y') }}</span>
            @endif
        </td>
        <td class="amount note">Ref #{{ str_pad((string) $billing->id, 5, '0', STR_PAD_LEFT) }}</td>
    </tr>
</table>
