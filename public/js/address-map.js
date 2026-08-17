/* Egliane — shared address map component (Leaflet + OpenStreetMap)
 *
 * Usage:
 *   1. Include Leaflet CSS + JS (defer) and this script (defer, after leaflet).
 *   2. Register maps via window._addressMapQueue.push({ id, lat, lng, interactive, geocodeUrl })
 *      or use the <x-address-map> Blade component which does this automatically.
 *   3. This script auto-initialises all registered maps once Leaflet is available.
 */
(function () {
  'use strict';

  var TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  var ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
  var DEFAULT_ZOOM = 16;

  /* -------- queue (populated by inline scripts before this file loads) -------- */
  var queue = (window._addressMapQueue = window._addressMapQueue || []);

  /* -------- public API -------- */
  var API = (window.AddressMap = {});
  var instances = (API._instances = {});

  /** Get a previously created Leaflet map instance by its container id. */
  API.getMap = function (id) { return instances[id] || null; };

  /** Create a read-only map (no dragging, no zoom). */
  API.initReadonly = function (el, lat, lng) {
    if (!el || lat == null || lng == null || isNaN(lat) || isNaN(lng)) return null;
    if (typeof window.L !== 'object' || typeof L.map !== 'function') return null;

    var map = L.map(el, {
      scrollWheelZoom: false,
      dragging: false,
      touchZoom: false,
      doubleClickZoom: false,
      boxZoom: false,
      keyboard: false,
    });
    L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);
    L.marker([lat, lng]).addTo(map);
    map.setView([lat, lng], DEFAULT_ZOOM);
    if (el && el.id) instances[el.id] = map;
    return map;
  };

  /** Create an interactive map (click-to-pin, draggable marker). Returns { map, marker }. */
  API.initInteractive = function (el, lat, lng, onPin) {
    if (!el) return null;
    if (typeof window.L !== 'object' || typeof L.map !== 'function') return null;

    var map = L.map(el, { scrollWheelZoom: false });
    L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);

    var marker = null;

    function setMarker(newLat, newLng) {
      if (marker) {
        marker.setLatLng([newLat, newLng]);
      } else {
        marker = L.marker([newLat, newLng], { draggable: true }).addTo(map);
        marker.on('dragend', function () {
          var ll = marker.getLatLng();
          if (onPin) onPin(ll.lat, ll.lng);
        });
      }
      map.setView([newLat, newLng], DEFAULT_ZOOM);
    }

    map.on('click', function (e) {
      setMarker(e.latlng.lat, e.latlng.lng);
      if (onPin) onPin(e.latlng.lat, e.latlng.lng);
    });

    if (lat != null && lng != null && !isNaN(lat) && !isNaN(lng)) {
      setMarker(lat, lng);
    }

    if (el && el.id) instances[el.id] = map;
    return { map: map, marker: marker, setMarker: setMarker };
  };

  /** Fallback: render an OSM embed iframe when Leaflet is unavailable. */
  API.renderIframe = function (el, lat, lng) {
    if (!el || lat == null || lng == null) return;
    var margin = 0.004;
    var bbox = (lng - margin) + ',' + (lat - margin) + ',' + (lng + margin) + ',' + (lat + margin);
    el.innerHTML =
      '<iframe src="https://www.openstreetmap.org/export/embed.html?bbox=' +
      encodeURIComponent(bbox) +
      '&layer=mapnik&marker=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng) +
      '" loading="lazy" title="Map preview" style="width:100%;height:100%;border:none;"></iframe>';
  };

  /* -------- auto-init: wait for Leaflet, then process the queue -------- */
  function processQueue() {
    for (var i = 0; i < queue.length; i++) {
      var item = queue[i];
      var el = document.getElementById(item.id);
      if (!el) continue;

      var lat = parseFloat(item.lat);
      var lng = parseFloat(item.lng);
      if (isNaN(lat) || isNaN(lng)) continue;

      if (item.interactive) {
        var cbName = item.onPinCallback;
        var cb = typeof window[cbName] === 'function' ? window[cbName] : null;
        API.initInteractive(el, lat, lng, cb);
      } else {
        var map = API.initReadonly(el, lat, lng);
        if (!map) API.renderIframe(el, lat, lng);
      }
    }
    queue.length = 0;
  }

  function waitForLeaflet(attempts) {
    if (typeof window.L === 'object' && typeof L.map === 'function') {
      processQueue();
      return;
    }
    if ((attempts || 0) > 100) {
      /* Leaflet never loaded — render iframe fallbacks */
      for (var i = 0; i < queue.length; i++) {
        var item = queue[i];
        var el = document.getElementById(item.id);
        var lat = parseFloat(item.lat);
        var lng = parseFloat(item.lng);
        if (el && !isNaN(lat) && !isNaN(lng)) API.renderIframe(el, lat, lng);
      }
      queue.length = 0;
      return;
    }
    setTimeout(function () { waitForLeaflet((attempts || 0) + 1); }, 50);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { waitForLeaflet(0); });
  } else {
    waitForLeaflet(0);
  }
})();
