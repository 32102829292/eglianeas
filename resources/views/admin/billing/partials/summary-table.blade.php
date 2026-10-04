{{--
    Billing Summary table — grouped two-level header mirroring the accounting
    workbook. Rendered from App\Support\BillingSummaryMatrix so the web page,
    the XLSX export and the print/PDF can never drift apart.

    Expects:
      $matrix   App\Support\BillingSummaryMatrix
      $variant  'web' (default) | 'pdf'
--}}
@php
    $bsmVariant = $variant ?? 'web';
    $bsmIsPdf = $bsmVariant === 'pdf';
    $bsmColumns = $matrix->columns();
    $bsmSpans = $matrix->groupSpans();
    $bsmRows = $matrix->rows();
    $bsmTotals = $matrix->columnTotals();
    $bsmColumnCount = count($bsmColumns);
    $bsmSubtotalCount = collect($bsmColumns)->where('subtotal', true)->count();
    $bsmRegularCount = $bsmColumnCount - 1 - $bsmSubtotalCount;
    $bsmClientWidth = 20;
    $bsmSubtotalWidth = 5.25;
    $bsmRegularWidth = $bsmRegularCount > 0
        ? (100 - $bsmClientWidth - ($bsmSubtotalCount * $bsmSubtotalWidth)) / $bsmRegularCount
        : 0;
@endphp

<table class="bsum {{ $bsmIsPdf ? 'bsum-pdf' : 'bsum-web' }}">
    <colgroup>
        <col class="bsum-col-client" style="width: {{ $bsmClientWidth }}%">
        @foreach ($bsmColumns as $bsmColumn)
            @if ($bsmColumn['key'] !== 'client')
                <col class="{{ $bsmColumn['subtotal'] ? 'bsum-col-sub' : 'bsum-col-num' }}" style="width: {{ $bsmColumn['subtotal'] ? $bsmSubtotalWidth : $bsmRegularWidth }}%">
            @endif
        @endforeach
    </colgroup>
    <thead>
        {{-- Level 1: CLIENT | BIR FORMS FILED | CASH IN | FEE | Other categories | TOTAL | GRAND TOTAL --}}
        <tr class="bsum-head-1">
            <th class="bsum-client-head" rowspan="2" scope="col">Client</th>
            @foreach ($bsmSpans as $group => $span)
                @if ($group === 'remittance')
                    <th class="bsum-group bsum-group-remit" colspan="{{ $span }}" scope="colgroup">For Remittance</th>
                @elseif ($group === 'fee')
                    <th class="bsum-group bsum-group-fee" colspan="{{ $span }}" scope="colgroup">For Fee</th>
                @else
                    <th class="bsum-group bsum-group-other" colspan="{{ $span }}" scope="colgroup">{{ ucfirst(str_replace('_', ' ', $group)) }}</th>
                @endif
            @endforeach
            <th class="bsum-single bsum-single-total" rowspan="2" scope="col">Total</th>
            <th class="bsum-single bsum-single-grand" rowspan="2" scope="col">Grand Total</th>
        </tr>
        {{-- Level 2: the individual columns --}}
        <tr class="bsum-head-2">
            @foreach ($bsmColumns as $bsmColumn)
                @if ($bsmColumn['key'] === 'client' || $bsmColumn['group'] === null)
                    @continue
                @endif
                <th class="bsum-sub {{ $bsmColumn['group'] === 'remittance' ? 'bsum-sub-remit' : 'bsum-sub-fee' }} {{ $bsmColumn['subtotal'] ? 'bsum-subtotal' : '' }}"
                    scope="col">{{ $bsmColumn['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($bsmRows as $bsmRow)
            <tr>
                <th class="bsum-client" scope="row">
                    @if ($bsmRow['client'] === '')
                        <span class="bsum-orphan" title="This statement's client record was removed">Client removed</span>
                    @else
                        {{ $bsmRow['client'] }}
                    @endif
                </th>
                @foreach ($bsmColumns as $bsmColumn)
                    @if ($bsmColumn['key'] === 'client')
                        @continue
                    @endif
                    @php($bsmIsSub = $bsmColumn['subtotal'])
                    <td class="bsum-num {{ $bsmColumn['key'] === 'remittance_subtotal' ? 'bsum-cell-remit' : ($bsmColumn['key'] === 'fee_subtotal' ? 'bsum-cell-fee' : ($bsmColumn['key'] === 'grand_total' ? 'bsum-cell-grand' : ($bsmColumn['key'] === 'total' ? 'bsum-cell-total' : ''))) }}">
                        {{ number_format((float) ($bsmRow[$bsmColumn['key']] ?? 0), 2) }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="bsum-empty" colspan="{{ $bsmColumnCount }}">No billing statements found for this period.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="bsum-total-row">
            <th class="bsum-total-label" scope="row">Total</th>
            @foreach ($bsmColumns as $bsmColumn)
                @if ($bsmColumn['key'] === 'client')
                    @continue
                @endif
                @php($bsmIsGrand = $bsmColumn['key'] === 'grand_total')
                <td class="bsum-num {{ $bsmColumn['key'] === 'remittance_subtotal' ? 'bsum-cell-remit' : ($bsmColumn['key'] === 'fee_subtotal' ? 'bsum-cell-fee' : ($bsmIsGrand ? 'bsum-cell-grand' : '')) }}">
                    {{ number_format((float) ($bsmTotals[$bsmColumn['key']] ?? 0), 2) }}
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>
