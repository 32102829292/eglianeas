/* Egliane — admin billing statement auto-calculations */
(function () {
  'use strict';

  var form = document.getElementById('billingForm');
  if (!form) return;

  var sales = document.getElementById('sales');
  var rate = document.getElementById('rate_2551q');
  var tax2551q = document.getElementById('tax_2551q');
  var totalDisplay = document.getElementById('totalDisplay');
  var moneyInputs = form.querySelectorAll('[data-money]');

  function round2(value) {
    var n = parseFloat(value);
    if (isNaN(n)) return 0;
    return Math.round(n * 100) / 100;
  }

  function money(value) {
    return '\u20B1' + round2(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function computeTotal() {
    var total = 0;
    for (var i = 0; i < moneyInputs.length; i++) {
      total += round2(moneyInputs[i].value);
    }
    if (totalDisplay) totalDisplay.textContent = money(total);
  }

  function compute2551q() {
    if (!tax2551q) return;
    var s = round2(sales ? sales.value : 0);
    var r = round2(rate ? rate.value : 3);
    tax2551q.value = round2(s * r / 100).toFixed(2);
    computeTotal();
  }

  function attachMoneyListener(el) {
    el.addEventListener('input', computeTotal);
    if (el.tagName === 'SELECT') {
      el.addEventListener('change', computeTotal);
    }
  }

  if (sales) sales.addEventListener('input', compute2551q);
  if (rate) rate.addEventListener('input', compute2551q);
  for (var i = 0; i < moneyInputs.length; i++) {
    attachMoneyListener(moneyInputs[i]);
  }

  computeTotal();
})();
