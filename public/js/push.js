/* Egliane Accounting Services — Push Subscription Logic */
(function () {
  'use strict';

  var E = window.egliane = window.egliane || {};
  var CSRF = '';
  var VAPID_PUBLIC_KEY = null;

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; i++) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function fetchVapidKey() {
    if (VAPID_PUBLIC_KEY) return Promise.resolve(VAPID_PUBLIC_KEY);
    return fetch('/push/vapid-key', { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        VAPID_PUBLIC_KEY = data.publicKey;
        return VAPID_PUBLIC_KEY;
      });
  }

  function subscribe() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      E.toast('Push notifications are not supported in this browser.');
      return Promise.resolve();
    }

    CSRF = getCsrfToken();

    return navigator.serviceWorker.ready.then(function (reg) {
      return fetchVapidKey().then(function (publicKey) {
        return reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey)
        });
      });
    }).then(function (subscription) {
      var json = subscription.toJSON();
      return fetch('/push/subscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          endpoint: json.endpoint,
          keys: json.keys
        })
      });
    }).then(function (res) {
      if (res.ok) {
        E.toast('Push notifications enabled.');
        updatePushUI(true);
      } else {
        E.toast('Could not save subscription.');
      }
    }).catch(function (err) {
      console.error('[Egliane push] subscribe error:', err);
      if (Notification.permission === 'denied') {
        E.toast('Push notifications are blocked. Enable them in your browser settings.');
      } else {
        E.toast('Push subscription failed.');
      }
    });
  }

  function unsubscribe() {
    CSRF = getCsrfToken();

    return navigator.serviceWorker.ready.then(function (reg) {
      return reg.pushManager.getSubscription();
    }).then(function (subscription) {
      if (!subscription) {
        updatePushUI(false);
        return;
      }
      var endpoint = subscription.endpoint;
      return subscription.unsubscribe().then(function () {
        return fetch('/push/unsubscribe', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json'
          },
          body: JSON.stringify({ endpoint: endpoint })
        });
      });
    }).then(function () {
      E.toast('Push notifications disabled.');
      updatePushUI(false);
    }).catch(function (err) {
      console.error('[Egliane push] unsubscribe error:', err);
      E.toast('Could not remove subscription.');
    });
  }

  function updatePushUI(enabled) {
    var btns = document.querySelectorAll('[data-push-toggle]');
    for (var i = 0; i < btns.length; i++) {
      var btn = btns[i];
      if (enabled) {
        btn.setAttribute('data-push-state', 'enabled');
        btn.setAttribute('aria-label', 'Disable push notifications');
        btn.title = 'Push notifications: ON';
      } else {
        btn.setAttribute('data-push-state', 'disabled');
        btn.setAttribute('aria-label', 'Enable push notifications');
        btn.title = 'Push notifications: OFF';
      }
    }
  }

  function checkSubscription() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      updatePushUI(false);
      return;
    }
    navigator.serviceWorker.ready.then(function (reg) {
      return reg.pushManager.getSubscription();
    }).then(function (subscription) {
      updatePushUI(!!subscription);
    }).catch(function () {
      updatePushUI(false);
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-push-toggle]');
    if (!btn) return;
    e.preventDefault();
    var state = btn.getAttribute('data-push-state');
    if (state === 'enabled') {
      unsubscribe();
    } else {
      subscribe();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    checkSubscription();
  });

  E.pushCheckSubscription = checkSubscription;
})();
