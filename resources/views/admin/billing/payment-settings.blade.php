@extends('layouts.dashboard')

@section('title', 'Payment Settings — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Payment Settings</h1>
            <p>GCash and bank account information shown on receipts.</p>
        </div>
        <a href="{{ route('admin.billing.settings') }}" class="btn btn-outline btn-sm">Back to Billing Settings</a>
    </div>

    <form method="POST" action="{{ route('admin.billing.paymentSettings.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- GCash --}}
        <div class="card" style="margin-bottom:24px;">
            <div class="card-head">
                <h3 class="card-title">GCash</h3>
            </div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="gcash_number">GCash mobile number</label>
                    <input class="form-control" id="gcash_number" name="gcash_number" type="text" maxlength="30" value="{{ old('gcash_number', $gcashNumber) }}" placeholder="e.g. 09171234567">
                    @error('gcash_number')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">QR code image</label>
                    @if ($gcashQrCode)
                        <div style="margin-bottom:8px;">
                            <img src="{{ route('payment.image', 'gcash') }}" alt="GCash QR" style="max-width:120px;max-height:120px;border:1px solid var(--border);border-radius:6px;">
                        </div>
                    @endif
                    <input class="form-control" name="gcash_qr_code" type="file" accept="image/*">
                    <small class="text-muted">Upload a GCash QR code image. Max 2 MB.</small>
                    @error('gcash_qr_code')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Bank Accounts --}}
        <div class="card" style="margin-bottom:24px;">
            <div class="card-head">
                <h3 class="card-title">Bank Accounts</h3>
            </div>
            <p class="card-sub" style="margin-bottom:16px;">Add one or more bank accounts. These appear on receipts alongside GCash.</p>

            <div id="bank-accounts-wrap">
                @forelse ($bankAccounts as $i => $account)
                    <div class="bank-account-entry" style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;position:relative;">
                        <button type="button" class="btn btn-link text-danger" onclick="this.closest('.bank-account-entry').remove()" style="position:absolute;top:8px;right:8px;padding:2px 6px;" title="Remove this bank account">&times;</button>
                        <input type="hidden" name="bank_accounts[{{ $i }}][existing_bank_qr_code]" value="{{ $account['bank_qr_code'] ?? '' }}">
                        <div class="form-grid two">
                            <div class="form-group">
                                <label class="form-label">Bank name</label>
                                <input class="form-control" name="bank_accounts[{{ $i }}][bank_name]" type="text" maxlength="100" value="{{ $account['bank_name'] ?? '' }}" placeholder="e.g. BDO, BPI, Metrobank">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account number</label>
                                <input class="form-control" name="bank_accounts[{{ $i }}][account_number]" type="text" maxlength="50" value="{{ $account['account_number'] ?? '' }}" placeholder="e.g. 1234567890">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account name</label>
                                <input class="form-control" name="bank_accounts[{{ $i }}][account_name]" type="text" maxlength="100" value="{{ $account['account_name'] ?? '' }}" placeholder="e.g. Egliane Accounting Services">
                            </div>
                            <div class="form-group">
                                <label class="form-label">QR code image (optional)</label>
                                @if (! empty($account['bank_qr_code']))
                                    <div style="margin-bottom:8px;">
                                        <img src="{{ route('payment.image', ['type' => 'bank', 'index' => $i]) }}" alt="Bank QR" style="max-width:100px;max-height:100px;border:1px solid var(--border);border-radius:6px;">
                                    </div>
                                @endif
                                <input class="form-control" name="bank_accounts[{{ $i }}][bank_qr_code]" type="file" accept="image/*">
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted" id="no-banks-note">No bank accounts added yet.</p>
                @endforelse
            </div>

            <button type="button" class="btn btn-outline" id="add-bank-btn" onclick="addBankAccount()">+ Add bank account</button>
        </div>

        <button type="submit" class="btn btn-primary">Save payment details</button>
    </form>

    <script>
        let bankIndex = {{ count($bankAccounts) }};

        function addBankAccount() {
            document.getElementById('no-banks-note')?.remove();
            const wrap = document.getElementById('bank-accounts-wrap');
            const html = `
                <div class="bank-account-entry" style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;position:relative;">
                    <button type="button" class="btn btn-link text-danger" onclick="this.closest('.bank-account-entry').remove()" style="position:absolute;top:8px;right:8px;padding:2px 6px;" title="Remove this bank account">&times;</button>
                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label">Bank name</label>
                            <input class="form-control" name="bank_accounts[${bankIndex}][bank_name]" type="text" maxlength="100" placeholder="e.g. BDO, BPI, Metrobank">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account number</label>
                            <input class="form-control" name="bank_accounts[${bankIndex}][account_number]" type="text" maxlength="50" placeholder="e.g. 1234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account name</label>
                            <input class="form-control" name="bank_accounts[${bankIndex}][account_name]" type="text" maxlength="100" placeholder="e.g. Egliane Accounting Services">
                        </div>
                        <div class="form-group">
                            <label class="form-label">QR code image (optional)</label>
                            <input class="form-control" name="bank_accounts[${bankIndex}][bank_qr_code]" type="file" accept="image/*">
                        </div>
                    </div>
                </div>
            `;
            wrap.insertAdjacentHTML('beforeend', html);
            bankIndex++;
        }
    </script>
@endsection
