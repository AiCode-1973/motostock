// MotoStock · comportamentos leves. Tudo funciona sem JS; isto só melhora a experiência.
(function () {
  'use strict';

  // Confirmação antes de ações sensíveis: <form data-confirm="Mensagem">
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
    // Estado de carregamento e proteção contra duplo envio.
    var btn = form.querySelector('button[type=submit]:not([data-no-loading]), button:not([type]):not([data-no-loading])');
    if (btn && form.method.toLowerCase() === 'post') {
      if (btn.dataset.sent) { ev.preventDefault(); return; }
      btn.dataset.sent = '1';
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
      if (btn.dataset.loading) { btn.textContent = btn.dataset.loading; }
    }
  });

  // Menu lateral no celular
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-nav-toggle]');
    if (t) { document.body.classList.toggle('nav-open'); t.setAttribute('aria-expanded', document.body.classList.contains('nav-open')); return; }
    if (document.body.classList.contains('nav-open') && !ev.target.closest('.sidebar')) { document.body.classList.remove('nav-open'); }
  });

  // Filtros que enviam ao mudar: <select data-autosubmit>
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // Linhas dinâmicas (venda no balcão, compras): <tbody data-rows> + <template data-row-template> + [data-add-row]
  document.querySelectorAll('[data-add-row]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var table = document.getElementById(btn.getAttribute('data-add-row'));
      var tpl = table.querySelector('template');
      var body = table.querySelector('tbody');
      var idx = body.querySelectorAll('tr').length;
      var html = tpl.innerHTML.replace(/__i__/g, String(idx + Date.now() % 100000));
      body.insertAdjacentHTML('beforeend', html);
      recalc(table);
    });
  });
  document.addEventListener('click', function (ev) {
    var rm = ev.target.closest('[data-remove-row]');
    if (rm) { var table = rm.closest('table'); rm.closest('tr').remove(); recalc(table); }
  });
  document.addEventListener('input', function (ev) { var t = ev.target.closest('table[data-calc]'); if (t) recalc(t); });
  document.addEventListener('change', function (ev) { var t = ev.target.closest('table[data-calc]'); if (t) recalc(t); });

  function parseMoney(v) { v = String(v || '').replace(/[R$\s.]/g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; }
  function fmt(n) { return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
  function recalc(table) {
    if (!table || !table.hasAttribute('data-calc')) return;
    var total = 0;
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var sel = tr.querySelector('select');
      var qty = parseInt((tr.querySelector('[data-qty]') || {}).value || '0', 10) || 0;
      var price = 0;
      var cost = tr.querySelector('[data-cost]');
      if (cost) { price = parseMoney(cost.value); }
      else if (sel && sel.selectedOptions[0]) { price = parseFloat(sel.selectedOptions[0].getAttribute('data-price') || '0') / 100; }
      var line = qty * price; total += line;
      var out = tr.querySelector('[data-line-total]'); if (out) out.textContent = fmt(line);
    });
    var t = document.querySelector('[data-grand-total="' + table.id + '"]');
    if (t) t.textContent = fmt(total);
  }
  document.querySelectorAll('table[data-calc]').forEach(recalc);

  // Endereço só quando a entrega exige: inputs com data-kind="delivery|pickup" + [data-address]
  function syncAddress() {
    var chosen = document.querySelector('input[name=shipping_method_id]:checked, select[name=shipping_method_id]');
    var box = document.querySelector('[data-address]');
    if (!box) return;
    var kind = 'pickup';
    if (chosen) {
      if (chosen.tagName === 'SELECT') { var o = chosen.selectedOptions[0]; kind = o ? (o.getAttribute('data-kind') || 'pickup') : 'pickup'; }
      else { kind = chosen.getAttribute('data-kind'); }
    }
    box.classList.toggle('is-hidden', kind !== 'delivery');
  }
  document.addEventListener('change', function (ev) { if (ev.target.name === 'shipping_method_id') syncAddress(); });
  syncAddress();

  // Galeria do produto
  document.querySelectorAll('[data-thumb]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      ev.preventDefault();
      var main = document.querySelector('[data-main-img]');
      if (main) main.src = a.getAttribute('href');
    });
  });
})();
