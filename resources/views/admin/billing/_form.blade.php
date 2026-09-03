@php
    $isEdit = $formMode === 'edit';
    // Bug 1: pre-selected client when arriving from a client's billing page.
    $defaultClientId = $isEdit ? $billing->client_id : ($selectedClientId ?? null);
    // Bug 2: default quarter computed from the selected client's history.
    $defaultQuarter = $isEdit ? $billing->quarter : ($defaultQuarter ?? null);
    $existingItems = $isEdit ? $billing->lineItems->keyBy(fn ($item) => $item->category.'_'.$item->form_type.'_'.$item->month) : collect();
    $professionalFeeRates = $feeRates->where('category', 'professional_fee');
    $bookkeepingFeeRates = $feeRates->where('category', 'bookkeeping_fee');
    $ptbFeeRates = $feeRates->where('category', 'post_closing_tb');
    $invFeeRates = $feeRates->where('category', 'inventory_list');
    $oaFeeRates = $feeRates->where('category', 'other_attachment');
    $deFeeRates = $feeRates->where('category', 'data_entry');
    $existingItemsJson = $isEdit ? json_encode($existingItems->mapWithKeys(fn ($item) => [
        $item->category . '_' . ($item->form_type ?? '') . '_' . ($item->month ?? 'null') => [
            'amount' => $item->amount,
            'fee_rate_id' => $item->fee_rate_id,
            'label' => $item->label,
        ],
    ])->all()) : 'null';
    $profFeeRatesJson = json_encode($professionalFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $bookFeeRatesJson = json_encode($bookkeepingFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $ptbFeeRatesJson = json_encode($ptbFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $invFeeRatesJson = json_encode($invFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $oaFeeRatesJson = json_encode($oaFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
    $deFeeRatesJson = json_encode($deFeeRates->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'label' => $r->money() . ($r->label ? ' — ' . $r->label : '')])->values()->all());
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
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $defaultClientId) === $client->id) data-name="{{ $client->business_name ?: $client->name }}">
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
                        <option value="{{ $q }}" @selected((int) old('quarter', $defaultQuarter) === $q)>{{ $label }} Quarter</option>
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

        <div id="customItemsContainer" style="margin-top:16px;"></div>

        <button type="button" class="btn btn-outline btn-sm" id="addCustomItemBtn" style="margin-top:8px;">
            + Add another item
        </button>

        <div class="form-group" style="margin-top:16px;">
            <label class="form-label">Computed total payment</label>
            <div class="form-control" id="totalDisplay" readonly style="font-weight:700;font-size:1.1em;">₱0.00</div>
        </div>

        <div class="btn-group-row">
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create billing statement' }}</button>
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
    var ptbFeeRates = {!! $ptbFeeRatesJson !!};
    var invFeeRates = {!! $invFeeRatesJson !!};
    var oaFeeRates = {!! $oaFeeRatesJson !!};
    var deFeeRates = {!! $deFeeRatesJson !!};
    var allFeeRateCategories = ['professional_fee', 'bookkeeping_fee', 'post_closing_tb', 'inventory_list', 'other_attachment', 'data_entry'];
    var monthNames = {1:'Jan',2:'Feb',3:'Mar',4:'Apr',5:'May',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Oct',11:'Nov',12:'Dec'};

    function round2(v) { var n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100) / 100; }
    function money(v) { return '\u20B1' + round2(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function computeTotal() {
        var total = 0;
        // Sum every amount field — preset rows are <select data-line-amount>,
        // free-form rows (BIR remittances, cash in) are <input data-line-amount>.
        container.querySelectorAll('[data-line-amount]').forEach(function (el) { total += round2(el.value); });
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
            displayLabel = 'Bookkeeping';
        } else if (category === 'post_closing_tb') {
            displayLabel = 'Post-Closing Trial Balance';
        } else if (category === 'inventory_list') {
            displayLabel = 'Inventory List (Notarized)';
        } else if (category === 'other_attachment') {
            displayLabel = 'Other Attachment';
        } else if (category === 'data_entry') {
            displayLabel = 'Data Entry';
        } else if (category === 'bir_remittance' && !formType) {
            displayLabel = 'Cash In';
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'line-item-label';
        labelSpan.textContent = displayLabel;

        var inputWrap = document.createElement('div');
        inputWrap.className = 'line-item-input';

        var feeRateMap = {
            'professional_fee': profFeeRates,
            'bookkeeping_fee': bookFeeRates,
            'post_closing_tb': ptbFeeRates,
            'inventory_list': invFeeRates,
            'other_attachment': oaFeeRates,
            'data_entry': deFeeRates
        };

        if (feeRateMap[category]) {
            var rates = feeRateMap[category];
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
        var bookRow = null;
        var ptbRow = null;
        var invRow = null;
        var oaRow = null;
        var hasCashIn = false;

        // Detect existing items for new categories
        var bookExisting = null;
        var ptbExisting = null;
        var invExisting = null;
        var oaExisting = null;
        var deExisting = null;

        if (existingItems) {
            Object.keys(existingItems).forEach(function (key) {
                if (key.startsWith('bookkeeping_fee_')) bookExisting = existingItems[key];
                if (key.startsWith('post_closing_tb_')) ptbExisting = existingItems[key];
                if (key.startsWith('inventory_list_')) invExisting = existingItems[key];
                if (key.startsWith('other_attachment_')) oaExisting = existingItems[key];
                if (key.startsWith('data_entry_')) deExisting = existingItems[key];
                if (key === 'bir_remittance__') hasCashIn = true;
            });
        }

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

        // Bookkeeping — always show (default in create, or from existing)
        bookRow = buildLineItemRow(idx++, 'bookkeeping_fee', null, null, 'Bookkeeping', bookExisting ? bookExisting.amount : '', bookExisting ? bookExisting.fee_rate_id : null);
        container.appendChild(buildSection('Bookkeeping Fee', [bookRow]));

        // Post-Closing Trial Balance — always show
        ptbRow = buildLineItemRow(idx++, 'post_closing_tb', null, null, 'Post-Closing Trial Balance', ptbExisting ? ptbExisting.amount : '', ptbExisting ? ptbExisting.fee_rate_id : null);
        container.appendChild(buildSection('Post-Closing Trial Balance', [ptbRow]));

        // Inventory List (Notarized) — always show
        invRow = buildLineItemRow(idx++, 'inventory_list', null, null, 'Inventory List (Notarized)', invExisting ? invExisting.amount : '', invExisting ? invExisting.fee_rate_id : null);
        container.appendChild(buildSection('Inventory List (Notarized)', [invRow]));

        // Other Attachment — always show
        oaRow = buildLineItemRow(idx++, 'other_attachment', null, null, 'Other Attachment', oaExisting ? oaExisting.amount : '', oaExisting ? oaExisting.fee_rate_id : null);
        container.appendChild(buildSection('Other Attachment', [oaRow]));

        // Data Entry — always show
        deRow = buildLineItemRow(idx++, 'data_entry', null, null, 'Data Entry', deExisting ? deExisting.amount : '', deExisting ? deExisting.fee_rate_id : null);
        container.appendChild(buildSection('Data Entry', [deRow]));

        // Reload any saved ad-hoc custom items.
        loadCustomItems(existingItems);

        computeTotal();
    }

    // ---- Additional ad-hoc line items (one-off charges) ----
    var customContainer = document.getElementById('customItemsContainer');
    var addCustomBtn = document.getElementById('addCustomItemBtn');
    var customIndex = 0;

    function loadCustomItems(existingItems) {
        if (!customContainer) return;
        customContainer.innerHTML = '';
        if (!existingItems) return;
        Object.keys(existingItems).forEach(function (key) {
            if (key.startsWith('custom_') || key === 'custom__') {
                var item = existingItems[key];
                customContainer.appendChild(buildCustomItemRow(item.label, item.amount));
            }
        });
    }

    function buildCustomItemRow(existingLabel, existingAmount) {
        var idx = customIndex++;
        var wrapper = document.createElement('div');
        wrapper.className = 'line-item-row';

        // Free-text label + amount, removably individually.
        wrapper.innerHTML =
            '<input type="hidden" name="line_items[' + idx + '][category]" value="custom">' +
            '<input type="hidden" name="line_items[' + idx + '][form_type]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][month]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][fee_rate_id]" value="">';

        var labelInput = document.createElement('input');
        labelInput.className = 'form-control form-control-sm';
        labelInput.type = 'text';
        labelInput.placeholder = 'Item description (e.g. one-off consultancy)';
        labelInput.name = 'line_items[' + idx + '][label]';
        labelInput.value = existingLabel || '';
        labelInput.style.flex = '1 1 220px';

        var amountInput = document.createElement('input');
        amountInput.className = 'form-control form-control-sm';
        amountInput.type = 'number';
        amountInput.step = '0.01';
        amountInput.min = '0';
        amountInput.name = 'line_items[' + idx + '][amount]';
        amountInput.value = existingAmount || '';
        amountInput.dataset.lineAmount = '1';
        amountInput.style.width = '160px';
        amountInput.addEventListener('input', computeTotal);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-outline danger btn-sm';
        removeBtn.title = 'Remove item';
        removeBtn.setAttribute('aria-label', 'Remove item');
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () {
            wrapper.remove();
            computeTotal();
        });

        wrapper.appendChild(labelInput);
        wrapper.appendChild(amountInput);
        wrapper.appendChild(removeBtn);
        return wrapper;
    }

    if (addCustomBtn) {
        addCustomBtn.addEventListener('click', function () {
            customContainer.appendChild(buildCustomItemRow('', ''));
            computeTotal();
        });
    }

    function loadApplicableForms() {
        var clientId = clientSelect.value;
        if (!clientId) {
            container.innerHTML = '<p class="muted" id="lineItemsPlaceholder">Select a client to load their applicable BIR forms.</p>';
            return;
        }

        var formsPromise = fetch('{{ route("admin.billing.applicableForms") }}?client_id=' + encodeURIComponent(clientId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });

        @if ($isEdit)
            formsPromise.then(function (data) {
                var forms = data.forms || [];
                var existingItems = {!! $existingItemsJson !!};
                buildLineItems(forms, existingItems);
            });
        @else
            var lastPromise = fetch('{{ route("admin.billing.lastBilling") }}?client_id=' + encodeURIComponent(clientId), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); });

            Promise.all([formsPromise, lastPromise]).then(function (results) {
                var forms = results[0].forms || [];
                var lastData = results[1];

                var existingItems = null;
                if (lastData.line_items && lastData.line_items.length > 0) {
                    existingItems = {};
                    lastData.line_items.forEach(function (item) {
                        var key = item.category + '_' + (item.form_type || '') + '_' + (item.month || 'null');
                        existingItems[key] = {
                            amount: item.amount,
                            fee_rate_id: item.fee_rate_id,
                        };
                    });
                }

                buildLineItems(forms, existingItems);

                if (lastData.period_title) {
                    var hint = document.getElementById('carryForwardHint');
                    if (!hint) {
                        hint = document.createElement('p');
                        hint.id = 'carryForwardHint';
                        hint.className = 'form-hint';
                        hint.style.marginTop = '-8px';
                        hint.style.marginBottom = '16px';
                        hint.style.color = '#6b7280';
                        container.parentNode.insertBefore(hint, container);
                    }
                    hint.textContent = 'Amounts carried forward from ' + lastData.period_title + '. Edit as needed.';
                }
            });
        @endif
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', loadApplicableForms);
    }

    // Load on page load for edit mode; for create when a client is pre-selected
    @if ($isEdit)
        if (clientSelect.value) {
            loadApplicableForms();
        }
    @else
        if (clientSelect && clientSelect.value) {
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
