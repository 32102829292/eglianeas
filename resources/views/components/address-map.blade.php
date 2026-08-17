@props([
    'latitude' => null,
    'longitude' => null,
    'id' => 'map',
    'interactive' => false,
    'hidden' => false,
    'onPinCallback' => null,
])

@php
    $hasCoords = $latitude !== null && $longitude !== null
                 && is_numeric($latitude) && is_numeric($longitude);
    $containerHidden = $hidden || ($interactive && !$hasCoords);
@endphp

<div
    class="map-preview"
    id="{{ $id }}"
    @if ($interactive && !$hasCoords) hidden @endif
    @if ($containerHidden && !$interactive) hidden @endif
></div>

@pushOnce('styles')
    <link rel="stylesheet" href="/vendor/leaflet/leaflet.css">
@endpushOnce

@pushOnce('scripts')
    <script src="/vendor/leaflet/leaflet.js" defer></script>
    <script src="/js/address-map.js" defer></script>
@endpushOnce

@if ($hasCoords)
    <script>
        (function () {
            var q = window._addressMapQueue = window._addressMapQueue || [];
            q.push({
                id: @js($id),
                lat: @js((float) $latitude),
                lng: @js((float) $longitude),
                interactive: @js($interactive),
                onPinCallback: @js($onPinCallback),
            });
        })();
    </script>
@endif
