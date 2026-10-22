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

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="cdp-receipt">
        <div class="cdp-receipt-meta">
            <div><strong>Date:</strong> {{ Carbon::parse($date)->format('F d, Y') }}</div>
            <div><strong>Reported by:</strong> {{ $reportedBy }}</div>
        </div>

        <form method="POST" action="{{ route('admin.cash-daily-position.store-daily') }}" id="cdpDailyForm">
            @csrf
            <input type="hidden" name="position_date" value="{{ $date }}">
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
                <table class="cdp-receipt-table" id="addTable">
                    <thead><tr><th>Company</th><th class="num">Price</th><th></th></tr></thead>
                    <tbody>
                        @if($addLines->isEmpty())
                            <tr>
                                <td>
                                    <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}">
                                        <input type="text" class="cdp-ac-input" name="additions[0][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required>
                                        <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                    </div>
                                </td>
                                <td class="num"><input type="number" step="0.01" class="amt-add" name="additions[0][amount]" required></td>
                                <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                            </tr>
                        @else
                            @foreach($addLines as $i => $line)
                                <tr>
                                    <td>
                                        <div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}">
                                            <input type="text" class="cdp-ac-input" name="additions[{{ $i }}][label]" value="{{ $line->label }}" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required>
                                            <ul class="cdp-ac-list" role="listbox" hidden></ul>
                                        </div>
                                    </td>
                                    <td class="num"><input type="number" step="0.01" class="amt-add" name="additions[{{ $i }}][amount]" value="{{ number_format((float)$line->amount, 2, '.', '') }}" required></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('tr').remove(); calcTotals();">Remove</button></td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3"><button type="button" class="btn btn-sm btn-primary" onclick="addRow('addTable','additions','amt-add')">+ Add Cash Received</button></td></tr>
                        <tr class="total-row"><th>TOTAL ADD</th><th class="num" id="totalAdd">₱{{ number_format($addTotal, 2) }}</th><th></th></tr>
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
                                    <td><input type="text" name="expenses[{{ $i }}][label]" value="{{ $line->label }}" required></td>
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
                <table class="cdp-receipt-table">
                    <tbody>
                        <tr><td>Beginning Balance</td><td class="num" id="sumBeg">₱{{ number_format($beginBal, 2) }}</td></tr>
                        <tr><td>Total ADD</td><td class="num" id="sumAdd">+₱{{ number_format($addTotal, 2) }}</td></tr>
                        <tr><td>Total LESS</td><td class="num" id="sumLess">-₱{{ number_format($lessTotal, 2) }}</td></tr>
                        <tr class="total-row"><th>ENDING BALANCE</th><th class="num" id="sumEnd">₱{{ number_format($endBal, 2) }}</th></tr>
                        <tr><td>Actual Cashout</td><td class="num"><input type="number" step="0.01" name="actual_cash" value="{{ $actual !== null ? number_format($actual, 2, '.', '') : '' }}" onchange="calcTotals()"></td></tr>
                        <tr class="total-row"><th>OVERAGE / (SHORTAGE)</th><th class="num" id="sumOS">₱{{ $overShort === null ? '0.00' : number_format($overShort, 2) }}</th></tr>
                    </tbody>
                </table>
            </section>

            <div class="cdp-receipt-actions">
                <a href="{{ route('admin.cash-daily-position.index') }}" class="btn btn-secondary">Back to Daily Reports</a>
                @if(!isset($mode) || $mode !== 'view')
                    <button type="submit" class="btn btn-primary">Save Report</button>
                @else
                    <a href="{{ route('admin.cash-daily-position.create', ['date' => $date]) }}" class="btn btn-primary">Edit/Add Lines</a>
                @endif
            </div>
        </form>
    </div>

    @push('styles')
    <style>
        .cdp-receipt { max-width: 980px; margin: 18px auto; }
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
            tr.innerHTML = '<td><div class="cdp-ac" data-search-url="{{ route('admin.cash-daily-position.clients.json') }}"><input type="text" class="cdp-ac-input" name="additions['+idx+'][label]" autocomplete="off" role="combobox" aria-expanded="false" placeholder="Company" required><ul class="cdp-ac-list" role="listbox" hidden></ul></div></td><td class="num"><input type="number" step="0.01" class="amt-add" name="additions['+idx+'][amount]" required></td><td><button type="button" class="btn btn-sm btn-secondary" onclick="this.closest(\'tr\').remove(); calcTotals();">Remove</button></td>';
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
    });
    </script>
@endsection
