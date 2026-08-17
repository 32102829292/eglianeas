@extends('layouts.dashboard')

@section('title', ($client->business_name ?: $client->name).' — Distribution — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.distribution.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Distribution
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1>{{ $client->business_name ?: $client->name }}</h1>
            <p>{{ $client->name }} &middot; {{ $client->email }} @if ($client->client_code) &middot; <span class="code-pill">{{ $client->client_code }}</span> @endif</p>
        </div>
        <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline">Client record</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    {{-- Client Location Map --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Business address &amp; location</h2>
        </div>
        @if ($profile?->latitude !== null && $profile?->longitude !== null)
            <div class="profile-grid">
                <div class="profile-row col-span-2"><span class="profile-k">Address</span><span class="profile-v">{{ $profile->business_address ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Latitude</span><span class="profile-v">{{ $profile->latitude }}</span></div>
                <div class="profile-row"><span class="profile-k">Longitude</span><span class="profile-v">{{ $profile->longitude }}</span></div>
                <div class="profile-row col-span-2"><span class="profile-k">Map</span><div class="map-preview" id="clientMap" data-map-view></div></div>
            </div>
        @else
            <p class="card-sub">No location pinned yet.</p>
            <div class="form-hint mb-2">Ask the client to pin their business address in their Profile, or set it manually below.</div>
            <form method="POST" action="{{ route('admin.distribution.update-location', $client) }}" id="locationForm">
                @csrf
                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="bizAddress">Business address</label>
                        <input class="form-control" id="bizAddress" type="text" value="{{ $profile->business_address ?? '' }}" placeholder="e.g. 123 Rizal Avenue, Quezon City">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Locate on map</label>
                        <button type="button" class="btn btn-outline btn-sm" id="distLocateBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            Locate address
                        </button>
                        <div class="form-hint geo-status" id="distGeoStatus" hidden></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="distLat">Latitude</label>
                        <input class="form-control" id="distLat" name="latitude" type="text" placeholder="14.5995" inputmode="decimal">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="distLng">Longitude</label>
                        <input class="form-control" id="distLng" name="longitude" type="text" placeholder="120.9842" inputmode="decimal">
                    </div>
                    <div class="form-group col-span-2">
                        <div class="map-preview" id="distMap" hidden></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save location</button>
            </form>
        @endif
    </div>

    {{-- BIR Form Checklist --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">BIR Form Checklist</h2>
            @php($filedCount = collect($birStatuses)->filter(fn ($s) => $s === 'filed')->count())
            <span class="badge badge-{{ $filedCount > 0 ? 'current' : 'neutral' }}">{{ $filedCount }}/{{ count($formTypes) }} filed</span>
        </div>
        <p class="card-sub">Track which BIR forms have been filed for this client. Click a status to update.</p>
        <div class="bir-checklist">
            @foreach ($formTypes as $ft)
                @php($current = $birStatuses[$ft] ?? 'not_filed')
                <div class="bir-checklist-item">
                    <span class="bir-checklist-type">{{ $ft }}</span>
                    <div class="bir-checklist-actions">
                        @foreach ($statuses as $statusVal => $statusLabel)
                            <form method="POST" action="{{ route('admin.distribution.bir-status', $client) }}" class="inline-form">
                                @csrf
                                <input type="hidden" name="form_type" value="{{ $ft }}">
                                <input type="hidden" name="status" value="{{ $statusVal }}">
                                <button type="submit" class="btn btn-sm {{ $current === $statusVal ? 'btn-'.($statusVal === 'filed' ? 'primary' : ($statusVal === 'not_applicable' ? 'outline' : 'outline')) : 'btn-outline' }} bir-status-btn bir-{{ $statusVal }}">{{ $statusLabel }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Delivery Log --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Delivery Log</h2>
            <span class="badge badge-neutral">{{ $deliveries->count() }} entries</span>
        </div>
        <p class="card-sub">Record how documents were delivered to the client (face-to-face or online).</p>

        <form method="POST" action="{{ route('admin.distribution.store-delivery', $client) }}" class="delivery-form">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="delFormType">Form type</label>
                    <select class="form-control" id="delFormType" name="form_type" required>
                        @foreach ($formTypes as $ft)
                            <option value="{{ $ft }}">{{ $ft }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="delMethod">Delivery method</label>
                    <select class="form-control" id="delMethod" name="delivery_method" required>
                        @foreach ($methods as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="delDate">Date received <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delDate" name="date_received" type="date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="delTime">Time received <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delTime" name="time_received" type="time">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label" for="delRemarks">Remarks <span class="opt-tag">(optional)</span></label>
                    <input class="form-control" id="delRemarks" name="remarks" type="text" maxlength="500" placeholder="e.g. Handed to the client at their office">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="no_file_flag" value="1"> No file (just in case)
                    </label>
                    <div class="form-hint">Check this if there&rsquo;s no digital record of the document.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Log delivery</button>
        </form>

        @if ($deliveries->isNotEmpty())
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Form Type</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Remarks</th>
                            <th>No File</th>
                            <th class="actions-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $del)
                            <tr>
                                <td><span class="code-pill">{{ $del->form_type }}</span></td>
                                <td>{{ $del->methodLabel() }}</td>
                                <td>{{ $del->date_received?->format('M j, Y') ?? '—' }}</td>
                                <td>{{ $del->timeLabel() ?? '—' }}</td>
                                <td>{{ $del->remarks ?: '—' }}</td>
                                <td>{{ $del->no_file_flag ? 'Yes' : '—' }}</td>
                                <td class="actions-cell">
                                    <form method="POST" action="{{ route('admin.distribution.destroy-delivery', [$client, $del]) }}" class="inline-form" onsubmit="return confirm('Remove this delivery entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="form-hint mt-2">No deliveries logged yet.</div>
        @endif
    </div>

    {{-- Softcopy Upload --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Softcopy files</h2>
        </div>
        <p class="card-sub">Upload digital copies of documents per form type. The client can view and download these from their Distribution page.</p>
        <form method="POST" action="{{ route('admin.distribution.store-softcopy', $client) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="scFormType">Form type</label>
                    <select class="form-control" id="scFormType" name="form_type" required>
                        @foreach ($formTypes as $ft)
                            <option value="{{ $ft }}">{{ $ft }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scFile">File</label>
                    <input class="form-control" id="scFile" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-hint">PDF, JPG, or PNG. Max 20 MB.</div>
                    @error('file')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Upload softcopy</button>
        </form>

        @if ($softcopies->isNotEmpty())
            @foreach ($softcopies as $formType => $files)
                <div class="softcopy-group">
                    <h3 class="softcopy-group-title"><span class="code-pill">{{ $formType }}</span></h3>
                    @foreach ($files as $doc)
                        <div class="softcopy-row">
                            <div>
                                <div class="cell-name">{{ $doc->original_name }}</div>
                                <small class="muted">{{ $doc->sizeLabel() }} &middot; {{ $doc->created_at?->format('M j, Y') }}</small>
                            </div>
                            <div class="btn-row">
                                <a href="{{ route('admin.distribution.download', $doc) }}" class="btn btn-outline btn-sm">Download</a>
                                <form method="POST" action="{{ route('admin.distribution.destroy-softcopy', [$client, $doc]) }}" class="inline-form" onsubmit="return confirm('Delete this softcopy?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="form-hint mt-2">No softcopies uploaded yet.</div>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="/vendor/leaflet/leaflet.css">
@endpush

@push('scripts')
    <script src="/vendor/leaflet/leaflet.js" defer></script>
    <script>
        (function () {
            'use strict';

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfToken ? csrfToken.getAttribute('content') : '';

            /* ---------- Read-only client map ---------- */
            var mapViewEl = document.getElementById('clientMap');
            if (mapViewEl) {
                var vLat = {{ $profile->latitude ? (float) $profile->latitude : 'null' }};
                var vLng = {{ $profile->longitude ? (float) $profile->longitude : 'null' }};
                if (vLat !== null && vLng !== null && typeof window.L === 'object') {
                    var viewMap = L.map(mapViewEl, {
                        scrollWheelZoom: false, dragging: false, touchZoom: false,
                        doubleClickZoom: false, boxZoom: false, keyboard: false
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(viewMap);
                    L.marker([vLat, vLng]).addTo(viewMap);
                    viewMap.setView([vLat, vLng], 16);
                }
            }

            /* ---------- Location form map ---------- */
            var distMapEl = document.getElementById('distMap');
            var distLat = document.getElementById('distLat');
            var distLng = document.getElementById('distLng');
            var distGeoStatus = document.getElementById('distGeoStatus');
            var distLocateBtn = document.getElementById('distLocateBtn');
            var distAddress = document.getElementById('bizAddress');
            var locMap = null, locMarker = null;

            function distShowGeo(msg, isError) {
                if (!distGeoStatus) return;
                distGeoStatus.className = isError ? 'form-hint geo-status geo-error' : 'form-hint geo-status';
                distGeoStatus.textContent = msg;
                distGeoStatus.hidden = false;
            }
            function distSetPin(lat, lng) {
                if (distLat) distLat.value = lat.toFixed(6);
                if (distLng) distLng.value = lng.toFixed(6);
                if (distMapEl) distMapEl.hidden = false;
                if (typeof window.L === 'object') {
                    if (!locMap) {
                        locMap = L.map(distMapEl, { scrollWheelZoom: false });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        }).addTo(locMap);
                        locMap.on('click', function (e) { distSetPin(e.latlng.lat, e.latlng.lng); });
                    }
                    if (locMarker) locMarker.setLatLng([lat, lng]);
                    else locMarker = L.marker([lat, lng], { draggable: true }).addTo(locMap);
                    locMap.setView([lat, lng], 16);
                }
            }

            if (distLocateBtn) {
                distLocateBtn.addEventListener('click', function () {
                    var q = (distAddress ? distAddress.value : '').trim();
                    if (!q) { distShowGeo('Enter an address first.', true); return; }
                    distShowGeo('Locating…', false);
                    fetch('{{ route("admin.distribution.geocode") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ q: q }),
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        var d = res.data || {};
                        if (typeof d.lat === 'number' && typeof d.lng === 'number') {
                            distSetPin(d.lat, d.lng);
                            distShowGeo(d.display_name ? ('Located: ' + d.display_name.slice(0, 90)) : 'Located.', false);
                        } else {
                            distShowGeo(d.error || 'Address not found.', true);
                        }
                    })
                    .catch(function () { distShowGeo('Request failed.', true); });
                });
            }
        })();
    </script>
@endpush
