@extends('layouts.dashboard')

@section('title', 'Fill Up Form — Other Services — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.other-services.billing') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Other Services
    </a>

    <div class="page-head fill-form-page-head">
        <span class="fill-form-eyebrow">Other Services</span>
        <h1>Fill Up Form</h1>
        <p>Record a one-off service request for a client. It will appear in the Service Tracker.</p>
    </div>

    <div class="card fill-form-card">
        <form method="POST" action="{{ route('admin.other-services.store') }}" id="serviceForm" data-submit-label="Creating…">
            @csrf

            {{-- CLIENT & SERVICE --}}
            <div class="form-section fill-form-section">
                <div class="fill-form-section-head">
                    <span class="fill-form-section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>
                    </span>
                    <div>
                        <h3 class="form-section-title">Client &amp; Service</h3>
                        <p class="fill-form-section-hint">Select the client and the service this request covers.</p>
                    </div>
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="client_search">Client</label>
                        <div class="autocomplete-wrap fill-autocomplete" id="client-autocomplete">
                            <svg class="fill-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                            <input class="form-control fill-search-input" id="client_search" type="text" placeholder="Search client by name, business, or email&hellip;" autocomplete="off" required role="combobox" aria-expanded="false" aria-controls="client-dropdown" aria-autocomplete="list">
                            <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                            <div class="autocomplete-dropdown" id="client-dropdown" role="listbox" aria-label="Matching clients"></div>
                        </div>
                        @error('client_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="service_search">Other Service</label>
                        <div class="autocomplete-wrap fill-autocomplete" id="service-autocomplete">
                            <svg class="fill-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                            <input class="form-control fill-search-input" id="service_search" type="text" placeholder="Search service type&hellip;" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="service-dropdown" aria-autocomplete="list">
                            <input type="hidden" name="service_type_id" id="service_type_id" value="{{ old('service_type_id') }}">
                            <div class="autocomplete-dropdown" id="service-dropdown" role="listbox" aria-label="Matching service types"></div>
                        </div>
                        @error('service_type_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group" id="customLabelGroup" style="{{ old('service_type_id') ? 'display:none' : '' }}">
                        <label class="form-label" for="custom_label">Custom label</label>
                        <input class="form-control" id="custom_label" name="custom_label" type="text" maxlength="120" value="{{ old('custom_label') }}" placeholder="e.g. Special Filing">
                        <p class="form-hint">Enter a label when &ldquo;Other&rdquo; or no service type is selected.</p>
                        @error('custom_label')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount</label>
                        <div class="currency-field">
                            <span class="currency-prefix" aria-hidden="true">₱</span>
                            <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0" inputmode="decimal" value="{{ old('amount') }}" required placeholder="0.00">
                        </div>
                        <p class="form-hint">Amount in Philippine pesos.</p>
                        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="clientInfo" class="client-info-box fill-selected-client" style="display:none; margin-top:16px;">
                    <div class="client-info-grid">
                        <div><span class="muted">Client ID:</span> <b id="info-client-code"></b></div>
                        <div><span class="muted">Business:</span> <b id="info-business-name"></b></div>
                        <div><span class="muted">Contact:</span> <b id="info-name"></b></div>
                        <div><span class="muted">Email:</span> <b id="info-email"></b></div>
                    </div>
                </div>
            </div>

            {{-- SCHEDULE --}}
            <div class="form-section fill-form-section">
                <div class="fill-form-section-head">
                    <span class="fill-form-section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                    <div>
                        <h3 class="form-section-title">Schedule</h3>
                        <p class="fill-form-section-hint">When the service was requested and when it is due.</p>
                    </div>
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="requested_at">Date requested</label>
                        <input class="form-control" id="requested_at" name="requested_at" type="date" value="{{ old('requested_at', now()->format('Y-m-d')) }}">
                        <p class="form-hint">When the service was requested</p>
                        @error('requested_at')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="due_date">Due date</label>
                        <input class="form-control" id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
                        <p class="form-hint">Optional</p>
                        @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- ASSIGNED STAFF --}}
            <div class="form-section fill-form-section">
                <div class="fill-form-section-head">
                    <span class="fill-form-section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <div>
                        <h3 class="form-section-title">Assigned Staff <span id="staffCount" class="staff-count"></span></h3>
                        <p class="fill-form-section-hint">Choose who processes this service request.</p>
                    </div>
                </div>

                <div class="staff-assign-panel">
                    <p class="form-hint mb-0">Assign one or more staff members to process this service. The service will appear in the Service Tracker with these assignments.</p>
                    <label class="form-label" for="staff_search">Search staff</label>
                    <div class="autocomplete-wrap fill-autocomplete" id="staff-autocomplete">
                        <svg class="fill-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input class="form-control fill-search-input" id="staff_search" type="text" placeholder="Search staff to assign&hellip;" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="staff-dropdown" aria-autocomplete="list">
                        <div class="autocomplete-dropdown" id="staff-dropdown" role="listbox" aria-label="Matching staff"></div>
                    </div>
                    <div id="staffTags" class="staff-tags"></div>
                    <div id="staffHiddenInputs" style="display:none;"></div>
                    @error('staff_ids')<div class="form-error">{{ $message }}</div>@enderror
                    @error('staff_ids.*')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- NOTES --}}
            <div class="form-section fill-form-section">
                <div class="fill-form-section-head">
                    <span class="fill-form-section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </span>
                    <div>
                        <h3 class="form-section-title">Notes</h3>
                        <p class="fill-form-section-hint">Optional remarks for the Service Tracker.</p>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="notes">Notes / Remarks</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Optional notes about this service request">{{ old('notes') }}</textarea>
                    @error('notes')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-actions fill-form-actions">
                <a href="{{ route('admin.other-services.billing') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Service Request</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var serviceTypes = @json($serviceTypes->map(fn($st) => ['id' => $st->id, 'label' => $st->label])->values());
    var clientsJson = @json($clients);
    var clientSearch = document.getElementById('client_search');
    var clientIdInput = document.getElementById('client_id');
    var clientDropdown = document.getElementById('client-dropdown');
    var clientInfo = document.getElementById('clientInfo');
    var clientAutocomplete = document.getElementById('client-autocomplete');
    var serviceSearch = document.getElementById('service_search');
    var serviceTypeIdInput = document.getElementById('service_type_id');
    var serviceDropdown = document.getElementById('service-dropdown');
    var customLabelGroup = document.getElementById('customLabelGroup');

    function syncExpanded(input, dd) {
        input.setAttribute('aria-expanded', dd.style.display === 'block' ? 'true' : 'false');
    }

    // --- Client autocomplete ---
    var clientTimer = null;
    clientSearch.addEventListener('input', function () {
        clearTimeout(clientTimer);
        var q = this.value.trim();
        clientSearch.classList.remove('is-selected');
        clientAutocomplete.classList.remove('is-selected');
        if (q.length < 1) { clientDropdown.style.display = 'none'; syncExpanded(clientSearch, clientDropdown); return; }
        clientTimer = setTimeout(function () {
            fetch('{{ route("admin.other-services.clientsJson") }}?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    clientDropdown.innerHTML = '';
                    if (!data.length) { clientDropdown.style.display = 'none'; syncExpanded(clientSearch, clientDropdown); return; }
                    data.forEach(function (c) {
                        var div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.setAttribute('role', 'option');
                        div.setAttribute('aria-selected', 'false');
                        div.textContent = (c.business_name || c.name) + ' — ' + c.name + ' (' + c.email + ')';
                        div.addEventListener('click', function () {
                            clientSearch.value = (c.business_name || c.name);
                            clientIdInput.value = c.id;
                            clientSearch.classList.add('is-selected');
                            clientAutocomplete.classList.add('is-selected');
                            clientDropdown.style.display = 'none';
                            syncExpanded(clientSearch, clientDropdown);
                            document.getElementById('info-client-code').textContent = c.client_code || '—';
                            document.getElementById('info-business-name').textContent = c.business_name || '—';
                            document.getElementById('info-name').textContent = c.name;
                            document.getElementById('info-email').textContent = c.email;
                            clientInfo.style.display = 'block';
                        });
                        clientDropdown.appendChild(div);
                    });
                    clientDropdown.style.display = 'block';
                    syncExpanded(clientSearch, clientDropdown);
                });
        }, 200);
    });
    clientSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { clientDropdown.style.display = 'none'; syncExpanded(clientSearch, clientDropdown); }
    });

    // --- Service type autocomplete ---
    serviceSearch.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        serviceDropdown.innerHTML = '';
        var matches = serviceTypes.filter(function (st) { return st.label.toLowerCase().indexOf(q) !== -1; });
        if (!matches.length && q.length > 0) {
            var other = document.createElement('div');
            other.className = 'autocomplete-item';
            other.setAttribute('role', 'option');
            other.setAttribute('aria-selected', 'false');
            other.textContent = 'Other (type custom label below)';
            other.addEventListener('click', function () {
                serviceSearch.value = 'Other';
                serviceTypeIdInput.value = '';
                serviceDropdown.style.display = 'none';
                syncExpanded(serviceSearch, serviceDropdown);
                customLabelGroup.style.display = '';
                highlightCustomLabel();
                document.getElementById('custom_label').focus();
            });
            serviceDropdown.appendChild(other);
        } else {
            matches.forEach(function (st) {
                var div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.setAttribute('role', 'option');
                div.setAttribute('aria-selected', 'false');
                div.textContent = st.label;
                div.addEventListener('click', function () {
                    serviceSearch.value = st.label;
                    serviceTypeIdInput.value = st.id;
                    serviceDropdown.style.display = 'none';
                    syncExpanded(serviceSearch, serviceDropdown);
                    customLabelGroup.style.display = 'none';
                });
                serviceDropdown.appendChild(div);
            });
        }
        serviceDropdown.style.display = matches.length || q.length > 0 ? 'block' : 'none';
        syncExpanded(serviceSearch, serviceDropdown);
    });
    serviceSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { serviceDropdown.style.display = 'none'; syncExpanded(serviceSearch, serviceDropdown); }
    });

    function highlightCustomLabel() {
        customLabelGroup.classList.remove('is-relevant');
        void customLabelGroup.offsetWidth;
        customLabelGroup.classList.add('is-relevant');
        setTimeout(function () { customLabelGroup.classList.remove('is-relevant'); }, 1400);
    }

    // --- Assigned Staff: compact tag multi-select (bound to staff user IDs) ---
    var staffOptions = @json($staffRoster->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values());
    var staffSearch = document.getElementById('staff_search');
    var staffDropdown = document.getElementById('staff-dropdown');
    var staffTags = document.getElementById('staffTags');
    var staffHidden = document.getElementById('staffHiddenInputs');
    var selectedStaff = {};

    function renderStaffTags() {
        staffTags.innerHTML = '';
        Object.keys(selectedStaff).forEach(function (id) {
            var item = selectedStaff[id];
            var tag = document.createElement('span');
            tag.className = 'staff-tag';
            tag.setAttribute('data-id', id);
            tag.title = 'Remove ' + item.name;
            var label = document.createElement('span');
            label.className = 'staff-tag-label';
            label.textContent = item.name;
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'staff-tag-remove';
            x.textContent = '×';
            x.setAttribute('aria-label', 'Remove ' + item.name);
            tag.appendChild(label);
            tag.appendChild(x);
            x.addEventListener('click', function (e) {
                e.stopPropagation();
                removeStaff(id);
            });
            staffTags.appendChild(tag);
        });
        var countEl = document.getElementById('staffCount');
        if (countEl) {
            var n = Object.keys(selectedStaff).length;
            countEl.textContent = n + ' staff assigned';
        }
    }

    function addStaff(id, name) {
        var key = String(id);
        if (!key || selectedStaff[key]) { staffDropdown.style.display = 'none'; return; }
        selectedStaff[key] = { name: name };
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'staff_ids[]';
        hidden.value = key;
        hidden.setAttribute('data-staff-hidden', key);
        staffHidden.appendChild(hidden);
        renderStaffTags();
        staffSearch.value = '';
        staffDropdown.style.display = 'none';
        syncExpanded(staffSearch, staffDropdown);
    }

    function removeStaff(id) {
        var key = String(id);
        var hidden = staffHidden.querySelector('input[data-staff-hidden="' + key + '"]');
        if (hidden) hidden.remove();
        delete selectedStaff[key];
        renderStaffTags();
    }

    function renderStaffDropdown() {
        var q = staffSearch.value.trim().toLowerCase();
        staffDropdown.innerHTML = '';
        staffOptions.forEach(function (o) {
            if (selectedStaff[o.id]) return;
            if (q && o.name.toLowerCase().indexOf(q) === -1) return;
            var div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.setAttribute('role', 'option');
            div.setAttribute('aria-selected', 'false');
            div.textContent = o.name;
            div.addEventListener('click', function () { addStaff(o.id, o.name); });
            staffDropdown.appendChild(div);
        });
        staffDropdown.style.display = staffDropdown.children.length ? 'block' : 'none';
        syncExpanded(staffSearch, staffDropdown);
    }

    staffSearch.addEventListener('focus', renderStaffDropdown);
    staffSearch.addEventListener('input', renderStaffDropdown);
    staffSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { staffDropdown.style.display = 'none'; syncExpanded(staffSearch, staffDropdown); }
    });

    (function restoreStaff() {
        var prevIds = @json(old('staff_ids') ? array_values(old('staff_ids')) : []);
        prevIds.forEach(function (id) {
            var o = staffOptions.find(function (s) { return String(s.id) === String(id); });
            if (o) addStaff(o.id, o.name);
        });
        renderStaffTags();
    })();

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#client-autocomplete')) { clientDropdown.style.display = 'none'; syncExpanded(clientSearch, clientDropdown); }
        if (!e.target.closest('#service-autocomplete')) { serviceDropdown.style.display = 'none'; syncExpanded(serviceSearch, serviceDropdown); }
        if (!e.target.closest('#staff-autocomplete')) { staffDropdown.style.display = 'none'; syncExpanded(staffSearch, staffDropdown); }
    });
})();
</script>
@endpush