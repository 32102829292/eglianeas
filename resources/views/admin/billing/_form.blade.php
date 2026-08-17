@php
    $feeDefaults = [];
    foreach (['fee_2551q', 'fee_1701q', 'fee_bookkeeping'] as $feeFieldKey) {
        $current = old($feeFieldKey, $billing->{$feeFieldKey} ?? $fees[$feeFieldKey]);
        $feeDefaults[$feeFieldKey] = [
            'current' => $current,
            'inPresets' => $feeRates->contains(fn ($rate) => abs((float) $rate->amount - (float) $current) < 0.005),
        ];
    }
@endphp

<div class="card">
    <div class="card-head">
        <h2 class="card-title">{{ $formMode === 'create' ? 'New billing statement' : 'Edit billing statement' }}</h2>
    </div>

    <form method="POST" action="{{ $formMode === 'create' ? route('admin.billing.store') : route('admin.billing.update', $billing) }}" id="billingForm">
        @csrf
        @if ($formMode === 'edit')
            @method('PUT')
        @endif

        <div class="form-grid two">
            <div class="form-group">
                <label class="form-label" for="client_id">Client</label>
                <select class="form-control" id="client_id" name="client_id" required>
                    <option value="">Select client&hellip;</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $billing->client_id) === $client->id)>
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
                <div class="form-hint">Leave blank to auto-set to the end of the quarter&rsquo;s month. Clients are reminded one week before this date until the bill is paid.</div>
                @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="sales">Sales (for the period)</label>
                <input class="form-control" id="sales" name="sales" type="number" step="0.01" min="0" value="{{ old('sales', $billing->sales) }}" data-tax-target="tax_2551q">
                @error('sales')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="rate_2551q">2551Q Percentage Tax rate (%)</label>
                <input class="form-control" id="rate_2551q" name="rate_2551q" type="number" step="0.01" min="0" max="100" value="{{ old('rate_2551q', $billing->rate_2551q ?? $rate) }}" data-tax-rate>
                @error('rate_2551q')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-grid two">
            <div class="form-group">
                <label class="form-label" for="tax_2551q">2551Q (Percentage Tax) — remittance</label>
                <input class="form-control" id="tax_2551q" name="tax_2551q" type="number" step="0.01" min="0" value="{{ old('tax_2551q', $billing->tax_2551q) }}" data-money data-total>
                @error('tax_2551q')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="tax_1701q">1701Q (Income Tax) — remittance</label>
                <input class="form-control" id="tax_1701q" name="tax_1701q" type="number" step="0.01" min="0" value="{{ old('tax_1701q', $billing->tax_1701q) }}" data-money data-total>
                @error('tax_1701q')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="cash_in">Cash in</label>
                <input class="form-control" id="cash_in" name="cash_in" type="number" step="0.01" min="0" value="{{ old('cash_in', $billing->cash_in) }}" data-money data-total>
                @error('cash_in')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="fee_2551q">Professional fee — 2551Q filing</label>
                <select class="form-control" id="fee_2551q" name="fee_2551q" data-money data-total>
                    <option value="">Select preset&hellip;</option>
                    @foreach ($feeRates as $rate)
                        <option value="{{ $rate->amount }}" @selected((string) $feeDefaults['fee_2551q']['current'] === (string) $rate->amount)>{{ $rate->money() }}</option>
                    @endforeach
                    @if (! $feeDefaults['fee_2551q']['inPresets'] && $feeDefaults['fee_2551q']['current'] !== '' && $feeDefaults['fee_2551q']['current'] !== null)
                        <option value="{{ $feeDefaults['fee_2551q']['current'] }}" selected>&#8369;{{ number_format((float) $feeDefaults['fee_2551q']['current'], 2) }} (saved)</option>
                    @endif
                </select>
                @error('fee_2551q')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="fee_1701q">Professional fee — 1701Q filing</label>
                <select class="form-control" id="fee_1701q" name="fee_1701q" data-money data-total>
                    <option value="">Select preset&hellip;</option>
                    @foreach ($feeRates as $rate)
                        <option value="{{ $rate->amount }}" @selected((string) $feeDefaults['fee_1701q']['current'] === (string) $rate->amount)>{{ $rate->money() }}</option>
                    @endforeach
                    @if (! $feeDefaults['fee_1701q']['inPresets'] && $feeDefaults['fee_1701q']['current'] !== '' && $feeDefaults['fee_1701q']['current'] !== null)
                        <option value="{{ $feeDefaults['fee_1701q']['current'] }}" selected>&#8369;{{ number_format((float) $feeDefaults['fee_1701q']['current'], 2) }} (saved)</option>
                    @endif
                </select>
                @error('fee_1701q')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="fee_bookkeeping">Bookkeeping / Post-Closing Trial Balance</label>
                <select class="form-control" id="fee_bookkeeping" name="fee_bookkeeping" data-money data-total>
                    <option value="">Select preset&hellip;</option>
                    @foreach ($feeRates as $rate)
                        <option value="{{ $rate->amount }}" @selected((string) $feeDefaults['fee_bookkeeping']['current'] === (string) $rate->amount)>{{ $rate->money() }}</option>
                    @endforeach
                    @if (! $feeDefaults['fee_bookkeeping']['inPresets'] && $feeDefaults['fee_bookkeeping']['current'] !== '' && $feeDefaults['fee_bookkeeping']['current'] !== null)
                        <option value="{{ $feeDefaults['fee_bookkeeping']['current'] }}" selected>&#8369;{{ number_format((float) $feeDefaults['fee_bookkeeping']['current'], 2) }} (saved)</option>
                    @endif
                </select>
                @error('fee_bookkeeping')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Computed total payment</label>
                <div class="form-control" id="totalDisplay" readonly>&#8369;0.00</div>
            </div>
        </div>

        <div class="form-grid two">
            <div class="form-group">
                <label class="checkbox-row" style="margin-top:26px;">
                    <input type="checkbox" name="sales_submitted" value="1" @checked($formMode === 'edit' && $billing->hasSubmittedSales())>
                    <span>Client has submitted their Sales for this period</span>
                </label>
                <div class="form-hint">Stops the daily "missing sales" reminder for this client.</div>
            </div>
            @if ($formMode === 'edit')
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="form-control-static">
                        <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                        <div class="form-hint">Status is computed automatically from sales submission and due date. Mark payments from the Collections page.</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="btn-group-row">
            <button type="submit" class="btn btn-primary">{{ $formMode === 'create' ? 'Create billing' : 'Save changes' }}</button>
            <a href="{{ $formMode === 'create' ? route('admin.billing.index') : route('admin.billing.show', $billing->client_id) }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
