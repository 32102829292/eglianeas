/* Egliane — client profile & BIR form upload logic */
(function () {
  'use strict';

  var E = (window.egliane = window.egliane || {});

  /* ---------- Reveal masked sensitive fields ---------- */
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

  /* ---------- Reveal masked text in view mode ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.reveal-text-btn') : null;
    if (!btn) return;
    var text = btn.parentElement ? btn.parentElement.querySelector('.reveal-text') : null;
    if (!text) return;
    var showing = btn.getAttribute('data-showing') === '1';
    if (showing) {
      text.textContent = text.getAttribute('data-masked') || text.textContent;
      btn.textContent = 'Reveal';
      btn.setAttribute('data-showing', '0');
    } else {
      text.textContent = text.getAttribute('data-full') || text.textContent;
      btn.textContent = 'Hide';
      btn.setAttribute('data-showing', '1');
    }
  });

  /* ---------- Never save masked sensitive values ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var masked = form.querySelectorAll('[data-masked][data-full]');
    for (var i = 0; i < masked.length; i++) {
      var el = masked[i];
      if (el.value === el.getAttribute('data-masked')) {
        el.value = el.getAttribute('data-full') || el.value;
      }
    }
  }, true);

  /* ---------- Line of business "Other" toggle ---------- */
  var lobSelect = document.querySelector('[data-lob-select]');
  var lobOther = document.querySelector('[data-lob-other]');
  if (lobSelect && lobOther) {
    var lobOtherInput = lobOther.querySelector('input');
    function syncLobOther() {
      if (lobSelect.value === 'Other') {
        lobOther.hidden = false;
        if (lobOtherInput && lobOtherInput.value === '') lobOtherInput.focus();
      } else {
        lobOther.hidden = true;
      }
    }
    lobSelect.addEventListener('change', syncLobOther);
    syncLobOther();
  }

  /* ---------- Map preview + address geocoding ---------- */
  var geoStatus = document.getElementById('geoStatus');
  var latInput = document.getElementById('latitude');
  var lngInput = document.getElementById('longitude');
  var mapPreview = document.getElementById('mapPreview');
  var locateBtn = document.getElementById('locateAddressBtn');
  var addressInput = document.getElementById('business_address');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

  var mapInstance = null;

  function showGeoError(message) {
    if (!geoStatus) return;
    geoStatus.className = 'form-hint geo-status geo-error';
    geoStatus.textContent = message;
    geoStatus.hidden = false;
    if (latInput) latInput.setAttribute('aria-invalid', 'true');
    if (lngInput) lngInput.setAttribute('aria-invalid', 'true');
  }

  function showGeoStatus(message) {
    if (!geoStatus) return;
    geoStatus.className = 'form-hint geo-status';
    geoStatus.textContent = message;
    geoStatus.hidden = false;
    if (latInput) latInput.removeAttribute('aria-invalid');
    if (lngInput) lngInput.removeAttribute('aria-invalid');
  }

  function setPin(lat, lng, fromUser) {
    var lat6 = Number(lat).toFixed(6);
    var lng6 = Number(lng).toFixed(6);
    if (latInput) latInput.value = lat6;
    if (lngInput) lngInput.value = lng6;
    if (fromUser) showGeoStatus('Pin adjusted manually — remember to save.');
  }

  function renderMap(lat, lng) {
    if (!mapPreview) return;
    if (lat == null || lng == null || isNaN(lat) || isNaN(lng)) {
      if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
      }
      mapPreview.hidden = true;
      return;
    }
    mapPreview.hidden = false;

    if (typeof window.AddressMap === 'object' && typeof window.AddressMap.initInteractive === 'function') {
      if (!mapInstance) {
        mapInstance = window.AddressMap.initInteractive(mapPreview, lat, lng, function (newLat, newLng) {
          setPin(newLat, newLng, true);
        });
      } else {
        mapInstance.setMarker(lat, lng);
      }
      if (mapInstance && mapInstance.map) mapInstance.map.invalidateSize();
      return;
    }

    var margin = 0.004;
    var bbox = (lng - margin) + ',' + (lat - margin) + ',' + (lng + margin) + ',' + (lat + margin);
    mapPreview.innerHTML =
      '<iframe src="https://www.openstreetmap.org/export/embed.html?bbox=' +
      encodeURIComponent(bbox) +
      '&layer=mapnik&marker=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng) +
      '" loading="lazy" title="Map preview"></iframe>';
  }

  function initialCoords() {
    var lat = (typeof window.profileLat !== 'undefined' && window.profileLat !== null)
      ? window.profileLat
      : parseFloat(latInput ? latInput.value : '');
    var lng = (typeof window.profileLng !== 'undefined' && window.profileLng !== null)
      ? window.profileLng
      : parseFloat(lngInput ? lngInput.value : '');
    if (!isNaN(lat) && !isNaN(lng)) {
      renderMap(lat, lng);
    }
  }
  initialCoords();

  function inputsChanged() {
    var lat = parseFloat(latInput ? latInput.value : '');
    var lng = parseFloat(lngInput ? lngInput.value : '');
    renderMap(lat, lng);
  }
  if (latInput) {
    latInput.addEventListener('input', inputsChanged);
    latInput.addEventListener('change', inputsChanged);
  }
  if (lngInput) {
    lngInput.addEventListener('input', inputsChanged);
    lngInput.addEventListener('change', inputsChanged);
  }

  var lastGeocodedText = '';
  var geocodeInFlight = false;
  var GEOCODE_ERROR = "Couldn't locate that address automatically — please drag the pin on the map or enter coordinates manually.";

  function geocodeAddress(text, force) {
    var q = (text || '').trim();
    if (!q) {
      showGeoError('Please enter a business address first.');
      return;
    }
    if (geocodeInFlight) return;
    if (!force && lastGeocodedText === q) return;
    geocodeInFlight = true;
    showGeoStatus('Locating address…');

    fetch('/client/geocode', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ q: q }),
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        geocodeInFlight = false;
        lastGeocodedText = q;
        var data = result.data || {};
        if (typeof data.lat === 'number' && typeof data.lng === 'number') {
          var lat = data.lat.toFixed(6);
          var lng = data.lng.toFixed(6);
          if (latInput) latInput.value = lat;
          if (lngInput) lngInput.value = lng;
          renderMap(lat, lng);
          if (data.display_name) {
            var shown = data.display_name.length > 90 ? data.display_name.slice(0, 90) + '…' : data.display_name;
            showGeoStatus('Located: ' + shown + ' — adjust the pin if needed.');
          } else {
            showGeoStatus('Address located — adjust the pin if needed.');
          }
        } else {
          showGeoError(data.error || GEOCODE_ERROR);
          var curLat = parseFloat(latInput ? latInput.value : '');
          var curLng = parseFloat(lngInput ? lngInput.value : '');
          if (!isNaN(curLat) && !isNaN(curLng)) {
            renderMap(curLat, curLng);
          }
        }
      })
      .catch(function () {
        geocodeInFlight = false;
        lastGeocodedText = q;
        showGeoError(GEOCODE_ERROR);
        var curLat = parseFloat(latInput ? latInput.value : '');
        var curLng = parseFloat(lngInput ? lngInput.value : '');
        if (!isNaN(curLat) && !isNaN(curLng)) {
          renderMap(curLat, curLng);
        }
      });
  }

  if (locateBtn) {
    locateBtn.addEventListener('click', function () {
      geocodeAddress(addressInput ? addressInput.value : '', true);
    });
  }

  var geocodeTimer = null;
  if (addressInput) {
    addressInput.addEventListener('input', function () {
      clearTimeout(geocodeTimer);
      geocodeTimer = setTimeout(function () {
        geocodeAddress(addressInput.value, false);
      }, 1000);
    });
  }
})();
