/* Egliane Accounting Services — global app logic */
(function () {
  'use strict';

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

  function updateOnlineState() {
    var offline = !navigator.onLine;
    if (banner) banner.classList.toggle('show', offline);
    document.body.classList.toggle('is-offline', offline);
    window.dispatchEvent(new CustomEvent('egliane:connectivity', { detail: { offline: offline } }));
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
  E.toast = function (message) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = message;
    toastEl.classList.add('show');
    clearTimeout(E.toast._t);
    E.toast._t = setTimeout(function () {
      toastEl.classList.remove('show');
    }, 3200);
  };

  /* ---------- Mobile nav toggle ---------- */
  var navToggle = document.getElementById('navToggle');
  var mobileNav = document.getElementById('mobileNav');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function () {
      mobileNav.classList.toggle('open');
    });
  }

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

  function setDrawer(open) {
    if (!drawer || !backdrop) return;
    drawer.classList.toggle('open', open);
    backdrop.classList.toggle('open', open);
    if (hamburger) hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.style.overflow = open ? 'hidden' : '';
    verifyMobileLayout();
  }

  if (hamburger) hamburger.addEventListener('click', function () { setDrawer(true); });
  if (drawerClose) drawerClose.addEventListener('click', function () { setDrawer(false); });
  if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('a')) setDrawer(false);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) setDrawer(false);
  });

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
    if (open && this.inputEl) setTimeout(function () { this.inputEl.focus(); }.bind(this), 250);
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
})();
