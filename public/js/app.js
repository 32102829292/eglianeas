/* Egliane Accounting Services — global app logic */
(function () {
  'use strict';

  /* Marker so CSS can opt into JS-only enhancement (e.g. landing reveals)
     without hiding content for users without JavaScript. */
  document.documentElement.classList.add('js');

  var E = (window.egliane = window.egliane || {});

  /* ---------- Install prompt ---------- */
  E.deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    E.deferredPrompt = e;
    window.dispatchEvent(new CustomEvent('egliane:installable'));
  });

  /* ---------- Service worker ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }

  /* ---------- bfcache guard ----------
     iOS Safari restores form pages from the back/forward cache byte-for-byte,
     including the CSRF token rendered under a previous session. Submitting
     after session expiry/logout then yields "419 Page Expired". Reload to get
     fresh HTML + a valid token whenever the page comes out of bfcache. */
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
  });

  /* ---------- Offline banner + connectivity ---------- */
  var banner = document.getElementById('offlineBanner');

  /* navigator.onLine is only a hint and can report false even with a working
     connection (VPNs, captive portals, some browsers/networks). Only surface
     the banner when BOTH the hint says offline AND a real same-origin network
     request also fails, debounced so a brief blip doesn't flash the banner. */
  var offlineTimer = null;
  var offlinePending = false;

  function flushConnectivity() {
    var offline = offlinePending;
    if (banner) banner.classList.toggle('show', offline);
    document.body.classList.toggle('is-offline', offline);
    window.dispatchEvent(new CustomEvent('egliane:connectivity', { detail: { offline: offline } }));
  }

  function probeOffline() {
    if (navigator.onLine) {
      offlinePending = false;
      flushConnectivity();
      return;
    }
    /* navigator.onLine is false; confirm with a real request so slow
       responses aren't misread as offline. In-flight/failed probes keep
       evaluating until we get a definitive answer. */
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timeoutId = controller ? setTimeout(function () { controller.abort(); }, 5000) : null;

    fetch('/manifest.json', { cache: 'no-store', signal: controller ? controller.signal : undefined })
      .then(function () {
        if (timeoutId) clearTimeout(timeoutId);
        offlinePending = false;
        flushConnectivity();
      })
      .catch(function () {
        if (timeoutId) clearTimeout(timeoutId);
        /* only treat a genuine abort/timeout-ish failure as offline; keep the
           banner hidden on any network error that could be transient */
        offlinePending = true;
        flushConnectivity();
      });
  }

  function updateOnlineState() {
    offlinePending = false;
    if (offlineTimer) { clearTimeout(offlineTimer); offlineTimer = null; }
    if (!navigator.onLine) {
      offlinePending = true;
      offlineTimer = setTimeout(function () {
        offlineTimer = null;
        probeOffline();
      }, 1500);
    } else {
      flushConnectivity();
    }
  }

  window.addEventListener('online', function () {
    updateOnlineState();
    E.flushOutbox();
    E.flushUploads();
  });
  window.addEventListener('offline', updateOnlineState);
  updateOnlineState();

  /* ---------- Toast ---------- */
