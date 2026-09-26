@if (! empty($payments['has']))
    <div class="cell-payments">
        <div class="cell-payments-title">Payment Details</div>
        <table class="cell-pmethod">
            <tr>
                <td class="cell-pinfo">
                    @if ($gcashNumber !== '')
                        <div class="cell-pline"><b>GCash</b> · {{ $gcashNumber }}</div>
                    @endif
                    @foreach ($bankAccounts as $bank)
                        <div class="cell-pline">{{ $bank['label'] }}</div>
                    @endforeach
                </td>
                <td class="cell-pqr">
                    @if ($gcashQr !== null)
                        <img src="{{ $gcashQr }}" alt="GCash QR Code">
                    @endif
                    @foreach ($bankAccounts as $bank)
                        @if (! empty($bank['qr']))
                            <img src="{{ $bank['qr'] }}" alt="{{ $bank['bank_name'] ?: 'Bank' }} QR Code">
                        @endif
                    @endforeach
                </td>
            </tr>
        </table>
        @if (in_array($billing->id, $overflowIds, true))
            <div class="cell-oversize">NOTE: this statement is taller than one page and continues onto the next page.</div>
        @endif
    </div>
@endif