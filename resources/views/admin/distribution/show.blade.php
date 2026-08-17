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
                <div class="profile-row col-span-2" id="coordToggleRow">
                    <span class="profile-k">Coordinates</span>
                    <span class="profile-v">
                        <button type="button" class="btn btn-outline btn-sm" id="coordToggleBtn" onclick="var el=document.getElementById('coordValues'); el.hidden=!el.hidden; this.textContent=el.hidden?'Show':'Hide';">Show</button>
                        <span id="coordValues" hidden>{{ $profile->latitude }}, {{ $profile->longitude }}</span>
                    </span>
                </div>
                <div class="profile-row col-span-2"><span class="profile-k">Map</span><x-address-map :latitude="$profile->latitude" :longitude="$profile->longitude" id="clientMap" /></div>
                <div class="profile-row col-span-2" id="trackRow">
                    <span class="profile-k">Distance tracker</span>
                    <span class="profile-v">
                        <button type="button" class="btn btn-primary btn-sm" id="trackStartBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/></svg>
                            Track distance to this client
                        </button>
                        <div id="trackPanel" hidden>
                            <div class="track-readout">
                                <span class="track-arrow" id="trackArrow" title="Direction to client">&#9650;</span>
                                <span class="track-distance" id="trackDistance">—</span>
                                <span class="track-direction" id="trackDirection"></span>
                            </div>
                            <div class="track-actions">
                                <a id="trackGmaps" href="#" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Get directions</a>
                                <button type="button" class="btn btn-outline btn-sm" id="trackStopBtn">Stop tracking</button>
                            </div>
                        </div>
                        <div class="track-error" id="trackError" hidden></div>
                    </span>
                </div>
            </div>
        @else
            <p class="card-sub">No location pinned yet.</p>
            <div class="form-hint mb-2">Ask the client to pin their business address in their Profile, or set it manually below.</div>
            <form method="POST" action="{{ route('admin.distribution.update-location', $client) }}" id="locationForm">
                @csrf
                <input type="hidden" name="business_address" value="{{ $profile->business_address ?? '' }}">
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
                    <input type="hidden" id="distLat" name="latitude">
                    <input type="hidden" id="distLng" name="longitude">
                    <div class="form-group col-span-2">
                        <button type="button" class="btn btn-outline btn-sm" onclick="var el=document.getElementById('distCoordValues'); el.hidden=!el.hidden; this.textContent=el.hidden?'Show coordinates':'Hide coordinates';">Show coordinates</button>
                        <div id="distCoordValues" hidden style="margin-top:8px;">
                            <div class="form-grid two">
                                <div class="form-group">
                                    <label class="form-label" for="distLatDisplay">Latitude</label>
                                    <input class="form-control" id="distLatDisplay" type="text" placeholder="14.5995" inputmode="decimal">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="distLngDisplay">Longitude</label>
                                    <input class="form-control" id="distLngDisplay" type="text" placeholder="120.9842" inputmode="decimal">
                                </div>
                            </div>
                        </div>
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
                                <a href="{{ route('admin.distribution.view', $doc) }}" class="btn btn-outline btn-sm">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
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

