/* Egliane Accounting Services — branded confirmation modal helper.
   Replaces native confirm() dialogs app-wide. Requires Bootstrap 5.3.3 JS
   (bootstrap.bundle) and the #eglianeConfirmModal markup included once per page.

   Usage:
     - Form (inline onsubmit): onsubmit="return Egliane.confirm.form(this, { title: '...', message: '...', danger: true })"
     - Submit button (inline onclick): onclick="return Egliane.confirm.button(this, { title: '...', message: '...', danger: true })"
     - Programmatic: Egliane.confirm.action({ title: '...', message: '...', danger: true, confirmLabel: 'Delete' }, function () { ... })
   Only the "Confirm" (primary) button runs the action; Cancel / Esc / backdrop close do nothing. */
(function () {
  'use strict';

  if (typeof bootstrap === 'undefined') return;

  var modalEl = document.getElementById('eglianeConfirmModal');
  if (!modalEl) return;

  var titleEl = document.getElementById('eglianeConfirmTitle');
  var messageEl = document.getElementById('eglianeConfirmMessage');
  var okBtn = document.getElementById('eglianeConfirmOk');
  var modal = new bootstrap.Modal(modalEl);

  var pendingForm = null;
  var pendingAction = null;
  var approvedForm = null;

  var E = (window.egliane = window.egliane || {});

  function show(opts) {
    opts = opts || {};
    titleEl.textContent = opts.title || 'Are you sure?';
    messageEl.textContent = opts.message || '';
    okBtn.textContent = opts.confirmLabel || 'Confirm';
    okBtn.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary');
    modalEl.classList.toggle('confirm-modal-danger', !!opts.danger);
    modal.show();
  }

  okBtn.addEventListener('click', function () {
    var form = pendingForm;
    var action = pendingAction;
    pendingForm = null;
    pendingAction = null;
    modal.hide();
    if (form) {
      approvedForm = form;
      if (form.requestSubmit) {
        // Keeps native HTML5 validation; the submit event re-enters Egliane.confirm.form()
        // which sees the approved form and lets the browser submit normally.
        form.requestSubmit();
      } else {
        form.submit();
      }
    } else if (action) {
      action();
    }
  });

  modalEl.addEventListener('shown.bs.modal', function () {
    okBtn.focus();
  });

  modalEl.addEventListener('hidden.bs.modal', function () {
    pendingForm = null;
    pendingAction = null;
    approvedForm = null;
  });

  E.confirm = {
    form: function (form, opts) {
      if (!form) return true;
      if (approvedForm === form) {
        approvedForm = null;
        return true;
      }
      pendingForm = form;
      pendingAction = null;
      show(opts);
      return false;
    },

    button: function (button, opts) {
      var form = button && button.form ? button.form : null;
      if (form && (button.type === 'submit' || button.type === '' || button.type === undefined || button.type === null)) {
        return E.confirm.form(form, opts);
      }
      E.confirm.action(opts);
      return false;
    },

    action: function (opts, callback) {
      pendingForm = null;
      pendingAction = callback || null;
      show(opts);
    }
  };
})();