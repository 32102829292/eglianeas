@extends('layouts.dashboard')

@section('title', 'Payment Settings — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Payment Settings</h1>
            <p>Your payment methods and bank account details appear on receipts and billing statements.</p>
        </div>
        <a href="{{ route('admin.billing.index') }}" class="btn btn-outline btn-sm">Back to Billing Statements</a>
    </div>

    <div class="invalid-note" id="save-validation-note" role="alert" aria-live="assertive" aria-hidden="true">
        <div class="invalid-note-inner">
            <span class="invalid-note-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M12 8h.01"/><path d="M12 12v4"/></svg>
            </span>
            <div class="invalid-note-body">
                <p class="invalid-note-title">Bank accounts are required and must be complete and valid before saving.</p>
                <p class="invalid-note-sub">Please check the highlighted fields and correct them before saving.</p>
            </div>
            <button type="button" class="invalid-note-close" id="save-validation-close" aria-label="Dismiss notification">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.billing.paymentSettings.update') }}" enctype="multipart/form-data" novalidate id="payment-settings-form">
        @csrf

        {{-- GCash --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">GCash</h3>
            </div>
            <p class="card-sub mb-3">Optional. Shown on receipts next to any bank accounts.</p>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="gcash_number">GCash mobile number</label>
                    <input class="form-control{{ $errors->has('gcash_number') ? ' is-invalid' : '' }}" id="gcash_number" name="gcash_number" type="tel" inputmode="numeric" maxlength="15" value="{{ old('gcash_number', $gcashNumber) }}" placeholder="e.g. 09171234567">
                    @error('gcash_number')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="gcash_qr_code">QR code <span class="req-tag opt">Optional</span></label>
                    <div class="qr-upload" data-qr-preview>
                        @if ($gcashQrCode)
                            <img src="{{ route('payment.image', 'gcash') }}" alt="GCash QR code">
                        @endif
                    </div>
                    <input class="form-control" id="gcash_qr_code" name="gcash_qr_code" type="file" accept="image/*">
                    <small class="form-hint">Upload a GCash QR code image. Max 2 MB.</small>
                    @error('gcash_qr_code')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Bank Accounts --}}
        <div class="card bank-accounts-card">
            <div class="card-head bank-card-head">
                <span class="bank-icon-badge" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 21 18 0"/><path d="M3 10 12 3l9 7"/><path d="M5 10v11"/><path d="M19 10v11"/><path d="M9 10v11"/><path d="M15 10v11"/></svg>
                </span>
                <div class="bank-card-head-text">
                    <h3 class="card-title">Bank Accounts</h3>
                    <p class="card-sub">Add one or more bank accounts. These appear on receipts alongside GCash. Every field marked <span class="req-tag" aria-hidden="true">*</span> must be filled in before saving.</p>
                </div>
            </div>

            <datalist id="supported-banks">
                @foreach ($supportedBanks as $def)
                    <option value="{{ $def['short'] }}" label="{{ $def['label'] }}">
                @endforeach
            </datalist>

            <div id="bank-accounts-wrap">
                @forelse ($bankAccounts as $i => $account)
                    <div class="bank-account-entry" data-bank-row>
                        <div class="bank-entry-head">
                            <span class="bank-entry-title">Bank account {{ $loop->iteration }}</span>
                            <button type="button" class="btn btn-link text-danger bank-remove-btn" data-remove-bank title="Remove this bank account">
                                <span aria-hidden="true">&times;</span> Remove
                            </button>
                        </div>

                        <input type="hidden" name="bank_accounts[{{ $i }}][existing_bank_qr_code]" value="{{ $account['bank_qr_code'] ?? '' }}">

                        <div class="bank-fields-grid">
                            <div class="form-group f-bank-name">
                                <label class="form-label">Bank name <span class="req-tag" aria-hidden="true">*</span></label>
                                <input class="form-control{{ $errors->has('bank_accounts.'.$i.'.bank_name') ? ' is-invalid' : '' }}"
                                       name="bank_accounts[{{ $i }}][bank_name]"
                                       type="text"
                                       list="supported-banks"
                                       data-bank-name
                                       maxlength="100"
                                       value="{{ old('bank_accounts.'.$i.'.bank_name', $account['bank_name'] ?? '') }}"
                                       placeholder="Select or type a bank (e.g. BDO)">
                                <div class="form-error" data-error="bank_name">@error('bank_accounts.'.$i.'.bank_name'){{ $message }}@enderror</div>
                            </div>

                            <div class="form-group f-account-name">
                                <label class="form-label">Account name <span class="req-tag" aria-hidden="true">*</span></label>
                                <input class="form-control{{ $errors->has('bank_accounts.'.$i.'.account_name') ? ' is-invalid' : '' }}"
                                       name="bank_accounts[{{ $i }}][account_name]"
                                       type="text"
                                       autocomplete="off"
                                       data-account-name
                                       maxlength="100"
                                       value="{{ old('bank_accounts.'.$i.'.account_name', $account['account_name'] ?? '') }}"
                                       placeholder="e.g. Egliane Accounting Services">
                                <div class="form-error" data-error="account_name">@error('bank_accounts.'.$i.'.account_name'){{ $message }}@enderror</div>
                            </div>

                            <div class="form-group f-account-number">
                                <label class="form-label">Account number <span class="req-tag" aria-hidden="true">*</span></label>
                                <input class="form-control{{ $errors->has('bank_accounts.'.$i.'.account_number') ? ' is-invalid' : '' }}"
                                       name="bank_accounts[{{ $i }}][account_number]"
                                       type="text"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       data-account-number
                                       maxlength="25"
                                       value="{{ old('bank_accounts.'.$i.'.account_number', $account['account_number'] ?? '') }}"
                                       placeholder="e.g. 000123456789">
                                <small class="form-hint" data-account-hint></small>
                                <div class="form-error" data-error="account_number">@error('bank_accounts.'.$i.'.account_number'){{ $message }}@enderror</div>
                            </div>

                            <div class="form-group f-qr">
                                <label class="form-label">QR code <span class="req-tag opt">Optional</span></label>
                                <div class="qr-upload" data-qr-preview>
                                    @if (! empty($account['bank_qr_code']))
                                        <img src="{{ route('payment.image', ['type' => 'bank', 'index' => $i]) }}" alt="Bank QR code">
                                    @endif
                                </div>
                                <input class="form-control" name="bank_accounts[{{ $i }}][bank_qr_code]" type="file" accept="image/*" data-qr-file>
                                <div class="form-error" data-error="bank_qr_code"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="muted empty-note" id="no-banks-note">No bank accounts added yet.</p>
                @endforelse
            </div>

            <button type="button" class="btn add-bank-btn" id="add-bank-btn" data-add-bank>
                <svg class="add-bank-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span>Add bank account</span>
            </button>
        </div>

        <div class="form-actions payment-actions">
            <button type="submit" class="btn btn-primary" id="save-payment-btn" data-save>Save payment details</button>
            <small class="form-hint">All bank accounts must be complete and valid before saving.</small>
        </div>
    </form>

    <script>
        (function () {
            const SUPPORTED_BANKS = @json($supportedBanks);
            const BANK_KEYS = Object.keys(SUPPORTED_BANKS);
            const NS = {};
            Object.keys(SUPPORTED_BANKS).forEach((slug) => {
                const d = SUPPORTED_BANKS[slug];
                NS[slug] = { label: d.label, short: d.short, digits: d.digits, aliases: [d.label, d.short, ...(d.aliases || [])] };
            });

            function normBank(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
            }

            function resolveBank(value) {
                const needle = normBank(value);
                if (!needle) return null;
                for (const slug of BANK_KEYS) {
                    if (NS[slug].aliases.some((a) => normBank(a) === needle)) return NS[slug];
                }
                return null;
            }

            function digitsOnly(value) {
                return String(value || '').replace(/[^0-9]/g, '');
            }

            function setError(row, field, message) {
                const el = row.querySelector('[data-error="' + field + '"]');
                const input = row.querySelector('[data-' + field + ']') || row.querySelector('[name$="[' + field + ']"]');
                if (!el) return;
                el.textContent = message || '';
                if (input) input.classList.toggle('is-invalid', Boolean(message));
            }

            function setHint(row, bank) {
                const hint = row.querySelector('[data-account-hint]');
                if (!hint) return;
                if (!bank) { hint.textContent = ''; return; }
                const d = bank.digits;
                const last = d[d.length - 1];
                const len = d.length > 1 ? d.slice(0, -1).join(', ') + ' or ' + last + ' digits' : last + ' digits';
                hint.textContent = len + ' for ' + bank.short;
            }

            function validateRow(row) {
                let ok = true;
                const bankInput = row.querySelector('[data-bank-name]');
                const numInput = row.querySelector('[data-account-number]');
                const nameInput = row.querySelector('[data-account-name]');

                const bankValue = bankInput ? bankInput.value.trim() : '';
                const bank = resolveBank(bankValue);

                if (!bankValue) {
                    setError(row, 'bank_name', 'Bank name is required.');
                    ok = false;
                } else if (!bank) {
                    setError(row, 'bank_name', 'This bank is not supported. Choose one from the list.');
                    ok = false;
                } else {
                    setError(row, 'bank_name', '');
                }

                if (nameInput && !nameInput.value.trim()) {
                    setError(row, 'account_name', 'Account name is required.');
                    ok = false;
                } else if (nameInput) {
                    setError(row, 'account_name', '');
                }

                const numValue = numInput ? numInput.value.trim() : '';
                if (!numValue) {
                    setError(row, 'account_number', 'Account number is required.');
                    ok = false;
                } else if (bank) {
                    const len = digitsOnly(numValue).length;
                    if (!/^[0-9\s-]+$/.test(numValue)) {
                        setError(row, 'account_number', 'Account number must contain numbers only.');
                        ok = false;
                    } else if (!bank.digits.includes(len)) {
                        setError(row, 'account_number', 'Enter a valid account number for this bank.');
                        ok = false;
                    } else {
                        setError(row, 'account_number', '');
                    }
                }

                return ok;
            }

            function validateAll() {
                const rows = Array.from(document.querySelectorAll('[data-bank-row]'));
                if (!rows.length) return true;
                let allOk = true;
                rows.forEach((row) => { if (!validateRow(row)) allOk = false; });
                return allOk;
            }

            let bankIndex = {{ count($bankAccounts) }};

            function refreshTitles() {
                document.querySelectorAll('[data-bank-row]').forEach((row, idx) => {
                    const t = row.querySelector('.bank-entry-title');
                    if (t) t.textContent = 'Bank account ' + (idx + 1);
                });
            }

            function removeBankRow(button) {
                const row = button.closest('[data-bank-row]');
                if (!row) return;
                row.remove();
                document.querySelectorAll('[data-bank-row]').forEach((r) => { r.classList.remove('is-new'); });
                refreshTitles();
                const wrap = document.getElementById('bank-accounts-wrap');
                if (!wrap.querySelector('[data-bank-row]')) {
                    const note = document.createElement('p');
                    note.className = 'muted empty-note';
                    note.id = 'no-banks-note';
                    note.textContent = 'No bank accounts added yet.';
                    wrap.appendChild(note);
                }
            }

            function addBankAccount() {
                const wrap = document.getElementById('bank-accounts-wrap');
                const note = document.getElementById('no-banks-note');
                if (note) note.remove();

                const row = document.createElement('div');
                row.className = 'bank-account-entry is-new';
                row.setAttribute('data-bank-row', '');
                const i = bankIndex++;
                row.innerHTML =
                    '<div class="bank-entry-head">' +
                    '  <span class="bank-entry-title">Bank account</span>' +
                    '  <button type="button" class="btn btn-link text-danger bank-remove-btn" data-remove-bank title="Remove this bank account"><span aria-hidden="true">&times;</span> Remove</button>' +
                    '</div>' +
                    '<input type="hidden" name="bank_accounts[' + i + '][existing_bank_qr_code]" value="">' +
                    '<div class="bank-fields-grid">' +
                    '  <div class="form-group f-bank-name">' +
                    '    <label class="form-label">Bank name <span class="req-tag" aria-hidden="true">*</span></label>' +
                    '    <input class="form-control" name="bank_accounts[' + i + '][bank_name]" type="text" list="supported-banks" data-bank-name maxlength="100" placeholder="Select or type a bank (e.g. BDO)">' +
                    '    <div class="form-error" data-error="bank_name"></div>' +
                    '  </div>' +
                    '  <div class="form-group f-account-name">' +
                    '    <label class="form-label">Account name <span class="req-tag" aria-hidden="true">*</span></label>' +
                    '    <input class="form-control" name="bank_accounts[' + i + '][account_name]" type="text" autocomplete="off" data-account-name maxlength="100" placeholder="e.g. Egliane Accounting Services">' +
                    '    <div class="form-error" data-error="account_name"></div>' +
                    '  </div>' +
                    '  <div class="form-group f-account-number">' +
                    '    <label class="form-label">Account number <span class="req-tag" aria-hidden="true">*</span></label>' +
                    '    <input class="form-control" name="bank_accounts[' + i + '][account_number]" type="text" inputmode="numeric" autocomplete="off" data-account-number maxlength="25" placeholder="e.g. 000123456789">' +
                    '    <small class="form-hint" data-account-hint></small>' +
                    '    <div class="form-error" data-error="account_number"></div>' +
                    '  </div>' +
                    '  <div class="form-group f-qr">' +
                    '    <label class="form-label">QR code <span class="req-tag opt">Optional</span></label>' +
                    '    <div class="qr-upload" data-qr-preview></div>' +
                    '    <input class="form-control" name="bank_accounts[' + i + '][bank_qr_code]" type="file" accept="image/*" data-qr-file>' +
                    '    <div class="form-error" data-error="bank_qr_code"></div>' +
                    '  </div>' +
                    '</div>';

                wrap.appendChild(row);
                refreshTitles();
                row.querySelector('.bank-remove-btn').addEventListener('click', function () { removeBankRow(this); });
                wireRow(row);
                row.querySelector('[data-bank-name]').focus();
            }

            function wireRow(row) {
                const bankInput = row.querySelector('[data-bank-name]');
                const numInput = row.querySelector('[data-account-number]');
                const nameInput = row.querySelector('[data-account-name]');
                const fileInput = row.querySelector('[data-qr-file]');

                if (bankInput) {
                    bankInput.addEventListener('input', function () {
                        setError(row, 'bank_name', '');
                        setHint(row, resolveBank(this.value));
                    });
                }
                if (numInput) {
                    numInput.addEventListener('input', function () { setError(row, 'account_number', ''); });
                }
                if (nameInput) {
                    nameInput.addEventListener('input', function () { setError(row, 'account_name', ''); });
                }
                if (fileInput) {
                    fileInput.addEventListener('change', function () {
                        const preview = row.querySelector('[data-qr-preview]');
                        const err = row.querySelector('[data-error="bank_qr_code"]');
                        err.textContent = '';
                        if (preview) {
                            preview.querySelectorAll('img').forEach((img) => img.remove());
                        }
                        const file = this.files && this.files[0];
                        if (!file) return;
                        if (file.size > 2 * 1024 * 1024) {
                            err.textContent = 'The image must not be larger than 2 MB.';
                            this.value = '';
                            return;
                        }
                        if (!/^image\//.test(file.type)) {
                            err.textContent = 'Please choose an image file.';
                            this.value = '';
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            if (!preview) return;
                            preview.querySelectorAll('img').forEach((img) => img.remove());
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'QR code preview';
                            preview.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            }

            function scrollToFirstError() {
                const first = document.querySelector('[data-bank-row] .is-invalid');
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // Wire existing rows
            document.querySelectorAll('[data-bank-row]').forEach((row) => {
                row.querySelector('.bank-remove-btn').addEventListener('click', function () { removeBankRow(this); });
                wireRow(row);
                const bankInput = row.querySelector('[data-bank-name]');
                if (bankInput) setHint(row, resolveBank(bankInput.value.trim()));
            });

            // Wire add button
            const addBtn = document.getElementById('add-bank-btn');
            if (addBtn) addBtn.addEventListener('click', addBankAccount);

            // Save-validation notification (only after a failed save attempt)
            const invalidNote = document.getElementById('save-validation-note');
            const invalidNoteClose = document.getElementById('save-validation-close');
            let invalidNoteTimer = null;

            function hideInvalidNote() {
                if (!invalidNote) return;
                invalidNote.classList.remove('is-visible');
                invalidNote.setAttribute('aria-hidden', 'true');
                if (invalidNoteTimer) { clearTimeout(invalidNoteTimer); invalidNoteTimer = null; }
            }

            function showInvalidNote() {
                if (!invalidNote) return;
                invalidNote.classList.remove('is-visible');
                void invalidNote.offsetWidth;
                invalidNote.classList.add('is-visible');
                invalidNote.setAttribute('aria-hidden', 'false');
                if (invalidNoteTimer) { clearTimeout(invalidNoteTimer); }
                invalidNoteTimer = setTimeout(hideInvalidNote, 5000);
            }

            if (invalidNoteClose) invalidNoteClose.addEventListener('click', hideInvalidNote);

            // Submit: validate all rows, then disable the button to block double submits.
            const form = document.getElementById('payment-settings-form');
            const saveBtn = document.getElementById('save-payment-btn');
            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!validateAll()) {
                        e.preventDefault();
                        showInvalidNote();
                        scrollToFirstError();
                        return;
                    }
                    if (saveBtn && saveBtn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    hideInvalidNote();
                    if (saveBtn) {
                        saveBtn.disabled = true;
                        saveBtn.setAttribute('data-busy', '1');
                        saveBtn.dataset.original = saveBtn.textContent;
                        saveBtn.textContent = 'Saving payment details…';
                    }
                });
            }
        })();
    </script>
@endsection