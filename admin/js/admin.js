/* Casinos.cc Tools – admin screens. */
(function () {
  'use strict';

  // Form / JSON tabs on the edit screen
  var mode = document.getElementById('cct-mode');
  document.querySelectorAll('.cct-tabs .nav-tab').forEach(function (tab) {
    tab.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelectorAll('.cct-tabs .nav-tab').forEach(function (t) { t.classList.toggle('nav-tab-active', t === tab); });
      document.querySelectorAll('.cct-tab').forEach(function (p) { p.hidden = '#' + p.id !== tab.getAttribute('href'); });
      if (mode) mode.value = tab.getAttribute('data-mode');
    });
  });

  // Required fields live in the form tab only; don't block saving from the JSON tab.
  var form = document.getElementById('cct-form');
  if (form) {
    form.addEventListener('submit', function () {
      if (mode && mode.value === 'json') {
        form.querySelectorAll('#cct-tab-form [required]').forEach(function (el) { el.removeAttribute('required'); });
      }
    });
  }

  // Repeater rows
  document.querySelectorAll('.cct-rep').forEach(function (rep) {
    var tbody = rep.querySelector('tbody');
    var tpl = rep.querySelector('template');
    var next = tbody.querySelectorAll('tr').length;
    rep.querySelector('.cct-rep__add').addEventListener('click', function () {
      var html = tpl.innerHTML.split('__i__').join(String(next++));
      tbody.insertAdjacentHTML('beforeend', html);
      var first = tbody.lastElementChild.querySelector('input, select');
      if (first) first.focus();
    });
    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('.cct-rep__remove');
      if (btn) btn.closest('tr').remove();
    });
  });

  // Confirm destructive links
  document.querySelectorAll('.cct-confirm').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (!window.confirm(a.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();
