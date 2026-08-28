@extends('layouts.dashboard')

@section('title', ($client->business_name ?: $client->name).' — Billing Statements — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.billing.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1>{{ $client->business_name ?: $client->name }}</h1>
            <p>{{ $client->name }} &middot; {{ $client->email }} &middot; {{ $client->profile?->line_of_business ?? '—' }}</p>
        </div>
        <div class="btn-row">
            <select id="batchPaperSize" class="form-control form-control-sm batch-paper-select" aria-label="Paper size" hidden>
                <option value="a4" selected>A4</option>
                <option value="letter">Letter</option>
            </select>
            <button type="button" id="printBatchBtn" class="btn btn-outline" hidden>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print selected (<span id="printBatchCount">0</span>)
            </button>
            <a href="{{ route('admin.billing.clientCsv', $client) }}" class="btn btn-outline">Export CSV</a>
            <a href="{{ route('admin.billing.create', ['client' => $client->id]) }}" class="btn btn-primary">New billing</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <span class="stat-label">Billing statement records</span>
            <b class="stat-value">{{ $stats['count'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($stats['billed'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱{{ number_format($stats['paid'], 2) }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱{{ number_format($stats['outstanding'], 2) }}</b>
        </div>
    </div>

    @forelse ($billingsByYear as $year => $billings)
        <div class="section-gap">
            <h2 class="text-section">{{ $year }}</h2>
            <div class="card">
                <div class="table-wrap table-card-view">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="print-check-cell"><input type="checkbox" data-batch-check-all aria-label="Select all statements for {{ $year }}"></th>
                                <th>Quarter</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Due date</th>
                                <th>Date paid</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($billings as $billing)
                                <tr>
                                    <td class="print-check-cell"><input type="checkbox" class="batch-print-check" value="{{ $billing->id }}" aria-label="Select {{ $billing->period_label }} for printing"></td>
                                    <td data-col="Quarter">
                                        <div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                        <small class="muted">{{ $billing->period_label }}</small>
                                    </td>
                                    <td data-col="Total"><b>{{ $billing->money($billing->total) }}</b></td>
                                    <td data-col="Status">
                                        <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                                    </td>
                                    <td data-col="Due date">{{ $billing->due_date?->format('M j, Y') ?? '—' }}</td>
                                    <td data-col="Date paid">{{ $billing->paid_at?->format('M j, Y') ?? '—' }}</td>
                                    <td class="actions-cell" data-col="Actions">
                                        @if ($billing->isDraft())
                                            <form method="POST" action="{{ route('admin.billing.finalize', $billing) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">Finalize</button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.billing.receipt', $billing) }}" class="btn btn-outline btn-sm">View receipt</a>
                                        @endif
                                        <a href="{{ route('admin.billing.csv', $billing) }}" class="link">CSV</a>
                                        <a href="{{ route('admin.billing.edit', $billing) }}" class="link">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="card-view-list">
                        @forelse ($billings as $billing)
                            <div class="cv-card">
                                <div class="cv-row"><span class="cv-label">Print</span><span class="cv-value"><input type="checkbox" class="batch-print-check" value="{{ $billing->id }}" aria-label="Select {{ $billing->period_label }} for printing"></span></div>
                                <div class="cv-row"><span class="cv-label">Quarter</span><span class="cv-value">{{ $billing->periodTitleUppercase() }} BILLING</span></div>
                                <div class="cv-row"><span class="cv-label">Total</span><span class="cv-value">{{ $billing->money($billing->total) }}</span></div>
                                <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value"><span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span></span></div>
                                <div class="cv-row"><span class="cv-label">Due date</span><span class="cv-value">{{ $billing->due_date?->format('M j, Y') ?? '—' }}</span></div>
                                <div class="cv-row"><span class="cv-label">Date paid</span><span class="cv-value">{{ $billing->paid_at?->format('M j, Y') ?? '—' }}</span></div>
                                <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value">@if($billing->isDraft())<form method="POST" action="{{ route('admin.billing.finalize', $billing) }}" class="d-inline">@csrf <button type="submit" class="btn btn-primary btn-sm">Finalize</button></form>@else<a href="{{ route('admin.billing.receipt', $billing) }}" class="btn btn-outline btn-sm">View receipt</a>@endif <a href="{{ route('admin.billing.csv', $billing) }}" class="link">CSV</a> <a href="{{ route('admin.billing.edit', $billing) }}" class="link">Edit</a></span></div>
                            </div>
                        @empty
                            <p class="cv-card" style="text-align:center;color:var(--text-muted);">No billing statements for this client yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="empty-state">
            <p>No billing statements for this client yet.</p>
            <a href="{{ route('admin.billing.create') }}" class="btn btn-primary">Create the first billing</a>
        </div>
    @endforelse
@endsection

@push('scripts')
    <script>
        (function () {
            var printBtn = document.getElementById('printBatchBtn');
            var countEl = document.getElementById('printBatchCount');
            var paperSelect = document.getElementById('batchPaperSize');
            if (!printBtn || !countEl) return;

            function checkedBoxes() {
                return Array.prototype.slice.call(document.querySelectorAll('.batch-print-check:checked'));
            }

            function refresh() {
                var n = checkedBoxes().length;
                countEl.textContent = n;
                printBtn.hidden = n === 0;
                paperSelect.hidden = n === 0;
            }

            document.addEventListener('change', function (e) {
                if (e.target.matches('.batch-print-check')) {
                    refresh();
                    return;
                }
                if (e.target.matches('[data-batch-check-all]')) {
                    var table = e.target.closest('table');
                    if (!table) return;
                    table.querySelectorAll('.batch-print-check').forEach(function (cb) {
                        cb.checked = e.target.checked;
                    });
                    refresh();
                }
            });

            printBtn.addEventListener('click', function () {
                var ids = checkedBoxes().map(function (cb) { return cb.value; });
                if (!ids.length) return;
                var params = new URLSearchParams();
                ids.forEach(function (id) { params.append('ids[]', id); });
                params.append('paper', paperSelect && paperSelect.value === 'letter' ? 'letter' : 'a4');
                window.location.href = '{{ route("admin.billing.printBatch") }}?' + params.toString();
            });
        })();
    </script>
@endpush
