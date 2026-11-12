@extends('layouts.dashboard')

@php
    use App\Models\CashDailyLine;
    use Illuminate\Support\Carbon;
    $date = $reportDate;
    $addLines = ($position && method_exists($position, 'lines')) ? $position->lines->where('type', CashDailyLine::TYPE_ADDITION) : collect();
    $salaryLines = ($position && method_exists($position, 'lines')) ? $position->lines->where('type', CashDailyLine::TYPE_SALARY) : collect();
    $expenseLines = ($position && method_exists($position, 'lines')) ? $position->lines->where('type', CashDailyLine::TYPE_EXPENSE) : collect();
    $addTotal = $addLines->sum(fn($l) => (float)$l->amount);
    $salTotal = $salaryLines->sum(fn($l) => (float)$l->amount);
    $expTotal = $expenseLines->sum(fn($l) => (float)$l->amount);
    $lessTotal = $salTotal + $expTotal;
    $beginBal = (float)($beginningBalance ?? 0);
    $endBal = $beginBal + $addTotal - $lessTotal;
    $actual = ($position && $position->actual_cash !== null) ? (float)$position->actual_cash : null;
    $overShort = $actual === null ? null : ($endBal - $actual);
    $reportedBy = auth()->user()->name ?? '';
@endphp

@section('title', 'Cash Daily Position Report - '.Carbon::parse($date)->format('F d, Y'))

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>EGLIANE ACCOUNTING SERVICES</h1>
            <p><strong>CASH DAILY POSITION REPORT</strong></p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.cash-daily-position.index') }}" class="btn btn-secondary">Back to Daily Reports</a>
        </div>
    </div>

    <div class="cdp-receipt">
        <div class="cdp-receipt-meta">
            <div><strong>Date:</strong> {{ Carbon::parse($date)->format('F d, Y') }}</div>
            <div><strong>Reported by:</strong> {{ $reportedBy }}</div>
        </div>

        <form method="POST" action="{{ route('admin.cash-daily-position.store-daily') }}" id="cdpDailyForm">
            @csrf
            <input type="hidden" name="position_date" value="{{ $date }}">
            @if(($mode ?? null) === 'edit' && $position)
                <input type="hidden" name="position_id" value="{{ $position->id }}">
            @endif
            <input type="hidden" name="budget" value="0">

            <section class="cdp-receipt-section">
                <h2>BEGINNING BALANCE</h2>
                <table class="cdp-receipt-table">
                    <thead><tr><th>Particulars</th><th class="num">Price</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Beginning Balance</td>
                            <td class="num"><input type="number" step="0.01" name="beginning_balance" value="{{ number_format($beginBal, 2, '.', '') }}" required></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="cdp-receipt-section">
                <h2>ADD — CASH RECEIVED</h2>
                <table class="cdp-receipt-table cdp-add-table" id="addTable">
                    <colgroup>
                        <col class="cdp-add-col-company">
                        <col class="cdp-add-col-remarks">
                        <col class="cdp-add-col-price">
                        <col class="cdp-add-col-actions">
                    </colgroup>
                    <thead><tr><th>Company</th><th>Remarks</th><th class="num">Price</th><th class="cdp-actions-col">Actions</th></tr></thead>
                    <tbody>
                        @if($addLines->isEmpty())
                            <tr>
                                <td>
                                    <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}">
                                        <input type="text" class="cdp-ac-input" name="additions[0][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required>
                                        <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                    </div>
                                </td>
                                <td><input type="text" name="additions[0][notes]" maxlength="500" autocomplete="off" placeholder="Enter remarks..."></td>
                                <td class="num"><input type="number" step="0.01" class="amt-add" name="additions[0][amount]" required></td>
                                <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                            </tr>
                        @else
                            @foreach($addLines as $i => $line)
                                <tr>
                                    <td>
                                        <input type="hidden" name="additions[{{ $i }}][id]" value="{{ $line->id }}">
                                        <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}">
                                            <input type="text" class="cdp-ac-input" name="additions[{{ $i }}][label]" value="{{ $line->label }}" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required>
                                            <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                        </div>
                                    </td>
                                    <td><input type="text" name="additions[{{ $i }}][notes]" value="{{ $line->notes }}" maxlength="500" autocomplete="off" placeholder="Enter remarks..."></td>
                                    <td class="num"><input type="number" step="0.01" class="amt-add" name="additions[{{ $i }}][amount]" value="{{ number_format((float)$line->amount, 2, '.', '') }}" required></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4"><button type="button" class="btn btn-sm btn-primary" onclick="addRow('addTable','additions','amt-add')">+ Add Cash Received</button></td></tr>
                        <tr class="total-row"><td>TOTAL ADD</td><td></td><td class="num" id="totalAdd">₱{{ number_format($addTotal, 2) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </section>

            <section class="cdp-receipt-section">
                <h2>LESS — SALARIES</h2>
                <table class="cdp-receipt-table" id="salTable">
                    <thead><tr><th>Description</th><th class="num">Price</th><th></th></tr></thead>
                    <tbody>
                        @if($salaryLines->isEmpty())
                            <tr>
                                <td>
                                    <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.staff.json') }}">
                                        <input type="text" class="cdp-ac-input" name="salaries[0][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Description" required>
                                        <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                    </div>
                                </td>
                                <td class="num"><input type="number" step="0.01" class="amt-sal" name="salaries[0][amount]" required></td>
                                <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                            </tr>
                        @else
                            @foreach($salaryLines as $i => $line)
                                <tr>
                                    <td>
                                        <input type="hidden" name="salaries[{{ $i }}][id]" value="{{ $line->id }}">
                                        <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.staff.json') }}">
                                            <input type="text" class="cdp-ac-input" name="salaries[{{ $i }}][label]" value="{{ $line->label }}" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Description" required>
                                            <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                        </div>
                                    </td>
                                    <td class="num"><input type="number" step="0.01" class="amt-sal" name="salaries[{{ $i }}][amount]" value="{{ number_format((float)$line->amount, 2, '.', '') }}" required></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3"><button type="button" class="btn btn-sm btn-primary" onclick="addRow('salTable','salaries','amt-sal')">+ Add Salary</button></td></tr>
                        <tr class="total-row"><th>TOTAL SALARIES</th><th class="num" id="totalSal">₱{{ number_format($salTotal, 2) }}</th><th></th></tr>
                    </tfoot>
                </table>
            </section>

            <section class="cdp-receipt-section">
                <h2>LESS — OTHER EXPENSES</h2>
                <table class="cdp-receipt-table" id="expTable">
                    <thead><tr><th>Description</th><th class="num">Price</th><th></th></tr></thead>
                    <tbody>
                        @if($expenseLines->isEmpty())
                            <tr>
                                <td><input type="text" name="expenses[0][label]" required></td>
                                <td class="num"><input type="number" step="0.01" class="amt-exp" name="expenses[0][amount]" required></td>
                                <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                            </tr>
                        @else
                            @foreach($expenseLines as $i => $line)
                                <tr>
                                    <td>
                                        <input type="hidden" name="expenses[{{ $i }}][id]" value="{{ $line->id }}">
                                        <input type="text" name="expenses[{{ $i }}][label]" value="{{ $line->label }}" required>
                                    </td>
                                    <td class="num"><input type="number" step="0.01" class="amt-exp" name="expenses[{{ $i }}][amount]" value="{{ number_format((float)$line->amount, 2, '.', '') }}" required></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3"><button type="button" class="btn btn-sm btn-primary" onclick="addRow('expTable','expenses','amt-exp')">+ Add Expense</button></td></tr>
                        <tr class="total-row"><th>TOTAL OTHER EXPENSES</th><th class="num" id="totalExp">₱{{ number_format($expTotal, 2) }}</th><th></th></tr>
                    </tfoot>
                </table>
            </section>

            <section class="cdp-receipt-section">
                <h2>CASH POSITION SUMMARY</h2>
                <table class="cdp-receipt-table cdp-summary-table" id="summaryTable">
                    <thead><tr><th>Particulars</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Beginning Balance</td><td class="num" id="sumBeg">₱{{ number_format($beginBal, 2) }}</td></tr>
                        <tr><td>Total ADD</td><td class="num" id="sumAdd">+₱{{ number_format($addTotal, 2) }}</td></tr>
                        <tr><td>Total LESS</td><td class="num" id="sumLess">-₱{{ number_format($lessTotal, 2) }}</td></tr>
                        <tr class="total-row"><td>ENDING BALANCE</td><td class="num" id="sumEnd">₱{{ number_format($endBal, 2) }}</td></tr>
                        <tr><td>Actual Cashout</td><td class="num"><input type="number" step="0.01" name="actual_cash" value="{{ $actual !== null ? number_format($actual, 2, '.', '') : '' }}" onchange="calcTotals()"></td></tr>
                        <tr class="total-row"><td>OVERAGE / (SHORTAGE)</td><td class="num" id="sumOS">₱{{ $overShort === null ? '0.00' : number_format($overShort, 2) }}</td></tr>
                    </tbody>
                </table>
            </section>
        </form>

        <!-- Receipt Attachment Section. Kept outside the report form: HTML does
             not allow nested forms, which previously made upload/delete submit
             the report form instead of the receipt action. -->
        <section class="cdp-receipt-section" aria-labelledby="receiptSectionTitle">
            <h2 id="receiptSectionTitle">Attach Receipts</h2>
            <div class="cdp-receipt-inner">
                @if($receiptsUnavailable)
                    <div class="cdp-receipt-note p-3 text-center text-muted">
                        Receipts require database migration. Please run <code>php artisan migrate</code> to enable receipt uploads.
                    </div>
                @elseif(!$position)
                    <div class="cdp-receipt-note p-3 text-center text-muted">
                        Save this report first before uploading receipts.
                    </div>
                @else
                    <!-- Upload Form -->
                    <form method="POST" action="{{ route('admin.cash-daily-position.receipts.store', ['position' => $position->id]) }}" enctype="multipart/form-data" id="receiptUploadForm" class="cdp-upload-form">
                        @csrf
                        <div class="cdp-upload-zone" id="receiptDropZone">
                            <input type="file" name="receipts[]" id="receiptFiles" class="cdp-upload-input" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" multiple hidden>
                            <div class="cdp-upload-icon" aria-hidden="true"><x-icon name="cloud-upload" size="26" /></div>
                            <div class="cdp-upload-copy">
                                <p class="cdp-upload-title">Drag &amp; drop receipts here</p>
                                <p class="cdp-upload-hint">or select files from your computer</p>
                            </div>
                            <button type="button" class="btn cdp-btn-primary cdp-choose-files" id="receiptChooseBtn">
                                <x-icon name="folder-open" size="16" /> Choose Files
                            </button>
                            <p class="cdp-upload-meta">
                                <span><x-icon name="image" size="13" /> JPG</span>
                                <span>PNG</span>
                                <span>PDF</span>
                                <span class="cdp-upload-sep" aria-hidden="true">&bull;</span>
                                <span>Max 2 MB per file</span>
                                <span class="cdp-upload-sep" aria-hidden="true">&bull;</span>
                                <span>Up to 10 files</span>
                            </p>
                        </div>
                        <div class="selected-files" id="selectedFiles" hidden></div>
                        <div class="cdp-upload-actions">
                            <button type="submit" class="btn cdp-btn-primary" id="receiptUploadBtn" disabled>
                                <x-icon name="upload" size="16" /> <span id="receiptUploadLabel">Upload Receipts</span>
                            </button>
                            <div class="upload-feedback" id="uploadFeedback" role="status" aria-live="polite"></div>
                        </div>
                    </form>

                    <!-- Receipt List -->
                    <div class="cdp-receipts-list">
                        @if($position->receipts->isEmpty())
                            <div class="cdp-receipt-empty">
                                <x-icon name="receipt" size="22" />
                                <p>No receipts uploaded yet.</p>
                            </div>
                        @else
                            <table class="cdp-receipts-table">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>File Type</th>
                                        <th>Uploaded At</th>
                                        <th class="cdp-actions-col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($position->receipts as $receipt)
                                        @php($isPdf = strtolower($receipt->file_type) === 'pdf')
                                        <tr>
                                            <td>
                                                <div class="cdp-receipt-file">
                                                    <span class="cdp-file-badge {{ $isPdf ? 'is-pdf' : 'is-image' }}" aria-hidden="true">
                                                        <x-icon :name="$isPdf ? 'file-text' : 'image'" size="15" />
                                                    </span>
                                                    <span class="receipt-filename" title="{{ $receipt->file_name }}">{{ $receipt->file_name }}</span>
                                                </div>
                                            </td>
                                            <td><span class="receipt-type">{{ strtoupper($receipt->file_type) }}</span></td>
                                            <td class="cdp-receipt-date">{{ $receipt->created_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                            <td>
                                                <div class="cdp-row-actions">
                                                    <button type="button"
                                                            class="btn cdp-btn-primary cdp-btn-view btn-view-receipt cdp-tooltip"
                                                            data-url="{{ route('admin.cash-daily-position.receipts.view', ['receipt' => $receipt->id]) }}"
                                                            data-download-url="{{ route('admin.cash-daily-position.receipts.download', ['receipt' => $receipt->id]) }}"
                                                            data-type="{{ $receipt->file_type }}"
                                                            data-filename="{{ $receipt->file_name }}"
                                                            data-tooltip="View Receipt"
                                                            title="View Receipt"
                                                            aria-label="View Receipt {{ $receipt->file_name }}">
                                                        <x-icon name="eye" size="15" /><span>View Receipt</span>
                                                    </button>
                                                    <form method="POST" action="{{ route('admin.cash-daily-position.receipts.destroy', ['receipt' => $receipt->id]) }}" class="cdp-delete-form" onsubmit="return (window.Egliane && Egliane.confirm ? Egliane.confirm.form(this, {title: 'Delete Receipt', message: 'Delete this receipt? This cannot be undone.', danger: true, confirmLabel: 'Delete'}) : confirm('Delete this receipt?'));">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn cdp-btn-danger-icon cdp-tooltip" data-tooltip="Delete Receipt" title="Delete Receipt" aria-label="Delete Receipt {{ $receipt->file_name }}">
                                                            <x-icon name="trash-2" size="14" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <template id="receiptRemoveIconTemplate"><x-icon name="x" size="12" /></template>

        <!-- Receipt preview modal: large, centered, and reused for every row. -->
        <div class="cdp-preview-modal" id="receiptPreviewModal" aria-hidden="true">
            <div class="cdp-preview-backdrop" data-receipt-close></div>
            <div class="cdp-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="receiptPreviewTitle">
                <div class="cdp-preview-header">
                    <div class="cdp-preview-heading">
                        <span class="cdp-preview-eyebrow">Receipt Preview</span>
                        <h3 id="receiptPreviewTitle">Receipt</h3>
                    </div>
                    <button type="button" class="cdp-preview-close" data-receipt-close aria-label="Close preview" title="Close"><x-icon name="x" size="18" /></button>
                </div>
                <div class="cdp-preview-toolbar">
                    <div class="cdp-zoom-controls" id="receiptZoomControls" hidden>
                        <button type="button" class="cdp-zoom-btn" id="receiptZoomOut" title="Zoom out" aria-label="Zoom out"><x-icon name="zoom-out" size="16" /></button>
                        <span class="cdp-zoom-value" id="receiptZoomLevel">100%</span>
                        <button type="button" class="cdp-zoom-btn" id="receiptZoomIn" title="Zoom in" aria-label="Zoom in"><x-icon name="zoom-in" size="16" /></button>
                        <button type="button" class="cdp-zoom-btn cdp-zoom-reset" id="receiptZoomReset" title="Reset zoom"><x-icon name="refresh-ccw" size="14" /> <span>Reset</span></button>
                        <button type="button" class="cdp-zoom-btn" id="receiptRotateBtn" title="Rotate" aria-label="Rotate image 90 degrees"><x-icon name="rotate-cw" size="16" /></button>
                    </div>
                    <div class="cdp-preview-toolbar-actions">
                        <a href="#" class="btn cdp-btn-primary cdp-btn-sm" id="receiptDownloadBtn" hidden><x-icon name="download" size="15" /> Download</a>
                        <button type="button" class="btn cdp-btn-ghost cdp-btn-sm" data-receipt-close><x-icon name="x" size="15" /> Close</button>
                    </div>
                </div>
                <div class="cdp-preview-body">
                    <div class="cdp-preview-state" id="receiptModalLoading" hidden>Loading receipt&hellip;</div>
                    <div class="cdp-preview-state cdp-preview-error" id="receiptModalError" hidden></div>
                    <div class="cdp-preview-content" id="receiptModalContent"></div>
                </div>
            </div>
        </div>

        <div class="cdp-receipt-actions">
            <a href="{{ route('admin.cash-daily-position.index') }}" class="btn btn-secondary"><x-icon name="arrow-left" size="16" /> Back to Daily Reports</a>
            @if(!isset($mode) || $mode !== 'view')
                <button type="submit" form="cdpDailyForm" class="btn btn-primary">Save Report</button>
            @else
                <a href="{{ route('admin.cash-daily-position.create', ['date' => $date]) }}" class="btn btn-primary"><x-icon name="pencil" size="15" /> Edit/Add Lines</a>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .cdp-receipt { max-width: 980px; margin: 18px auto; }
        .e-icon { display: inline-block; flex: 0 0 auto; vertical-align: -0.15em; }
        .e-icon-spin { animation: e-icon-spin 1s linear infinite; }
        @keyframes e-icon-spin { to { transform: rotate(360deg); } }
        .cdp-receipt-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: linear-gradient(180deg, rgba(227,242,253,0.7) 0%, rgba(255,255,255,1) 100%);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: 0 10px 30px rgba(27,27,58,0.05);
            padding: 12px 16px;
            color: var(--navy);
        }
        .cdp-receipt-section {
            margin-top: 16px;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            background: var(--surface);
            box-shadow: 0 8px 24px rgba(27,27,58,0.04);
            overflow: visible;
        }
        .cdp-receipt-section h2 {
            margin: 0;
            padding: 10px 16px;
            background: linear-gradient(90deg, rgba(227,242,253,0.8) 0%, rgba(255,255,255,1) 100%);
            color: var(--navy);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border-subtle);
        }
        .cdp-receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .cdp-receipt-table thead th {
            background: var(--bg-alt);
            color: var(--navy);
            font-weight: 600;
            text-align: left;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .cdp-receipt-table thead th.num { text-align: right; }
        .cdp-receipt-table tbody td {
            padding: 8px 16px;
            border-bottom: 1px solid rgba(229,231,235,0.8);
            vertical-align: middle;
            position: relative;
        }
        .cdp-receipt-table tbody td.num { text-align: right; }
        .cdp-receipt-table tbody tr:hover { background: rgba(227,242,253,0.25); }
        .cdp-receipt-table tfoot td,
        .cdp-receipt-table tfoot th {
            padding: 8px 16px;
            background: var(--bg-alt);
            border-top: 1px solid var(--border-subtle);
        }
        .cdp-receipt-table .total-row th,
        .cdp-receipt-table .total-row td {
            font-weight: 700;
            color: var(--navy);
        }
        .cdp-receipt-table input[type="text"],
        .cdp-receipt-table input[type="number"] {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-btn);
            font-size: 13px;
            background: #fff;
            color: var(--text);
        }
        .cdp-receipt-table td.num input[type="number"] {
            text-align: right;
            font-variant-numeric: tabular-nums;
            max-width: 160px;
            margin-left: auto;
            display: block;
        }
        .cdp-add-table { table-layout: fixed; }
        .cdp-add-col-company { width: 30%; }
        .cdp-add-col-remarks { width: 34%; }
        .cdp-add-col-price { width: 17%; }
        .cdp-add-col-actions { width: 19%; }
        .cdp-add-table .cdp-actions-col { text-align: right; }
        .cdp-add-table tbody td:last-child,
        .cdp-add-table tfoot td:last-child { text-align: right; }
        .cdp-summary-table tbody td {
            height: 40px;
            font-variant-numeric: tabular-nums;
        }
        .cdp-summary-table tbody td.num { white-space: nowrap; }
        .cdp-receipt-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 18px;
        }
        .cdp-receipt-actions .btn {
            border-radius: var(--radius-btn);
        }
        .cdp-receipt .cdp-ac {
            position: relative;
        }
        .cdp-receipt .cdp-ac-input {
            width: 100%;
        }
        .cdp-receipt .cdp-ac-list {
            position: absolute;
            z-index: 50;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            background: #fff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-btn);
            box-shadow: 0 12px 30px rgba(27,27,58,0.12);
            list-style: none;
            margin: 0;
            padding: 6px 0;
            max-height: 220px;
            overflow: auto;
        }
        .cdp-receipt .cdp-ac-list[hidden] {
            display: none !important;
        }
        .cdp-receipt .cdp-ac-list li {
            padding: 6px 10px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text);
        }
        .cdp-receipt .cdp-ac-list li:hover {
            background: rgba(227,242,253,0.6);
        }
        .cdp-receipt .cdp-ac-list .cdp-ac-empty {
            color: var(--muted-text);
            cursor: default;
        }
        .cdp-receipt .cdp-ac-list .cdp-ac-sub {
            color: var(--muted-text);
            font-size: 12px;
            margin-left: 6px;
        }
        /* Receipt attachment: compact upload dropzone, table and preview */
        .cdp-receipt-inner { padding: 14px 16px 16px; }
        .cdp-upload-form { margin: 0 0 16px; }
        .cdp-upload-zone {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 20px 16px;
            border: 1.5px dashed #b9c6da;
            border-radius: 12px;
            background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
            text-align: center;
            transition: border-color var(--transition-fast), background var(--transition-fast), box-shadow var(--transition-fast);
        }
        .cdp-upload-zone.is-dragging {
            border-color: var(--sky-deep);
            background: var(--sky-light);
            box-shadow: 0 0 0 4px rgba(46,155,222,0.12);
        }
        .cdp-upload-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
        .cdp-upload-icon { font-size: 26px; line-height: 1; color: var(--sky-deep); }
        .cdp-upload-title { margin: 0; font-size: 14px; font-weight: 600; color: var(--navy); }
        .cdp-upload-hint { margin: 0; font-size: 12px; color: var(--muted-text); }
        .cdp-upload-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 4px 8px;
            margin: 4px 0 0;
            font-size: 11.5px;
            color: var(--muted-text);
        }
        .cdp-upload-meta i { color: var(--sky-deep); }
        .cdp-upload-sep { opacity: .45; }
        .cdp-btn-primary {
            background: var(--navy);
            border: 1px solid var(--navy);
            color: #fff;
        }
        .cdp-btn-primary:hover,
        .cdp-btn-primary:focus-visible {
            background: var(--sky-deep);
            border-color: var(--sky-deep);
            color: #fff;
            box-shadow: 0 6px 16px rgba(46,155,222,0.25);
            text-decoration: none;
            transform: translateY(-1px);
        }
        .cdp-btn-ghost {
            background: #fff;
            border: 1px solid var(--border-subtle);
            color: var(--navy);
        }
        .cdp-btn-ghost:hover,
        .cdp-btn-ghost:focus-visible {
            background: var(--bg-alt);
            border-color: #cbd5e1;
            color: var(--navy);
            text-decoration: none;
        }
        .cdp-choose-files {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .cdp-upload-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }
        .upload-feedback { min-height: 18px; font-size: 12.5px; }
        .upload-feedback .text-success { color: var(--success); }
        .upload-feedback .text-danger { color: var(--danger); }
        .selected-files {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            background: #fff;
        }
        .selected-files[hidden] { display: none; }
        .selected-files-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 12px;
            color: var(--muted-text);
        }
        .selected-files-clear {
            padding: 0;
            border: 0;
            background: none;
            color: var(--sky-deep);
            font-size: 12px;
            cursor: pointer;
        }
        .selected-files-clear:hover { text-decoration: underline; }
        .selected-files-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 110px;
            overflow: auto;
        }
        .selected-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
            padding: 4px 8px;
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            background: var(--bg-alt);
            color: var(--navy);
            font-size: 11.5px;
        }
        .selected-file-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .selected-file-size { color: var(--muted-text); }
        .selected-file-remove {
            padding: 0 2px;
            border: 0;
            background: none;
            color: var(--muted-text);
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
        }
        .selected-file-remove:hover { color: var(--danger); }

        .cdp-receipts-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .cdp-receipts-table thead th {
            padding: 8px 12px;
            background: var(--bg-alt);
            color: var(--navy);
            font-weight: 600;
            text-align: left;
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap;
        }
        .cdp-receipts-table thead th:first-child { border-top-left-radius: 9px; }
        .cdp-receipts-table thead th:last-child { border-top-right-radius: 9px; }
        .cdp-receipts-table tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid rgba(229,231,235,0.8);
            vertical-align: middle;
        }
        .cdp-receipts-table tbody tr { transition: background var(--transition-fast); }
        .cdp-receipts-table tbody tr:hover { background: rgba(227,242,253,0.35); }
        .cdp-receipts-table .cdp-actions-col { text-align: right; }
        .cdp-receipt-file { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .cdp-file-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            font-size: 13px;
        }
        .cdp-file-badge.is-image { background: var(--sky-soft); color: var(--sky-deep); }
        .cdp-file-badge.is-pdf { background: var(--danger-soft); color: var(--danger); }
        .receipt-filename {
            display: block;
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text);
            font-weight: 500;
        }
        .receipt-type {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eef2f7;
            color: var(--navy);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .cdp-receipt-date {
            color: var(--muted-text);
            font-size: 12.5px;
            white-space: nowrap;
        }
        .cdp-row-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        .cdp-btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border-radius: 9px;
            font-size: 12.5px;
            font-weight: 600;
            white-space: nowrap;
        }
        .cdp-delete-form { margin: 0; }
        .cdp-btn-danger-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid rgba(231,76,60,0.28);
            border-radius: 9px;
            background: var(--danger-soft);
            color: var(--danger);
            font-size: 12.5px;
            transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast), transform var(--transition-fast);
        }
        .cdp-btn-danger-icon:hover,
        .cdp-btn-danger-icon:focus-visible {
            background: var(--danger);
            border-color: var(--danger);
            color: #fff;
            transform: translateY(-1px);
        }
        .cdp-tooltip { position: relative; }
        .cdp-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 7px);
            left: 50%;
            z-index: 30;
            padding: 5px 8px;
            border-radius: 6px;
            background: var(--navy);
            color: #fff;
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translate(-50%, 4px);
            transition: opacity var(--transition-fast), transform var(--transition-fast), visibility var(--transition-fast);
        }
        .cdp-tooltip:hover::after,
        .cdp-tooltip:focus-visible::after {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, 0);
        }
        .cdp-receipt-empty {
            padding: 26px 16px;
            text-align: center;
            color: var(--muted-text);
            font-size: 13px;
        }
        .cdp-receipt-empty i {
            display: block;
            margin-bottom: 6px;
            color: var(--sky-deep);
            font-size: 22px;
            opacity: .75;
        }
        .cdp-receipt-empty p { margin: 0; }
        .cdp-receipt-note { font-size: 13px; }

        /* Receipt preview modal */
        .cdp-preview-modal {
            position: fixed;
            inset: 0;
            z-index: 1080;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .cdp-preview-modal.is-open { display: flex; }
        .cdp-preview-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(18,18,43,0.72);
            backdrop-filter: blur(3px);
        }
        .cdp-preview-dialog {
            position: relative;
            display: flex;
            flex-direction: column;
            width: min(1100px, 96vw);
            max-height: 92vh;
            overflow: hidden;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 80px rgba(0,0,0,0.35);
            animation: cdpModalIn .18s ease-out;
        }
        @keyframes cdpModalIn {
            from { opacity: 0; transform: translateY(8px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .cdp-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .cdp-preview-heading { min-width: 0; }
        .cdp-preview-eyebrow {
            display: block;
            color: var(--muted-text);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .cdp-preview-heading h3 {
            margin: 2px 0 0;
            overflow: hidden;
            color: var(--navy);
            font-size: 16px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cdp-preview-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid var(--border-subtle);
            border-radius: 9px;
            background: #fff;
            color: var(--muted-text);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast);
        }
        .cdp-preview-close:hover { background: var(--bg-alt); color: var(--navy); border-color: #cbd5e1; }
        .cdp-preview-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 9px 16px;
            border-bottom: 1px solid var(--border-subtle);
            background: var(--surface-sunken);
        }
        .cdp-zoom-controls { display: flex; align-items: center; gap: 6px; }
        .cdp-zoom-controls[hidden] { display: none; }
        .cdp-zoom-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            background: #fff;
            color: var(--navy);
            font-size: 12px;
            cursor: pointer;
            transition: background var(--transition-fast), border-color var(--transition-fast);
        }
        .cdp-zoom-btn:hover { background: var(--bg-alt); border-color: #cbd5e1; }
        .cdp-zoom-value {
            min-width: 48px;
            text-align: center;
            color: var(--navy);
            font-size: 12px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        .cdp-preview-toolbar-actions { display: flex; gap: 8px; margin-left: auto; }
        .cdp-preview-toolbar-actions[hidden] { display: none; }
        .cdp-preview-body {
            flex: 1 1 auto;
            min-height: 240px;
            overflow: auto;
            padding: 16px;
            background: var(--surface-sunken);
        }
        .cdp-preview-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            min-height: 240px;
        }
        .cdp-image-stage {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            width: 100%;
            max-height: 68vh;
            overflow: auto;
            padding: 4px;
        }
        .cdp-preview-image {
            display: block;
            max-width: 100%;
            max-height: 66vh;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(16,24,40,0.12);
            transform-origin: center center;
            transition: transform var(--transition-fast);
        }
        .cdp-pdf-frame {
            width: 100%;
            height: 70vh;
            min-height: 420px;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            background: #fff;
        }
        .cdp-pdf-fallback {
            margin: 0;
            color: var(--muted-text);
            font-size: 12px;
            text-align: center;
        }
        .cdp-preview-state {
            padding: 42px 20px;
            color: var(--muted-text);
            font-size: 13px;
            text-align: center;
        }
        .cdp-preview-state[hidden] { display: none; }
        .cdp-preview-error { color: var(--danger); }
        body.cdp-modal-open { overflow: hidden; }

        @media (max-width: 720px) {
            .cdp-receipt-inner { padding: 12px; }
            .cdp-upload-zone { padding: 18px 12px; }
            .cdp-choose-files { width: 100%; justify-content: center; }
            .cdp-receipts-table thead { display: none; }
            .cdp-receipts-table tbody tr {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 6px 12px;
                padding: 12px;
                border-bottom: 1px solid var(--border-subtle);
            }
            .cdp-receipts-table tbody td { padding: 0; border: 0; }
            .cdp-receipts-table tbody td:nth-child(1) { grid-column: 1 / -1; }
            .cdp-receipts-table tbody td:nth-child(4) { grid-column: 1 / -1; justify-self: end; }
            .cdp-row-actions { justify-content: flex-start; }
            .receipt-filename { max-width: 220px; }
            .cdp-preview-modal { padding: 0; }
            .cdp-preview-dialog { width: 100%; max-height: 100vh; border-radius: 0; }
            .cdp-preview-toolbar { align-items: stretch; flex-direction: column; }
            .cdp-preview-toolbar-actions { justify-content: flex-end; width: 100%; }
            .cdp-preview-body { padding: 10px; }
            .cdp-pdf-frame { height: 64vh; min-height: 320px; }
        }
        @media (max-width: 640px) {
            .cdp-receipt-table thead { display: none; }
            .cdp-receipt-table tbody tr {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 6px 10px;
                padding: 10px 16px;
                border-bottom: 1px solid var(--border-subtle);
            }
            .cdp-receipt-table tbody td {
                border: 0;
                padding: 4px 0;
            }
            .cdp-receipt-table tbody td:first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 640px) {
            .cdp-receipt-meta { flex-direction: column; align-items: flex-start; }
            .cdp-receipt-table thead { display: none; }
            .cdp-receipt-table tbody tr {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px 10px;
                padding: 10px 16px;
                border-bottom: 1px solid var(--border-subtle);
            }
            .cdp-receipt-table tbody td {
                border: 0;
                padding: 4px 0;
            }
            .cdp-receipt-table tbody td:first-child { grid-column: 1 / -1; }
            .cdp-receipt-table tfoot tr { display: grid; grid-template-columns: 1fr 1fr; padding: 10px 16px; }
            .cdp-receipt-table tfoot td,
            .cdp-receipt-table tfoot th { border: 0; padding: 4px 0; }

            /* Keep the summary as a real two-column table on narrow screens. */
            .cdp-summary-table thead { display: table-header-group; }
            .cdp-summary-table tbody tr { display: table-row; padding: 0; }
            .cdp-summary-table tbody td {
                display: table-cell;
                padding: 8px 12px;
                border-bottom: 1px solid rgba(229,231,235,0.8);
            }
            .cdp-summary-table tbody td:first-child { grid-column: auto; }
            .cdp-summary-table tbody td.num { text-align: right; }

            /* Company and Remarks get full-width rows; price/actions share one. */
            .cdp-add-table tbody tr { grid-template-columns: 1fr auto; }
            .cdp-add-table tbody td:nth-child(1),
            .cdp-add-table tbody td:nth-child(2) { grid-column: 1 / -1; }
            .cdp-add-table tbody td:nth-child(3) { grid-column: 1; }
            .cdp-add-table tbody td:nth-child(4) { grid-column: 2; text-align: right; }
            .cdp-add-table tfoot tr { display: table-row; }
            .cdp-add-table tfoot td {
                display: table-cell;
                padding: 8px 12px;
                border-top: 1px solid var(--border-subtle);
            }
        }
    </style>
    @endpush

    <script>
    function addRow(tableId, name, cls){
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const idx = Date.now() + Math.floor(Math.random()*1000);
        const tr = document.createElement('tr');
        if(name === 'additions'){
            tr.innerHTML = '<td><div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}"><input type="text" class="cdp-ac-input" name="additions['+idx+'][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required><ul class="cdp-ac-list" role="listbox" hidden></ul></div></td><td><input type="text" name="additions['+idx+'][notes]" maxlength="500" autocomplete="off" placeholder="Enter remarks..."></td><td class="num"><input type="number" step="0.01" class="amt-add" name="additions['+idx+'][amount]" required></td><td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest(\'tr\').remove(); calcTotals();">Remove</button></td>';
        } else if(name === 'salaries'){
            tr.innerHTML = '<td><div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.staff.json') }}"><input type="text" class="cdp-ac-input" name="salaries['+idx+'][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Description" required><ul class="cdp-ac-list" role="listbox" hidden></ul></div></td><td class="num"><input type="number" step="0.01" class="amt-sal" name="salaries['+idx+'][amount]" required></td><td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest(\'tr\').remove(); calcTotals();">Remove</button></td>';
        } else if(name === 'expenses'){
            tr.innerHTML = '<td><input type="text" name="expenses['+idx+'][label]" required></td><td class="num"><input type="number" step="0.01" class="amt-exp" name="expenses['+idx+'][amount]" required></td><td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest(\'tr\').remove(); calcTotals();">Remove</button></td>';
        }
        tbody.appendChild(tr);
        tr.querySelectorAll('input[type=number]').forEach(inp => inp.addEventListener('input', calcTotals));
        tr.querySelectorAll('input[type=text]').forEach(inp => inp.addEventListener('input', calcTotals));
        if(typeof initCashAutocomplete === 'function'){
            tr.querySelectorAll('.cdp-ac').forEach(function(root){ initCashAutocomplete(root); });
        }
        calcTotals();
    }
    function calcTotals(){
        let a=0,s=0,e=0;
        document.querySelectorAll('.amt-add').forEach(i=>{ if(i.value!=='' && !isNaN(parseFloat(i.value))) a+=parseFloat(i.value); });
        document.querySelectorAll('.amt-sal').forEach(i=>{ if(i.value!=='' && !isNaN(parseFloat(i.value))) s+=parseFloat(i.value); });
        document.querySelectorAll('.amt-exp').forEach(i=>{ if(i.value!=='' && !isNaN(parseFloat(i.value))) e+=parseFloat(i.value); });
        const begEl = document.querySelector('input[name=beginning_balance]');
        const beg = begEl && begEl.value!=='' ? parseFloat(begEl.value)||0 : 0;
        const less = s+e; const end = beg+a-less;
        const actEl = document.querySelector('input[name=actual_cash]');
        const actual = actEl && actEl.value!=='' ? parseFloat(actEl.value) : NaN;
        document.getElementById('totalAdd').textContent = '₱'+a.toFixed(2);
        document.getElementById('totalSal').textContent = '₱'+s.toFixed(2);
        document.getElementById('totalExp').textContent = '₱'+e.toFixed(2);
        document.getElementById('sumBeg').textContent = '₱'+beg.toFixed(2);
        document.getElementById('sumAdd').textContent = '+'+'₱'+a.toFixed(2);
        document.getElementById('sumLess').textContent = '-'+'₱'+less.toFixed(2);
        document.getElementById('sumEnd').textContent = '₱'+end.toFixed(2);
        if(!isNaN(actual)){ document.getElementById('sumOS').textContent = '₱'+(end-actual).toFixed(2); } else { document.getElementById('sumOS').textContent = '₱0.00'; }
    }
    function initCashAutocomplete(root){
        if(!root || root.dataset.acInit === '1'){ return; }
        root.dataset.acInit = '1';
        const input = root.querySelector('.cdp-ac-input');
        const list = root.querySelector('.cdp-ac-list');
        const url = root.getAttribute('data-search-url');
        if(!input || !list || !url){ return; }
        let timer = null;
        let controller = null;
        function closeList(){
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }
        function render(items){
            list.innerHTML = '';
            if(!items.length){
                const li = document.createElement('li');
                li.className = 'cdp-ac-empty';
                li.textContent = 'No suggestions';
                list.appendChild(li);
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                return;
            }
            items.forEach(function(item){
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.innerHTML = item.label + (item.sub ? ' <span class="cdp-ac-sub">' + item.sub + '</span>' : '');
                li.addEventListener('mousedown', function(e){
                    e.preventDefault();
                    input.value = item.label;
                    closeList();
                });
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        function normalize(raw){
            return (raw||[]).map(function(c){
                return {
                    id: c.id,
                    label: c.name || c.business_name || c.label || ('#' + c.id),
                    sub: c.business_name ? '' : (c.role || c.email || '')
                };
            });
        }
        input.addEventListener('input', function(){
            const q = input.value.trim();
            if(timer) clearTimeout(timer);
            timer = setTimeout(function(){
                if(controller) controller.abort();
                controller = new AbortController();
                fetch(url + '?q=' + encodeURIComponent(q), { signal: controller.signal, headers: { 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); })
                    .then(function(data){ render(normalize(data)); })
                    .catch(function(){ closeList(); });
            }, 150);
        });
        input.addEventListener('focus', function(){
            if(list.children.length){ list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        });
        input.addEventListener('blur', function(){ setTimeout(closeList, 150); });
        input.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeList(); } });
    }

        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.cdp-receipt input[type=number], .cdp-receipt input[type=text]').forEach(inp=>{
                inp.addEventListener('input', calcTotals);
            });
            document.querySelectorAll('.cdp-receipt .cdp-ac').forEach(function(root){ initCashAutocomplete(root); });
            calcTotals();

            // Guard the report save against a double click / repeated submit.
            const dailyForm = document.getElementById('cdpDailyForm');
            if(dailyForm){
                dailyForm.addEventListener('submit', function(e){
                    if(dailyForm.dataset.submitting === '1'){
                        e.preventDefault();
                        return;
                    }
                    dailyForm.dataset.submitting = '1';
                    document.querySelectorAll('[form="cdpDailyForm"]').forEach(function(btn){ btn.disabled = true; });
                });
            }

            // ---- Receipt upload: drag/drop + selected file chips --------------
            const fileInput = document.getElementById('receiptFiles');
            const dropZone = document.getElementById('receiptDropZone');
            const chooseBtn = document.getElementById('receiptChooseBtn');
            const selectedFilesDiv = document.getElementById('selectedFiles');
            const uploadForm = document.getElementById('receiptUploadForm');
            const uploadBtn = document.getElementById('receiptUploadBtn');
            const uploadFeedback = document.getElementById('uploadFeedback');
            const MAX_FILES = 10;
            const MAX_BYTES = 2 * 1024 * 1024;
            const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf'];
            const ALLOWED_MIME = ['image/jpeg', 'image/png', 'application/pdf'];
            let pendingFiles = [];

            function showUploadFeedback(message, tone){
                if(!uploadFeedback) return;
                uploadFeedback.innerHTML = '';
                if(!message) return;
                const span = document.createElement('span');
                span.className = tone === 'danger' ? 'text-danger' : (tone === 'success' ? 'text-success' : 'text-muted');
                span.textContent = message;
                uploadFeedback.appendChild(span);
            }

            function receiptFileKey(file){
                return [file.name, file.size, file.lastModified].join(':');
            }

            function syncReceiptInput(){
                if(fileInput){
                    try {
                        const dt = new DataTransfer();
                        pendingFiles.forEach(function(file){ dt.items.add(file); });
                        fileInput.files = dt.files;
                    } catch (err) {
                        // Older browsers keep the original input selection.
                    }
                }
                renderSelectedReceipts();
            }

            function renderSelectedReceipts(){
                if(!selectedFilesDiv || !uploadBtn) return;
                selectedFilesDiv.innerHTML = '';
                if(pendingFiles.length === 0){
                    selectedFilesDiv.hidden = true;
                    uploadBtn.disabled = true;
                    return;
                }
                selectedFilesDiv.hidden = false;
                uploadBtn.disabled = false;

                const head = document.createElement('div');
                head.className = 'selected-files-head';
                const label = document.createElement('span');
                label.textContent = 'Selected files (' + pendingFiles.length + ')';
                const clear = document.createElement('button');
                clear.type = 'button';
                clear.className = 'selected-files-clear';
                clear.textContent = 'Clear all';
                clear.addEventListener('click', function(){
                    pendingFiles = [];
                    syncReceiptInput();
                    showUploadFeedback('');
                });
                head.appendChild(label);
                head.appendChild(clear);
                selectedFilesDiv.appendChild(head);

                const list = document.createElement('div');
                list.className = 'selected-files-list';
                pendingFiles.forEach(function(file, index){
                    const chip = document.createElement('span');
                    chip.className = 'selected-file-chip';

                    const name = document.createElement('span');
                    name.className = 'selected-file-name';
                    name.textContent = file.name;

                    const size = document.createElement('span');
                    size.className = 'selected-file-size';
                    size.textContent = (file.size / 1024).toFixed(1) + ' KB';

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'selected-file-remove';
                    remove.setAttribute('aria-label', 'Remove ' + file.name);
                    const removeIconTemplate = document.getElementById('receiptRemoveIconTemplate');
                    if(removeIconTemplate){
                        remove.appendChild(removeIconTemplate.content.cloneNode(true));
                    }
                    remove.addEventListener('click', function(){
                        pendingFiles.splice(index, 1);
                        syncReceiptInput();
                    });

                    chip.appendChild(name);
                    chip.appendChild(size);
                    chip.appendChild(remove);
                    list.appendChild(chip);
                });
                selectedFilesDiv.appendChild(list);
            }

            function addReceiptFiles(fileList){
                if(!fileList || !fileList.length) return;
                const errors = [];
                Array.from(fileList).forEach(function(file){
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    if(ALLOWED_EXT.indexOf(ext) === -1 || (file.type && ALLOWED_MIME.indexOf(file.type) === -1)){
                        errors.push(file.name + ' is not a supported file type.');
                        return;
                    }
                    if(file.size > MAX_BYTES){
                        errors.push(file.name + ' is larger than 2 MB.');
                        return;
                    }
                    if(pendingFiles.some(function(existing){ return receiptFileKey(existing) === receiptFileKey(file); })){
                        return;
                    }
                    if(pendingFiles.length >= MAX_FILES){
                        errors.push('You can upload up to 10 files at once.');
                        return;
                    }
                    pendingFiles.push(file);
                });
                syncReceiptInput();
                showUploadFeedback(errors.length ? errors.join(' ') : '', errors.length ? 'danger' : '');
            }

            if(fileInput){
                fileInput.addEventListener('change', function(){ addReceiptFiles(fileInput.files); });
            }
            if(chooseBtn && fileInput){
                chooseBtn.addEventListener('click', function(e){ e.preventDefault(); fileInput.click(); });
            }
            if(dropZone && fileInput){
                ['dragenter', 'dragover'].forEach(function(evt){
                    dropZone.addEventListener(evt, function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.add('is-dragging');
                    });
                });
                ['dragleave', 'dragend'].forEach(function(evt){
                    dropZone.addEventListener(evt, function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.remove('is-dragging');
                    });
                });
                dropZone.addEventListener('drop', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('is-dragging');
                    if(e.dataTransfer) addReceiptFiles(e.dataTransfer.files);
                });
                dropZone.addEventListener('click', function(e){
                    if(e.target.closest('button, a, input, label')) return;
                    fileInput.click();
                });
            }

            if(uploadForm && uploadBtn){
                uploadForm.addEventListener('submit', function(e){
                    if(uploadForm.dataset.submitting === '1'){
                        e.preventDefault();
                        return;
                    }
                    if(!fileInput || !fileInput.files || fileInput.files.length === 0){
                        e.preventDefault();
                        showUploadFeedback('Please select at least one file.', 'danger');
                        return;
                    }
                    uploadForm.dataset.submitting = '1';
                    uploadBtn.disabled = true;
                    const uploadLabel = document.getElementById('receiptUploadLabel');
                    if(uploadLabel) uploadLabel.textContent = 'Uploading...';
                    showUploadFeedback('Uploading ' + fileInput.files.length + ' file(s)…', '');
                });
            }

            // ---- Receipt preview modal --------------------------------------
            const previewModal = document.getElementById('receiptPreviewModal');
            const previewTitle = document.getElementById('receiptPreviewTitle');
            const previewContent = document.getElementById('receiptModalContent');
            const previewLoading = document.getElementById('receiptModalLoading');
            const previewError = document.getElementById('receiptModalError');
            const zoomControls = document.getElementById('receiptZoomControls');
            const zoomLevel = document.getElementById('receiptZoomLevel');
            const downloadBtn = document.getElementById('receiptDownloadBtn');
            const viewButtons = document.querySelectorAll('.btn-view-receipt');
            let currentPreviewUrl = null;
            let currentZoom = 1;
            let currentRotation = 0;
            let lastFocused = null;
            let previewRequestId = 0;

            function showPreviewError(message){
                if(previewLoading) previewLoading.hidden = true;
                if(zoomControls) zoomControls.hidden = true;
                if(previewContent) previewContent.innerHTML = '';
                if(previewError){
                    previewError.textContent = message;
                    previewError.hidden = false;
                }
            }

            function applyPreviewZoom(){
                if(!previewContent) return;
                const image = previewContent.querySelector('.cdp-preview-image');
                if(!image) return;
                image.style.transform = 'scale(' + currentZoom + ') rotate(' + currentRotation + 'deg)';
                if(zoomLevel) zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
            }

            function setPreviewZoom(next){
                currentZoom = Math.min(4, Math.max(0.5, next));
                applyPreviewZoom();
            }

            function closePreview(){
                if(!previewModal || !previewModal.classList.contains('is-open')) return;
                previewRequestId++;
                previewModal.classList.remove('is-open');
                previewModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('cdp-modal-open');
                if(currentPreviewUrl){
                    URL.revokeObjectURL(currentPreviewUrl);
                    currentPreviewUrl = null;
                }
                if(previewContent) previewContent.innerHTML = '';
                if(previewError) previewError.hidden = true;
                if(previewLoading) previewLoading.hidden = true;
                if(zoomControls) zoomControls.hidden = true;
                currentZoom = 1;
                currentRotation = 0;
                if(lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
            }

            function openPreview(button){
                if(!previewModal) return;
                const requestId = ++previewRequestId;
                lastFocused = button;
                const url = button.getAttribute('data-url');
                const downloadUrl = button.getAttribute('data-download-url');
                const type = (button.getAttribute('data-type') || '').toLowerCase();
                const fileName = button.getAttribute('data-filename') || 'Receipt';

                currentZoom = 1;
                currentRotation = 0;
                if(previewTitle) previewTitle.textContent = fileName;
                if(downloadBtn){
                    downloadBtn.href = downloadUrl || '#';
                    downloadBtn.hidden = !downloadUrl;
                }
                if(previewContent) previewContent.innerHTML = '';
                if(previewError) previewError.hidden = true;
                if(previewLoading) previewLoading.hidden = false;
                if(zoomControls) zoomControls.hidden = true;
                previewModal.classList.add('is-open');
                previewModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('cdp-modal-open');

                if(currentPreviewUrl){
                    URL.revokeObjectURL(currentPreviewUrl);
                    currentPreviewUrl = null;
                }

                fetch(url, {
                    credentials: 'same-origin'
                })
                    .then(function(response){
                        if(!response.ok){
                            if(response.status === 404) throw new Error('This receipt file is missing or no longer available.');
                            if(response.status === 415) throw new Error('This file type cannot be previewed. Use Download to open it.');
                            throw new Error('Unable to load this receipt (' + response.status + ').');
                        }
                        return response.json();
                    })
                    .then(function(data){
                        if(typeof data === 'object' && data.url){
                            // Server returned a signed temporary URL – follow it.
                            window.location = data.url;
                            // Also set the download button so the user can save the file.
                            if(downloadBtn){
                                downloadBtn.href = data.url;
                                downloadBtn.download = data.fileName || 'receipt';
                                downloadBtn.hidden = false;
                            }
                            return;
                        }
                        // Fallback: treat as blob preview (original behaviour).
                        const objectUrl = URL.createObjectURL(data);
                        if(requestId !== previewRequestId){
                            URL.revokeObjectURL(objectUrl);
                            return;
                        }
                        currentPreviewUrl = objectUrl;
                        if(previewLoading) previewLoading.hidden = true;

                        const blobType = (data.type || '').toLowerCase();
                        const isImage = /^image\/(jpeg|png)$/.test(blobType) || /^(jpg|jpeg|png)$/.test(type);
                        const isPdf = blobType.indexOf('application/pdf') !== -1 || type === 'pdf';

                        if(isImage){
                            const stage = document.createElement('div');
                            stage.className = 'cdp-image-stage';
                            const image = document.createElement('img');
                            image.className = 'cdp-preview-image';
                            image.alt = 'Receipt preview';
                            image.src = objectUrl;
                            image.addEventListener('load', applyPreviewZoom);
                            stage.appendChild(image);
                            previewContent.appendChild(stage);
                            if(zoomControls) zoomControls.hidden = false;
                            applyPreviewZoom();
                            return;
                        }

                        if(isPdf){
                            const pdfViewerSupported = !('pdfViewerEnabled' in navigator) || navigator.pdfViewerEnabled;
                            if(!pdfViewerSupported){
                                showPreviewError('PDF preview is not supported in this browser. Use Download to open the file.');
                                return;
                            }
                            const frame = document.createElement('iframe');
                            frame.className = 'cdp-pdf-frame';
                            frame.title = 'Receipt PDF preview';
                            frame.src = objectUrl + '#toolbar=1&view=FitH';
                            previewContent.appendChild(frame);
                            const fallback = document.createElement('p');
                            fallback.className = 'cdp-pdf-fallback';
                            fallback.textContent = 'If the PDF preview does not load, use the Download button.';
                            previewContent.appendChild(fallback);
                            return;
                        }

                        showPreviewError('This file type cannot be previewed. Use Download to open it.');

                        // Ensure the download button is hidden when we fall back to blob preview.
                        if(downloadBtn){
                            downloadBtn.hidden = true;
                        }
                    })
                    .catch(function(err){
                        if(requestId !== previewRequestId) return;
                        showPreviewError(err && err.message ? err.message : 'Unable to load receipt preview.');
                    });
            }

            document.querySelectorAll('[data-receipt-close]').forEach(function(el){
                el.addEventListener('click', closePreview);
            });
            document.addEventListener('keydown', function(e){
                if(e.key === 'Escape') closePreview();
            });
            const zoomIn = document.getElementById('receiptZoomIn');
            const zoomOut = document.getElementById('receiptZoomOut');
            const zoomReset = document.getElementById('receiptZoomReset');
            const rotateBtn = document.getElementById('receiptRotateBtn');
            if(zoomIn) zoomIn.addEventListener('click', function(){ setPreviewZoom(currentZoom + 0.25); });
            if(zoomOut) zoomOut.addEventListener('click', function(){ setPreviewZoom(currentZoom - 0.25); });
            if(zoomReset) zoomReset.addEventListener('click', function(){ currentZoom = 1; currentRotation = 0; applyPreviewZoom(); });
            if(rotateBtn) rotateBtn.addEventListener('click', function(){ currentRotation = (currentRotation + 90) % 360; applyPreviewZoom(); });
            viewButtons.forEach(function(button){
                button.addEventListener('click', function(){ openPreview(button); });
            });
        });
    </script>
@endsection