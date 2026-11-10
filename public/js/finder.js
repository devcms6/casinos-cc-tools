/*
 * Casinos.cc Tools – bonus finder ([cc_bonus_finder]).
 * Each finder carries its own data in <script type="application/json" class="cctf-data">.
 */
(function () {
  'use strict';

  var LOCALE = document.documentElement.lang || 'en';

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function init(root) {
    var payload;
    try { payload = JSON.parse(root.querySelector('.cctf-data').textContent); } catch (e) { return; }
    var D = payload.data, C = payload.config, T = C.i18n;
    var form = root.querySelector('form');
    var out = function (k) { return root.querySelector('[data-out="' + k + '"]'); };
    var t = function (key, vars) {
      var s = T[key] || key;
      Object.keys(vars || {}).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
      return s;
    };
    var fmt;
    try { fmt = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: C.currency, maximumFractionDigits: 0 }); }
    catch (e) { fmt = new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }); }
    var money = function (n) { return fmt.format(Math.round(n)); };

    var state = { type: (root.querySelector('.cctf__tab[aria-pressed="true"]') || {}).getAttribute ? root.querySelector('.cctf__tab[aria-pressed="true"]').getAttribute('data-type') : '', shown: C.limit };
    var range = form.range;

    function deposit() { return C.stops[+range.value]; }

    function syncSlider() {
      var v = deposit();
      out('val').textContent = money(v);
      range.setAttribute('aria-valuetext', money(v));
      range.style.setProperty('--fill', (range.value / (C.stops.length - 1) * 100) + '%');
    }

    function excluded(casino, method) {
      if (!method) return false;
      if (method === 'crypto') return casino.ex.some(function (m) { return C.crypto.indexOf(m) > -1; });
      return casino.ex.indexOf(method) > -1;
    }

    function compute(dep) {
      var method = form.method.value, wx = +form.wx.value || 0, rows = [];
      D.o.forEach(function (o) {
        var c = D.c[o[0]];
        if (state.type && o[2] !== state.type) return;
        if (o[5] != null && dep < o[5]) return;
        if (wx && o[6] > wx) return;
        if (excluded(c, method)) return;
        var bonus = Math.min(dep * o[3] / 100, o[4]);
        var wager = ({ db: dep + bonus, b: bonus, d: dep }[o[7]]) * o[6];
        rows.push({
          c: c, name: o[1], pct: o[3], max: o[4], min: o[5], x: o[6], base: o[7], bonus: bonus, wager: wager,
          value: bonus - wager * 0.04, days: o[10], extras: o[8] && (o[9] == null || dep >= o[9]) ? o[8] : ''
        });
      });
      var by = {
        value: function (a, b) { return b.value - a.value || b.bonus - a.bonus; },
        bonus: function (a, b) { return b.bonus - a.bonus || a.wager - b.wager; },
        wager: function (a, b) { return a.wager - b.wager || b.bonus - a.bonus; },
        min: function (a, b) { return (a.min || 0) - (b.min || 0) || b.value - a.value; }
      }[form.sort.value] || function () { return 0; };
      rows.sort(by);
      if (form.one.checked) {
        var seen = {};
        rows = rows.filter(function (r) { if (seen[r.c.n]) return false; seen[r.c.n] = 1; return true; });
      }
      return rows;
    }

    function rowHtml(r, i) {
      var base = r.x ? t(r.base, { x: r.x }) : t('noWagering');
      var pos = r.value >= 0;
      return '<li class="cctf__offer">' +
        '<span class="cctf__rank" aria-label="' + esc(t('rank', { n: i + 1 })) + '">' + (i + 1) + '</span>' +
        '<div class="cctf__who"><a href="' + esc(r.c.u) + '">' + esc(r.c.n) + '</a>' +
          '<span class="cctf__what">' + esc(r.name) + ' · ' + esc(t('upTo', { pct: r.pct, max: money(r.max) })) + '</span>' +
          (r.extras ? '<span class="cctf__extra">+ ' + esc(r.extras) + '</span>' : '') + '</div>' +
        '<div class="cctf__stats">' +
          '<div class="cctf__stat"><b>' + esc(money(r.bonus)) + '</b><small>' + esc(t('bonus')) + '</small></div>' +
          '<div class="cctf__stat"><b>' + esc(money(r.wager)) + '</b><small>' + esc(t('toWager') + ' · ' + base) + '</small></div>' +
          '<div class="cctf__stat"><b>' + (r.days ? esc(t('days', { n: r.days })) : '–') + '</b><small>' +
            esc((r.days ? t('toWager') : t('noLimit')) + (r.c.mb ? ' · ' + t('maxBet', { amount: money(r.c.mb) }) : '')) + '</small></div>' +
        '</div>' +
        '<div class="cctf__ev"><span class="cctf__evpill ' + (pos ? 'is-pos' : 'is-neg') + '">' + (pos ? '+' : '−') + esc(money(Math.abs(r.value))) + '</span><small>' + esc(t('value')) + '</small></div>' +
        '<div class="cctf__go"><a class="cctf__btn cctf__btn--y" href="' + esc(r.c.b) + '">' + esc(t('calculate')) + '</a>' +
          '<a class="cctf__btn cctf__btn--l" href="' + esc(r.c.u) + '">' + esc(t('review')) + '</a></div>' +
        '</li>';
    }

    function render() {
      var dep = deposit();
      var rows = compute(dep);
      var casinos = {};
      rows.forEach(function (r) { casinos[r.c.n] = 1; });
      var n = rows.length === 1 ? t('offer') : t('offers', { n: rows.length });
      out('sum').innerHTML = rows.length
        ? (form.one.checked ? t('summary', { amount: esc(money(dep)), n: n }) : t('summaryAt', { amount: esc(money(dep)), n: n, c: Object.keys(casinos).length }))
        : '';
      out('list').innerHTML = rows.length
        ? rows.slice(0, state.shown).map(rowHtml).join('')
        : '<li class="cctf__empty">' + esc(t('none', { amount: money(dep) })) + '</li>';
      var more = out('more');
      more.hidden = rows.length <= state.shown;
      more.textContent = t('more', { n: Math.min(C.limit, rows.length - state.shown) });
    }

    range.addEventListener('input', function () { syncSlider(); state.shown = C.limit; render(); });
    ['method', 'wx', 'sort', 'one'].forEach(function (name) {
      form[name].addEventListener('change', function () { state.shown = C.limit; render(); });
    });
    root.querySelectorAll('.cctf__tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        state.type = tab.getAttribute('data-type'); state.shown = C.limit;
        root.querySelectorAll('.cctf__tab').forEach(function (x) { x.setAttribute('aria-pressed', String(x === tab)); });
        render();
      });
    });
    out('more').addEventListener('click', function () { state.shown += C.limit; render(); });

    syncSlider();
    render();
  }

  function boot() {
    document.querySelectorAll('.cct-finder:not([data-ready])').forEach(function (root) {
      root.setAttribute('data-ready', '1');
      init(root);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.cctFinderBoot = boot;
})();
