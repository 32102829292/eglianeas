@php
    $isEdit = $formMode === 'edit';
    $existingItems = $isEdit ? $billing->lineItems->keyBy(fn ($item) => $item->category.'_'.$item->form_type.'_'.$item->month) : collect();
    $professionalFeeRates = $feeRates->where('category', 'professional_fee');
    $bookkeepingFeeRates = $feeRates->where('category', 'bookkeeping_fee');
    $existingItemsJson = $isEdit ? json_encode($existingItems->mapWithKeys(fn ($item) => [
        $item->category . '_' . ($item->form_type ?? '') . '_' . ($item->month ?? 'null') => [
            'amount' => $item->amount,
            'fee_rate_id' => $item->fee_rate_id,
        ],
    ])->all()) : 'null';
    $profFeeRatesJson = json_encode($professionalFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $bookFeeRatesJson = json_encode($bookkeepingFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
@endphp

<div class="card">
    <div class="card-head">
        <h2 class="card-title">{{ $isEdit ? 'Edit billing statement' : 'New billing statement' }}</h2>
    </div>

    <form method="POST" action="{{ $isEdit ? route('admin.billing.update', $billing) : route('admin.billing.store') }}" id="billingForm">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="form-grid two">
            <div class="form-group">
                <label class="form-label" for="client_id">Client</label>
                <select class="form-control" id="client_id" name="client_id" required>
                    <option value="">Select client&hellip;</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $billing->client_id) === $client->id) data-name="{{ $client->business_name ?: $client->name }}">
                            {{ $client->name }} — {{ $client->business_name }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="period_label">Billing period label</label>
                <input class="form-control" id="period_label" name="period_label" type="text" value="{{ old('period_label', $billing->period_label) }}" placeholder="2ND QUARTER 2026 BILLING">
                <div class="form-hint">Leave blank to auto-generate from quarter and year.</div>
                @error('period_label')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="quarter">Quarter</label>
                <select class="form-control" id="quarter" name="quarter">
                    <option value="">—</option>
                    @foreach (App\Models\Billing::QUARTERS as $q => $label)
                        <option value="{{ $q }}" @selected((int) old('quarter', $billing->quarter) === $q)>{{ $label }} Quarter</option>
                    @endforeach
                </select>
                @error('quarter')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="year">Year</label>
                <input class="form-control" id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $billing->year ?? now()->format('Y')) }}">
                @error('year')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="due_date">Due date</label>
                <input class="form-control" id="due_date" name="due_date" type="date" value="{{ old('due_date', $billing->due_date?->format('Y-m-d')) }}">
                <div class="form-hint">Leave blank to auto-set to the end of the quarter&rsquo;s month.</div>
                @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="section-divider"></div>

        <div id="lineItemsContainer">
            <p class="muted" id="lineItemsPlaceholder">Select a client to load their applicable BIR forms and generate billing line items.</p>
        </div>

        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Computed total payment</label>
            <div class="form-control" id="totalDisplay" readonly style="font-weight:700;font-size:1.1em;">₱0.00</div>
        </div>

        <div class="btn-group-row">
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create billing' }}</button>
            <a href="{{ $isEdit ? route('admin.billing.show', $billing->client) : route('admin.billing.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@php
    $monthlyForms = ['1601C', '0619E', '0619F'];
@endphp

