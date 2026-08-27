@extends('layouts.dashboard')

@section('title', 'Billing Settings — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.billing.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1>Fee Presets</h1>
            <p>Set suggested amounts for each fee category below. These appear as quick-select options when creating or editing a billing statement — admins can still enter a custom amount instead.</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('admin.billing.paymentSettings') }}" class="btn btn-outline btn-sm">Payment Settings</a>
            <a href="{{ route('admin.billing.index') }}" class="btn btn-outline btn-sm">Back to Billing Statements</a>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Default 2551Q rate</h3>
        <p class="card-sub">The 2551Q percentage tax rate used for reference. Tax Due amounts are now entered directly per form.</p>
        <form method="POST" action="{{ route('admin.billing.settings.update') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="tax_2551q_rate">2551Q Percentage Tax rate (%)</label>
                    <input class="form-control" id="tax_2551q_rate" name="tax_2551q_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_2551q_rate', \App\Models\Setting::get('tax_2551q_rate', '3')) }}">
                    @error('tax_2551q_rate')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Save settings</button>
        </form>
    </div>

    @php
        $feeCategories = [
            'professional_fee'  => ['title' => 'Professional Fee',           'placeholder' => 'Amount, e.g. 520',        'rates' => $feeRates->where('category', 'professional_fee')],
            'bookkeeping_fee'   => ['title' => 'Bookkeeping Fee',            'placeholder' => 'Amount, e.g. 1500',       'rates' => $feeRates->where('category', 'bookkeeping_fee')],
            'post_closing_tb'   => ['title' => 'Post-Closing Trial Balance', 'placeholder' => 'Amount, e.g. 1500',       'rates' => $feeRates->where('category', 'post_closing_tb')],
            'inventory_list'    => ['title' => 'Inventory List (Notarized)', 'placeholder' => 'Amount, e.g. 2000',       'rates' => $feeRates->where('category', 'inventory_list')],
            'other_attachment'  => ['title' => 'Other Attachment',           'placeholder' => 'Amount, e.g. 500',        'rates' => $feeRates->where('category', 'other_attachment')],
            'data_entry'        => ['title' => 'Data Entry',                 'placeholder' => 'Amount, e.g. 200',        'rates' => $feeRates->where('category', 'data_entry')],
        ];
    @endphp

    <div class="preset-grid">
        @foreach ($feeCategories as $categoryKey => $cat)
            <div class="card preset-card">
                <div class="card-head">
                    <h4 class="card-title">{{ $cat['title'] }}</h4>
                </div>

                <div class="chip-list">
                    @forelse ($cat['rates'] as $rate)
                        <div class="chip-row">
                            <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                            <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link danger" title="Remove preset">&times;</button>
                            </form>
                        </div>
                    @empty
                        <p class="muted" style="margin:0;">No presets yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add preset-add">
                    @csrf
                    <input type="hidden" name="category" value="{{ $categoryKey }}">
                    <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="{{ $cat['placeholder'] }}" required>
                    <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label shown in dropdown, e.g. 'Standard rate'">
                    <button type="submit" class="btn btn-outline">Add preset</button>
                </form>
                @error('amount')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
@endsection
