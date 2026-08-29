@php
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };
    $maskName = function (?string $value): string {
        if (! $value) { return ''; }
        return mb_substr($value, 0, 1).str_repeat('•', max(mb_strlen($value) - 1, 0));
    };
    $lobSaved = old('line_of_business', $profile->line_of_business);
    $lobIsOther = $lobSaved !== null && $lobSaved !== '' && ! in_array($lobSaved, $lobOptions, true);
    $lobSelect = $lobIsOther ? 'Other' : (string) $lobSaved;
    $lobOtherVal = $lobIsOther ? (string) $lobSaved : (string) old('line_of_business_other');
@endphp

@extends('layouts.dashboard')

@section('title', 'Edit Client — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.clients.show', $client) }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to {{ $client->business_name ?: $client->name }}
    </a>

    <div class="page-head">
        <h1>Edit client</h1>
        <p>{{ $client->business_name ?: $client->name }} &middot; {{ $client->email }}</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.clients.update', $client) }}" id="clientEditForm">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-head"><h2 class="card-title">Account</h2></div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="name">Taxpayer name</label>
                    <input class="form-control" id="name" name="name" type="text" value="{{ old('name', $client->name) }}" required>
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $client->email) }}" required>
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="business_name">Business name</label>
                    <input class="form-control" id="business_name" name="business_name" type="text" value="{{ old('business_name', $client->business_name) }}">
                    @error('business_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Account status</h2></div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="status">Account status</label>
                    <select class="form-control" id="status" name="status">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $profile->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-hint">{{ $statusNotes[$profile->status] ?? '' }}</div>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="payment_status">Payment status</label>
                    <select class="form-control" id="payment_status" name="payment_status">
                        <option value="">—</option>
                        <option value="paid" @selected(old('payment_status', $profile->payment_status) === 'paid')>Paid</option>
                        <option value="partial" @selected(old('payment_status', $profile->payment_status) === 'partial')>Partial</option>
                        <option value="unpaid" @selected(old('payment_status', $profile->payment_status) === 'unpaid')>Unpaid</option>
                    </select>
                    @error('payment_status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_started">Date started</label>
                    <input class="form-control" id="date_started" name="date_started" type="date" value="{{ old('date_started', $profile->date_started?->format('Y-m-d')) }}">
                    @error('date_started')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Business information</h2></div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="taxpayer_name">Taxpayer's name</label>
                    <input class="form-control" id="taxpayer_name" name="taxpayer_name" type="text" value="{{ old('taxpayer_name', $profile->taxpayer_name) }}" placeholder="Registered taxpayer name (if different from business name)">
                    @error('taxpayer_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="business_type">Business type</label>
                    <select class="form-control" id="business_type" name="business_type">
                        <option value="">Select business type</option>
                        @foreach ($businessTypes as $type)
                            <option value="{{ $type }}" @selected(old('business_type', $profile->business_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('business_type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="taxpayer_type">Type of taxpayer</label>
                    <select class="form-control" id="taxpayer_type" name="taxpayer_type">
                        <option value="">Select taxpayer type</option>
                        @foreach (\App\Models\ClientProfile::TAXPAYER_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('taxpayer_type', $profile->taxpayer_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('taxpayer_type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="line_of_business">Line of business</label>
                    <select class="form-control" id="line_of_business" name="line_of_business" data-lob-select>
                        <option value="">Select line of business</option>
                        @foreach ($lobOptions as $lob)
                            <option value="{{ $lob }}" @selected($lobSelect === $lob)>{{ $lob }}</option>
                        @endforeach
                    </select>
                    @error('line_of_business')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-span-2" data-lob-other @if (! $lobIsOther) hidden @endif>
                    <label class="form-label" for="line_of_business_other">Other line of business</label>
                    <input class="form-control" id="line_of_business_other" name="line_of_business_other" type="text" value="{{ $lobOtherVal }}" placeholder="Describe your line of business">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bir_registration_type">BIR registration type</label>
                    <select class="form-control" id="bir_registration_type" name="bir_registration_type">
                        <option value="">Select registration type</option>
                        @foreach ($regTypes as $reg)
                            <option value="{{ $reg }}" @selected(old('bir_registration_type', $profile->bir_registration_type) === $reg)>{{ $reg }}</option>
                        @endforeach
                    </select>
                    @error('bir_registration_type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Address &amp; location</h2></div>
            <div class="form-grid two">
                <div class="form-group col-span-2">
                    <label class="form-label" for="business_address">Business address</label>
                    <input class="form-control" id="business_address" name="business_address" type="text" value="{{ old('business_address', $profile->business_address) }}" placeholder="e.g. 123 Rizal Avenue, Quezon City">
                    @error('business_address')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="latitude">Latitude</label>
                    <input class="form-control" id="latitude" name="latitude" type="text" value="{{ old('latitude', $profile->latitude) }}" placeholder="14.5995" inputmode="decimal">
                    @error('latitude')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="longitude">Longitude</label>
                    <input class="form-control" id="longitude" name="longitude" type="text" value="{{ old('longitude', $profile->longitude) }}" placeholder="120.9842" inputmode="decimal">
                    @error('longitude')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Contact information</h2></div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="contact_no">Contact number</label>
                    <input class="form-control" id="contact_no" name="contact_no" type="tel" value="{{ old('contact_no', $profile->contact_no) }}" placeholder="+63 917 000 0000">
                    @error('contact_no')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="second_contact_name">2nd contact name</label>
                    <input class="form-control" id="second_contact_name" name="second_contact_name" type="text" value="{{ old('second_contact_name', $profile->second_contact_name) }}" placeholder="Name of 2nd contact person">
                    @error('second_contact_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="second_contact_channel">2nd person contact channel</label>
                    <div class="form-inline-row">
                        <select class="form-control" id="second_contact_channel" name="second_contact_channel" data-second-channel>
                            @foreach (\App\Models\ClientProfile::SECOND_CONTACT_CHANNELS as $ch)
                                <option value="{{ $ch }}" {{ old('second_contact_channel', $profile->second_contact_channel ?? 'phone') === $ch ? 'selected' : '' }}>{{ ['phone' => 'Phone Number', 'viber' => 'Viber', 'facebook' => 'Facebook', 'telegram' => 'Telegram'][$ch] }}</option>
                            @endforeach
                        </select>
                        <input class="form-control" id="second_contact_no" name="second_contact_no" type="text" value="{{ old('second_contact_no', $profile->second_contact_no) }}" placeholder="0918 765 4321"
                            @if (old('second_contact_channel', $profile->second_contact_channel ?? 'phone') === 'phone')
                                pattern="(?:\+63|0)[\d\s\-()]{7,17}" title="PH mobile or landline, e.g. 0918 765 4321"
                            @endif>
                    </div>
                    @error('second_contact_channel')<div class="form-error">{{ $message }}</div>@enderror
                    @error('second_contact_no')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="second_email">2nd person email</label>
                    <input class="form-control" id="second_email" name="second_email" type="email" value="{{ old('second_email', $profile->second_email) }}" placeholder="name@example.com">
                    @error('second_email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="birth_date">Birth date</label>
                    <input class="form-control" id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $profile->birth_date?->format('Y-m-d')) }}">
                    @error('birth_date')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">BIR details</h2></div>            <div class="form-hint mb-2">Sensitive fields are masked. Click &ldquo;Reveal&rdquo; to view or edit them.</div>
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="tin_no">TIN number</label>
                    <div class="reveal-field">
                        <input class="form-control" id="tin_no" name="tin_no" type="text" value="{{ old('tin_no', $maskTin($profile->tin_no)) }}" data-full="{{ $profile->tin_no }}" data-masked="{{ $maskTin($profile->tin_no) }}" placeholder="123-456-789-000">
                        <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="tin_no">Reveal</button>
                    </div>
                    @error('tin_no')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="mother_maiden_name">Mother&rsquo;s maiden name</label>
                    <div class="reveal-field">
                        <input class="form-control" id="mother_maiden_name" name="mother_maiden_name" type="text" value="{{ old('mother_maiden_name', $maskName($profile->mother_maiden_name)) }}" data-full="{{ $profile->mother_maiden_name }}" data-masked="{{ $maskName($profile->mother_maiden_name) }}">
                        <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="mother_maiden_name">Reveal</button>
                    </div>
                    @error('mother_maiden_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="father_name">Father&rsquo;s name</label>
                    <div class="reveal-field">
                        <input class="form-control" id="father_name" name="father_name" type="text" value="{{ old('father_name', $maskName($profile->father_name)) }}" data-full="{{ $profile->father_name }}" data-masked="{{ $maskName($profile->father_name) }}">
                        <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="father_name">Reveal</button>
                    </div>
                    @error('father_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card" id="bir-forms-card">
            <div class="card-head"><h2 class="card-title">BIR Forms</h2></div>
            <div class="form-hint mb-2">Choose which BIR forms apply to this client. Billing line items, filing checklists, and the BIR forms matrix are generated from this list.</div>
            <div class="bir-form-grid">
                @foreach ($formTypes as $type)
                    <label class="bir-form-check {{ $applicableForms->contains($type) ? 'is-checked' : '' }}">
                        <input type="checkbox" name="bir_forms[]" value="{{ $type }}" @checked($applicableForms->contains($type))>
                        <span>{{ $type }}</span>
                    </label>
                @endforeach
            </div>
            @error('bir_forms.*')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Internal remarks</h2></div>
            <div class="form-grid two">
                <div class="form-group col-span-2">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="1000" placeholder="Internal notes about this client.">{{ old('remarks', $profile->remarks) }}</textarea>
                    @error('remarks')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="btn-group-row">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            var revealBtns = document.querySelectorAll('.reveal-btn');
            for (var r = 0; r < revealBtns.length; r++) {
                (function (btn) {
                    var input = document.getElementById(btn.getAttribute('data-target'));
                    var showing = false;
                    btn.addEventListener('click', function () {
                        if (!input) return;
                        if (!showing) {
                            input.value = input.getAttribute('data-full') || input.value;
                            btn.textContent = 'Hide';
                        } else {
                            input.value = input.getAttribute('data-masked') || input.value;
                            btn.textContent = 'Reveal';
                        }
                        showing = !showing;
                    });
                })(revealBtns[r]);
            }

            var form = document.getElementById('clientEditForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    var masked = form.querySelectorAll('[data-masked][data-full]');
                    for (var i = 0; i < masked.length; i++) {
                        var el = masked[i];
                        if (el.value === el.getAttribute('data-masked')) {
                            el.value = el.getAttribute('data-full') || el.value;
                        }
                    }
                }, true);
            }

            var lobSelect = document.querySelector('[data-lob-select]');
            var lobOther = document.querySelector('[data-lob-other]');
            if (lobSelect && lobOther) {
                var lobOtherInput = lobOther.querySelector('input');
                function syncLobOther() {
                    lobOther.hidden = lobSelect.value !== 'Other';
                    if (lobSelect.value === 'Other' && lobOtherInput && lobOtherInput.value === '') lobOtherInput.focus();
                }
                lobSelect.addEventListener('change', syncLobOther);
                syncLobOther();
            }

            var birFormsCard = document.getElementById('bir-forms-card');
            if (birFormsCard) {
                birFormsCard.addEventListener('change', function (e) {
                    var label = e.target.closest('.bir-form-check');
                    if (label) label.classList.toggle('is-checked', e.target.checked);
                });
            }
        })();
    </script>
@endpush
