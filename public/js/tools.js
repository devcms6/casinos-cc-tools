/*
 * Casinos.cc Tools – front-end widgets.
 * Every widget carries its own data in <script type="application/json" class="cct-data">,
 * so several tools (or casinos) can sit on one page. No dependencies.
 */
(function () {
  'use strict';

  var T = window.cctI18n || {};
  var LOCALE = document.documentElement.lang || 'en';

  function t(key, vars) {
    var s = T[key] || key;
    if (vars) {
      Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    }
    return s;
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function moneyFormatter(currency) {
    var f;
    try { f = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: currency, maximumFractionDigits: 0 }); }
    catch (e) { f = new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }); }
    var symbol = '';
    try { symbol = (f.formatToParts(0).filter(function (p) { return p.type === 'currency'; })[0] || {}).value || ''; } catch (e) {}
    var fn = function (n) { return f.format(Math.round(n)); };
    fn.symbol = symbol;
    return fn;
  }
  var num = new Intl.NumberFormat(LOCALE);

  function out(root, key) { return root.querySelector('[data-out="' + key + '"]'); }
  function setText(root, key, value) { var el = out(root, key); if (el) el.textContent = value; }
  function rule(type, html) {
    var icon = { ok: '✓', no: '!', warn: '!', info: 'i' }[type];
    return '<li class="rules__item"><span class="ht-step ht-step--' + type + '">' + icon + '</span>' + html + '</li>';
  }

  /* Shared behaviour: currency symbol, quick amounts, sticky bar. */
  function initShared(root, form, eur, update) {
    var money = root.querySelector('.cct__money');
    if (money) money.setAttribute('data-symbol', eur.symbol);

    var quick = root.querySelector('[data-quick]');
    if (quick) {
      quick.innerHTML = quick.getAttribute('data-quick').split(',').map(function (v) {
        return '<button type="button" data-v="' + v + '" aria-pressed="false">' + esc(eur(+v)) + '</button>';
      }).join('');
      quick.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        form.amount.value = b.getAttribute('data-v');
        update();
      });
    }

    var bar = root.querySelector('.cct__bar');
    var results = root.querySelector('[data-results]');
    var jump = root.querySelector('[data-jump]');
    if (bar && results && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        bar.classList.toggle('is-away', entries[0].isIntersecting);
      }, { rootMargin: '0px 0px -35% 0px' }).observe(results);
    }
    if (jump && results) {
      jump.addEventListener('click', function () { results.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);

    return function syncQuick() {
      if (!quick) return;
      quick.querySelectorAll('button').forEach(function (b) {
        b.setAttribute('aria-pressed', String(+b.getAttribute('data-v') === +form.amount.value));
      });
    };
  }

  /* ---------- Wagering calculator ---------- */
  function initWagering(root, data) {
    var form = root.querySelector('form');
    var tool = data.tool;
    var casino = data.casino;
    var eur = moneyFormatter(casino.currency || 'EUR');
    var maxBetEl = out(root, 'maxbet');
    if (maxBetEl && tool.max_bet != null) maxBetEl.textContent = eur(tool.max_bet);

    function update() {
      var o = tool.offers.filter(function (x) { return x.id === form.offer.value; })[0] || tool.offers[0];
      var amount = Math.max(0, parseFloat(form.amount.value) || 0);
      var rtp = form.rtp ? parseFloat(form.rtp.value) : 96;
      var days = o.days != null ? o.days : tool.days;
      var x = o.wagering_x || 0;
      var bonus, wager, list = [];

      if (o.kind === 'spins') {
        bonus = amount;
        wager = amount * x;
        setText(root, 'amount-label', t('spinWinnings'));
        setText(root, 'amount-help', t('spinHelp'));
        setText(root, 'offer-help', o.description || '');
      } else {
        bonus = Math.min(amount * (o.match_pct || 0) / 100, o.max_bonus || 0);
        var base = { deposit_bonus: amount + bonus, bonus: bonus, deposit: amount, winnings: bonus }[o.wagering_base] || amount + bonus;
        wager = base * x;
        setText(root, 'amount-label', t('deposit'));
        setText(root, 'amount-help', o.match_pct ? t('fullBonus', { amount: eur(o.max_bonus * 100 / o.match_pct) }) : '');
        var help = o.description || (t('offerLine', { pct: o.match_pct, max: eur(o.max_bonus) }) + (o.extras ? ' + ' + o.extras : '') + '.');
        if (o.min_deposit != null) help += ' ' + t('minDeposit', { amount: eur(o.min_deposit) });
        setText(root, 'offer-help', help);

        if (o.min_deposit != null && amount < o.min_deposit) {
          list.push(rule('no', t('belowMin', { min: eur(o.min_deposit), amount: eur(amount) })));
        } else if (o.extras && o.extras_min_deposit != null && amount < o.extras_min_deposit) {
          list.push(rule('warn', t('extrasMin', { min: eur(o.extras_min_deposit), extras: esc(o.extras) })));
        }
      }

      // result rows
      var extrasRow = root.querySelector('[data-row="extras"]');
      if (extrasRow) extrasRow.hidden = !o.extras;
      if (o.extras) {
        var hasExtras = o.extras_min_deposit == null || amount >= o.extras_min_deposit;
        setText(root, 'extras', hasExtras ? o.extras : t('needAmount', { amount: eur(o.extras_min_deposit) }));
      }
      var ruleKey = { deposit_bonus: 'ruleXdb', bonus: 'ruleXb', deposit: 'ruleXd', winnings: 'ruleXw' }[o.wagering_base] || 'ruleXdb';
      setText(root, 'rule', x ? t(ruleKey, { x: x }) : t('noWagering'));

      var cost = wager * (1 - rtp / 100);
      setText(root, 'wager', eur(wager));
      setText(root, 'bar-v', eur(wager));
      setText(root, 'bonus', eur(bonus));
      setText(root, 'cost', eur(cost));
      if (tool.max_bet) setText(root, 'spins', num.format(Math.ceil(wager / tool.max_bet)));
      var perdayRow = root.querySelector('[data-row="perday"]');
      if (perdayRow) perdayRow.hidden = !days;
      if (days) setText(root, 'perday', eur(wager / days));

      // warnings
      if (form.method && form.method.value) {
        list.push(rule('no', t('excluded', { method: esc(form.method.value), casino: esc(casino.name) })));
      }
      if (form.country && form.country.value && o.kind !== 'spins' && tool.win_cap && tool.win_cap.multiplier) {
        list.push(rule('warn', t('winCap', { country: esc(form.country.value), x: tool.win_cap.multiplier, amount: eur(bonus * tool.win_cap.multiplier) })));
      }
      if (bonus > 0 && x > 0) {
        var vars = { rtp: rtp, cost: eur(cost), bonus: eur(bonus) };
        list.push(cost > bonus ? rule('warn', t('costHigh', vars)) : rule('ok', t('costOk', vars)));
      }
      if (tool.activate_hours) list.push(rule('info', t('activate', { hours: tool.activate_hours })));
      if (tool.max_bet) list.push(rule('info', t('maxBet', { amount: eur(tool.max_bet) })));
      if (x > 0) list.push(rule('info', t('forfeit')));

      out(root, 'rules').innerHTML = list.join('');
      syncQuick();
    }

    var syncQuick = initShared(root, form, eur, update);
    update();
  }

  /* ---------- Withdrawal checker ---------- */
  function daysNeeded(amount, lim) {
    var d = lim.daily || Infinity, w = lim.weekly || Infinity, m = lim.monthly || Infinity;
    var left = amount, day = 0, paid = [];
    while (left > 0 && day < 1000) {
      var week = paid.slice(-6).reduce(function (a, b) { return a + b; }, 0);
      var month = paid.slice(-29).reduce(function (a, b) { return a + b; }, 0);
      var today = Math.max(0, Math.min(d, w - week, m - month, left));
      paid.push(today); left -= today; day++;
    }
    return day;
  }

  function initWithdrawal(root, data) {
    var form = root.querySelector('form');
    var tool = data.tool;
    var casino = data.casino;
    var eur = moneyFormatter(casino.currency || 'EUR');
    var filter = 'all';
    var expanded = false;
    var moreBtn = out(root, 'more');
    var types = T.types || {};
    var dt = tool.default_time || {};

    function timeFor(m) {
      if (m.time) return m.time;
      return (m.type === 'bank' ? dt.bank : dt.other) || '';
    }

    function update() {
      var amount = Math.max(0, parseFloat(form.amount.value) || 0);
      var lim = tool.limits && tool.limits.length && form.vip ? tool.limits[+form.vip.value] : null;
      if (lim) {
        setText(root, 'vip-help', t('limitsHelp', {
          daily: lim.daily != null ? eur(lim.daily) : '–',
          weekly: lim.weekly != null ? eur(lim.weekly) : '–',
          monthly: lim.monthly != null ? eur(lim.monthly) : '–'
        }));
      }

      var methods = tool.methods;
      var payable = methods.filter(function (m) { return m.withdrawals === 'yes' && amount >= (m.min_withdrawal || 0); });
      var days = lim && amount > 0 ? daysNeeded(amount, lim) : 0;

      out(root, 'lead').innerHTML = (payable.length === 1 ? t('methodPay', { amount: eur(amount) }) : t('methodsPay', { n: payable.length, amount: eur(amount) })) +
        (days > 1 ? ' ' + t('dailyPayments', { n: days }) : '');
      setText(root, 'bar-v', payable.length);
      setText(root, 'bar-l', t(payable.length === 1 ? 'barMethod' : 'barMethods', { amount: eur(amount) }));

      function rank(m) { return m.withdrawals === 'yes' ? (amount >= (m.min_withdrawal || 0) ? 0 : 1) : (m.withdrawals === 'unknown' ? 2 : 3); }
      var shown = methods.filter(function (m) {
        return filter === 'all' || m.type === filter || (filter === 'card' && m.type === 'voucher');
      }).slice().sort(function (a, b) { return rank(a) - rank(b); });

      var hiddenCount = 0;
      out(root, 'rows').innerHTML = shown.map(function (m) {
        var tag, off = false, time = timeFor(m);
        if (m.withdrawals === 'yes') {
          tag = amount >= (m.min_withdrawal || 0) ? '<span class="tag tag--ok">' + esc(t('available')) + '</span>'
            : '<span class="tag tag--warn">' + esc(t('minShort', { amount: eur(m.min_withdrawal) })) + '</span>';
        } else if (m.withdrawals === 'unknown') { tag = '<span class="tag tag--na">' + esc(t('notConfirmed')) + '</span>'; off = true; time = ''; }
        else { tag = '<span class="tag tag--no">' + esc(t('depositsOnly')) + '</span>'; off = true; time = ''; }
        if (off) hiddenCount++;
        var cls = (off ? 'is-off' : '') + (off && !expanded ? ' is-hidden' : '');
        return '<tr class="' + cls + '">' +
          '<td class="c-name"><span class="fw-b">' + esc(m.name) + '</span><span class="cct__cat">' + esc(types[m.type] || '') + '</span></td>' +
          '<td class="c-min num" data-label="' + esc(t('minLabel')) + '">' + (m.withdrawals === 'yes' && m.min_withdrawal != null ? esc(eur(m.min_withdrawal)) : '') + '</td>' +
          '<td class="c-status">' + tag + '</td>' +
          '<td class="c-time">' + esc(time) + '</td></tr>';
      }).join('');

      moreBtn.hidden = hiddenCount === 0;
      moreBtn.setAttribute('aria-expanded', String(expanded));
      moreBtn.textContent = expanded ? t('hideMore') : (hiddenCount === 1 ? t('showOne') : t('showMore', { n: hiddenCount }));

      var list = [];
      if (form.bonus && form.bonus.checked) list.push(rule('no', t('activeBonus')));
      var dep = form.dep ? methods.filter(function (m) { return m.name === form.dep.value; })[0] : null;
      if (dep && dep.withdrawals === 'no') list.push(rule('warn', t('cantReceive', { method: esc(dep.name), casino: esc(casino.name) })));
      else if (dep && amount < (dep.min_withdrawal || 0)) list.push(rule('warn', t('methodMin', { method: esc(dep.name), amount: eur(dep.min_withdrawal) })));
      else if (dep) list.push(rule('ok', t('canReturn', { method: esc(dep.name) })));
      if (days > 1) list.push(rule('info', t('splitDays', { amount: eur(amount), n: days })));
      out(root, 'rules').innerHTML = list.join('');
      syncQuick();
    }

    root.querySelectorAll('.cct__filter').forEach(function (btn) {
      btn.addEventListener('click', function () {
        filter = btn.getAttribute('data-filter');
        root.querySelectorAll('.cct__filter').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
        update();
      });
    });
    moreBtn.addEventListener('click', function () { expanded = !expanded; update(); });

    var syncQuick = initShared(root, form, eur, update);
    update();
  }

  /* ---------- boot ---------- */
  function boot() {
    document.querySelectorAll('.cct-widget[data-cct]:not([data-ready])').forEach(function (root) {
      var el = root.querySelector('.cct-data');
      var data;
      try { data = JSON.parse(el.textContent); } catch (e) { return; }
      root.setAttribute('data-ready', '1');
      if (root.getAttribute('data-cct') === 'wagering') initWagering(root, data);
      if (root.getAttribute('data-cct') === 'withdrawal') initWithdrawal(root, data);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.cctBoot = boot; // for widgets injected later (AJAX / lazy content)
})();