@push('scripts')
    <script>
        (function () {
            'use strict';

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfToken ? csrfToken.getAttribute('content') : '';

            var distMapEl = document.getElementById('distMap');
            var distLat = document.getElementById('distLat');
            var distLng = document.getElementById('distLng');
            var distLatDisplay = document.getElementById('distLatDisplay');
            var distLngDisplay = document.getElementById('distLngDisplay');
            var distGeoStatus = document.getElementById('distGeoStatus');
            var distLocateBtn = document.getElementById('distLocateBtn');
            var distAddress = document.getElementById('bizAddress');
            var distInstance = null;

            function distSyncDisplay() {
                if (distLatDisplay && distLat) distLatDisplay.value = distLat.value || '';
                if (distLngDisplay && distLng) distLngDisplay.value = distLng.value || '';
            }

            function distSyncHidden() {
                if (distLat && distLatDisplay && distLatDisplay.value) distLat.value = distLatDisplay.value;
                if (distLng && distLngDisplay && distLngDisplay.value) distLng.value = distLngDisplay.value;
            }

            if (distLatDisplay) {
                distLatDisplay.addEventListener('input', distSyncHidden);
                distLatDisplay.addEventListener('change', distSyncHidden);
            }
            if (distLngDisplay) {
                distLngDisplay.addEventListener('input', distSyncHidden);
                distLngDisplay.addEventListener('change', distSyncHidden);
            }

            function distShowGeo(msg, isError) {
                if (!distGeoStatus) return;
                distGeoStatus.className = isError ? 'form-hint geo-status geo-error' : 'form-hint geo-status';
                distGeoStatus.textContent = msg;
                distGeoStatus.hidden = false;
            }

            function distMapPin(lat, lng) {
                if (distLat) distLat.value = Number(lat).toFixed(6);
                if (distLng) distLng.value = Number(lng).toFixed(6);
                distSyncDisplay();
            }

            function distSetPin(lat, lng) {
                if (distLat) distLat.value = Number(lat).toFixed(6);
                if (distLng) distLng.value = Number(lng).toFixed(6);
                distSyncDisplay();
                if (distMapEl) distMapEl.hidden = false;

                if (typeof window.AddressMap === 'object' && typeof window.AddressMap.initInteractive === 'function') {
                    if (!distInstance) {
                        distInstance = window.AddressMap.initInteractive(distMapEl, lat, lng, distMapPin);
                    } else {
                        distInstance.setMarker(lat, lng);
                    }
                }
            }

            if (distLocateBtn) {
                distLocateBtn.addEventListener('click', function () {
                    var bizAddrHidden = document.querySelector('input[name="business_address"]');
                    if (bizAddrHidden && distAddress) bizAddrHidden.value = distAddress.value;
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

            var locForm = document.getElementById('locationForm');
            if (locForm) {
                locForm.addEventListener('submit', function () {
                    var bizAddrHidden = document.querySelector('input[name="business_address"]');
                    if (bizAddrHidden && distAddress) bizAddrHidden.value = distAddress.value;
                });
            }

            /* ===== Distance tracker ===== */
            var clientLat = @json((float) $profile->latitude);
            var clientLng = @json((float) $profile->longitude);

            var trackStartBtn = document.getElementById('trackStartBtn');
            var trackStopBtn = document.getElementById('trackStopBtn');
            var trackPanel = document.getElementById('trackPanel');
            var trackError = document.getElementById('trackError');
            var trackDistanceEl = document.getElementById('trackDistance');
            var trackDirectionEl = document.getElementById('trackDirection');
            var trackArrow = document.getElementById('trackArrow');
            var trackGmaps = document.getElementById('trackGmaps');

            var watchId = null;
            var adminMarker = null;
            var trackMap = null;
            var legendControl = null;

            function trackShowError(msg) {
                if (!trackError) return;
                trackError.textContent = msg;
                trackError.hidden = false;
            }
            function trackHideError() { if (trackError) trackError.hidden = true; }

            function haversineKm(lat1, lon1, lat2, lon2) {
                var R = 6371;
                var toRad = function (d) { return d * Math.PI / 180; };
                var dLat = toRad(lat2 - lat1);
                var dLon = toRad(lon2 - lon1);
                var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                        Math.sin(dLon / 2) * Math.sin(dLon / 2);
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }

            function formatDistance(km) {
                if (km < 1) return Math.round(km * 1000) + ' m away';
                return km.toFixed(1) + ' km away';
            }

            function bearingDeg(lat1, lon1, lat2, lon2) {
                var toRad = function (d) { return d * Math.PI / 180; };
                var toDeg = function (r) { return r * 180 / Math.PI; };
                var dLon = toRad(lon2 - lon1);
                var y = Math.sin(dLon) * Math.cos(toRad(lat2));
                var x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) -
                        Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(dLon);
                return (toDeg(Math.atan2(y, x)) + 360) % 360;
            }

            function bearingCompass(deg) {
                var dirs = ['North', 'Northeast', 'East', 'Southeast', 'South', 'Southwest', 'West', 'Northwest'];
                return dirs[Math.round(deg / 45) % 8];
            }

            function updateTrackReadout(adminLat, adminLng) {
                var dist = haversineKm(adminLat, adminLng, clientLat, clientLng);
                var bear = bearingDeg(adminLat, adminLng, clientLat, clientLng);

                if (trackDistanceEl) trackDistanceEl.textContent = formatDistance(dist);
                if (trackDirectionEl) trackDirectionEl.textContent = 'to the ' + bearingCompass(bear);
                if (trackArrow) {
                    trackArrow.style.transform = 'rotate(' + bear + 'deg)';
                    trackArrow.setAttribute('data-bearing', bear);
                }

                var gmapsUrl = 'https://www.google.com/maps/dir/' +
                    encodeURIComponent(adminLat + ',' + adminLng) + '/' +
                    encodeURIComponent(clientLat + ',' + clientLng);
                if (trackGmaps) trackGmaps.href = gmapsUrl;

                if (trackMap && adminMarker) {
                    adminMarker.setLatLng([adminLat, adminLng]);
                }
            }

            function startTracking() {
                trackHideError();
                if (!navigator.geolocation) {
                    trackShowError('Geolocation is not supported by your browser.');
                    return;
                }

                trackMap = (typeof window.AddressMap === 'object') ? window.AddressMap.getMap('clientMap') : null;
                if (!trackMap) {
                    trackShowError('Map not loaded — please refresh the page.');
                    return;
                }

                if (trackStartBtn) trackStartBtn.hidden = true;
                if (trackPanel) trackPanel.hidden = false;

                var adminIcon = L.divIcon({
                    className: 'track-admin-marker',
                    html: '<span class="track-admin-dot"></span>',
                    iconSize: [18, 18],
                    iconAnchor: [9, 9]
                });

                legendControl = L.control({ position: 'bottomleft' });
                legendControl.onAdd = function () {
                    var div = L.DomUtil.create('div', 'track-legend');
                    div.innerHTML =
                        '<span class="track-legend-item">' +
                            '<span class="track-legend-icon track-legend-pin"></span> ' +
                            '{{ $client->business_name ?: $client->name }}' +
                        '</span>' +
                        '<span class="track-legend-item">' +
                            '<span class="track-legend-icon track-legend-dot"></span> Your location' +
                        '</span>';
                    return div;
                };

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var aLat = pos.coords.latitude;
                        var aLng = pos.coords.longitude;
                        adminMarker = L.marker([aLat, aLng], { icon: adminIcon, interactive: false }).addTo(trackMap);
                        legendControl.addTo(trackMap);
                        updateTrackReadout(aLat, aLng);

                        var midLat = (aLat + clientLat) / 2;
                        var midLng = (aLng + clientLng) / 2;
                        var dist = haversineKm(aLat, aLng, clientLat, clientLng);
                        var fitZoom = dist < 1 ? 15 : dist < 5 ? 13 : dist < 20 ? 12 : 11;
                        trackMap.setView([midLat, midLng], fitZoom);

                        watchId = navigator.geolocation.watchPosition(
                            function (p) { updateTrackReadout(p.coords.latitude, p.coords.longitude); },
                            function (err) {
                                if (err.code === err.PERMISSION_DENIED) {
                                    trackShowError('Location access is needed to track distance — please allow location permission.');
                                }
                            },
                            { enableHighAccuracy: true, maximumAge: 5000 }
                        );
                    },
                    function (err) {
                        if (err.code === err.PERMISSION_DENIED) {
                            trackShowError('Location access is needed to track distance — please allow location permission.');
                        } else if (err.code === err.POSITION_UNAVAILABLE) {
                            trackShowError('Your location could not be determined. Make sure GPS is enabled.');
                        } else {
                            trackShowError('Could not get your location. Please try again.');
                        }
                        if (trackStartBtn) trackStartBtn.hidden = false;
                        if (trackPanel) trackPanel.hidden = true;
                    },
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            }

            function stopTracking() {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                if (adminMarker && trackMap) {
                    trackMap.removeLayer(adminMarker);
                    adminMarker = null;
                }
                if (legendControl && trackMap) {
                    trackMap.removeControl(legendControl);
                }
                if (trackStartBtn) trackStartBtn.hidden = false;
                if (trackPanel) trackPanel.hidden = true;
                trackHideError();
            }

            if (trackStartBtn) trackStartBtn.addEventListener('click', startTracking);
            if (trackStopBtn) trackStopBtn.addEventListener('click', stopTracking);

            /* ===== DeviceOrientation for compass-relative arrow (optional) ===== */
            function tryDeviceOrientation() {
                if (typeof DeviceOrientationEvent !== 'function') return;
                if (typeof DeviceOrientationEvent.requestPermission === 'function') {
                    DeviceOrientationEvent.requestPermission().then(function (state) {
                        if (state === 'granted') window.addEventListener('deviceorientation', applyOrientation);
                    }).catch(function () {});
                } else {
                    window.addEventListener('deviceorientation', applyOrientation);
                }
            }
            function applyOrientation(e) {
                if (e.alpha == null || !trackArrow || (trackPanel && trackPanel.hidden)) return;
                var bear = parseFloat(trackArrow.getAttribute('data-bearing') || '0');
                trackArrow.style.transform = 'rotate(' + (bear - e.alpha) + 'deg)';
            }
            tryDeviceOrientation();
        })();
    </script>
@endpush