@push('scripts')
<script>
(function () {
    'use strict';

    var clientSelect = document.getElementById('client_id');
    var quarterSelect = document.getElementById('quarter');
    var yearInput = document.getElementById('year');
    var container = document.getElementById('lineItemsContainer');
    var placeholder = document.getElementById('lineItemsPlaceholder');
    var totalDisplay = document.getElementById('totalDisplay');
    var form = document.getElementById('billingForm');
    var isEdit = {{ $isEdit ? 'true' : 'false' }};

    var monthlyForms = @json($monthlyForms);
    var profFeeRates = {!! $profFeeRatesJson !!};
    var bookFeeRates = {!! $bookFeeRatesJson !!};
    var monthNames = {1:'Jan',2:'Feb',3:'Mar',4:'Apr',5:'May',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Oct',11:'Nov',12:'Dec'};

    function round2(v) { var n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100) / 100; }
    function money(v) { return '\u20B1' + round2(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function computeTotal() {
        var total = 0;
        container.querySelectorAll('input[data-line-amount]').forEach(function (el) { total += round2(el.value); });
        var cashIn = document.getElementById('cash_in_input');
        if (cashIn) total += round2(cashIn.value);
        totalDisplay.textContent = money(total);
    }

    function buildFeeSelect(name, rates, selectedAmount, selectedFeeRateId) {
        var sel = document.createElement('select');
        sel.className = 'form-control form-control-sm';
        sel.name = name;

        var defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select preset…';
        sel.appendChild(defaultOpt);

        rates.forEach(function (r) {
            var opt = document.createElement('option');
            opt.value = r.amount;
            opt.textContent = r.label;
            opt.dataset.feeRateId = r.id;
            if (selectedAmount !== null && parseFloat(opt.value) === parseFloat(selectedAmount)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        // Custom option if value doesn't match any preset
        if (selectedAmount !== null && selectedAmount !== '' && parseFloat(selectedAmount) > 0) {
            var matchFound = rates.some(function (r) { return parseFloat(r.amount) === parseFloat(selectedAmount); });
            if (!matchFound) {
                var customOpt = document.createElement('option');
                customOpt.value = selectedAmount;
                customOpt.textContent = money(parseFloat(selectedAmount)) + ' (custom)';
                customOpt.selected = true;
                sel.appendChild(customOpt);
            }
        }

        return sel;
    }

    function buildLineItemRow(idx, category, formType, month, label, amount, feeRateId) {
        var key = category + '_' + (formType || '') + '_' + (month || '');
        var wrapper = document.createElement('div');
        wrapper.className = 'line-item-row';
        wrapper.dataset.key = key;

        // Hidden fields
        wrapper.innerHTML =
            '<input type="hidden" name="line_items[' + idx + '][category]" value="' + category + '">' +
            '<input type="hidden" name="line_items[' + idx + '][form_type]" value="' + (formType || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][month]" value="' + (month || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][label]" value="' + (label || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][fee_rate_id]" class="fee-rate-id-field" value="' + (feeRateId || '') + '">';

        var displayLabel = label || (formType || 'Cash In') + (month ? ' — ' + monthNames[month] : '');
        if (category === 'professional_fee' && formType) {
            displayLabel = 'Fee — ' + formType + (month ? ' (' + monthNames[month] + ')' : '');
        } else if (category === 'bookkeeping_fee') {
            displayLabel = 'Bookkeeping / Post-Closing TB';
        } else if (category === 'bir_remittance' && !formType) {
            displayLabel = 'Cash In';
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'line-item-label';
        labelSpan.textContent = displayLabel;

        var inputWrap = document.createElement('div');
        inputWrap.className = 'line-item-input';

        if (category === 'professional_fee' || category === 'bookkeeping_fee') {
            var rates = category === 'bookkeeping_fee' ? bookFeeRates : profFeeRates;
            var select = buildFeeSelect('line_items[' + idx + '][amount]', rates, amount, feeRateId);
            select.dataset.lineAmount = '1';
            select.dataset.feeRateSelect = '1';
            select.addEventListener('change', function () {
                var selected = this.options[this.selectedIndex];
                var feeRateField = wrapper.querySelector('.fee-rate-id-field');
                feeRateField.value = selected.dataset.feeRateId || '';
                computeTotal();
            });
            inputWrap.appendChild(select);
        } else {
            var inp = document.createElement('input');
            inp.className = 'form-control form-control-sm';
            inp.type = 'number';
            inp.step = '0.01';
            inp.min = '0';
            inp.name = 'line_items[' + idx + '][amount]';
            inp.value = amount || '';
            inp.dataset.lineAmount = '1';
            inp.addEventListener('input', computeTotal);
            inputWrap.appendChild(inp);
        }

        wrapper.appendChild(labelSpan);
        wrapper.appendChild(inputWrap);
        return wrapper;
    }

    function buildCashInRow(idx, amount) {
        var wrapper = document.createElement('div');
        wrapper.className = 'line-item-row line-item-cash-in';
        wrapper.dataset.key = 'cash_in';

        wrapper.innerHTML =
            '<input type="hidden" name="line_items[' + idx + '][category]" value="bir_remittance">' +
            '<input type="hidden" name="line_items[' + idx + '][form_type]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][month]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][label]" value="Cash In">' +
            '<input type="hidden" name="line_items[' + idx + '][fee_rate_id]" value="">';

        var labelSpan = document.createElement('span');
        labelSpan.className = 'line-item-label';
        labelSpan.textContent = 'Cash In';

        var inp = document.createElement('input');
        inp.className = 'form-control form-control-sm';
        inp.type = 'number';
        inp.step = '0.01';
        inp.min = '0';
        inp.name = 'line_items[' + idx + '][amount]';
        inp.value = amount || '';
        inp.dataset.lineAmount = '1';
        inp.id = 'cash_in_input';
        inp.addEventListener('input', computeTotal);

        var inputWrap = document.createElement('div');
        inputWrap.className = 'line-item-input';
        inputWrap.appendChild(inp);

        wrapper.appendChild(labelSpan);
        wrapper.appendChild(inputWrap);
        return wrapper;
    }

    function buildSection(title, rows) {
        var section = document.createElement('div');
        section.className = 'line-items-section';

        var heading = document.createElement('h4');
        heading.className = 'line-items-title';
        heading.textContent = title;
        section.appendChild(heading);

        rows.forEach(function (row) { section.appendChild(row); });
        return section;
    }

    function buildLineItems(applicableForms, existingItems) {
        container.innerHTML = '';
        var idx = 0;

        var remRows = [];
        var feeRows = [];
        var hasBookkeeping = false;
        var hasCashIn = false;
        var bookkeepingAmount = null;
        var bookkeepingFeeRateId = null;

        // Check if existing items have bookkeeping
        if (existingItems) {
            Object.keys(existingItems).forEach(function (key) {
                if (key.startsWith('bookkeeping_fee_')) {
                    hasBookkeeping = true;
                    bookkeepingAmount = existingItems[key].amount;
                    bookkeepingFeeRateId = existingItems[key].fee_rate_id;
                }
                if (key === 'bir_remittance__') {
                    hasCashIn = true;
                }
            });
        }

        // Default: show bookkeeping if applicable or if editing and it exists
        if (!existingItems) hasBookkeeping = true;

        // BIR Remittances
        applicableForms.forEach(function (ft) {
            var isMonthly = monthlyForms.indexOf(ft) !== -1;
            if (isMonthly) {
                [1, 2, 3].forEach(function (m) {
                    var key = 'bir_remittance_' + ft + '_' + m;
                    var existing = existingItems ? existingItems[key] : null;
                    remRows.push(buildLineItemRow(idx++, 'bir_remittance', ft, m, ft + ' Remittance — ' + monthNames[m], existing ? existing.amount : '', null));
                });
            } else {
                var key = 'bir_remittance_' + ft + '_null';
                var existing = existingItems ? existingItems[key] : null;
                remRows.push(buildLineItemRow(idx++, 'bir_remittance', ft, null, ft + ' Remittance', existing ? existing.amount : '', null));
            }
        });

        // Cash In
        var cashInExisting = existingItems ? existingItems['bir_remittance__'] : null;
        remRows.push(buildCashInRow(idx++, cashInExisting ? cashInExisting.amount : ''));

        // Professional Fees
        applicableForms.forEach(function (ft) {
            var isMonthly = monthlyForms.indexOf(ft) !== -1;
            if (isMonthly) {
                [1, 2, 3].forEach(function (m) {
                    var key = 'professional_fee_' + ft + '_' + m;
                    var existing = existingItems ? existingItems[key] : null;
                    feeRows.push(buildLineItemRow(idx++, 'professional_fee', ft, m, 'Fee — ' + ft + ' (' + monthNames[m] + ')', existing ? existing.amount : '', existing ? existing.fee_rate_id : null));
                });
            } else {
                var key = 'professional_fee_' + ft + '_null';
                var existing = existingItems ? existingItems[key] : null;
                feeRows.push(buildLineItemRow(idx++, 'professional_fee', ft, null, 'Fee — ' + ft, existing ? existing.amount : '', existing ? existing.fee_rate_id : null));
            }
        });

        if (remRows.length) container.appendChild(buildSection('BIR Remittances', remRows));
        if (feeRows.length) container.appendChild(buildSection('Professional Fees', feeRows));

        if (hasBookkeeping) {
            var bookRow = buildLineItemRow(idx++, 'bookkeeping_fee', null, null, 'Bookkeeping / Post-Closing TB', bookkeepingAmount, bookkeepingFeeRateId);
            container.appendChild(buildSection('Bookkeeping Fee', [bookRow]));
        }

        computeTotal();
    }

    function loadApplicableForms() {
        var clientId = clientSelect.value;
        if (!clientId) {
            container.innerHTML = '<p class="muted" id="lineItemsPlaceholder">Select a client to load their applicable BIR forms.</p>';
            return;
        }

        fetch('{{ route("admin.billing.applicableForms") }}?client_id=' + encodeURIComponent(clientId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var forms = data.forms || [];
                var existingItems = null;

                @if ($isEdit)
                    existingItems = {!! $existingItemsJson !!};
                @endif

                buildLineItems(forms, existingItems);
            })
            .catch(function () {
                container.innerHTML = '<p class="muted">Could not load applicable forms. Try again.</p>';
            });
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', loadApplicableForms);
    }

    // Load on page load for edit mode; on change for create mode
    @if ($isEdit)
        if (clientSelect.value) {
            loadApplicableForms();
        }
    @endif

    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            @if (!$isEdit)
                var opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    var q = quarterSelect ? quarterSelect.value : '';
                    var y = yearInput ? yearInput.value : new Date().getFullYear();
                    var periodLabel = document.getElementById('period_label');
                    if (periodLabel && !periodLabel.value.trim() && q) {
                        var qLabels = {1:'1ST',2:'2ND',3:'3RD',4:'4TH'};
                        periodLabel.value = (qLabels[q] || '') + ' QUARTER ' + y + ' BILLING';
                    }
                }
            @endif
            loadApplicableForms();
        });
    }
})();
</script>
@endpush
