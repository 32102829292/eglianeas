@extends('layouts.dashboard')

@section('title', 'New Service Instance — Service Tracker — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.service-tracker.index') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Service Tracker
    </a>

    <div class="page-head">
        <h1>New Service Instance</h1>
        <p>Track a service for a specific client.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Create instance</h2>
        </div>

        <form method="POST" action="{{ route('admin.service-tracker.store') }}" id="trackerForm">
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
                    <label class="form-label" for="service_search">Service</label>
                    <div class="autocomplete-wrap" id="service-autocomplete">
                        <input class="form-control" id="service_search" type="text" placeholder="Search service&hellip;" autocomplete="off" required>
                        <input type="hidden" name="service_id" id="service_id" value="{{ old('service_id') }}">
                        <div class="autocomplete-dropdown" id="service-dropdown"></div>
                    </div>
                    @error('service_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="primary_responsible">Primary responsible</label>
                    <input class="form-control" id="primary_responsible" name="primary_responsible" type="text" maxlength="120" value="{{ old('primary_responsible') }}" placeholder="Staff member name">
                    @error('primary_responsible')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_identified">Date identified</label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date" value="{{ old('date_identified', now()->format('Y-m-d')) }}">
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_started">Date started</label>
                    <input class="form-control" id="date_started" name="date_started" type="date" value="{{ old('date_started') }}">
                    @error('date_started')<div class="form-error">{{ $message }}</div>@enderror
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

            <div class="section-divider"></div>

            <div class="form-group">
                <label class="form-label">Assigned Staff</label>
                <p class="form-hint" style="margin-top:0;">Add staff members responsible. Each can be marked done independently.</p>
                <div id="staffList" class="chip-list" style="margin-bottom:8px;"></div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input class="form-control" id="staffInput" type="text" maxlength="120" placeholder="Staff name" style="flex:1;min-width:0;">
                    <button type="button" class="btn btn-outline btn-sm" id="addStaffBtn">Add</button>
                </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Optional notes">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Create instance</button>
                <a href="{{ route('admin.service-tracker.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var services = @json($services->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values());
    var clientSearch = document.getElementById('client_search');
    var clientIdInput = document.getElementById('client_id');
    var clientDropdown = document.getElementById('client-dropdown');
    var clientInfo = document.getElementById('clientInfo');
    var serviceSearch = document.getElementById('service_search');
    var serviceIdInput = document.getElementById('service_id');
    var serviceDropdown = document.getElementById('service-dropdown');
    var staffInput = document.getElementById('staffInput');
    var addStaffBtn = document.getElementById('addStaffBtn');
    var staffList = document.getElementById('staffList');
    var staffIndex = 0;

    function addStaffChip(name) {
        if (!name.trim()) return;
        var chip = document.createElement('div');
        chip.className = 'chip-row';
        chip.innerHTML = '<span class="chip-label">' + name.trim() + '</span>' +
            '<input type="hidden" name="staff_names[' + staffIndex + ']" value="' + name.trim() + '">' +
            '<button type="button" class="link danger" onclick="this.parentElement.remove()">&times;</button>';
        staffList.appendChild(chip);
        staffIndex++;
    }

    addStaffBtn.addEventListener('click', function () {
        addStaffChip(staffInput.value);
        staffInput.value = '';
        staffInput.focus();
    });
    staffInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addStaffBtn.click(); }
    });

    // Client autocomplete
    var clientTimer = null;
    clientSearch.addEventListener('input', function () {
        clearTimeout(clientTimer);
        var q = this.value.trim();
        if (q.length < 1) { clientDropdown.style.display = 'none'; return; }
        clientTimer = setTimeout(function () {
            fetch('{{ route("admin.service-tracker.clientsJson") }}?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
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

    // Service autocomplete
    serviceSearch.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        serviceDropdown.innerHTML = '';
        var matches = services.filter(function (s) { return s.name.toLowerCase().indexOf(q) !== -1; });
        matches.forEach(function (s) {
            var div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.textContent = s.name;
            div.addEventListener('click', function () {
                serviceSearch.value = s.name;
                serviceIdInput.value = s.id;
                serviceDropdown.style.display = 'none';
            });
            serviceDropdown.appendChild(div);
        });
        serviceDropdown.style.display = matches.length ? 'block' : 'none';
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#client-autocomplete')) clientDropdown.style.display = 'none';
        if (!e.target.closest('#service-autocomplete')) serviceDropdown.style.display = 'none';
    });
})();
</script>
@endpush
