@php
    $isEdit = $formMode === 'edit';
    $locked = $isEdit && $billing->isPaid();
    // Bug 1: pre-selected client when arriving from a client's billing page.
    $defaultClientId = $isEdit ? $billing->client_id : ($selectedClientId ?? null);
    $defaultCompanyId = $isEdit ? $billing->client_company_id : ($selectedCompanyId ?? null);
    // Bug 2: default quarter computed from the selected client's history.
    $defaultQuarter = $isEdit ? $billing->quarter : ($defaultQuarter ?? null);
    $existingItems = $isEdit ? $billing->lineItems->keyBy(fn ($item) => $item->category.'_'.$item->form_type.'_'.$item->month) : collect();

    // Fee presets per category. These are the *master* rates; the amount a
    // statement actually charges is stored on billing_line_items and is never
    // written back here, so editing an amount on one statement cannot change
    // the default price for any other client.
    $feeRatesByCategory = $feeRates->groupBy('category');
    $feeRatesJson = json_encode(
        $feeRatesByCategory->map(fn ($group) => $group->map(fn ($r) => [
            'id' => $r->id,
            'amount' => (float) $r->amount,
            'label' => $r->label,
            'display' => $r->money().($r->label ? ' — '.$r->label : ''),
        ])->values())->all()
    );

    $existingItemsJson = $isEdit ? json_encode($existingItems->mapWithKeys(fn ($item) => [
        $item->category . '_' . ($item->form_type ?? '') . '_' . ($item->month ?? 'null') => [
            'amount' => $item->amount,
            'fee_rate_id' => $item->fee_rate_id,
            'label' => $item->label,
            'frequency' => $item->frequency,
            'manual_include' => (bool) $item->manual_include,
            'notes' => $item->notes,
        ],
    ])->all()) : 'null';

    $frequencyMapsJson = json_encode(App\Support\BillingFrequency::toJsMaps());
    $frequencyOptionsJson = json_encode(App\Support\BillingFrequency::selectable());
@endphp

<div class="card">
    <div class="card-head">
        <h2 class="card-title">{{ $isEdit ? 'Edit billing statement' : 'New billing statement' }}</h2>
    </div>

    @if ($locked)
        {{--
            A paid statement is server-immutable (BillingController::update aborts
            403). Previously the edit form still rendered here and only failed on
            submit, so the admin filled the whole form in before being rejected.
            It is now shown read-only with the reason stated up front.
        --}}
        <div class="alert-strip bill-locked-strip" role="alert">
            <strong>&#128274; This billing statement is finalized and cannot be edited.</strong>
            It has already been marked as paid, so its line items and total are part of the accounting record.
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.billing.update', $billing) : route('admin.billing.store') }}" id="billingForm"
          onsubmit="return {{ $isEdit ? 'egliane.billingUpdateGuard' : 'egliane.billingCreateGuard' }}(this);">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <fieldset class="bill-fieldset" @disabled($locked)>
            <legend class="visually-hidden">Billing statement details</legend>

            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="client_id">Client <span class="text-danger" aria-hidden="true">*</span></label>
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
                    <label class="form-label" for="client_company_id">Company / branch</label>
                    <select class="form-control" id="client_company_id" name="client_company_id">
                        <option value="">Primary company</option>
                        @foreach ($clients as $client)
                            @foreach ($client->companies as $company)
                                <option value="{{ $company->id }}" data-client-id="{{ $client->id }}" @selected((int) old('client_company_id', $defaultCompanyId) === $company->id)>
                                    {{ $company->company_code }} — {{ $company->company_name ?: 'Unnamed company' }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('client_company_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="period_label">Billing period label</label>
                    <input class="form-control" id="period_label" name="period_label" type="text" value="{{ old('period_label', $billing->period_label) }}" placeholder="2ND QUARTER 2026 BILLING">
                    <div class="form-hint">Leave blank to auto-generate from quarter and year.</div>
                    @error('period_label')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="quarter">Quarter @if(!$isEdit)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
                    <select class="form-control" id="quarter" name="quarter" @if(!$isEdit) required @endif>
                        <option value="">—</option>
                        @foreach (App\Models\Billing::QUARTERS as $q => $label)
                            <option value="{{ $q }}" @selected((int) old('quarter', $defaultQuarter) === $q)>{{ $label }} Quarter</option>
                        @endforeach
                    </select>
                    @error('quarter')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="year">Year @if(!$isEdit)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
                    <input class="form-control" id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $billing->year ?? now()->format('Y')) }}" @if(!$isEdit) required @endif>
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

            {{--
                Every row below is an explicit include/exclude decision.

                A row only exists on the statement when it is ticked AND carries an
                amount, so nothing is charged by accident. Annual, one-time and
                as-needed services start unticked with the reason shown, because
                they must never ride along just because the service exists.

                The items and the live summary share a two-column workspace. The
                summary used to be a plain sibling stuck to the bottom of the
                viewport (position:sticky; bottom:10px). Sticky offsets an element
                out of its own flow slot, so the panel floated up over the service
                sections and the action buttons instead of sitting beside them. It
                is now a real grid column, so it occupies its own space and can
                never overlap a row.
            --}}
            <div class="bill-workspace">
                <div class="bill-workspace-main">
                    <div class="bill-items-head">
                        <h3 class="bill-items-heading">Billing Items</h3>
                        <p class="form-hint">Tick only the services being charged this period. Amounts marked <em>Default</em> come from the master fee rate and are copied here &mdash; editing them affects this statement only.</p>
                    </div>

                    <div id="lineItemsContainer">
                        <p class="muted" id="lineItemsPlaceholder">Select a client to load their applicable BIR forms and generate billing line items.</p>
                    </div>

                    <div id="customItemsContainer" class="bill-custom-items"></div>

                    @if (! $locked)
                        <button type="button" class="btn btn-outline btn-sm mt-2" id="addCustomItemBtn">+ Add Custom Billing Item</button>
                    @endif
                </div>

                {{--
                    Live summary. It sits in its own column beside the items and
                    sticks to the top of that column, so it stays visible while a
                    long item list is scrolled without ever covering a row.
                    Its arithmetic mirrors BillingSummaryMatrix exactly, so what
                    is shown here is what the generated Excel-style summary will
                    contain.
                --}}
                <aside class="bill-summary-wrap" aria-label="Billing summary">
                    <section class="bill-summary" id="billingSummary" aria-live="polite">
                        <h4 class="bill-summary-title">Billing Summary</h4>
                        <dl class="bill-summary-list">
                            <div class="bill-summary-line"><dt>Selected services</dt><dd data-bill-sum="count">0</dd></div>
                            <div class="bill-summary-line"><dt>BIR Remittances</dt><dd data-bill-sum="remittance">&#8369;0.00</dd></div>
                            <div class="bill-summary-line"><dt>Professional Fees</dt><dd data-bill-sum="fees">&#8369;0.00</dd></div>
                            <div class="bill-summary-line"><dt>Bookkeeping</dt><dd data-bill-sum="bookkeeping">&#8369;0.00</dd></div>
                            <div class="bill-summary-line"><dt>Other Fees</dt><dd data-bill-sum="other">&#8369;0.00</dd></div>
                        </dl>
                        <div class="bill-summary-total">
                            <span>TOTAL</span>
                            <strong data-bill-sum="total">&#8369;0.00</strong>
                        </div>
                        <p class="bill-summary-note" data-bill-sum="note">No services selected yet.</p>
                    </section>
                </aside>
            </div>

            <div class="form-group bill-total-block">
                <label class="form-label">Computed total payment</label>
                <div class="form-control amount-display" id="totalDisplay" readonly>&#8369;0.00</div>
                <small class="form-hint">Totals every ticked line item automatically.</small>
                <div class="form-error" id="lineItemsError" role="alert" hidden></div>
                @error('line_items')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                @if ($locked)
                    <a href="{{ route('admin.billing.show', $billing->client) }}" class="btn btn-primary">View Statement</a>
                @else
                    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create billing statement' }}</button>
                    <a href="{{ $isEdit ? route('admin.billing.show', $billing->client) : route('admin.billing.index') }}" class="btn btn-outline">Cancel</a>
                @endif
            </div>
        </fieldset>
    </form>
