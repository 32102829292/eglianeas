{{--
    BILLING RECEIPT SUMMARY — the workbook's closing section. One row per
    statement with its remittance, fee and grand total, then the three subtotal
    lines. Driven by the same matrix as the main table.

    Expects:
      $matrix   App\Support\BillingSummaryMatrix
      $variant  'web' (default) | 'pdf'
--}}
@php
    $brsVariant = $variant ?? 'web';
    $brsIsPdf = $brsVariant === 'pdf';
    $brsRows = $matrix->receiptRows();
    $brsTotals = $matrix->grandTotals();
@endphp

<section class="brsum {{ $brsIsPdf ? 'brsum-pdf' : 'brsum-web' }}" aria-labelledby="brsum-heading">
    <h2 class="brsum-title" id="brsum-heading">Billing Receipt Summary</h2>

    <table class="brsum-table">
        <thead>
            <tr>
                <th scope="col">Client</th>
                <th scope="col" class="brsum-remit">Remittance</th>
                <th scope="col" class="brsum-fee">Fee</th>
                <th scope="col" class="brsum-grand">Grand Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($brsRows as $brsRow)
                <tr>
                    <th scope="row" class="brsum-client">
                        @if ($brsRow['client'] === '')
                            <span class="bsum-orphan" title="This statement's client record was removed">Client removed</span>
                        @else
                            {{ $brsRow['client'] }}
                        @endif
                    </th>
                    <td class="brsum-num brsum-cell-remit">{{ number_format($brsRow['remittance'], 2) }}</td>
                    <td class="brsum-num brsum-cell-fee">{{ number_format($brsRow['fee'], 2) }}</td>
                    <td class="brsum-num brsum-cell-grand">{{ number_format($brsRow['grand_total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="bsum-empty">No billing statements found for this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="brsum-total">
                <th scope="row">Subtotal for Remittance</th>
                <td class="brsum-num brsum-cell-remit">{{ number_format($brsTotals['remittance'], 2) }}</td>
                <td class="brsum-num brsum-cell-fee"></td>
                <td class="brsum-num brsum-cell-grand"></td>
            </tr>
            <tr class="brsum-total">
                <th scope="row">Subtotal for Fee</th>
                <td class="brsum-num brsum-cell-remit"></td>
                <td class="brsum-num brsum-cell-fee">{{ number_format($brsTotals['fee'], 2) }}</td>
                <td class="brsum-num brsum-cell-grand"></td>
            </tr>
            <tr class="brsum-total brsum-grand-row">
                <th scope="row">Grand Total</th>
                <td class="brsum-num brsum-cell-remit">{{ number_format($brsTotals['remittance'], 2) }}</td>
                <td class="brsum-num brsum-cell-fee">{{ number_format($brsTotals['fee'], 2) }}</td>
                <td class="brsum-num brsum-cell-grand">{{ number_format($brsTotals['grand_total'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</section>
