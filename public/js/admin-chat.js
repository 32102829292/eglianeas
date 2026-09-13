/* Egliane Assistant — /admin/chatbot conversation page.
   Reuses the shared response engine (window.egliane.chatReply / chatEscape)
   defined in app.js, so this page answers with exactly the same rules,
   welcome/fallback messages and Messenger link as the floating chatbot. */
(function () {
  'use strict';

  var E = window.egliane || {};

  var messagesEl = null;
  var inputEl = null;
  var sendBtn = null;
  var typingEl = null;
  var quickEl = null;
  var chatView = null;
  var settingsWrap = null;
  var settingsToggle = null;
  var settingsForm = null;
  var cfg = null;
  var busy = false;
  var welcomeShown = false;

  function $(id) { return document.getElementById(id); }

  function escapeHtml(text) {
    if (E.chatEscape) return E.chatEscape(text);
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function timeStr() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function scrollDown() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function addMessage(html, who) {
    var div = document.createElement('div');
    div.className = 'msg msg-' + who;
    var meta = document.createElement('span');
    meta.className = 'meta';
    meta.textContent = timeStr();

    if (who === 'bot') {
      var avatar = document.createElement('div');
      avatar.className = 'msg-avatar';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.textContent = 'E';
      var row = document.createElement('div');
      row.className = 'msg-row';
      var bubble = document.createElement('div');
      bubble.className = 'msg-bubble';
      bubble.innerHTML = html;
      row.appendChild(bubble);
      row.appendChild(meta);
      div.appendChild(avatar);
      div.appendChild(row);
    } else {
      var inner = document.createElement('div');
      inner.className = 'msg-user-inner';
      var bubble2 = document.createElement('div');
      bubble2.className = 'msg-bubble';
      bubble2.innerHTML = html;
      inner.appendChild(bubble2);
      inner.appendChild(meta);
      div.appendChild(inner);
    }

    messagesEl.appendChild(div);
    scrollDown();
    return div;
  }

  function addBot(html) { return addMessage(html, 'bot'); }
  function addUser(text) { return addMessage(escapeHtml(text), 'user'); }

  function ensureWelcome(c) {
    if (welcomeShown) return;
    welcomeShown = true;
    var text = (c && c.welcome_message) || 'Hello! How can I help you today?';
    addBot(escapeHtml(text));
  }

  function loadConfig() {
    try {
      var cached = JSON.parse(localStorage.getItem('egliane:chatbot:cfg'));
      if (cached) { cfg = cached; ensureWelcome(cached); }
    } catch (e) {}

    fetch('/chatbot/config')
      .then(function (res) { return res.json(); })
      .then(function (c) {
        cfg = c;
        ensureWelcome(c);
        try { localStorage.setItem('egliane:chatbot:cfg', JSON.stringify(c)); } catch (e) {}
      })
      .catch(function () { /* offline: keep cached config */ });
  }

  function autoGrow() {
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
  }

  function setBusy(b) {
    busy = b;
    inputEl.disabled = b;
    sendBtn.disabled = b;
    sendBtn.setAttribute('aria-disabled', String(b));
    inputEl.setAttribute('aria-busy', String(b));
  }

  function submit(forcedText) {
    if (busy) return;
    var text = typeof forcedText === 'string' ? forcedText : inputEl.value;
    if (!text || !text.trim()) return;
    text = text.trim();

    if (typeof forcedText !== 'string') {
      inputEl.value = '';
      autoGrow();
    }

    addUser(text);
    if (!E.chatReply) return;

    setBusy(true);
    typingEl.hidden = false;
    scrollDown();

    var reply = E.chatReply(text, cfg || {});
    setTimeout(function () {
      typingEl.hidden = true;
      addBot(reply);
      setBusy(false);
      if (typeof forcedText !== 'string') inputEl.focus();
    }, 700);
  }

  function setSettingsOpen(open) {
    if (!settingsWrap || !settingsToggle) return;
    settingsWrap.hidden = !open;
    settingsToggle.setAttribute('aria-expanded', String(open));
    var back = document.querySelectorAll('[data-back-to-chat]');
    back.forEach(function (el) { el.setAttribute('aria-expanded', String(open)); });
    if (chatView) chatView.hidden = open;
    if (open) {
      var firstInput = settingsWrap.querySelector('.form-control');
      if (firstInput) firstInput.focus();
    }
  }

  function init() {
    messagesEl = $('adminChatMessages');
    inputEl = $('adminChatInput');
    sendBtn = $('adminChatSend');
    typingEl = $('adminChatTyping');
    quickEl = $('adminChatQuick');
    chatView = $('adminChatView');
    settingsWrap = $('chatbotSettingsWrap');
    settingsToggle = $('chatbotSettingsToggle');
    settingsForm = $('chatbotSettingsForm');

    if (!messagesEl || !inputEl || !sendBtn || !typingEl) return;

    sendBtn.addEventListener('click', function () { submit(); });

    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
      }
    });
    inputEl.addEventListener('input', autoGrow);

    quickEl.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-q]');
      if (btn) submit(btn.getAttribute('data-q'));
    });

    if (settingsToggle && settingsWrap) {
      settingsToggle.addEventListener('click', function () {
        setSettingsOpen(settingsWrap.hasAttribute('hidden'));
      });
      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-back-to-chat]')) {
          setSettingsOpen(false);
        }
      });
    }

    if (settingsForm) {
      var saveBtn = $('chatbotSettingsSave');
      settingsForm.addEventListener('submit', function () {
        if (saveBtn) {
          saveBtn.disabled = true;
          saveBtn.classList.add('is-saving');
          saveBtn.textContent = 'Saving\u2026';
        }
      });
    }

    loadConfig();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();