</div>

@php
    $monthlyForms = ['1601C', '0619E', '0619F'];
@endphp

@if (! $locked)
    {{--
        Add Billing Item modal (Bootstrap 5.3.3, which this layout already loads —
        the Alpine-based x-modal component is not loaded here and would not work).

        Custom items belong to this statement only: they are saved as line items
        under the `custom` category and never create or modify a master service,
        so nothing here can leak into another client's billing.
    --}}
    <div class="modal fade" id="addBillingItemModal" tabindex="-1" role="dialog" aria-labelledby="addBillingItemModalTitle" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="addBillingItemForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="addBillingItemModalTitle">Add Billing Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="form-hint">This item is added to the current billing statement only. It does not change any master fee rate.</p>
                        <div class="form-group">
                            <label class="form-label" for="customItemDescription">Description <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control" id="customItemDescription" type="text" maxlength="120" placeholder="e.g. Annual BIR Registration">
                            <div class="form-error" data-custom-error="description" hidden></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="customItemAmount">Amount <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control" id="customItemAmount" type="number" step="0.01" min="0" placeholder="0.00">
                            <div class="form-error" data-custom-error="amount" hidden></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="customItemFrequency">Frequency</label>
                            <select class="form-control" id="customItemFrequency"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="customItemNotes">Notes</label>
                            <textarea class="form-control" id="customItemNotes" rows="2" maxlength="255" placeholder="Registration renewal"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" id="confirmAddBillingItem">Add Item</button>
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    'use strict';

    var clientSelect = document.getElementById('client_id');
    var companySelect = document.getElementById('client_company_id');
    var quarterSelect = document.getElementById('quarter');
    var yearInput = document.getElementById('year');
    var container = document.getElementById('lineItemsContainer');
    var totalDisplay = document.getElementById('totalDisplay');
    var lineItemsError = document.getElementById('lineItemsError');
    var form = document.getElementById('billingForm');
    var summaryEl = document.getElementById('billingSummary');
    var isEdit = {{ $isEdit ? 'true' : 'false' }};

    var FREQ = {!! $frequencyMapsJson !!};
    var FREQ_OPTIONS = {!! $frequencyOptionsJson !!};
    var feeRatesByCategory = {!! $feeRatesJson !!};

    // Track the last-known applicable BIR forms + computed total for the
    // create-guard (feasibility + amount checks) in billingCreateGuard().
    var currentForms = [];
    var currentTotal = 0;

    var monthlyForms = @json($monthlyForms);
    var monthNames = {1:'Jan',2:'Feb',3:'Mar',4:'Apr',5:'May',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Oct',11:'Nov',12:'Dec'};

    // One counter for every submitted line_items[i] row. The previous version
    // kept a separate counter for custom items, so a custom row could claim the
    // same index as a generated row and PHP would silently drop one of them.
    var itemIndex = 0;

    function round2(v) { var n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100) / 100; }
    function money(v) { return '\u20B1' + round2(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    // ---- Frequency helpers (mirror App\Support\BillingFrequency) ----
    function frequencyFor(category, formType) {
        if (formType) return FREQ.forms[formType] || 'unknown';
        if (category === 'bir_remittance') return 'as_needed';
        return FREQ.categories[category] || 'unknown';
    }
    function frequencyLabel(freq) { return FREQ.labels[freq] || FREQ.labels.unknown; }
    function isRecurring(freq) { return FREQ.recurring.indexOf(freq) !== -1; }
    function notScheduledNote(freq) {
        if (freq === 'annual') return 'Not scheduled for this quarter';
        if (freq === 'one_time') return 'One-time charge \u2014 include manually';
        if (freq === 'as_needed') return 'Only when requested';
        return null;
    }

    function amountOf(row) {
        var input = row.querySelector('[data-bill-amount]');
        if (!input || input.disabled) return 0;
        var v = parseFloat(input.value);
        return isNaN(v) ? 0 : v;
    }

    function isSelected(row) {
        var check = row.querySelector('[data-bill-check]');
        return !!check && check.checked;
    }

    /**
     * Totals mirror BillingSummaryMatrix so the live panel and the generated
     * Excel-style summary can never disagree:
     *   BIR Remittances = bir_remittance lines that carry a form type
     *   Professional    = professional_fee
     *   Bookkeeping    = bookkeeping_fee
     *   Other Fees     = Cash In + the attachment categories + custom items
     */
    function computeTotals() {
        var t = {count: 0, remittance: 0, fees: 0, bookkeeping: 0, other: 0, total: 0};

        form.querySelectorAll('[data-bill-row]').forEach(function (row) {
            if (!isSelected(row)) return;
            var amount = amountOf(row);
            var category = row.dataset.category;
            var formType = row.dataset.formType || '';

            t.count += 1;
            t.total += amount;

            if (category === 'bir_remittance') {
                if (formType) t.remittance += amount; else t.other += amount;
            } else if (category === 'professional_fee') {
                t.fees += amount;
            } else if (category === 'bookkeeping_fee') {
                t.bookkeeping += amount;
            } else {
                t.other += amount;
            }
        });

        t.total = round2(t.total);
        return t;
    }

    function renderSummary() {
        if (!summaryEl) return;
        var t = computeTotals();
        currentTotal = t.total;

        function set(key, value) {
            var el = summaryEl.querySelector('[data-bill-sum="' + key + '"]');
            if (el) el.textContent = value;
        }

        set('count', String(t.count));
        set('remittance', money(t.remittance));
        set('fees', money(t.fees));
        set('bookkeeping', money(t.bookkeeping));
        set('other', money(t.other));
        set('total', money(t.total));

        var note = summaryEl.querySelector('[data-bill-sum="note"]');
        if (note) {
            note.textContent = t.count === 0
                ? 'No services selected yet.'
                : t.count + (t.count === 1 ? ' service' : ' services') + ' will appear on this statement.';
        }

        if (totalDisplay) totalDisplay.textContent = money(t.total);

        // A stale "no amount" error must disappear as soon as any amount exists,
        // otherwise it lingers next to a valid computed total.
        if (t.total > 0 && lineItemsError && !lineItemsError.hidden) {
            lineItemsError.textContent = '';
            lineItemsError.hidden = true;
        }
    }

    function refreshSectionCounts() {
        form.querySelectorAll('[data-bill-section]').forEach(function (section) {
            var count = 0;
            section.querySelectorAll('[data-bill-row]').forEach(function (row) {
                if (isSelected(row)) count += 1;
            });
            var badge = section.querySelector('[data-bill-section-count]');
            if (badge) {
                badge.textContent = count + (count === 1 ? ' selected' : ' selected');
                badge.classList.toggle('is-zero', count === 0);
            }
        });
    }

    function refreshAll() {
        renderSummary();
        refreshSectionCounts();
    }

    /**
     * Tick/untick a row.
     *
     * Unticking disables the preset and amount controls and blanks the amount,
     * so an excluded service can never submit a figure. Ticking a service that
     * is not due this period marks it as a deliberate manual inclusion, which is
     * shown as a badge so the exception is obvious on the saved statement.
     */
    function setRowSelected(row, selected) {
        var check = row.querySelector('[data-bill-check]');
        if (check) check.checked = selected;

        var freq = row.dataset.frequency;
        var manualField = row.querySelector('[data-bill-manual]');
        var manual = selected && !isRecurring(freq);
        if (manualField) manualField.value = manual ? '1' : '0';

        row.querySelectorAll('[data-bill-amount], [data-bill-preset]').forEach(function (el) {
            el.disabled = !selected;
        });
        if (!selected) {
            var input = row.querySelector('[data-bill-amount]');
            if (input) input.value = '';
        }

        row.classList.toggle('is-selected', selected);
        var badge = row.querySelector('[data-bill-manual-badge]');
        if (badge) badge.hidden = !manual;
    }

    function buildPresetSelect(category, selectedFeeRateId) {
        var wrap = document.createElement('div');
        wrap.className = 'bill-row-preset';

        var label = document.createElement('label');
        label.className = 'bill-mini-label';
        label.textContent = 'Preset';

        var sel = document.createElement('select');
        sel.className = 'form-control form-control-sm';
        sel.dataset.billPreset = '1';

        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select preset\u2026';
        sel.appendChild(blank);

        (feeRatesByCategory[category] || []).forEach(function (r) {
            var opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = r.display;
            opt.dataset.amount = String(r.amount);
            if (selectedFeeRateId && String(selectedFeeRateId) === String(r.id)) opt.selected = true;
            sel.appendChild(opt);
        });

        sel.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            var input = amountInputFor(this);
            var feeField = this.closest('[data-bill-row]').querySelector('[data-bill-fee-rate]');
            if (!input) return;
            // Choosing a preset copies the master rate onto this statement's
            // amount. The rate itself is never modified, and the copied figure
            // stays editable so the admin can still adjust it.
            if (opt && opt.dataset.amount) {
                input.value = round2(opt.dataset.amount).toFixed(2);
                input.dataset.touched = '1';
            } else {
                input.value = '';
                input.dataset.touched = '';
            }
            if (feeField) feeField.value = opt && opt.value ? opt.value : '';
            syncDefaultHint(this.closest('[data-bill-row]'));
            refreshAll();
        });

        wrap.appendChild(label);
        wrap.appendChild(sel);
        return wrap;
    }

    /** Resolves a control back to its row's amount input. */
    function amountInputFor(scope) {
        var row = scope.closest('[data-bill-row]');
        return row ? row.querySelector('[data-bill-amount]') : null;
    }

    function syncDefaultHint(row) {
        var hint = row.querySelector('[data-bill-default]');
        if (!hint) return;
        var sel = row.querySelector('[data-bill-preset]');
        var input = row.querySelector('[data-bill-amount]');
        if (!sel || !sel.value) { hint.textContent = ''; hint.hidden = true; return; }
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.dataset.amount) { hint.textContent = ''; hint.hidden = true; return; }
        var defaultAmount = round2(opt.dataset.amount);
        var current = input ? parseFloat(input.value) : NaN;
        var differs = !isNaN(current) && round2(current) !== defaultAmount;
        hint.hidden = false;
        hint.textContent = 'Default ' + money(defaultAmount) + (differs ? ' \u00b7 adjusted for this statement' : '');
    }

    function buildLineItemRow(opts) {
        var idx = itemIndex++;
        var row = document.createElement('div');
        row.className = 'bill-row';
        row.dataset.billRow = '1';
        row.dataset.category = opts.category;
        row.dataset.formType = opts.formType || '';
        row.dataset.month = opts.month || '';
        row.dataset.frequency = opts.frequency;

        row.innerHTML =
            '<input type="hidden" name="line_items[' + idx + '][category]" value="' + opts.category + '">' +
            '<input type="hidden" name="line_items[' + idx + '][form_type]" value="' + (opts.formType || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][month]" value="' + (opts.month || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][label]" value="' + (opts.label || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][fee_rate_id]" data-bill-fee-rate value="' + (opts.feeRateId || '') + '">' +
            '<input type="hidden" name="line_items[' + idx + '][frequency]" value="' + opts.frequency + '">' +
            '<input type="hidden" name="line_items[' + idx + '][manual_include]" data-bill-manual value="0">' +
            (opts.notes ? '<input type="hidden" name="line_items[' + idx + '][notes]" value="' + opts.notes.replace(/"/g, '&quot;') + '">' : '');

        // Tick box
        var checkCell = document.createElement('div');
        checkCell.className = 'bill-row-check';
        var check = document.createElement('input');
        check.type = 'checkbox';
        check.dataset.billCheck = '1';
        check.setAttribute('aria-label', (opts.selected ? 'Include ' : 'Do not include ') + opts.displayLabel);
        check.addEventListener('change', function () {
            setRowSelected(row, this.checked);
            refreshAll();
        });
        checkCell.appendChild(check);

        // Identity: name + frequency + scheduling note
        var idCell = document.createElement('div');
        idCell.className = 'bill-row-identity';

        var name = document.createElement('div');
        name.className = 'bill-row-name';
        name.textContent = opts.displayLabel;
        idCell.appendChild(name);

        var meta = document.createElement('div');
        meta.className = 'bill-row-meta';

        var badge = document.createElement('span');
        badge.className = 'bill-freq bill-freq--' + opts.frequency;
        badge.textContent = frequencyLabel(opts.frequency);
        meta.appendChild(badge);

        var manualBadge = document.createElement('span');
        manualBadge.className = 'bill-manual-badge';
        manualBadge.dataset.billManualBadge = '1';
        manualBadge.textContent = 'Manually included';
        manualBadge.hidden = true;
        meta.appendChild(manualBadge);

        var note = notScheduledNote(opts.frequency);
        if (note && !opts.selected) {
            var noteEl = document.createElement('span');
            noteEl.className = 'bill-row-note';
            noteEl.textContent = note;
            meta.appendChild(noteEl);
        }
        idCell.appendChild(meta);

        if (opts.notes) {
            var notesEl = document.createElement('div');
            notesEl.className = 'bill-row-note';
            notesEl.textContent = opts.notes;
            idCell.appendChild(notesEl);
        }

        // Controls
        var controls = document.createElement('div');
        controls.className = 'bill-row-controls';

        if (feeRatesByCategory[opts.category]) {
            controls.appendChild(buildPresetSelect(opts.category, opts.feeRateId));
        }

        var amountWrap = document.createElement('div');
        amountWrap.className = 'bill-row-amount';

        var amountLabel = document.createElement('label');
        amountLabel.className = 'bill-mini-label';
        amountLabel.textContent = 'Amount';

        var input = document.createElement('input');
        input.className = 'form-control form-control-sm';
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.name = 'line_items[' + idx + '][amount]';
        input.value = opts.amount || '';
        input.dataset.billAmount = '1';
        input.setAttribute('aria-label', 'Amount for ' + opts.displayLabel);
        input.addEventListener('input', function () {
            input.dataset.touched = '1';
            syncDefaultHint(row);
            refreshAll();
        });

        amountWrap.appendChild(amountLabel);
        amountWrap.appendChild(input);
        controls.appendChild(amountWrap);

        var hint = document.createElement('div');
        hint.className = 'bill-row-default';
        hint.dataset.billDefault = '1';
        hint.hidden = true;
        controls.appendChild(hint);

        row.appendChild(checkCell);
        row.appendChild(idCell);
        row.appendChild(controls);

        setRowSelected(row, !!opts.selected);
        if (opts.selected) { syncDefaultHint(row); }
        return row;
    }

    /**
     * A BIR form the client is not registered for. Rendered so the admin can see
     * it was considered and deliberately excluded, rather than wondering whether
     * it was forgotten. It carries no form field names, so it cannot be submitted.
     */
    function buildNotApplicableRow(formType) {
        var freq = FREQ.forms[formType] || 'unknown';
        var row = document.createElement('div');
        row.className = 'bill-row bill-row--na';

        row.innerHTML =
            '<div class="bill-row-check"><input type="checkbox" disabled aria-label="' + formType + ' not applicable"></div>' +
            '<div class="bill-row-identity">' +
                '<div class="bill-row-name">' + formType + ' Remittance</div>' +
                '<div class="bill-row-meta">' +
                    '<span class="bill-freq bill-freq--' + freq + '">' + frequencyLabel(freq) + '</span>' +
                    '<span class="bill-na-badge">Not applicable</span>' +
                '</div>' +
            '</div>' +
            '<div class="bill-row-controls"><div class="bill-row-amount"><span class="bill-mini-label">&nbsp;</span>' +
            '<div class="form-control form-control-sm bill-control-disabled">&mdash;</div></div></div>';

        return row;
    }

    function buildCashInRow(existing) {
        return buildLineItemRow({
            category: 'bir_remittance',
            formType: '',
            month: '',
            label: 'Cash In',
            displayLabel: 'Cash In',
            amount: existing ? existing.amount : '',
            feeRateId: null,
            frequency: frequencyFor('bir_remittance', ''),
            selected: existing ? true : false,
        });
    }

    function buildSection(title, rows) {
        var section = document.createElement('details');
        section.className = 'bill-section';
        section.dataset.billSection = '1';
        section.open = true;

        var summary = document.createElement('summary');
        summary.className = 'bill-section-head';

        var caret = document.createElement('span');
        caret.className = 'bill-section-caret';
        caret.setAttribute('aria-hidden', 'true');
        caret.textContent = '\u25BC';

        var name = document.createElement('span');
        name.className = 'bill-section-name';
        name.textContent = title;

        var count = document.createElement('span');
        count.className = 'bill-section-count';
        count.dataset.billSectionCount = '1';
        count.textContent = '0 selected';

        summary.appendChild(caret);
        summary.appendChild(name);
        summary.appendChild(count);
        section.appendChild(summary);

        var body = document.createElement('div');
        body.className = 'bill-section-body';
        rows.forEach(function (row) { body.appendChild(row); });
        section.appendChild(body);

        return section;
    }

    /**
     * Decide whether a service should start included.
     *
     * An existing statement keeps exactly what it already charged. Otherwise a
     * service is only pre-ticked when it genuinely recurs every cycle; annual,
     * one-time and as-needed services start unticked and must be opted into.
     */
    function shouldSelect(existing, frequency) {
        if (existing) return true;
        return isRecurring(frequency);
    }

    function buildLineItems(applicableForms, allForms, existingItems) {
        container.innerHTML = '';
        itemIndex = 0;

        var remRows = [];
        var feeRows = [];
        var naRows = [];

        // Existing single-category rows, keyed by their category prefix.
        var singles = {};
        if (existingItems) {
            Object.keys(existingItems).forEach(function (key) {
                var cat = key.split('_')[0];
                singles[cat] = existingItems[key];
            });
        }

        function existingFor(category, formType, month) {
            if (!existingItems) return null;
            var key = category + '_' + (formType || '') + '_' + (month === null || month === undefined ? 'null' : month);
            return existingItems[key] || null;
        }

        // ---- BIR Remittances ----
        applicableForms.forEach(function (ft) {
            var freq = frequencyFor('bir_remittance', ft);
            if (monthlyForms.indexOf(ft) !== -1) {
                [1, 2, 3].forEach(function (m) {
                    var ex = existingFor('bir_remittance', ft, m);
                    remRows.push(buildLineItemRow({
                        category: 'bir_remittance', formType: ft, month: m,
                        label: ft + ' Remittance — ' + monthNames[m],
                        displayLabel: ft + ' Remittance',
                        amount: ex ? ex.amount : '',
                        feeRateId: null, frequency: freq,
                        selected: shouldSelect(ex, freq),
                    }));
                });
            } else {
                var ex = existingFor('bir_remittance', ft, null);
                remRows.push(buildLineItemRow({
                    category: 'bir_remittance', formType: ft, month: '',
                    label: ft + ' Remittance',
                    displayLabel: ft + ' Remittance',
                    amount: ex ? ex.amount : '',
                    feeRateId: null, frequency: freq,
                    selected: shouldSelect(ex, freq),
                }));
            }
        });

        var cashEx = existingItems ? existingItems['bir_remittance__'] : null;
        remRows.push(buildCashInRow(cashEx));

        // ---- Professional Fees ----
        applicableForms.forEach(function (ft) {
            var freq = frequencyFor('professional_fee', ft);
            if (monthlyForms.indexOf(ft) !== -1) {
                [1, 2, 3].forEach(function (m) {
                    var ex = existingFor('professional_fee', ft, m);
                    feeRows.push(buildLineItemRow({
                        category: 'professional_fee', formType: ft, month: m,
                        label: 'Fee — ' + ft + ' (' + monthNames[m] + ')',
                        displayLabel: 'Fee — ' + ft + ' (' + monthNames[m] + ')',
                        amount: ex ? ex.amount : '',
                        feeRateId: ex ? ex.fee_rate_id : null, frequency: freq,
                        selected: shouldSelect(ex, freq),
                    }));
                });
            } else {
                var ex = existingFor('professional_fee', ft, null);
                feeRows.push(buildLineItemRow({
                    category: 'professional_fee', formType: ft, month: '',
                    label: 'Fee — ' + ft,
                    displayLabel: 'Fee — ' + ft,
                    amount: ex ? ex.amount : '',
                    feeRateId: ex ? ex.fee_rate_id : null, frequency: freq,
                    selected: shouldSelect(ex, freq),
                }));
            }
        });

        // ---- Singleton categories (always offered, never auto-charged
        // unless the service actually recurs) ----
        var singletonDefs = [
            { category: 'bookkeeping_fee', label: 'Bookkeeping' },
            { category: 'post_closing_tb', label: 'Post-Closing Trial Balance' },
            { category: 'inventory_list', label: 'Inventory List (Notarized)' },
            { category: 'other_attachment', label: 'Other Attachment' },
            { category: 'data_entry', label: 'Data Entry' },
        ];

        var singletonRows = singletonDefs.map(function (def) {
            var ex = singles[def.category] || null;
            var freq = frequencyFor(def.category, '');
            return buildLineItemRow({
                category: def.category, formType: '', month: '',
                label: def.label, displayLabel: def.label,
                amount: ex ? ex.amount : '',
                feeRateId: ex ? ex.fee_rate_id : null, frequency: freq,
                selected: shouldSelect(ex, freq),
            });
        });

        // ---- Form types the client is not registered for ----
        if (allForms && allForms.length) {
            allForms.forEach(function (ft) {
                if (applicableForms.indexOf(ft) === -1) naRows.push(buildNotApplicableRow(ft));
            });
        }

        if (remRows.length) container.appendChild(buildSection('BIR Remittances', remRows));
        if (feeRows.length) container.appendChild(buildSection('Professional Fees', feeRows));
        container.appendChild(buildSection('Bookkeeping Fee', [singletonRows[0]]));
        container.appendChild(buildSection('Post-Closing Trial Balance', [singletonRows[1]]));
        container.appendChild(buildSection('Inventory List (Notarized)', [singletonRows[2]]));
        container.appendChild(buildSection('Other Attachment', [singletonRows[3]]));
        container.appendChild(buildSection('Data Entry', [singletonRows[4]]));

        if (naRows.length) {
            var naSection = buildSection('Not Applicable to This Client', naRows);
            naSection.classList.add('bill-section--na');
            naSection.open = false;
            container.appendChild(naSection);
        }

        // Reload any saved ad-hoc custom items.
        loadCustomItems(existingItems);
        refreshAll();
    }

    // ---- Ad-hoc custom items (current statement only) ----
    var customContainer = document.getElementById('customItemsContainer');
    var addCustomBtn = document.getElementById('addCustomItemBtn');

    function loadCustomItems(existingItems) {
        if (!customContainer) return;
        customContainer.innerHTML = '';
        if (!existingItems) return;
        Object.keys(existingItems).forEach(function (key) {
            if (key.indexOf('custom_') === 0 || key === 'custom__') {
                var item = existingItems[key];
                customContainer.appendChild(buildCustomItemRow({
                    label: item.label,
                    amount: item.amount,
                    frequency: item.frequency || 'one_time',
                    notes: item.notes,
                    selected: true,
                }));
            }
        });
        refreshSectionCounts();
    }

    function buildCustomItemRow(data) {
        var idx = itemIndex++;
        var freq = data.frequency || 'one_time';

        var wrapper = document.createElement('div');
        wrapper.className = 'bill-row bill-row--custom is-selected';
        wrapper.dataset.billRow = '1';
        wrapper.dataset.category = 'custom';
        wrapper.dataset.formType = '';
        wrapper.dataset.frequency = freq;

        wrapper.innerHTML =
            '<input type="hidden" name="line_items[' + idx + '][category]" value="custom">' +
            '<input type="hidden" name="line_items[' + idx + '][form_type]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][month]" value="">' +
            '<input type="hidden" name="line_items[' + idx + '][frequency]" value="' + freq + '">' +
            '<input type="hidden" name="line_items[' + idx + '][manual_include]" data-bill-manual value="0">';

        var checkCell = document.createElement('div');
        checkCell.className = 'bill-row-check';
        var check = document.createElement('input');
        check.type = 'checkbox';
        check.dataset.billCheck = '1';
        check.checked = true;
        check.setAttribute('aria-label', 'Include ' + (data.label || 'custom item'));
        check.addEventListener('change', function () {
            setRowSelected(wrapper, this.checked);
            refreshAll();
        });
        checkCell.appendChild(check);

        var idCell = document.createElement('div');
        idCell.className = 'bill-row-identity';

        var name = document.createElement('div');
        name.className = 'bill-row-name';
        name.textContent = data.label || 'Custom item';
        idCell.appendChild(name);

        var meta = document.createElement('div');
        meta.className = 'bill-row-meta';
        var badge = document.createElement('span');
        badge.className = 'bill-freq bill-freq--' + freq;
        badge.textContent = frequencyLabel(freq);
        meta.appendChild(badge);

        var manualBadge = document.createElement('span');
        manualBadge.className = 'bill-manual-badge';
        manualBadge.dataset.billManualBadge = '1';
        manualBadge.textContent = 'Manually included';
        manualBadge.hidden = freq === 'monthly' || freq === 'quarterly';
        meta.appendChild(manualBadge);
        idCell.appendChild(meta);

        if (data.notes) {
            var notesEl = document.createElement('div');
            notesEl.className = 'bill-row-note';
            notesEl.textContent = data.notes;
            idCell.appendChild(notesEl);
        }

        var controls = document.createElement('div');
        controls.className = 'bill-row-controls';

        var amountWrap = document.createElement('div');
        amountWrap.className = 'bill-row-amount';

        var amountLabel = document.createElement('label');
        amountLabel.className = 'bill-mini-label';
        amountLabel.textContent = 'Amount';

        var amountInput = document.createElement('input');
        amountInput.className = 'form-control form-control-sm';
        amountInput.type = 'number';
        amountInput.step = '0.01';
        amountInput.min = '0';
        amountInput.name = 'line_items[' + idx + '][amount]';
        amountInput.value = data.amount || '';
        amountInput.dataset.billAmount = '1';
        amountInput.setAttribute('aria-label', 'Amount for ' + (data.label || 'custom item'));
        amountInput.addEventListener('input', refreshAll);

        amountWrap.appendChild(amountLabel);
        amountWrap.appendChild(amountInput);
        controls.appendChild(amountWrap);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-outline danger btn-sm bill-remove';
        removeBtn.title = 'Remove item';
        removeBtn.setAttribute('aria-label', 'Remove ' + (data.label || 'custom item'));
        removeBtn.textContent = '\u00D7';
        removeBtn.addEventListener('click', function () {
            wrapper.remove();
            refreshAll();
        });
        controls.appendChild(removeBtn);

        wrapper.appendChild(checkCell);
        wrapper.appendChild(idCell);
        wrapper.appendChild(controls);
        return wrapper;
    }

    // ---- Add Custom Billing Item modal ----
    var customForm = document.getElementById('addBillingItemForm');
    var descInput = document.getElementById('customItemDescription');
    var amountField = document.getElementById('customItemAmount');
    var freqSelect = document.getElementById('customItemFrequency');
    var notesField = document.getElementById('customItemNotes');
    var addModalEl = document.getElementById('addBillingItemModal');

    function setCustomError(field, message) {
        var el = document.querySelector('[data-custom-error="' + field + '"]');
        if (!el) return;
        el.textContent = message || '';
        el.hidden = !message;
    }

    function openCustomModal() {
        if (!freqSelect) return;
        if (!freqSelect.options.length) {
            Object.keys(FREQ_OPTIONS).forEach(function (key) {
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = FREQ_OPTIONS[key];
                freqSelect.appendChild(opt);
            });
        }
        // Reset every field on each open so the modal is reusable: without this
        // the previous item's frequency stayed selected for the next one.
        freqSelect.value = 'one_time';
        descInput.value = '';
        amountField.value = '';
        notesField.value = '';
        setCustomError('description', '');
        setCustomError('amount', '');
        if (window.bootstrap && addModalEl) {
            bootstrap.Modal.getOrCreateInstance(addModalEl).show();
        }
    }

    function confirmCustomItem() {
        var label = (descInput.value || '').trim();
        var amount = parseFloat(amountField.value);
        var ok = true;

        if (!label) { setCustomError('description', 'Description is required.'); ok = false; }
        else { setCustomError('description', ''); }

        if (isNaN(amount) || amount <= 0) { setCustomError('amount', 'Enter an amount greater than zero.'); ok = false; }
        else { setCustomError('amount', ''); }

        if (!ok) return;

        if (window.bootstrap && addModalEl) {
            bootstrap.Modal.getOrCreateInstance(addModalEl).hide();
        }

        if (!customContainer) return;
        customContainer.appendChild(buildCustomItemRow({
            label: label,
            amount: amount.toFixed(2),
            frequency: freqSelect.value,
            notes: (notesField.value || '').trim() || null,
            selected: true,
        }));
        refreshAll();
    }

    if (addCustomBtn) addCustomBtn.addEventListener('click', openCustomModal);
    if (customForm) {
        customForm.addEventListener('submit', function (e) { e.preventDefault(); confirmCustomItem(); });
    }

    function loadApplicableForms() {
        var clientId = clientSelect.value;
        var companyId = companySelect ? companySelect.value : '';
        if (!clientId) {
            currentForms = [];
            currentTotal = 0;
            container.innerHTML = '<p class="muted" id="lineItemsPlaceholder">Select a client to load their applicable BIR forms.</p>';
            if (totalDisplay) totalDisplay.textContent = money(0);
            if (customContainer) customContainer.innerHTML = '';
            return;
        }

        var query = 'client_id=' + encodeURIComponent(clientId) + '&client_company_id=' + encodeURIComponent(companyId);
        var formsPromise = fetch('{{ route("admin.billing.applicableForms") }}?' + query, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });

        @if ($isEdit)
            formsPromise.then(function (data) {
                var forms = data.forms || [];
                currentForms = forms;
                var existingItems = {!! $existingItemsJson !!};
                buildLineItems(forms, data.all_forms || [], existingItems);
            });
        @else
            var lastPromise = fetch('{{ route("admin.billing.lastBilling") }}?' + query, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); });

            Promise.all([formsPromise, lastPromise]).then(function (results) {
                var forms = results[0].forms || [];
                var allForms = results[0].all_forms || [];
                currentForms = forms;
                var lastData = results[1];

                var existingItems = null;
                if (lastData.line_items && lastData.line_items.length > 0) {
                    existingItems = {};
                    lastData.line_items.forEach(function (item) {
                        var key = item.category + '_' + (item.form_type || '') + '_' + (item.month || 'null');
                        existingItems[key] = {
                            amount: item.amount,
                            fee_rate_id: item.fee_rate_id,
                            label: item.label,
                            frequency: item.frequency,
                            manual_include: item.manual_include,
                            notes: item.notes,
                        };
                    });
                }

                buildLineItems(forms, allForms, existingItems);

                // Immediate pre-check: the moment a client with no applicable
                // BIR forms is chosen, surface the "Add BIR Forms First" modal
                // instead of letting the user fill the form first.
                if (!isEdit) {
                    var resolvedClientId = clientSelect ? clientSelect.value : '';
                    if (resolvedClientId && resolvedClientId === clientId && currentForms.length === 0) {
                        showMissingBirFormsModal(resolvedClientId);
                    }
                }

                if (lastData.period_title) {
                    var hint = document.getElementById('carryForwardHint');
                    if (!hint) {
                        hint = document.createElement('p');
                        hint.id = 'carryForwardHint';
                        hint.className = 'form-hint';
                        container.parentNode.insertBefore(hint, container);
                    }
                    hint.textContent = 'Amounts carried forward from ' + lastData.period_title + '. Review each selection and edit amounts as needed.';
                }
            });
        @endif
    }

    function syncCompanyOptions() {
        if (!companySelect || !clientSelect) return;
        var clientId = clientSelect.value;
        for (var i = 0; i < companySelect.options.length; i++) {
            var option = companySelect.options[i];
            if (!option.dataset.clientId) continue;
            option.hidden = !!clientId && option.dataset.clientId !== clientId;
            option.disabled = !!clientId && option.dataset.clientId !== clientId;
        }
        var selected = companySelect.options[companySelect.selectedIndex];
        if (selected && selected.dataset.clientId && selected.dataset.clientId !== clientId) companySelect.value = '';
    }

    if (clientSelect) clientSelect.addEventListener('change', syncCompanyOptions);
    if (companySelect) companySelect.addEventListener('change', loadApplicableForms);

    // Load on page load for edit mode; for create when a client is pre-selected
    @if ($isEdit)
        syncCompanyOptions();
        if (clientSelect.value) {
            loadApplicableForms();
        }
    @else
        syncCompanyOptions();
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

    // ---- Create-guard: feasibility + amounts + confirmation (replaces the old
    // simple confirm). Wired via `onsubmit="return egliane.billingCreateGuard(this)"`
    // so it composes with the shared Egliane.confirm helper (which handles the
    // post-approval re-entry by returning true). Only present in create mode. ----
    function showMissingBirFormsModal(clientId) {
        var e = window.egliane || {};
        if (!e.confirm) return;
        e.confirm.action({
            title: 'Add BIR Forms First',
            message: 'This client does not have any BIR Forms selected yet. Add at least one BIR Form before creating a billing statement.',
            confirmLabel: 'Add BIR Forms'
        }, function () {
            window.location.href = '{{ route("admin.bir-forms.index") }}?client_id=' + encodeURIComponent(clientId || '');
        });
    }

    function freezeSubmitButton() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            if (btn.dataset.originalLabel == null) btn.dataset.originalLabel = btn.textContent;
            btn.textContent = 'Saving…';
        }
    }

    function showNoAmountError() {
        if (lineItemsError) {
            lineItemsError.textContent = 'Tick at least one billing item and give it an amount greater than zero.';
            lineItemsError.hidden = false;
        }
        if (totalDisplay) totalDisplay.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (!isEdit) {
        if (!window.egliane) window.egliane = {};

        window.egliane.billingCreateGuard = function (f) {
            var e = window.egliane || {};
            if (!e.confirm) return true;

            // Recompute from the live form so this check always reflects exactly
            // which items are ticked and the values that would be submitted.
            renderSummary();

            if (currentTotal <= 0) {
                showNoAmountError();
                return false;
            }

            // Feasibility: the selected client must have applicable BIR forms.
            if (currentForms.length === 0) {
                var clientId = clientSelect ? clientSelect.value : '';
                showMissingBirFormsModal(clientId);
                return false;
            }

            var approved = e.confirm.form(f, {
                title: 'Save billing statement?',
                message: 'The billing statement will be saved and the client will be notified.',
                confirmLabel: 'Save Billing Statement'
            });

            // Only after ALL client-side validation passes AND the user confirms
            // does the real submission begin.
            if (approved) freezeSubmitButton();

            return approved;
        };
    }

    // ---- Update-guard (edit mode only): validates then asks for confirmation
    // before an existing statement is updated. ----
    if (isEdit) {
        if (!window.egliane) window.egliane = {};

        window.egliane.billingUpdateGuard = function (f) {
            var e = window.egliane || {};
            if (!e.confirm) return true;

            renderSummary();

            if (currentTotal <= 0) {
                showNoAmountError();
                return false;
            }

            var approved = e.confirm.form(f, {
                title: 'Update billing statement?',
                message: 'Your changes will be saved to this billing statement.',
                confirmLabel: 'Update Billing Statement'
            });

            if (approved) freezeSubmitButton();

            return approved;
        };
    }

    refreshAll();
})();
</script>
@endpush