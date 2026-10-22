@extends('layouts.dashboard')

@php
    use App\Support\Cash\CashReportPeriod;
    use App\Models\CashDailyLine;
    use Illuminate\Support\Carbon;
@endphp

@section('title', 'Cash Daily Position — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Cash Daily Position Report</h1>
            <p>One client, one accounting period: beginning balance, cash received (ADD), salaries and expenses (LESS), ending balance, actual cash counted, and the overage or shortage.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.cash-daily-position.index') }}" class="btn btn-secondary">Daily Archive</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cdpCreateModal">
                Add Cash Daily Position Report
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Creation modal --}}
    <div class="modal fade" id="cdpCreateModal" tabindex="-1" aria-hidden="true" data-auto-open="{{ ($period === null && ($selection['client_id'] ?? null)) ? '1' : '0' }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="GET" action="{{ route('admin.cash-daily-position.index') }}" id="cdpCreateForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Cash Daily Position Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="cdp-modal-grid">
                            <div class="cdp-field cdp-field-client">
                                <span>Client name</span>
                                <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}">
                                    <input type="text" class="cdp-ac-input" placeholder="Type to search clients..." autocomplete="off" role="combobox" aria-expanded="false" aria-controls="cdpAcList">
                                    <input type="hidden" name="client_id" value="{{ (int) ($selection['client_id'] ?? 0) ?: '' }}">
                                    <button type="button" class="cdp-ac-clear" title="Clear selection" aria-label="Clear selection" hidden>&times;</button>
                                    <ul class="cdp-ac-list" id="cdpAcList" role="listbox" hidden></ul>
                                </div>
                            </div>

                            <label class="cdp-field">
                                <span>Period type</span>
                                <select name="period_type" id="cdpPeriodType" required>
                                    <option value="">— Select a period —</option>
                                    @foreach (CashReportPeriod::TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected(($selection['period_type'] ?? null) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="cdp-field cdp-field-week" data-period="week">
                                <span>Month</span>
                                <select name="month">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected((int) ($selection['month'] ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                                    @endfor
                                </select>
                            </label>

                            <label class="cdp-field cdp-field-week" data-period="week">
                                <span>Week</span>
                                <select name="week" id="cdpWeek">
                                    @for ($w = 1; $w <= 5; $w++)
                                        <option value="{{ $w }}" @selected((int) ($selection['week'] ?? 0) === $w)>{{ $w }}{{ ['','st','nd','rd','th','th'][$w] }} Week</option>
                                    @endfor
                                </select>
                            </label>

                            <label class="cdp-field cdp-field-month" data-period="month">
                                <span>Month</span>
                                <select name="month">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @selected((int) ($selection['month'] ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                                    @endfor
                                </select>
                            </label>

                            <label class="cdp-field cdp-field-quarter" data-period="quarter">
                                <span>Quarter</span>
                                <select name="quarter">
                                    @for ($q = 1; $q <= 4; $q++)
                                        <option value="{{ $q }}" @selected((int) ($selection['quarter'] ?? 0) === $q)>{{ $q }}{{ ['','st','nd','rd','th'][$q] }} Quarter</option>
                                    @endfor
                                </select>
                            </label>

                            <label class="cdp-field">
                                <span>Year</span>
                                <select name="year" required>
                                    <option value="">— Year —</option>
                                    @foreach ($years as $year)
                                        <option value="{{ $year }}" @selected((int) ($selection['year'] ?? 0) === $year)>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <p class="cdp-hint">All fields are required. The worksheet shows only the selected client's data for the selected period.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">View Worksheet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if(isset($view) && $view === 'archive')
        @php
            $arch = $archive ?? ['year' => now()->year, 'month' => now()->month, 'monthName' => now()->format('F Y'), 'days' => [], 'prevMonth' => now()->subMonth()->format('Y-m'), 'nextMonth' => now()->addMonth()->format('Y-m')];
            [$py, $pm] = explode('-', $arch['prevMonth']);
            [$ny, $nm] = explode('-', $arch['nextMonth']);
        @endphp
        <div class="cdp-archive-head">
            <div class="cdp-archive-nav">
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.cash-daily-position.index', ['year' => $py, 'month' => $pm]) }}">&larr; Prev Month</a>
                <strong>{{ $arch['monthName'] }}</strong>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.cash-daily-position.index', ['year' => $ny, 'month' => $nm]) }}">Next Month &rarr;</a>
            </div>
            <div class="cdp-archive-add">
                <form method="GET" action="{{ route('admin.cash-daily-position.create') }}" class="cdp-archive-add-form">
                    <input type="date" name="date" value="{{ now()->toDateString() }}" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Report for Date</button>
                </form>
            </div>
        </div>
        <div class="cdp-archive-grid">
            @foreach($arch['days'] as $day)
                @php $d = $day['date']; $key = $d->format('Y-m-d'); $has = $day['hasReport']; @endphp
                <div class="cdp-archive-card {{ $has ? 'has' : '' }}">
                    <div class="cdp-archive-date">
                        <div class="cdp-archive-day">{{ $d->format('d') }}</div>
                        <div class="cdp-archive-dow">{{ $d->format('D') }}</div>
                    </div>
                    <div class="cdp-archive-actions">
                        @if($has)
                            <a href="{{ route('admin.cash-daily-position.show', ['date' => $key]) }}" class="btn btn-secondary btn-sm">Open Report</a>
                        @else
                            <a href="{{ route('admin.cash-daily-position.create', ['date' => $key]) }}" class="btn btn-primary btn-sm">Add Report</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @push('styles')
        <style>
            .cdp-archive-head {
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin:18px 0;
                flex-wrap:wrap;
                gap:12px;
                background: linear-gradient(180deg, rgba(227,242,253,0.6) 0%, rgba(255,255,255,1) 100%);
                border: 1px solid var(--border-subtle);
                border-radius: var(--radius-card);
                box-shadow: 0 10px 30px rgba(27,27,58,0.05);
                padding: 12px 16px;
            }
            .cdp-archive-nav {
                display:flex;
                align-items:center;
                gap:12px;
            }
            .cdp-archive-nav strong {
                font-size: 16px;
                color: var(--navy);
                letter-spacing: 0.01em;
            }
            .cdp-archive-add-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
            .cdp-archive-add-form input[type="date"] {
                padding: 6px 10px;
                border: 1px solid var(--border-subtle);
                border-radius: var(--radius-btn);
                font-size: 13px;
                background: #fff;
                color: var(--text);
            }
            .cdp-archive-grid {
                display:grid;
                grid-template-columns: repeat(auto-fill,minmax(230px,1fr));
                gap:12px;
            }
            .cdp-archive-card {
                display:flex;
                justify-content:space-between;
                align-items:center;
                border: 1px solid var(--border-subtle);
                border-radius: var(--radius-card);
                padding: 12px 14px;
                background: var(--surface);
                box-shadow: 0 6px 18px rgba(27,27,58,0.04);
                transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease, background 160ms ease;
            }
            .cdp-archive-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 26px rgba(27,27,58,0.08);
                border-color: rgba(90,179,240,0.6);
            }
            .cdp-archive-card.has {
                background: linear-gradient(180deg, rgba(227,242,253,0.5) 0%, rgba(255,255,255,1) 100%);
            }
            .cdp-archive-date { display:flex; align-items:center; gap:12px; }
            .cdp-archive-day {
                font-size: 22px;
                font-weight: 700;
                color: var(--navy);
                line-height: 1;
            }
            .cdp-archive-dow {
                color: var(--muted-text);
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-size: 11px;
            }
            .cdp-archive-actions .btn {
                border-radius: var(--radius-btn);
            }
            @media(max-width:640px){
                .cdp-archive-head{flex-direction:column;align-items:flex-start;}
                .cdp-archive-grid{grid-template-columns: repeat(auto-fill,minmax(200px,1fr));}
            }
        </style>
        @endpush
        @push('scripts')
        <script>
            (function(){
                const typeSelect = document.getElementById('cdpPeriodType');
                if(typeSelect){
                    function applyVisibility(){
                        const type = typeSelect.value;
                        document.querySelectorAll('[data-period]').forEach(function(el){ el.style.display = (el.getAttribute('data-period')===type)?'':'none'; });
                        document.querySelectorAll('[data-period] select').forEach(function(sel){ const active = sel.closest('[data-period]').getAttribute('data-period')===type; sel.disabled = !active; });
                    }
                    typeSelect.addEventListener('change', applyVisibility); applyVisibility();
                    const modal = document.getElementById('cdpCreateModal');
                    if(modal && modal.getAttribute('data-auto-open')==='1' && window.bootstrap){ window.bootstrap.Modal.getOrCreateInstance(modal).show(); }
                }
            })();
        </script>
        @endpush
    @else
        @if ($period === null || $selectedClient === null)
            <div class="cdp-empty">
                <p><strong>No worksheet open.</strong></p>
                <p>Click <em>Add Cash Daily Position Report</em> above to choose a client and period.</p>
            </div>
        @else
            <div class="cdp-band">
                <div class="cdp-band-id">
                    <h2>{{ $selectedClient->name }}</h2>
                    <p>{{ $period->label() }} &middot; {{ $period->rangeLabel() }}</p>
                </div>
                <div class="cdp-band-actions">
                    <a class="btn btn-light btn-sm" href="{{ route('admin.cash-daily-position.index') }}">&larr; Back to Cash Daily Position</a>
                    <button type="button" class="btn btn-light btn-sm" onclick="window.print()">Print</button>
                </div>
            </div>

            <div class="cdp-navbar">
                <div class="cdp-nav">
                    @if ($previousPeriod)
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.cash-daily-position.index', array_merge(['client_id' => $selectedClient->id], $previousPeriod->query())) }}" title="Previous period">&larr; Prev</a>
                    @else
                        <span class="btn btn-secondary btn-sm disabled">&larr; Prev</span>
                    @endif
                    <select class="cdp-nav-jump" onchange="if (this.value) { window.location = this.value; }" aria-label="Jump to period">
                        @foreach ($sameTypePeriods as $sibling)
                            <option value="{{ route('admin.cash-daily-position.index', array_merge(['client_id' => $selectedClient->id], $sibling->query())) }}"
                                @selected($sibling->startDate() === $period->startDate() && $sibling->endDate() === $period->endDate())>
                                {{ $sibling->label() }}
                            </option>
                        @endforeach
                    </select>
                    @if ($nextPeriod)
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.cash-daily-position.index', array_merge(['client_id' => $selectedClient->id], $nextPeriod->query())) }}" title="Next period">Next &rarr;</a>
                    @else
                        <span class="btn btn-secondary btn-sm disabled">Next &rarr;</span>
                    @endif
                </div>
            </div>

            {{-- Minimal sheet info for tests (legacy path) --}}
            @push('styles')
            <style>
                .cdp-dow {
                    display: inline-block;
                    margin-right: 6px;
                    padding: 1px 6px;
                    border-radius: 999px;
                    background: #e7f2fd;
                    color: var(--navy);
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                }
                .cdp-fold {
                    display: block;
                    margin-top: 2px;
                    font-size: 11px;
                    font-style: italic;
                    color: var(--muted-text);
                }
            </style>
            @endpush
            <div class="cdp-sheet">
                <div class="cdp-sheet-head">
                    <h3>{{ $period->label() }} &middot; {{ $period->rangeLabel() }}</h3>
                </div>
                <table class="cdp-sheet-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Beginning Balance</th>
                            <th>ADD - Cash Received</th>
                            <th>LESS - Salaries</th>
                            <th>LESS - Expenses</th>
                            <th>Total LESS</th>
                            <th>Ending Balance</th>
                            <th>Actual Cash Counted</th>
                            <th>Over/(Short)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($days as $day)
                            @php
                                $dayDate = $day['date'] ?? null;
                                $isCarbonDate = $dayDate instanceof \Illuminate\Support\Carbon;
                                // Null actual cash means the day has not been counted yet;
                                // null over/short follows from it.
                                $actualCash = array_key_exists('actual_cash', $day) ? $day['actual_cash'] : null;
                                $overShort = array_key_exists('over_short', $day) ? $day['over_short'] : null;
                            @endphp
                            <tr>
                                <td>
                                    @if ($isCarbonDate)
                                        <span class="cdp-dow">{{ $dayDate->format('D') }}</span>
                                        {{ $dayDate->format('M d, Y') }}
                                    @else
                                        {{ $dayDate ?? '' }}
                                    @endif
                                    @if (! empty($day['has_weekend_fold']))
                                        <span class="cdp-fold">Includes weekend activity folded in</span>
                                    @endif
                                </td>
                                <td class="num">{{ number_format($day['beginning_balance'] ?? $day['beginning'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($day['additions'] ?? $day['collected'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($day['salaries'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($day['expenses'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($day['less'] ?? (($day['salaries'] ?? 0) + ($day['expenses'] ?? 0)), 2) }}</td>
                                <td class="num">{{ number_format($day['ending_balance'] ?? $day['budget'] ?? 0, 2) }}</td>
                                <td class="num">{{ $actualCash === null ? 'Not counted yet' : number_format((float) $actualCash, 2) }}</td>
                                <td class="num">{{ $overShort === null ? '&mdash;' : number_format((float) $overShort, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="cdp-total">
                            <th>Total</th>
                            <th class="num">{{ number_format($totals['budget'] ?? $totals['collected'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['collected'] ?? ($totals['budget'] ?? 0), 2) }}</th>
                            <th class="num">{{ number_format($totals['salaries'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['expenses'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['less'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['budget'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['actual_cash'] ?? 0, 2) }}</th>
                            <th class="num">{{ number_format($totals['over_short'] ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif

    {{-- Saved (frozen) Cash Daily Position Reports: overview list + one
         details modal per report. Everything shown here is the stored snapshot,
         never a recalculation from live records. --}}
    @if (! empty($savedReports))
        <div class="cdp-reports">
            <h2 class="cdp-reports-title">Recorded Cash Daily Position Reports</h2>

            <div class="cdp-reports-grid">
                @foreach ($savedReports as $savedReport)
                    @php $savedModalId = 'cdpReportModal'.$savedReport->id; @endphp
                    <div class="cdp-report-card">
                        <div class="cdp-report-card-head">
                            <strong>{{ $savedReport->client?->name ?? 'Client #'.$savedReport->client_id }}</strong>
                            <span class="cdp-report-period">{{ $savedReport->periodLabel() }}</span>
                        </div>
                        <div class="cdp-report-card-totals">
                            <span>Cash received <b>{{ number_format((float) $savedReport->total_collected, 2) }}</b></span>
                            <span>Total LESS <b>{{ number_format((float) $savedReport->total_less, 2) }}</b></span>
                        </div>
                        <div class="cdp-report-card-actions">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#{{ $savedModalId }}">View Report</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @foreach ($savedReports as $savedReport)
            @php $savedModalId = 'cdpReportModal'.$savedReport->id; @endphp
            <div class="modal fade" id="{{ $savedModalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $savedModalId }}Label">
                <div class="modal-dialog modal-dialog-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="{{ $savedModalId }}Label">
                                {{ $savedReport->client?->name ?? 'Client #'.$savedReport->client_id }} — {{ $savedReport->periodLabel() }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="cdp-report-totals">
                                <div><span>Cash received (ADD)</span><strong>{{ number_format((float) $savedReport->total_collected, 2) }}</strong></div>
                                <div><span>Salaries</span><strong>{{ number_format((float) $savedReport->total_salaries, 2) }}</strong></div>
                                <div><span>Expenses</span><strong>{{ number_format((float) $savedReport->total_expenses, 2) }}</strong></div>
                                <div><span>Total LESS</span><strong>{{ number_format((float) $savedReport->total_less, 2) }}</strong></div>
                                <div><span>Budget</span><strong>{{ number_format((float) $savedReport->total_budget, 2) }}</strong></div>
                                <div><span>Over/(Short)</span><strong>{{ number_format((float) $savedReport->total_over_short, 2) }}</strong></div>
                            </div>

                            <table class="cdp-report-days">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Beginning balance</th>
                                        <th>Cash received</th>
                                        <th>Salaries</th>
                                        <th>Expenses</th>
                                        <th>Total LESS</th>
                                        <th>Ending balance</th>
                                        <th>Actual cash</th>
                                        <th>Over/(Short)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($savedReport->days as $savedDay)
                                        <tr>
                                            <td>
                                                <span class="cdp-dow">{{ $savedDay->report_date->format('D') }}</span>
                                                {{ $savedDay->report_date->format('M d, Y') }}
                                            </td>
                                            <td class="num">{{ number_format((float) $savedDay->beginning_balance, 2) }}</td>
                                            <td class="num">{{ number_format((float) $savedDay->collected, 2) }}</td>
                                            <td class="num">{{ number_format((float) $savedDay->salaries, 2) }}</td>
                                            <td class="num">{{ number_format((float) $savedDay->expenses, 2) }}</td>
                                            <td class="num">{{ number_format((float) $savedDay->less, 2) }}</td>
                                            <td class="num">{{ number_format((float) $savedDay->ending_balance, 2) }}</td>
                                            <td class="num">{{ $savedDay->actual_cash === null ? 'Not counted yet' : number_format((float) $savedDay->actual_cash, 2) }}</td>
                                            <td class="num">{{ $savedDay->actual_cash === null ? '&mdash;' : number_format((float) $savedDay->over_short, 2) }}</td>
                                        </tr>
                                        @foreach ($savedDay->lines as $savedLine)
                                            <tr class="cdp-report-detail">
                                                <td colspan="9">{{ $savedLine->typeLabel() }}: {{ $savedLine->label }} — {{ number_format((float) $savedLine->amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach ($savedDay->payments as $savedPayment)
                                            <tr class="cdp-report-detail">
                                                <td colspan="9">Payment on {{ $savedPayment->paid_on?->format('M d, Y') }} — {{ number_format((float) $savedPayment->amount, 2) }} ({{ $savedPayment->statusLabel() }})</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <form method="POST" action="{{ route('admin.cash-daily-position.reports.destroy', $savedReport) }}" onsubmit="return confirm('Delete this saved Cash Daily Position Report?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete Report</button>
                            </form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @push('styles')
        <style>
            .cdp-reports { margin-top: 26px; }
            .cdp-reports-title { font-size: 17px; color: var(--navy); margin-bottom: 12px; }
            .cdp-reports-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
            .cdp-report-card {
                display: flex; flex-direction: column; gap: 8px;
                padding: 14px 16px;
                border: 1px solid var(--border-subtle);
                border-radius: var(--radius-card);
                background: var(--surface);
            }
            .cdp-report-card-head { display: flex; flex-direction: column; gap: 2px; }
            .cdp-report-period { font-size: 12px; color: var(--muted-text); }
            .cdp-report-card-totals { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--muted-text); }
            .cdp-report-card-totals b { color: var(--navy); }
            .cdp-report-totals { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 14px; }
            .cdp-report-totals span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted-text); }
            .cdp-report-totals strong { font-size: 15px; color: var(--navy); }
            .cdp-report-days { width: 100%; border-collapse: collapse; font-size: 13px; }
            .cdp-report-days th, .cdp-report-days td { padding: 6px 8px; border-bottom: 1px solid var(--border-subtle); text-align: left; }
            .cdp-report-days td.num { text-align: right; }
            .cdp-report-detail td { font-size: 12px; color: var(--muted-text); border-bottom-style: dashed; }
        </style>
        @endpush
    @endif
@endsection
