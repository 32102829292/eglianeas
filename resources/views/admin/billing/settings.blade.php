@extends('layouts.dashboard')

@section('title', 'Billing Settings — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing Settings</h1>
            <p>Default tax rates and fee amount presets by category.</p>
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

        <div class="section-divider"></div>

        @php
            $profRates = $feeRates->where('category', 'professional_fee');
            $bookRates = $feeRates->where('category', 'bookkeeping_fee');
            $ptbRates = $feeRates->where('category', 'post_closing_tb');
            $invRates = $feeRates->where('category', 'inventory_list');
            $oaRates = $feeRates->where('category', 'other_attachment');
            $deRates = $feeRates->where('category', 'data_entry');
        @endphp

        {{-- Professional Fee Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Professional Fee Presets</h4>
                <p class="card-sub">Amounts shown in professional fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($profRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No professional fee presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="professional_fee">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 520" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror

        <div class="section-divider"></div>

        {{-- Bookkeeping Fee Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Bookkeeping Fee Presets</h4>
                <p class="card-sub">Amounts shown in bookkeeping fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($bookRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No bookkeeping fee presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="bookkeeping_fee">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 1500" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror

        <div class="section-divider"></div>

        {{-- Post-Closing Trial Balance Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Post-Closing Trial Balance Presets</h4>
                <p class="card-sub">Amounts shown in post-closing TB fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($ptbRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No post-closing TB presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="post_closing_tb">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 1500" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror

        <div class="section-divider"></div>

        {{-- Inventory List Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Inventory List (Notarized) Presets</h4>
                <p class="card-sub">Amounts shown in inventory list fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($invRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No inventory list presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="inventory_list">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 2000" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror

        <div class="section-divider"></div>

        {{-- Other Attachment Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Other Attachment Presets</h4>
                <p class="card-sub">Amounts shown in other attachment fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($oaRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No other attachment presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="other_attachment">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 500" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror

        <div class="section-divider"></div>

        {{-- Data Entry Presets --}}
        <div class="card-head">
            <div>
                <h4 class="card-title">Data Entry Presets</h4>
                <p class="card-sub">Amounts shown in data entry fee dropdowns when creating or editing a billing statement.</p>
            </div>
        </div>

        <div class="chip-list">
            @forelse ($deRates as $rate)
                <div class="chip-row">
                    <span class="chip-label">{{ $rate->money() }}{{ $rate->label ? ' — '.$rate->label : '' }}</span>
                    <form method="POST" action="{{ route('admin.billing.feeRates.destroy', $rate) }}" onsubmit="return confirm('Remove this fee preset?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No data entry presets yet.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.billing.feeRates.store') }}" class="inline-add mt-2">
            @csrf
            <input type="hidden" name="category" value="data_entry">
            <input class="form-control" name="amount" type="number" step="0.01" min="0" placeholder="Amount, e.g. 200" required>
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Label (optional)">
            <button type="submit" class="btn btn-outline">Add preset</button>
        </form>
        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
    </div>
@endsection
