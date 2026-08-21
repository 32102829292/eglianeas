@extends('layouts.dashboard')

@section('title', 'Fill Up Form — Other Services — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.other-services.billing') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Other Services
    </a>

    <div class="page-head">
        <h1>Fill Up Form</h1>
        <p>Record a one-off service request for a client.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">New Service Request</h2>
        </div>

        <form method="POST" action="{{ route('admin.other-services.store') }}" id="serviceForm">
            @csrf

            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="client_search">Client</label>
                    <div class="autocomplete-wrap" id="client-autocomplete">
                        <input class="form-control" id="client_search" type="text" placeholder="Search by name, business, or email&hellip;" autocomplete="off" required>
                        <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                        <div class="autocomplete-dropdown" id="client-dropdown"></div>
                    </div>
                    @error('client_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="service_type_id">Other Service</label>
                    <div class="autocomplete-wrap" id="service-autocomplete">
                        <input class="form-control" id="service_search" type="text" placeholder="Search service type&hellip;" autocomplete="off">
                        <input type="hidden" name="service_type_id" id="service_type_id" value="{{ old('service_type_id') }}">
                        <div class="autocomplete-dropdown" id="service-dropdown"></div>
                    </div>
                    @error('service_type_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group" id="customLabelGroup" style="{{ old('service_type_id') ? 'display:none' : '' }}">
                    <label class="form-label" for="custom_label">Custom label (if &ldquo;Other&rdquo; or no type selected)</label>
                    <input class="form-control" id="custom_label" name="custom_label" type="text" maxlength="120" value="{{ old('custom_label') }}" placeholder="e.g. Special Filing">
                    @error('custom_label')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="amount">Amount</label>
                    <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" required placeholder="0.00">
                    @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="requested_at">Date requested</label>
                    <input class="form-control" id="requested_at" name="requested_at" type="date" value="{{ old('requested_at', now()->format('Y-m-d')) }}">
                    @error('requested_at')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="due_date">Due date (optional)</label>
                    <input class="form-control" id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
                    @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Client info reference --}}
            <div id="clientInfo" class="client-info-box" style="display:none">
                <div class="client-info-grid">
                    <div><span class="muted">Client ID:</span> <b id="info-client-code"></b></div>
                    <div><span class="muted">Business:</span> <b id="info-business-name"></b></div>
                    <div><span class="muted">Contact:</span> <b id="info-name"></b></div>
                    <div><span class="muted">Email:</span> <b id="info-email"></b></div>
                </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label" for="notes">Notes / Remarks</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Optional notes about this service request">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Submit</button>
                <a href="{{ route('admin.other-services.billing') }}" class="btn btn-outline">Cancel</a>
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
    var serviceSearch = document.getElementById('service_search');
    var serviceTypeIdInput = document.getElementById('service_type_id');
    var serviceDropdown = document.getElementById('service-dropdown');
    var customLabelGroup = document.getElementById('customLabelGroup');

    // --- Client autocomplete ---
    var clientTimer = null;
    clientSearch.addEventListener('input', function () {
        clearTimeout(clientTimer);
        var q = this.value.trim();
        if (q.length < 1) { clientDropdown.style.display = 'none'; return; }
        clientTimer = setTimeout(function () {
            fetch('{{ route("admin.other-services.clientsJson") }}?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    clientDropdown.innerHTML = '';
                    if (!data.length) { clientDropdown.style.display = 'none'; return; }
                    data.forEach(function (c) {
                        var div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.textContent = (c.business_name || c.name) + ' — ' + c.name + ' (' + c.email + ')';
                        div.addEventListener('click', function () {
                            clientSearch.value = (c.business_name || c.name);
                            clientIdInput.value = c.id;
                            clientDropdown.style.display = 'none';
                            document.getElementById('info-client-code').textContent = c.client_code || '—';
                            document.getElementById('info-business-name').textContent = c.business_name || '—';
                            document.getElementById('info-name').textContent = c.name;
                            document.getElementById('info-email').textContent = c.email;
                            clientInfo.style.display = 'block';
                        });
                        clientDropdown.appendChild(div);
                    });
                    clientDropdown.style.display = 'block';
                });
        }, 200);
    });

    // --- Service type autocomplete ---
    serviceSearch.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        serviceDropdown.innerHTML = '';
        var matches = serviceTypes.filter(function (st) { return st.label.toLowerCase().indexOf(q) !== -1; });
        if (!matches.length && q.length > 0) {
            var other = document.createElement('div');
            other.className = 'autocomplete-item';
            other.textContent = 'Other (type custom label below)';
            other.addEventListener('click', function () {
                serviceSearch.value = 'Other';
                serviceTypeIdInput.value = '';
                serviceDropdown.style.display = 'none';
                customLabelGroup.style.display = '';
                document.getElementById('custom_label').focus();
            });
            serviceDropdown.appendChild(other);
        } else {
            matches.forEach(function (st) {
                var div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.textContent = st.label;
                div.addEventListener('click', function () {
                    serviceSearch.value = st.label;
                    serviceTypeIdInput.value = st.id;
                    serviceDropdown.style.display = 'none';
                    customLabelGroup.style.display = 'none';
                });
                serviceDropdown.appendChild(div);
            });
        }
        serviceDropdown.style.display = matches.length || q.length > 0 ? 'block' : 'none';
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#client-autocomplete')) clientDropdown.style.display = 'none';
        if (!e.target.closest('#service-autocomplete')) serviceDropdown.style.display = 'none';
    });
})();
</script>
@endpush