var toastEl = null;
  E.toast = function (message, type) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      document.body.appendChild(toastEl);
    }
    toastEl.classList.remove('toast-success', 'toast-error', 'toast-warning', 'toast-info');
    if (type === 'success' || type === 'error' || type === 'warning' || type === 'info') {
      toastEl.classList.add('toast-' + type);
    }
    toastEl.textContent = message;
    toastEl.classList.add('show');
    clearTimeout(E.toast._t);
    E.toast._t = setTimeout(function () {
      toastEl.classList.remove('show');
    }, 3000);
  };

  /* ---------- Mobile nav toggle (site header) ---------- */
  var navToggle = document.getElementById('navToggle');
  var mobileNav = document.getElementById('mobileNav');
  if (navToggle && mobileNav) {
    function setMobileNav(open) {
      mobileNav.classList.toggle('open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    navToggle.addEventListener('click', function () {
      setMobileNav(!mobileNav.classList.contains('open'));
    });
    mobileNav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setMobileNav(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && mobileNav.classList.contains('open')) {
        setMobileNav(false);
        navToggle.focus();
      }
    });
  }

  /* ---------- Landing page reveal on scroll ----------
     Content is fully visible without JS; we only lift elements into
     place when the observer is available and motion is allowed. */
  (function initLandingReveal() {
    var revealEls = document.querySelectorAll('.lp-reveal');
    if (!revealEls.length) return;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var supportsIO = 'IntersectionObserver' in window;
    if (!supportsIO || reduceMotion) {
      for (var i = 0; i < revealEls.length; i++) revealEls[i].classList.add('lp-reveal--in');
      return;
    }
    var revealIo = new IntersectionObserver(function (entries) {
      for (var k = 0; k < entries.length; k++) {
        if (entries[k].isIntersecting) {
          entries[k].target.classList.add('lp-reveal--in');
          revealIo.unobserve(entries[k].target);
        }
      }
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    for (var j = 0; j < revealEls.length; j++) revealIo.observe(revealEls[j]);
  })();

  /* ---------- Notification bell dropdown ---------- */
  var bellBtn = document.getElementById('bellBtn');
  var bellDropdown = document.getElementById('bellDropdown');
  if (bellBtn && bellDropdown) {
    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = bellDropdown.classList.toggle('open');
      bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (bellDropdown.classList.contains('open') && !e.target.closest('#bellWrap')) {
        bellDropdown.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && bellDropdown.classList.contains('open')) {
        bellDropdown.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
        bellBtn.focus();
      }
    });
  }

  /* ---------- Mobile drawer ---------- */
  var hamburger = document.getElementById('hamburgerBtn');
  var drawer = document.getElementById('dashDrawer');
  var backdrop = document.getElementById('dashDrawerBackdrop');
  var drawerClose = document.getElementById('drawerClose');

  /* REGRESSION WARNING: Two independent layout bugs on mobile (<=900px):
     1. nav.dashDrawer must stay position:fixed so it never enters .dash-layout's flex
        flow. If it does, content shifts right and clips the left edge at 375-440px.
        (Regressed 3 times — JS enforces inline position:fixed as safety net.)
     2. .dash-layout must use align-items:stretch (not flex-start) in column mode so
        main.dash-main doesn't take intrinsic content width and exceed the viewport.
        (Caused 22px+ overflow — max-width:100% on .dash-main is the belt-and-suspenders.)
     Verify: main.dash-main computed width <= viewport width at 375-440px.
     Do NOT remove without confirming document.body.scrollWidth == window.innerWidth. */
  var MOBILE_BP = 600;
  function verifyMobileLayout() {
    if (window.innerWidth > MOBILE_BP) return;
    if (drawer) drawer.style.position = 'fixed';
    if (backdrop) backdrop.style.position = 'fixed';
    var main = document.querySelector('.dash-main');
    if (main) {
      var mainW = Math.round(main.getBoundingClientRect().width);
      var vpW = window.innerWidth;
      if (mainW > vpW) {
        console.warn('[Egliane layout] OVERFLOW: .dash-main width (' + mainW + 'px) > viewport (' + vpW + 'px) on mobile. Content will shift right.');
      }
    }
  }

  var lastDrawerTrigger = null;

  function setDrawer(open) {
    if (!drawer || !backdrop) return;
    drawer.classList.toggle('open', open);
    backdrop.classList.toggle('open', open);
    if (hamburger) hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
      lastDrawerTrigger = document.activeElement || hamburger;
      /* Keep the trigger in the tab order but move focus into the drawer so
         keyboard/AT users land on the navigation; the Escape/backdrop handlers
         return focus to the hamburger on close. */
      if (hamburger) hamburger.setAttribute('tabindex', '-1');
      var firstLink = drawer.querySelector('a, button');
      if (firstLink) setTimeout(function () { firstLink.focus(); }, 100);
    } else {
      if (hamburger) hamburger.setAttribute('tabindex', '0');
      if (lastDrawerTrigger) {
        var toFocus = lastDrawerTrigger;
        lastDrawerTrigger = null;
        setTimeout(function () { toFocus.focus(); }, 0);
      }
    }
    verifyMobileLayout();
  }

  if (hamburger) hamburger.addEventListener('click', function () { setDrawer(true); });
  if (drawerClose) drawerClose.addEventListener('click', function () { setDrawer(false); });
  if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      /* Close when a real navigation link is clicked (any <a> we own). We keep
         the click-through so the browser can navigate to the target page. */
      var link = e.target.closest('a');
      if (link && drawer.contains(link)) {
        lastDrawerTrigger = lastDrawerTrigger || hamburger;
        setDrawer(false);
      }
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) setDrawer(false);
  });
  /* Simple focus trap inside the open drawer: Tab / Shift+Tab cycle through the
     drawer's focusable elements so focus never escapes behind the backdrop. */
  document.addEventListener('keydown', function (e) {
    if (!drawer || !drawer.classList.contains('open')) return;
    if (e.key !== 'Tab') return;
    var focusables = drawer.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey && (document.activeElement === first || document.activeElement === drawer)) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  /* ---------- Sidebar / drawer scroll persistence ----------
     Both the desktop sidebar (.dash-nav) and the mobile drawer (.dash-drawer)
     are internally-scrolling columns that render the same navigation partial.
     A full-page navigation tears them down and re-creates them with scrollTop 0,
     which makes the sidebar/drawer "jump back to the top" when you click a page
     and reopen navigation. We persist scrollTop to sessionStorage under
     egliane_sidebar_scroll (per-tab, survives same-tab navigations), flush on
     pagehide so the last position is never lost, and restore it early on the
     next page via the inline <script> in layouts/dashboard.blade.php (before
     first paint), then re-apply on load to cover any font/reflow clamping.

     The key is shared between the sidebar and drawer deliberately: they contain
     the same nav items, so keeping a single scroll position keeps navigation
     feeling consistent whether it is opened on desktop or from the mobile drawer.
     Because only one of them is on-screen/scrollable at a time (the other is
     display:none on desktop and off-canvas on mobile), writing to one shared
     value is race-free. */
  var NAV_SCROLL_KEY = 'egliane_sidebar_scroll';
  var scrollContainers = [];
  var sideNav = document.querySelector('.dash-nav');
  var drawerNav = document.getElementById('dashDrawer');
  var pendingNavSave = false;

  if (sideNav) scrollContainers.push(sideNav);
  if (drawerNav) scrollContainers.push(drawerNav);

  function saveNavScroll() {
    pendingNavSave = false;
    var top = 0;
    for (var i = 0; i < scrollContainers.length; i++) {
      var el = scrollContainers[i];
      if (!el) continue;
      /* Only read from a container that is actually laid out/visible, so the
         desktop sidebar (display:none on mobile) can't overwrite the drawer's
         position with its own 0 and vice-versa. */
      if (el.offsetParent !== null || el === drawerNav) {
        top = el.scrollTop;
        break;
      }
    }
    try { sessionStorage.setItem(NAV_SCROLL_KEY, String(top)); } catch (e) {}
  }

  for (var i = 0; i < scrollContainers.length; i++) {
    (function (el) {
      el.addEventListener('scroll', function () {
        pendingNavSave = true;
        clearTimeout(saveNavScroll._t);
        saveNavScroll._t = setTimeout(saveNavScroll, 15);
      }, { passive: true });
    })(scrollContainers[i]);
  }

  window.addEventListener('pagehide', function () {
    if (pendingNavSave) saveNavScroll();
  });

  function restoreNavScroll() {
    var top = -1;
    try { top = parseInt(sessionStorage.getItem(NAV_SCROLL_KEY), 10); } catch (e) {}
    if (!(top > 0)) return;
    for (var i = 0; i < scrollContainers.length; i++) {
      if (scrollContainers[i]) scrollContainers[i].scrollTop = top;
    }
  }

  window.addEventListener('load', restoreNavScroll);
  /* Re-apply whenever the drawer is opened so an in-session reopen on the same
     page (no navigation) still lands where the user left off. */
  if (drawerNav) {
    drawerNav.addEventListener('transitionend', function (e) {
      if (e.propertyName === 'transform' && drawerNav.classList.contains('open')) {
        var top = -1;
        try { top = parseInt(sessionStorage.getItem(NAV_SCROLL_KEY), 10); } catch (err) {}
        if (top > 0) drawerNav.scrollTop = top;
      }
    });
  }

  /* ---------- Announcement dismiss ---------- */
  var annClose = document.getElementById('announcementClose');
  if (annClose) {
    annClose.addEventListener('click', function () {
      var bar = document.getElementById('announcementBar');
      if (bar) bar.style.display = 'none';
      try { localStorage.setItem('egliane:announcement:dismissed', '1'); } catch (e) {}
    });
  }

  /* ---------- PWA install button ---------- */
  document.addEventListener('egliane:installable', function () {
    var installBtns = document.querySelectorAll('[data-install]');
    for (var i = 0; i < installBtns.length; i++) {
      installBtns[i].classList.remove('hidden');
    }
  });
  document.addEventListener('click', function (e) {
    var target = e.target.closest('[data-install]');
    if (target && E.deferredPrompt) {
      e.preventDefault();
      E.deferredPrompt.prompt();
      E.deferredPrompt.userChoice.then(function () {
        E.deferredPrompt = null;
        target.classList.add('hidden');
      });
    }
  });

  /* ---------- iOS install tip ----------
     iOS Safari never fires `beforeinstallprompt`, so the standard Install
     button stays hidden. Show a dismissible tooltip on iPhone/iPad instead,
     unless the app is already running standalone or the user dismissed it. */
  var IOS_INSTALL_KEY = 'egliane:ios-install:dismissed';

  function isIOSStandalone() {
    return window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
  }

  function isIOSWebView() {
    var ua = window.navigator.userAgent;
    var standalone = window.navigator.standalone;
    return (typeof standalone !== 'undefined') &&
           (ua.indexOf('CriOS') === -1) &&
           (ua.indexOf('FxiOS') === -1) &&
           (ua.indexOf('Safari') === -1) &&
           (ua.indexOf('AppleWebKit') !== -1);
  }

  function isIOS() {
    var ua = window.navigator.userAgent;
    return /iPad|iPhone|iPod/.test(ua) ||
           (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function maybeShowIosInstallTip() {
    var tip = document.getElementById('iosInstallTip');
    if (!tip) return;
    // iOS Safari (not Chrome/Firefox/Edge), not standalone, not a webview, not dismissed
    if (!isIOS() || isIOSStandalone() || isIOSWebView()) return;
    var dismissed = false;
    try { dismissed = localStorage.getItem(IOS_INSTALL_KEY) === '1'; } catch (e) {}
    if (dismissed) return;
    tip.classList.remove('hidden');
    tip.setAttribute('aria-hidden', 'false');
  }

  var iosTipClose = document.getElementById('iosInstallTipClose');
  if (iosTipClose) {
    iosTipClose.addEventListener('click', function () {
      var tip = document.getElementById('iosInstallTip');
      if (tip) {
        tip.classList.add('hidden');
        tip.setAttribute('aria-hidden', 'true');
      }
      try { localStorage.setItem(IOS_INSTALL_KEY, '1'); } catch (e) {}
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    maybeShowIosInstallTip();
  });

  /* ---------- Offline action queue (outbox) ---------- */
  E.getOutbox = function () {
    try {
      return JSON.parse(localStorage.getItem('egliane:outbox') || '[]');
    } catch (e) {
      return [];
    }
  };

  E.pushOutbox = function (item) {
    var queue = E.getOutbox();
    var entry = { id: 'm' + Date.now(), at: Date.now() };
    for (var key in item) {
      if (Object.prototype.hasOwnProperty.call(item, key)) entry[key] = item[key];
    }
    queue.push(entry);
    try { localStorage.setItem('egliane:outbox', JSON.stringify(queue)); } catch (e) {}
  };

  E.flushOutbox = function () {
    var queue = E.getOutbox();
    if (!queue.length) return;
    try {
      localStorage.setItem('egliane:outbox', '[]');
      E.toast(queue.length + ' pending message(s) synced.');
    } catch (e) {}
  };

  /* ---------- Offline document uploads (IndexedDB outbox) ---------- */
  var uploadDB = null;
  function openUploadDB() {
    return new Promise(function (resolve, reject) {
      if (uploadDB) { resolve(uploadDB); return; }
      if (!('indexedDB' in window)) { reject(new Error('no indexeddb')); return; }
      var req = indexedDB.open('egliane-uploads', 1);
      req.onupgradeneeded = function () {
        req.result.createObjectStore('uploads', { keyPath: 'id' });
      };
      req.onsuccess = function () {
        uploadDB = req.result;
        resolve(uploadDB);
      };
      req.onerror = function () { reject(req.error); };
    });
  }

  E.queueUpload = function (item) {
    return openUploadDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction('uploads', 'readwrite');
        tx.objectStore('uploads').put(item);
        tx.oncomplete = resolve;
        tx.onerror = function () { reject(tx.error); };
      });
    });
  };

  function removeUpload(id) {
    return openUploadDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx = db.transaction('uploads', 'readwrite');
        tx.objectStore('uploads').delete(id);
        tx.oncomplete = resolve;
      });
    });
  }

  E.flushUploads = function () {
    if (!('indexedDB' in window) || !navigator.onLine) return;
    openUploadDB().then(function (db) {
      var tx = db.transaction('uploads', 'readonly');
      var req = tx.objectStore('uploads').getAll();
      req.onsuccess = function () {
        var items = req.result || [];
        if (!items.length) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        token = token ? token.getAttribute('content') : '';
        items.forEach(function (item) {
          var fd = new FormData();
          fd.append('_token', token);
          fd.append('file', item.file, item.file.name || 'file');
          fd.append('notes', item.notes || '');
          fetch(item.url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
              if (res.ok) {
                removeUpload(item.id);
                E.toast('Queued upload "' + (item.file.name || 'file') + '" synced.');
                return;
              }
              throw new Error('upload failed');
            })
            .catch(function () { /* keep queued, retry later */ });
        });
      };
    }).catch(function () {});
  };

  /* ---------- Offline document upload form intercept ---------- */
  var uploadForm = document.getElementById('uploadForm');
  if (uploadForm) {
    uploadForm.addEventListener('submit', function (e) {
      if (navigator.onLine) return;
      e.preventDefault();
      var fileInput = uploadForm.querySelector('input[type=file]');
      var notesInput = uploadForm.querySelector('[name=notes]');
      var file = fileInput && fileInput.files && fileInput.files[0];
      if (!file) { E.toast('Choose a file first.'); return; }
      E.queueUpload({
        id: 'u' + Date.now(),
        url: uploadForm.action,
        file: file,
        notes: notesInput ? notesInput.value : ''
      }).then(function () {
        uploadForm.reset();
        E.toast('You are offline — file queued. It uploads automatically when you reconnect.');
      }).catch(function () {
        E.toast('Could not queue the file. Please try again.');
      });
    });
  }

  if (navigator.onLine) E.flushUploads();

  /* ---------- Chatbot ---------- */
  var CHAT_POS_KEY = 'egliane:chatbot:pos';
  var CHAT_EDGE = 18;

  function Chatbot(opts) {
    this.opts = opts || {};
    this.cfg = null;
    this.open = false;
    this.messagesEl = null;
    this.widgetEl = null;
    this.fabEl = null;
    this.lastRuleHit = false;
    this.offline = !navigator.onLine;
    this.dragged = false;
    this.build();
    this.loadConfig();
    this.bindConnectivity();
    this.showWelcome();
  }

  Chatbot.prototype.build = function () {
    var self = this;

    this.fabEl = document.getElementById('chatFab');
    this.widgetEl = document.getElementById('chatWidget');

    if (!this.fabEl || !this.widgetEl) return;

    this.fabEl.addEventListener('click', function () {
      if (self.suppressClick) return;
      self.toggle(true);
    });

    this.initDrag();

    var closeBtn = this.widgetEl.querySelector('.close-chat');
    if (closeBtn) closeBtn.addEventListener('click', function () { self.toggle(false); });

    // Close the widget with the Escape key from anywhere inside it.
    this.widgetEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && self.open) self.toggle(false);
    });

    this.messagesEl = this.widgetEl.querySelector('.chat-messages');
    this.inputEl = this.widgetEl.querySelector('#chatInput');
    this.sendBtn = this.widgetEl.querySelector('#chatSend');

    var quickBtns = this.widgetEl.querySelectorAll('.chat-quick button');
    for (var i = 0; i < quickBtns.length; i++) {
      (function (btn) {
        btn.addEventListener('click', function () {
          self.userSay(btn.getAttribute('data-q') || btn.textContent);
        });
      })(quickBtns[i]);
    }

    this.sendBtn.addEventListener('click', function () { self.userSay(self.inputEl.value); });
    this.inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') self.userSay(self.inputEl.value);
    });

    this.restorePosition();
    window.addEventListener('resize', function () {
      if (self.dragged) self.placeWidget();
    });
  };

  Chatbot.prototype.readSavedPosition = function () {
    try {
      var pos = JSON.parse(localStorage.getItem(CHAT_POS_KEY));
      if (pos && (pos.side === 'left' || pos.side === 'right') && typeof pos.bottom === 'number') return pos;
    } catch (e) {}
    return null;
  };

  Chatbot.prototype.restorePosition = function () {
    var pos = this.readSavedPosition();
    if (!pos) return;
    var fab = this.fabEl;
    fab.style.top = 'auto';
    fab.style.left = 'auto';
    fab.style.right = 'auto';
    fab.style[pos.side] = CHAT_EDGE + 'px';
    this.dragged = true;
    this.applyBottom(Math.max(pos.bottom, 0));
    this.placeWidget();
  };

  Chatbot.prototype.applyBottom = function (bottom) {
    var vh = window.innerHeight;
    var h = this.fabEl.offsetHeight || 58;
    bottom = Math.min(Math.max(bottom, 12), Math.max(vh - h - 12, 12));
    this.fabEl.style.bottom = 'calc(' + Math.round(bottom) + 'px + env(safe-area-inset-bottom))';
  };

  Chatbot.prototype.initDrag = function () {
    var self = this;
    var fab = this.fabEl;
    var THRESHOLD = 6;
    var startX = 0, startY = 0, startLeft = 0, startTop = 0;
    var dragging = false, pointerId = null;

    function clamp(value, min, max) {
      return Math.min(Math.max(value, min), max);
    }

    fab.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      pointerId = e.pointerId;
      startX = e.clientX;
      startY = e.clientY;
      var rect = fab.getBoundingClientRect();
      startLeft = rect.left;
      startTop = rect.top;
      dragging = false;
      try { fab.setPointerCapture(pointerId); } catch (err) {}
    });

    fab.addEventListener('pointermove', function (e) {
      if (pointerId === null || e.pointerId !== pointerId) return;
      var dx = e.clientX - startX;
      var dy = e.clientY - startY;

      if (!dragging) {
        if (Math.sqrt(dx * dx + dy * dy) < THRESHOLD) return;
        dragging = true;
        self.dragged = true;
        fab.classList.add('dragging');
      }

      var margin = 8;
      var vw = window.innerWidth, vh = window.innerHeight;
      var w = fab.offsetWidth || 58, h = fab.offsetHeight || 58;
      var left = clamp(startLeft + dx, margin, vw - w - margin);
      var top = clamp(startTop + dy, margin, vh - h - margin);

      fab.style.right = 'auto';
      fab.style.bottom = 'auto';
      fab.style.left = Math.round(left) + 'px';
      fab.style.top = Math.round(top) + 'px';

      e.preventDefault();
    });

    function endDrag(e) {
      if (pointerId === null || (e.pointerId !== undefined && e.pointerId !== pointerId)) return;
      try { fab.releasePointerCapture(pointerId); } catch (err) {}
      pointerId = null;

      if (!dragging) return;
      dragging = false;
      fab.classList.remove('dragging');
      self.suppressClick = true;
      setTimeout(function () { self.suppressClick = false; }, 0);

      var vw = window.innerWidth, vh = window.innerHeight;
      var rect = fab.getBoundingClientRect();
      var side = (rect.left + rect.width / 2) < vw / 2 ? 'left' : 'right';

      fab.classList.add('snapping');
      fab.style.top = 'auto';
      fab.style.left = 'auto';
      fab.style.right = 'auto';
      fab.style[side] = CHAT_EDGE + 'px';
      var bottom = vh - rect.top - rect.height;
      self.applyBottom(bottom);
      setTimeout(function () { fab.classList.remove('snapping'); }, 240);

      try {
        localStorage.setItem(CHAT_POS_KEY, JSON.stringify({ side: side, bottom: bottom }));
      } catch (err) {}

      self.placeWidget();
    }

    fab.addEventListener('pointerup', endDrag);
    fab.addEventListener('pointercancel', endDrag);
  };

  Chatbot.prototype.placeWidget = function () {
    if (!this.widgetEl || !this.dragged) return;

    var widget = this.widgetEl;
    var fabRect = this.fabEl.getBoundingClientRect();
    var vw = window.innerWidth, vh = window.innerHeight;
    widget.style.right = 'auto';
    widget.style.left = 'auto';
    widget.style.bottom = 'auto';
    widget.style.top = 'auto';

    var w = widget.offsetWidth || Math.min(vw * 0.92, 360);
    var gap = 10;

    if ((fabRect.left + fabRect.width / 2) < vw / 2) {
      widget.style.left = Math.round(clampEdge(fabRect.left, 8, Math.max(vw - w - 8, 8))) + 'px';
    } else {
      widget.style.right = Math.round(clampEdge(vw - fabRect.right, 8, Math.max(vw - w - 8, 8))) + 'px';
    }

    var spaceAbove = fabRect.top;
    var spaceBelow = vh - fabRect.bottom;

    if (spaceAbove > spaceBelow) {
      widget.style.bottom = Math.round(vh - fabRect.top + gap) + 'px';
    } else {
      widget.style.top = Math.round(fabRect.bottom + gap) + 'px';
    }
  };

  function clampEdge(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  Chatbot.prototype.bindConnectivity = function () {
    var self = this;
    window.addEventListener('egliane:connectivity', function (ev) {
      self.offline = ev.detail.offline;
      if (self.inputEl) self.inputEl.disabled = self.offline;
      if (self.sendBtn) self.sendBtn.disabled = self.offline;
    });
  };

  Chatbot.prototype.loadConfig = function () {
    var self = this;
    var cached = null;
    try { cached = JSON.parse(localStorage.getItem('egliane:chatbot:cfg')); } catch (e) {}

    if (cached) this.cfg = cached;

    fetch('/chatbot/config')
      .then(function (res) { return res.json(); })
      .then(function (cfg) {
        self.cfg = cfg;
        try { localStorage.setItem('egliane:chatbot:cfg', JSON.stringify(cfg)); } catch (e) {}
      })
      .catch(function () { /* offline: keep cached cfg */ });
  };

  Chatbot.prototype.toggle = function (open) {
    if (typeof open !== 'boolean') open = !this.open;
    this.open = open;
    if (this.widgetEl) this.widgetEl.classList.toggle('open', open);
    if (open) {
      // Move focus into the input when opened so screen-reader/keyboard users
      // can start typing immediately.
      if (this.inputEl) setTimeout(function () { this.inputEl.focus(); }.bind(this), 250);
    } else if (this.fabEl) {
      // Return focus to the trigger button when closed.
      this.fabEl.focus();
    }
  };

  Chatbot.prototype.showWelcome = function () {
    var cfg = this.cfg;
    var text = cfg && cfg.welcome_message ? cfg.welcome_message : 'Hello! How can I help you today?';
    this.addMessage(text, 'bot');
  };

  Chatbot.prototype.addMessage = function (text, who, extra) {
    if (!this.messagesEl) return;
    var div = document.createElement('div');
    div.className = 'msg msg-' + who;
    var time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    div.innerHTML = text;
    if (extra) div.classList.add(extra);
    var meta = document.createElement('span');
    meta.className = 'meta';
    meta.textContent = time;
    div.appendChild(meta);
    this.messagesEl.appendChild(div);
    this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    return div;
  };

  Chatbot.prototype.userSay = function (raw) {
    if (!raw || !raw.trim()) return;
    var text = raw.trim();
    this.inputEl.value = '';
    this.addMessage(this.escape(text), 'user');

    if (this.offline) {
      E.pushOutbox({ type: 'chat', text: text });
      var queued = this.addMessage('You are offline — your message is queued and will sync when you reconnect.', 'bot', 'pending');
      this.addMessage(this.respondTo(text), 'bot');
      return;
    }

    var pending = this.addMessage('…', 'bot', 'pending');
    var self = this;
    setTimeout(function () {
      if (pending && pending.parentNode) pending.parentNode.removeChild(pending);
      self.addMessage(self.respondTo(text), 'bot');
    }, 420);
  };

  Chatbot.prototype.respondTo = function (text) {
    var cfg = this.cfg;
    var rules = (cfg && cfg.rules) || [];
    var normalized = text.toLowerCase();

    for (var i = 0; i < rules.length; i++) {
      var rule = rules[i];
      if (!rule || !rule.keywords) continue;
      for (var k = 0; k < rule.keywords.length; k++) {
        if (normalized.indexOf(rule.keywords[k].toLowerCase()) !== -1) {
          return this.markdown(rule.response);
        }
      }
    }

    var fb = (cfg && cfg.fallback_message) || "I'm not sure about that one yet.";
    var url = (cfg && cfg.messenger_url) || 'https://www.facebook.com/harris.egliane.2025';
    return this.markdown(fb) +
      '<br><br><a href="' + url + '" target="_blank" rel="noopener">Message us on Messenger →</a>';
  };

  Chatbot.prototype.markdown = function (text) {
    return this.escape(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  };

  Chatbot.prototype.escape = function (text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  };

  document.addEventListener('DOMContentLoaded', function () {
    verifyMobileLayout();
    var root = document.getElementById('chatWidget');
    if (root) window.egliane.chatbot = new Chatbot(root.getAttribute('data-config'));
  });

  /* ---------- Double-submit protection ----------
     Disable submit buttons (with a loading label) on any native form submit
     so an accidental double-click/multi-submit can't create duplicate records,
     send duplicate notifications, or double-charge. Buttons are re-enabled if
     the submit is cancelled (returning false from onsubmit sets
     defaultPrevented, e.g. a confirm() dialog the user dismissed), and again
     via pageshow as a safety net when the page is revisited (e.g. after a
     validation-error redirect that re-renders the form). AJAX-driven forms
     ignore this because their submit handlers preventDefault(). Opt out of the
     guard per-form with data-no-disable="1" (e.g. forms that reload segments). */
  function restoreSubmitButtons(root) {
    var btns = root.querySelectorAll('button[data-submit-guard]');
    for (var i = 0; i < btns.length; i++) {
      var b = btns[i];
      if (typeof b.dataset.origHtml === 'string') b.innerHTML = b.dataset.origHtml;
      b.disabled = false;
      b.removeAttribute('data-submit-guard');
      delete b.dataset.origHtml;
    }
  }

  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;
    if (form.getAttribute('data-no-disable') === '1') return;
    if (form.getAttribute('target') === '_blank') return;

    var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      if (btn.disabled) continue;
      var isInput = btn.nodeName === 'INPUT';
      btn.setAttribute('data-submit-guard', '1');
      btn.dataset.origHtml = isInput ? (btn.value || '') : btn.innerHTML;
      btn.disabled = true;
      if (!isInput) {
        var isGet = (form.getAttribute('method') || 'get').toLowerCase() === 'get';
        var label = form.getAttribute('data-submit-label') || (isGet ? 'Loading…' : 'Saving…');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + label;
      }
    }
  });

  /* ---------- BIR forms: async applicability toggle ----------
     Selecting a form used to submit a native POST and reload the page.
     Intercept the submit on .bir-toggle-form, POST via fetch (same endpoint,
     CSRF header included), optimistically flip the icon, and keep the page in
     place. The per-form pending flag blocks duplicate/concurrent requests;
     on failure the previous state is restored and a toast explains the error.
     With JavaScript disabled the native submit still works. */
  var birToggleForms = document.querySelectorAll('.inline-form.bir-toggle-form');
  for (var bi = 0; bi < birToggleForms.length; bi++) {
    birToggleForms[bi].addEventListener('submit', birToggleSubmit);
  }

  function birSetState(btn, on) {
    btn.classList.toggle('bir-toggle-on', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function birRefreshCounts(form) {
    var scope = form.closest('.cv-card') || form.closest('tr');
    if (!scope) return;
    var total = scope.querySelectorAll('.inline-form.bir-toggle-form').length;
    var on = scope.querySelectorAll('.bir-toggle.bir-toggle-on').length;
    var badges = scope.querySelectorAll('.bir-count-badge');
    for (var j = 0; j < badges.length; j++) {
      badges[j].textContent = on + '/' + total;
      badges[j].classList.toggle('badge-success', on > 0);
      badges[j].classList.toggle('badge-neutral', on === 0);
    }
  }

  function birToggleSubmit(e) {
    e.preventDefault();
    var form = e.target;
    if (!form || form.nodeName !== 'FORM' || form.dataset.pending === '1') return;

    var btn = form.querySelector('.bir-toggle');
    var token = form.querySelector('input[name="_token"]');
    var formType = form.querySelector('input[name="form_type"]');
    if (!btn || !token || !formType) return;

    var wasOn = btn.classList.contains('bir-toggle-on');
    var savedTitle = btn.title;

    birSetState(btn, !wasOn);
    form.dataset.pending = '1';
    btn.disabled = true;
    btn.classList.add('bir-toggle-pending');
    btn.title = 'Saving…';

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token.value, 'Accept': 'application/json' },
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json()
          .catch(function () { return null; })
          .then(function (data) {
            if (!res.ok || !data || data.ok === false) {
              throw new Error(data && data.message ? data.message : 'Could not update this form. Please try again.');
            }
            return data;
          });
      })
      .then(function (data) {
        var on = !!data.applicable;
        birSetState(btn, on);
        btn.title = formType.value + ': ' + (on ? 'Applicable' : 'Not applicable');
        birRefreshCounts(form);
        E.toast(data.message || formType.value + ' updated.', 'success');
      })
      .catch(function (err) {
        birSetState(btn, wasOn);
        btn.title = savedTitle;
        E.toast(err && err.message ? err.message : 'Could not update this form. Please try again.', 'error');
      })
      .finally(function () {
        form.dataset.pending = '';
        btn.classList.remove('bir-toggle-pending');
        btn.disabled = false;
      });
  }

  /* ---------- Interactive onboarding tour ----------
     Canva-style tutorial spotlighted over the live dashboard UI. Rendered by
     <x-onboarding /> only on the dashboard layout. Auto-shows once per role
     (localStorage, keyed by a version so future tours replay for everyone) and
     can be replayed from the Help / profile pages. */
  var ONB_VERSION = '1';
  var ONB_VERSION_KEY = 'eas_onboarding_version';
  var ONB_DONE_PREFIX = 'eas_onboarding_done_';
  var ONB_REPLAY_PREFIX = 'eas_onboarding_replay_';
  var ONB_MOBILE_BP = 900;

  var onboardRoot = document.getElementById('easOnboarding');
  var onboardRole = onboardRoot ? (onboardRoot.getAttribute('data-role') || '') : '';
  var onboardSteps = [];
  try {
    var onboardRaw = document.getElementById('easOnboardSteps');
    if (onboardRaw && onboardRaw.textContent) {
      var onboardParsed = JSON.parse(onboardRaw.textContent);
      if (onboardParsed && onboardParsed.steps) onboardSteps = onboardParsed.steps;
    }
  } catch (e) { onboardSteps = []; }

  var onboardCard = document.getElementById('easOnboardCard');
  var onboardTitle = document.getElementById('easOnboardTitle');
  var onboardDesc = document.getElementById('easOnboardDesc');
  var onboardProgress = document.getElementById('easOnboardProgress');
  var onboardDots = document.getElementById('easOnboardDots');
  var onboardNext = document.getElementById('easOnboardNext');
  var onboardBack = document.getElementById('easOnboardBack');
  var onboardSkip = document.getElementById('easOnboardSkip');
  var onboardClose = document.getElementById('easOnboardClose');
  var onboardPanels = [
    document.querySelector('.eas-onboard-top'),
    document.querySelector('.eas-onboard-left'),
    document.querySelector('.eas-onboard-right'),
    document.querySelector('.eas-onboard-bottom'),
    document.querySelector('.eas-onboard-ring')
  ];

  var onboardDoneKey = onboardRole ? (ONB_DONE_PREFIX + onboardRole) : '';
  var onboardReplayKey = onboardRole ? (ONB_REPLAY_PREFIX + onboardRole) : '';

  var onboardVisible = [];
  var onboardIndex = 0;
  var onboardRunning = false;
  var onboardLastFocus = null;
  var onboardRaf = 0;
  var onboardOpenedDrawer = false;
  var onboardJustOpenedDrawer = false;
  var onboardTarget = null;

  function onboardStorageGet(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }
  function onboardStorageSet(key, val) {
    try { localStorage.setItem(key, val); } catch (e) { /* storage disabled — tour still works */ }
  }
  function onboardStorageDel(key) {
    try { localStorage.removeItem(key); } catch (e) { /* no-op */ }
  }

  function onboardMarkDone() {
    onboardStorageSet(ONB_VERSION_KEY, ONB_VERSION);
    if (onboardDoneKey) onboardStorageSet(onboardDoneKey, '1');
    if (onboardReplayKey) onboardStorageDel(onboardReplayKey);
  }

  function onboardShouldAutoStart() {
    if (!onboardRoot || !onboardRole) return false;
    if (onboardStorageGet(ONB_VERSION_KEY) !== ONB_VERSION) return true;
    if (onboardStorageGet(onboardDoneKey) !== '1') return true;
    if (onboardStorageGet(onboardReplayKey) === '1') return true;
    return false;
  }

  function onboardIsMobile() {
    return window.matchMedia('(max-width: ' + ONB_MOBILE_BP + 'px)').matches;
  }

  function onboardElVisible(el) {
    if (!el) return false;
    var r = el.getBoundingClientRect();
    if (r.width <= 0 || r.height <= 0) return false;
    if (r.right <= 0 || r.left >= window.innerWidth || r.bottom <= 0 || r.top >= window.innerHeight) return false;
    for (var a = el; a && a !== document.documentElement; a = a.parentElement) {
      var cs = getComputedStyle(a);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    }
    return true;
  }

  function onboardResolveTarget(step) {
    if (step.final) return null;
    var selectors = step.selectors || [];
    for (var i = 0; i < selectors.length; i++) {
      try {
        var el = document.querySelector(selectors[i]);
        if (onboardElVisible(el)) return el;
      } catch (e) { /* bad selector — try next */ }
    }
    return null;
  }

  function onboardIsSidebarStep(step) {
    return /#dashDrawer/.test((step.selectors || []).join(' '));
  }

  function onboardPickTarget(step) {
    onboardJustOpenedDrawer = false;
    var isSidebar = onboardIsSidebarStep(step);
    if (!isSidebar && onboardOpenedDrawer && drawer && drawer.classList.contains('open')) {
      setDrawer(false);
      onboardOpenedDrawer = false;
      onboardJustOpenedDrawer = true;
    }
    if (step.final) { onboardTarget = null; return; }
    var el = onboardResolveTarget(step);
    if (!el && isSidebar && onboardIsMobile()) {
      if (drawer && !drawer.classList.contains('open')) {
        setDrawer(true);
        onboardOpenedDrawer = true;
        onboardJustOpenedDrawer = true;
      }
      el = onboardResolveTarget(step);
    }
    onboardTarget = el || null;
  }

  function onboardEnsureInView(el) {
    if (!el) return;
    var cs = getComputedStyle(el);
    if (cs.position === 'fixed' || cs.position === 'sticky') return;
    var r = el.getBoundingClientRect();
    var c = r.top + r.height / 2;
    if (c > 96 && c < window.innerHeight - 96) return;
    try { el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' }); }
    catch (e) { el.scrollIntoView(true); }
  }

  function onboardPosition() {
    if (!onboardRunning) return;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var topbarH = 64;

    if (!onboardTarget) {
      var cardW = onboardCard.offsetWidth || 320;
      var cardH = onboardCard.offsetHeight || 220;
      for (var p = 0; p < onboardPanels.length; p++) { if (onboardPanels[p]) onboardPanels[p].style.opacity = p < 4 ? '0.35' : '0'; }
      onboardCard.style.top = Math.max(topbarH + 10, Math.round((vh - cardH) * 0.42)) + 'px';
      onboardCard.style.left = Math.max(10, Math.round((vw - cardW) / 2)) + 'px';
      onboardCard.style.transform = 'translateY(0)';
      onboardCard.setAttribute('data-arrow', 'none');
      return;
    }

    var r = onboardTarget.getBoundingClientRect();
    var pad = 8;
    var hx = Math.max(0, r.left - pad);
    var hy = Math.max(0, r.top - pad);
    var hw = Math.min(vw, r.width + pad * 2);
    var hh = Math.min(vh, r.height + pad * 2);

    var t = hx;
    var l = hy;
    var ri = Math.max(0, vw - (hx + hw));
    var b = Math.max(0, vh - (hy + hh));

    if (onboardPanels[0]) {
      onboardPanels[0].style.top = '0';
      onboardPanels[0].style.left = '0';
      onboardPanels[0].style.width = vw + 'px';
      onboardPanels[0].style.height = hy + 'px';
    }
    if (onboardPanels[1]) {
      onboardPanels[1].style.top = hy + 'px';
      onboardPanels[1].style.left = '0';
      onboardPanels[1].style.width = hx + 'px';
      onboardPanels[1].style.height = hh + 'px';
    }
    if (onboardPanels[2]) {
      onboardPanels[2].style.top = hy + 'px';
      onboardPanels[2].style.left = (hx + hw) + 'px';
      onboardPanels[2].style.width = ri + 'px';
      onboardPanels[2].style.height = hh + 'px';
    }
    if (onboardPanels[3]) {
      onboardPanels[3].style.top = (hy + hh) + 'px';
      onboardPanels[3].style.left = '0';
      onboardPanels[3].style.width = vw + 'px';
      onboardPanels[3].style.height = b + 'px';
    }
    if (onboardPanels[4]) {
      onboardPanels[4].style.left = hx + 'px';
      onboardPanels[4].style.top = hy + 'px';
      onboardPanels[4].style.width = hw + 'px';
      onboardPanels[4].style.height = hh + 'px';
    }
    for (var q = 0; q < 5; q++) { if (onboardPanels[q]) onboardPanels[q].style.opacity = '1'; }

    var cardW = onboardCard.offsetWidth || 320;
    var cardH = onboardCard.offsetHeight || 220;
    var gap = 14;
    var below = vh - (r.bottom + gap);
    var above = r.top - gap - topbarH;
    var place;
    if (above >= cardH) place = 'above';
    else if (below >= cardH) place = 'below';
    else place = (above >= below) ? 'above' : 'below';

    var cardX = r.left + r.width / 2 - cardW / 2;
    var cardY = place === 'above' ? (r.top - gap - cardH) : (r.bottom + gap);
    cardX = Math.max(10, Math.min(cardX, vw - cardW - 10));
    if (cardY < topbarH + 10) cardY = topbarH + 12;
    if (cardY + cardH > vh - 10) cardY = Math.max(topbarH + 12, vh - cardH - 10);

    onboardCard.style.top = cardY + 'px';
    onboardCard.style.left = cardX + 'px';
    onboardCard.style.transform = 'translateY(0)';

    var arrowPct = (r.left + r.width / 2 - cardX) / cardW * 100;
    arrowPct = Math.max(16, Math.min(arrowPct, 84));
    onboardCard.style.setProperty('--eas-arrow-x', arrowPct + '%');
    onboardCard.setAttribute('data-arrow', place === 'above' ? 'down' : 'up');
  }

  function onboardSchedule() {
    if (onboardRaf) cancelAnimationFrame(onboardRaf);
    onboardRaf = requestAnimationFrame(function () {
      onboardRaf = 0;
      if (!onboardRunning) return;
      if (onboardTarget && !onboardElVisible(onboardTarget)) {
        onboardEnsureInView(onboardTarget);
      }
      onboardPosition();
    });
  }

  function onboardRender() {
    var step = onboardVisible[onboardIndex];
    if (!step) return;
    onboardTitle.textContent = step.title || '';
    onboardDesc.textContent = step.desc || '';
    onboardProgress.textContent = (onboardIndex + 1) + ' of ' + onboardVisible.length;
    onboardDots.innerHTML = '';
    for (var i = 0; i < onboardVisible.length; i++) {
      var dot = document.createElement('span');
      dot.className = 'eas-onboard-dot' + (i === onboardIndex ? ' on' : '');
      onboardDots.appendChild(dot);
    }
    var isLast = onboardIndex >= onboardVisible.length - 1;
    onboardNext.textContent = isLast ? (step.confirm || 'Finish') : 'Next';
    onboardBack.disabled = onboardIndex === 0;
  }

  function onboardGoto(idx) {
    if (!onboardVisible.length || !onboardRunning) return;
    onboardIndex = Math.max(0, Math.min(idx, onboardVisible.length - 1));
    var step = onboardVisible[onboardIndex];
    onboardPickTarget(step);
    onboardRender();
    if (onboardTarget) onboardEnsureInView(onboardTarget);
    if (onboardJustOpenedDrawer) {
      setTimeout(function () {
        if (!onboardRunning) return;
        var cur = onboardVisible[onboardIndex];
        if (cur && !cur.final && !onboardTarget && onboardIsSidebarStep(cur)) {
          onboardTarget = onboardResolveTarget(cur);
          if (onboardTarget) onboardEnsureInView(onboardTarget);
        }
        onboardPosition();
        onboardCard.focus({ preventScroll: true });
      }, 340);
    } else {
      onboardPosition();
      onboardCard.focus({ preventScroll: true });
    }
  }

  function onboardFocusables() {
    return Array.prototype.filter.call(
      onboardCard.querySelectorAll('button'),
      function (b) { return !b.disabled; }
    );
  }

  function onboardTrapFocus(e) {
    var items = onboardFocusables();
    if (!items.length) { e.preventDefault(); onboardCard.focus(); return; }
    var first = items[0];
    var last = items[items.length - 1];
    var active = document.activeElement;
    if (e.shiftKey) {
      if (active === first || !onboardCard.contains(active)) {
        e.preventDefault();
        last.focus();
      }
    } else {
      if (active === last || !onboardCard.contains(active)) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  function onboardKeydown(e) {
    if (e.key === 'Escape') { e.preventDefault(); onboardCloseTour(); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); onboardNextClick(); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); if (onboardIndex > 0) onboardGoto(onboardIndex - 1); }
    else if (e.key === 'Tab') { onboardTrapFocus(e); }
  }

  function onboardNextClick() {
    if (onboardIndex >= onboardVisible.length - 1) {
      onboardCloseTour();
      return;
    }
    onboardGoto(onboardIndex + 1);
  }

  function onboardBackClick() { if (onboardIndex > 0) onboardGoto(onboardIndex - 1); }
  function onboardSkipClick() { onboardCloseTour(); }
  function onboardCloseClick() { onboardCloseTour(); }

  function onboardBindEvents() {
    window.addEventListener('resize', onboardSchedule);
    window.addEventListener('orientationchange', onboardSchedule);
    window.addEventListener('scroll', onboardSchedule, true);
    document.addEventListener('keydown', onboardKeydown);
    onboardNext.addEventListener('click', onboardNextClick);
    onboardBack.addEventListener('click', onboardBackClick);
    onboardSkip.addEventListener('click', onboardSkipClick);
    onboardClose.addEventListener('click', onboardCloseClick);
  }

  function onboardUnbindEvents() {
    window.removeEventListener('resize', onboardSchedule);
    window.removeEventListener('orientationchange', onboardSchedule);
    window.removeEventListener('scroll', onboardSchedule, true);
    document.removeEventListener('keydown', onboardKeydown);
    onboardNext.removeEventListener('click', onboardNextClick);
    onboardBack.removeEventListener('click', onboardBackClick);
    onboardSkip.removeEventListener('click', onboardSkipClick);
    onboardClose.removeEventListener('click', onboardCloseClick);
  }

  function onboardCloseTour() {
    if (!onboardRunning) return;
    onboardRunning = false;
    onboardJustOpenedDrawer = false;
    if (onboardRaf) cancelAnimationFrame(onboardRaf);
    if (onboardOpenedDrawer && drawer && drawer.classList.contains('open')) setDrawer(false);
    onboardOpenedDrawer = false;
    onboardUnbindEvents();
    onboardRoot.hidden = true;
    onboardRoot.classList.remove('running');
    if (onboardLastFocus && document.contains(onboardLastFocus)) {
      try { onboardLastFocus.focus({ preventScroll: true }); } catch (e) { onboardLastFocus.focus(); }
    }
    onboardMarkDone();
  }

  function onboardStart(auto) {
    if (!onboardRoot || onboardRunning) return;
    onboardVisible = [];
    for (var i = 0; i < onboardSteps.length; i++) {
      var step = onboardSteps[i];
      if (step.final || onboardResolveTarget(step)) { onboardVisible.push(step); continue; }
      if (step && onboardIsSidebarStep(step) && onboardIsMobile() && drawer) {
        for (var k = 0; k < (step.selectors || []).length; k++) {
          if (String(step.selectors[k]).indexOf('#dashDrawer') === 0 && document.querySelector(step.selectors[k])) {
            onboardVisible.push(step);
            break;
          }
        }
      }
    }
    if (!onboardVisible.length) return;
    if (!auto) onboardStorageDel(onboardReplayKey);
    onboardLastFocus = document.activeElement;
    onboardIndex = 0;
    onboardRunning = true;
    onboardOpenedDrawer = false;
    onboardRoot.hidden = false;
    onboardRoot.classList.add('running');
    onboardBindEvents();
    onboardGoto(0);
  }

  if (onboardRoot && onboardShouldAutoStart()) onboardStart(true);

  /* Replay entry points (Help page + profile pages). Buttons carry the role so
     the site-layout Help page can pre-arm the tour for the correct account. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-onboarding-replay]');
    if (!btn) return;
    e.preventDefault();
    var role = btn.getAttribute('data-onboarding-role') || onboardRole;
    if (onboardRoot) {
      if (!onboardRunning) onboardStart(false);
    } else if (role) {
      onboardStorageSet(ONB_REPLAY_PREFIX + role, '1');
      var dash = btn.getAttribute('data-onboarding-dash');
      if (dash) { window.location.assign(dash); return; }
    }
  });

  window.addEventListener('pageshow', function () { restoreSubmitButtons(document); });
})();
