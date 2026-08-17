@extends('layouts.dashboard')

@section('title', 'Billing — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing</h1>
            <p>Report your quarterly sales and view your billing statements from Egliane Accounting Services.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($summary['billed'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <span class="stat-label">Total paid</span>
            <b class="stat-value">₱{{ number_format($summary['paid'], 2) }}</b>
        </div>
    </div>

    <div class="card card-narrow">
        <div class="card-head">
            <h2 class="card-title">Submit your quarterly Sales</h2>
        </div>
        <p class="card-sub">
            The 2551Q percentage tax is computed automatically from your sales at the current rate
            ({{ $rate }}%). The 1701Q income tax and professional / filing fees are added by Egliane.
        </p>

        <form method="POST" action="{{ route('client.billing.submit') }}" id="salesEntryForm">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="entry_quarter">Quarter</label>
                    <select class="form-control" id="entry_quarter" name="quarter">
                        @foreach (App\Models\Billing::QUARTERS as $q => $label)
                            <option value="{{ $q }}" @selected((int) old('quarter', $entryBilling?->quarter ?? $currentQuarter) === $q)>{{ $label }} Quarter</option>
                        @endforeach
                    </select>
                    @error('quarter')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="entry_year">Year</label>
                    <select class="form-control" id="entry_year" name="year">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((int) old('year', $entryBilling?->year ?? $currentYear) === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('year')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="entry_sales">Sales for the period</label>
                    <input class="form-control" id="entry_sales" name="sales" type="number" step="0.01" min="0" value="{{ old('sales', $entryBilling?->sales) }}" placeholder="0.00">
                    @error('sales')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="entry_cash_in">Cash in (optional)</label>
                    <input class="form-control" id="entry_cash_in" name="cash_in" type="number" step="0.01" min="0" value="{{ old('cash_in', $entryBilling?->cash_in) }}" placeholder="0.00">
                    @error('cash_in')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="entry-preview">
                <span>2551Q ({{ $rate }}%) =</span>
                <b id="entryPreview2551q">₱0.00</b>
            </div>

            @if ($entryBilling?->hasSubmittedSales())
                <div class="entry-note">
                    You already submitted sales for this period ({{ $entryBilling->sales_submitted_at->format('M j, Y g:i A') }}).
                    Submitting again will update your figures until the billing is finalized.
                </div>
            @endif

            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Submit sales</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Billing history</h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Billing period</th>
                        <th>Sales</th>
                        <th>Total payment</th>
                        <th>Status</th>
                        <th class="actions-cell">Statement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($billings as $billing)
                        <tr>
                            <td>
                                <div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="muted">{{ $billing->period_label }}</small>
                            </td>
                            <td>{{ $billing->money($billing->sales) }}</td>
                            <td><b>{{ $billing->money($billing->total) }}</b></td>
                            <td>
                                <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('client.billing.show', $billing) }}" class="btn btn-outline btn-sm">View statement</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No billing statements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $billings->links('pagination.simple') }}
    </div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var quarterSel = document.getElementById('entry_quarter');
  var yearSel = document.getElementById('entry_year');
  var salesInput = document.getElementById('entry_sales');
  var cashInput = document.getElementById('entry_cash_in');
  var preview = document.getElementById('entryPreview2551q');
  var rate = {{ (float) $rate }};

  function money(value) {
    return '\u20B1' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function updatePreview() {
    var s = parseFloat(salesInput.value);
    if (isNaN(s) || s < 0) s = 0;
    preview.textContent = money(Math.round(s * rate) / 100);
  }

  function loadPeriod() {
    var url = '{{ route('client.billing.period-data') }}?year=' + encodeURIComponent(yearSel.value) + '&quarter=' + encodeURIComponent(quarterSel.value);
    fetch(url, { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        salesInput.value = data.sales || '';
        cashInput.value = data.cash_in || '';
        updatePreview();
      })
      .catch(function () {});
  }

  if (salesInput) salesInput.addEventListener('input', updatePreview);
  if (quarterSel) quarterSel.addEventListener('change', loadPeriod);
  if (yearSel) yearSel.addEventListener('change', loadPeriod);

  updatePreview();
})();
</script>
@endpush
