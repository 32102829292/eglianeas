@extends('layouts.dashboard')

@section('title', 'Billing Settings — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing Settings</h1>
            <p>Default tax rates, professional fees, and fee amount presets.</p>
        </div>
        <a href="{{ route('admin.billing.index') }}" class="btn btn-outline btn-sm">Back to Billing</a>
    </div>

    <div class="card">
        <h3 class="card-title">Default rates &amp; fees</h3>
        <p class="card-sub">The 2551Q percentage tax rate and the fixed professional / filing fee schedule applied when generating billing statements. Adjustable because BIR rates and fees change.</p>
        <form method="POST" action="{{ route('admin.billing.settings.update') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="tax_2551q_rate">2551Q Percentage Tax rate (%)</label>
                    <input class="form-control" id="tax_2551q_rate" name="tax_2551q_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_2551q_rate', $tax_2551q_rate) }}">
                    @error('tax_2551q_rate')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="fee_2551q">Default fee — 2551Q filing</label>
                    <select class="form-control" id="fee_2551q" name="fee_2551q">
                        @foreach ($feeRates as $rate)
                            <option value="{{ $rate->amount }}" @selected((string) $fee_2551q === (string) $rate->amount)>{{ $rate->money() }}</option>
                        @endforeach
                    </select>
                    @error('fee_2551q')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="fee_1701q">Default fee — 1701Q filing</label>
                    <select class="form-control" id="fee_1701q" name="fee_1701q">
                        @foreach ($feeRates as $rate)
                            <option value="{{ $rate->amount }}" @selected((string) $fee_1701q === (string) $rate->amount)>{{ $rate->money() }}</option>
                        @endforeach
                    </select>
                    @error('fee_1701q')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="fee_bookkeeping">Default fee — Bookkeeping / Post-Closing Trial Balance</label>
                    <select class="form-control" id="fee_bookkeeping" name="fee_bookkeeping">
                        @foreach ($feeRates as $rate)
                            <option value="{{ $rate->amount }}" @selected((string) $fee_bookkeeping === (string) $rate->amount)>{{ $rate->money() }}</option>
                        @endforeach
                    </select>
                    @error('fee_bookkeeping')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Save settings</button>
        </form>

        <div class="section-divider"></div>

        <div class="card-head">
            <div>
                <h4 class="card-title">Fee amount presets</h4>
                <p class="card-sub">These amounts appear in the professional-fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($feeRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset? Billing records keep their saved amounts.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 520" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
    </div>
@endsection
