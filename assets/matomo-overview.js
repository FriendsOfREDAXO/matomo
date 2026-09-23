/**
 * Matomo Übersicht: lädt alle Abschnitte parallel über rex-api-call=matomo_stats,
 * damit die Seite nicht auf Matomo wartet. Filter: Domain und Zeitraum.
 */
(function () {
  'use strict';

  var root, cfg, state, timer;

  function init() {
    root = document.getElementById('matomo-overview');
    if (!root || root.dataset.movInit) {
      return;
    }
    root.dataset.movInit = '1';
    cfg = JSON.parse(root.getAttribute('data-config') || '{}');
    state = restoreFilters();

    root.querySelectorAll('[data-matomo-filter]').forEach(function (el) {
      var key = el.getAttribute('data-matomo-filter');
      if (state[key] !== undefined && [].some.call(el.options, function (o) { return o.value === String(state[key]); })) {
        el.value = String(state[key]);
      }
      el.addEventListener('change', function () {
        state[key] = key === 'site' ? parseInt(el.value, 10) || 0 : el.value;
        saveFilters();
        loadAll();
      });
    });
    var refresh = root.querySelector('[data-matomo-refresh]');
    if (refresh) {
      refresh.addEventListener('click', function () { loadAll(true); });
    }
    loadAll();
    // Auto-Refresh nur alle 15 Minuten und nur bei sichtbarem Tab; Daten kommen meist aus dem Server-Cache
    timer = window.setInterval(function () {
      if (document.visibilityState === 'visible' && document.body.contains(root)) { loadAll(); } else if (!document.body.contains(root)) { window.clearInterval(timer); }
    }, 15 * 60 * 1000);
  }

  function restoreFilters() {
    var s = { site: 0, range: 'last7' };
    try {
      var saved = JSON.parse(window.localStorage.getItem('matomo_overview_filters') || '{}');
      if (saved.site !== undefined) { s.site = parseInt(saved.site, 10) || 0; }
      if (saved.range) { s.range = saved.range; }
    } catch (e) { /* localStorage nicht verfügbar */ }
    return s;
  }

  function saveFilters() {
    try { window.localStorage.setItem('matomo_overview_filters', JSON.stringify(state)); } catch (e) { /* ignorieren */ }
  }

  function t(key) {
    return (cfg.i18n && cfg.i18n[key]) || key;
  }

  function fmtInt(n) {
    return new Intl.NumberFormat(cfg.locale || undefined).format(Math.round(n || 0));
  }

  function fmtDec(n) {
    return new Intl.NumberFormat(cfg.locale || undefined, { maximumFractionDigits: 1 }).format(n || 0);
  }

  function fmtDuration(sec) {
    sec = Math.round(sec || 0);
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function esc(str) {
    return String(str === undefined || str === null ? '' : str).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function partNode(part) {
    return root.querySelector('[data-matomo-part="' + part + '"]');
  }

  function bodyOf(part) {
    var node = partNode(part);
    return node ? (node.querySelector('.matomo-ov-body') || node) : null;
  }

  function setLoading(part) {
    var body = bodyOf(part);
    if (!body) { return; }
    body.classList.add('is-loading');
    if (!body.querySelector('.matomo-ov-skeleton')) {
      body.innerHTML = '<div class="matomo-ov-skeleton"></div>';
    }
  }

  function setError(part, message) {
    var body = bodyOf(part);
    if (body) {
      body.classList.remove('is-loading');
      body.innerHTML = '<div class="matomo-ov-error"><i class="fa fa-exclamation-triangle"></i> ' + esc(t('error_prefix')) + ': ' + esc(message) + '</div>';
    }
  }

  function setEmpty(part) {
    var body = bodyOf(part);
    if (body) {
      body.classList.remove('is-loading');
      body.innerHTML = '<div class="matomo-ov-empty">' + esc(t('no_data')) + '</div>';
    }
  }

  var PARTS = ['summary', 'chart', 'pages', 'times', 'referrers', 'devices', 'countries'];
  var runId = 0;

  // Abschnitte nacheinander laden: es ist immer nur ein Matomo-Request unterwegs,
  // die Seite bleibt trotzdem sofort bedienbar. Ein Filterwechsel bricht die Kette ab.
  function loadAll(force) {
    var id = ++runId;
    var openLink = root.querySelector('[data-matomo-open]');
    if (openLink && cfg.openUrlAll) {
      openLink.setAttribute('href', cfg.openUrlAll);
    }
    var queue = PARTS.filter(function (part) { return !!partNode(part); });
    queue.forEach(function (part) { setLoading(part); if (part === 'summary') { setLoading('sites'); } });

    function next() {
      if (id !== runId || !queue.length) { return; }
      var part = queue.shift();
      var url = cfg.endpoint + '&part=' + part + '&site=' + (state.site || 0) + '&range=' + encodeURIComponent(state.range) + (force === true ? '&refresh=1' : '');
      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (id !== runId) { return; }
          if (!json.success) { throw new Error(json.message || 'unknown'); }
          RENDER[part](json.data);
          var upd = root.querySelector('[data-matomo-updated]');
          if (upd) {
            var at = new Date(Date.now() - (json.age || 0) * 1000);
            upd.textContent = t('updated') + ' ' + at.toLocaleTimeString(cfg.locale || undefined, { hour: '2-digit', minute: '2-digit' });
          }
        })
        .catch(function (e) {
          if (id !== runId) { return; }
          setError(part, e.message);
          if (part === 'summary') { setError('sites', e.message); }
        })
        .then(next);
    }
    next();
  }

  function delta(cur, prev, invert) {
    if (!prev) { return ''; }
    var pct = ((cur - prev) / prev) * 100;
    var up = pct > 0;
    var cls = pct === 0 ? '' : ((up && !invert) || (!up && invert) ? 'is-up' : 'is-down');
    var icon = pct === 0 ? 'fa-minus' : (up ? 'fa-arrow-up' : 'fa-arrow-down');
    return '<span class="' + cls + '"><i class="fa ' + icon + '"></i>' + (up ? '+' : '') + fmtDec(pct) + ' %</span>';
  }

  var RENDER = {};

  RENDER.summary = function (data) {
    var c = data.current, p = data.previous;
    var tiles = [
      ['visits', fmtInt(c.visits), delta(c.visits, p.visits)],
      ['unique_visitors', data.has_unique === false ? '–' : fmtInt(c.unique), data.has_unique === false ? '<span class="text-muted">' + esc(t('unique_unavailable')) + '</span>' : delta(c.unique, p.unique), data.has_unique === false],
      ['actions', fmtInt(c.actions), delta(c.actions, p.actions)],
      ['bounce_rate', fmtDec(c.bounce_rate) + ' %', delta(c.bounce_rate, p.bounce_rate, true)],
      ['avg_duration', fmtDuration(c.avg_time), delta(c.avg_time, p.avg_time)],
      ['actions_per_visit', fmtDec(c.actions_per_visit), delta(c.actions_per_visit, p.actions_per_visit)],
      ['conversions', fmtInt(c.converted), delta(c.converted, p.converted)],
      ['conversion_rate', fmtDec(c.conversion_rate) + ' %', delta(c.conversion_rate, p.conversion_rate)]
    ];
    var node = partNode('summary');
    node.innerHTML = tiles.map(function (tile) {
      return '<div class="matomo-ov-kpi"><div class="matomo-ov-kpi-value">' + tile[1] + '</div>'
        + '<div class="matomo-ov-kpi-label">' + esc(t(tile[0])) + '</div>'
        + '<div class="matomo-ov-kpi-delta">' + (tile[2] ? tile[2] + (tile[3] ? '' : ' <span class="text-muted">' + esc(t('vs_previous')) + '</span>') : '&nbsp;') + '</div></div>';
    }).join('');
    RENDER.sites(data);
    // "Matomo öffnen" folgt dem Domain-Filter
    var openLink = root.querySelector('[data-matomo-open]');
    if (openLink) {
      var picked = (data.sites || []).filter(function (s) { return s.idsite === state.site; })[0];
      openLink.setAttribute('href', picked ? picked.open_url : cfg.openUrlAll);
    }
  };

  RENDER.sites = function (data) {
    var body = bodyOf('sites');
    if (!body) { return; }
    body.classList.remove('is-loading');
    if (!data.sites || !data.sites.length) { setEmpty('sites'); return; }
    var cols = [['visits', 'visits', fmtInt], ['unique_visitors', 'unique', fmtInt], ['actions', 'actions', fmtInt], ['bounce_rate', 'bounce_rate', function (v) { return fmtDec(v) + ' %'; }], ['avg_duration', 'avg_time', fmtDuration], ['conversions', 'converted', fmtInt]];
    var html = '<div class="table-responsive"><table class="matomo-ov-sites"><thead><tr><th>' + esc(t('domain')) + '</th>'
      + cols.map(function (col) { return '<th class="num">' + esc(t(col[0])) + '</th>'; }).join('') + '<th></th></tr></thead><tbody>';
    data.sites.forEach(function (site) {
      html += '<tr' + (state.site === site.idsite ? ' class="is-active"' : '') + '><td><strong>' + esc(site.host || site.name) + '</strong>' + (site.host && site.name !== site.host ? ' <small class="text-muted">' + esc(site.name) + '</small>' : '') + '</td>';
      cols.forEach(function (col) {
        var cur = site.metrics[col[1]], prev = site.previous[col[1]];
        if (col[1] === 'unique' && data.has_unique === false) { html += '<td class="num">–</td>'; return; }
        html += '<td class="num">' + col[2](cur) + (prev ? '<span class="matomo-ov-delta ' + (cur > prev ? (col[1] === 'bounce_rate' ? 'is-down' : 'is-up') : (cur < prev ? (col[1] === 'bounce_rate' ? 'is-up' : 'is-down') : '')) + '">' + (cur >= prev ? '+' : '') + fmtDec(((cur - prev) / prev) * 100) + ' %</span>' : '') + '</td>';
      });
      html += '<td class="num">' + openControl(site) + '</td></tr>';
    });
    body.innerHTML = html + '</tbody></table></div>';
  };

  // "Öffnen" je Website: mit Auto-Login als POST-Formular (logme), sonst Token-Link
  function openControl(site) {
    var a = cfg.autoLogin;
    if (a && a.action) {
      var target = 'index.php?module=CoreHome&action=index&idSite=' + site.idsite + '&period=day&date=today';
      return '<form method="post" action="' + esc(a.action) + '" target="_blank" class="matomo-autologin" style="display:inline">'
        + '<input type="hidden" name="module" value="Login"><input type="hidden" name="action" value="logme">'
        + '<input type="hidden" name="login" value="' + esc(a.login) + '"><input type="hidden" name="password" value="' + esc(a.hash) + '">'
        + '<input type="hidden" name="url" value="' + esc(target) + '">'
        + '<button type="submit" class="btn btn-default btn-xs"><i class="fa fa-sign-in-alt"></i> ' + esc(t('open')) + '</button></form>';
    }
    return '<a class="btn btn-default btn-xs" target="_blank" href="' + esc(site.open_url) + '"><i class="fa fa-external-link-alt"></i> ' + esc(t('open')) + '</a>';
  }

  // Feste Farben je Kategorie (Identität), Reihenfolge nie rotieren
  var CATEGORY_COLORS = ['#2f7fcf', '#e08a1e', '#2e8b57', '#7b5cd6', '#1c9aa8', '#c8463e', '#8a6d3b', '#5c6b7a'];
  var KNOWN_KEYS = {
    referrers: ['Direct Entry', 'Search Engines', 'Websites', 'Social Networks', 'Campaigns'],
    devices: ['Desktop', 'Smartphone', 'Tablet', 'Phablet', 'Tv', 'Console', 'Wearable', 'Unknown']
  };

  function colorFor(kind, label, index) {
    var known = KNOWN_KEYS[kind] || [];
    var i = known.indexOf(label);
    if (i < 0) { i = known.length + index; }
    return CATEGORY_COLORS[i % CATEGORY_COLORS.length];
  }

  // Balkenliste: Breite = Anteil an der Summe (nicht am Maximum), Prozent direkt beschriftet.
  // opts.kind setzt feste Farben je Kategorie (Herkunft, Geräte); sonst eine Farbe (Rangliste).
  function bars(rows, opts) {
    if (!rows || !rows.length) { return null; }
    var total = rows.reduce(function (sum, r) { return sum + (r[opts.value] || 0); }, 0) || 1;
    var html = '';
    if (opts.kind) {
      html += '<div class="matomo-ov-share" role="img">' + rows.map(function (r, i) {
        var pct = (r[opts.value] / total) * 100;
        return '<span style="width:' + pct.toFixed(2) + '%;background:' + colorFor(opts.kind, r.label, i) + '" title="' + esc(r.label) + ' ' + fmtDec(pct) + ' %"></span>';
      }).join('') + '</div>';
    }
    html += '<ul class="matomo-ov-bars">' + rows.map(function (r, i) {
      var pct = (r[opts.value] / total) * 100;
      var color = opts.kind ? colorFor(opts.kind, r.label, i) : '';
      return '<li class="matomo-ov-bar"><span class="matomo-ov-bar-label" title="' + esc(opts.title ? opts.title(r) : r.label) + '">'
        + (color ? '<i class="matomo-ov-swatch" style="background:' + color + '"></i>' : '') + (opts.label ? opts.label(r) : esc(r.label)) + '</span>'
        + '<span class="matomo-ov-bar-track"><span class="matomo-ov-bar-fill" style="width:' + Math.max(1, pct).toFixed(2) + '%' + (color ? ';background:' + color : '') + '"></span></span>'
        + '<span class="matomo-ov-bar-value">' + fmtInt(r[opts.value]) + ' <small>' + fmtDec(pct) + ' %' + (opts.extra ? ' · ' + opts.extra(r) : '') + '</small></span></li>';
    }).join('') + '</ul>';
    return html;
  }

  function fillCard(part, html) {
    var body = bodyOf(part);
    if (!body) { return; }
    body.classList.remove('is-loading');
    if (html === null) { setEmpty(part); } else { body.innerHTML = html; }
  }

  RENDER.pages = function (data) {
    fillCard('pages', bars(data.rows, {
      value: 'hits',
      label: function (r) {
        var path = r.label === '/' && !r.title ? t('page_home') + ' <small>/</small>' : esc(r.label);
        var host = state.site === 0 && r.host ? ' <small>' + esc(r.host) + '</small>' : '';
        return r.title ? '<strong>' + esc(r.title) + '</strong> <small>' + esc(r.label) + '</small>' + host : path + host;
      },
      title: function (r) { return r.url || r.label; },
      extra: function (r) { return fmtDuration(r.avg_time); }
    }));
  };

  RENDER.referrers = function (data) {
    var html = bars(data.types, { value: 'visits', kind: 'referrers' });
    if (html !== null && data.websites && data.websites.length) {
      html += '<p class="matomo-ov-subhead">' + esc(t('referrer_websites')) + '</p>' + bars(data.websites, { value: 'visits' });
    }
    fillCard('referrers', html);
  };

  RENDER.devices = function (data) {
    fillCard('devices', bars(data.rows, { value: 'visits', kind: 'devices' }));
  };

  RENDER.countries = function (data) {
    fillCard('countries', bars(data.rows, {
      value: 'visits',
      label: function (r) { return '<span class="matomo-ov-flag">' + flag(r.code) + '</span>' + esc(r.label); }
    }));
  };

  function flag(code) {
    if (!code || code.length !== 2 || code === 'xx') { return '🌐'; }
    return String.fromCodePoint.apply(null, code.toUpperCase().split('').map(function (ch) { return 127397 + ch.charCodeAt(0); }));
  }

  RENDER.chart = function (data) {
    var body = bodyOf('chart');
    body.classList.remove('is-loading');
    var labels = data.labels || [], visits = data.visits || [], actions = data.actions || [];
    if (!labels.length || !visits.some(function (v) { return v > 0; }) ) { setEmpty('chart'); return; }
    var pretty = labels.map(function (l) { return prettyLabel(l, data.kind); });
    body.innerHTML = '<div class="matomo-ov-chart"></div><div class="matomo-ov-legend"><span>' + esc(t('chart_visits')) + '</span></div><div class="matomo-ov-chart-table-wrap" hidden></div>';
    drawLine(body.querySelector('.matomo-ov-chart'), pretty, visits, actions);
    var table = '<table class="matomo-ov-chart-table"><thead><tr><th></th><th>' + esc(t('chart_visits')) + '</th><th>' + esc(t('chart_actions')) + '</th></tr></thead><tbody>'
      + labels.map(function (l, i) { return '<tr><td>' + esc(pretty[i]) + '</td><td>' + fmtInt(visits[i]) + '</td><td>' + fmtInt(actions[i]) + '</td></tr>'; }).join('') + '</tbody></table>';
    body.querySelector('.matomo-ov-chart-table-wrap').innerHTML = table;
    var toggle = partNode('chart').querySelector('[data-matomo-toggle-table]');
    if (toggle && !toggle.dataset.bound) {
      toggle.dataset.bound = '1';
      toggle.addEventListener('click', function () {
        var chart = body.querySelector('.matomo-ov-chart'), wrap = body.querySelector('.matomo-ov-chart-table-wrap'), legend = body.querySelector('.matomo-ov-legend');
        var showTable = wrap.hasAttribute('hidden');
        wrap.toggleAttribute('hidden', !showTable);
        chart.toggleAttribute('hidden', showTable);
        legend.toggleAttribute('hidden', showTable);
        toggle.textContent = showTable ? t('chart_view') : t('table_view');
      });
    }
  };

  RENDER.times = function (data) {
    var hours = data.hours || [], days = data.weekdays || [];
    if (!hours.some(function (v) { return v > 0; })) { setEmpty('times'); return; }
    var body = bodyOf('times');
    body.classList.remove('is-loading');
    var dayNames = (function () { var base = new Date(Date.UTC(2024, 0, 1)); return [0, 1, 2, 3, 4, 5, 6].map(function (i) { var d = new Date(base); d.setUTCDate(base.getUTCDate() + i); return d.toLocaleDateString(cfg.locale || undefined, { weekday: 'short', timeZone: 'UTC' }); }); })();
    body.innerHTML = '<div class="matomo-ov-times"><div><h5>' + esc(t('by_hour')) + '</h5><div class="matomo-ov-cols" data-cols="hours"></div></div><div><h5>' + esc(t('by_weekday')) + '</h5><div class="matomo-ov-cols" data-cols="days"></div></div></div>';
    drawColumns(body.querySelector('[data-cols=hours]'), hours.map(function (v, i) { return { label: (i < 10 ? '0' : '') + i + ' ' + t('hour_suffix'), short: i % 3 === 0 ? String(i) : '', value: v }; }));
    drawColumns(body.querySelector('[data-cols=days]'), days.map(function (v, i) { return { label: dayNames[i], short: dayNames[i], value: v }; }));
  };

  // Säulen mit Hover-Tooltip, eine Farbe, Spitzenwert direkt beschriftet
  function drawColumns(container, items) {
    var W = 600, H = 140, padL = 6, padR = 6, padT = 16, padB = 22;
    var n = items.length, max = Math.max.apply(null, items.map(function (i) { return i.value; })) || 1;
    var slot = (W - padL - padR) / n, bw = Math.max(3, slot - 3);
    var svgNS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.setAttribute('preserveAspectRatio', 'none');
    var el = function (name, attrs) { var node = document.createElementNS(svgNS, name); Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); }); return node; };
    var grid = el('g', { 'class': 'mov-grid' }); grid.appendChild(el('line', { x1: padL, x2: W - padR, y1: H - padB, y2: H - padB })); svg.appendChild(grid);
    var axis = el('g', { 'class': 'mov-axis' });
    var peak = items.reduce(function (best, it, i) { return it.value > items[best].value ? i : best; }, 0);
    items.forEach(function (it, i) {
      var h = (it.value / max) * (H - padT - padB), x = padL + i * slot + (slot - bw) / 2, y = H - padB - h;
      var rect = el('rect', { 'class': 'mov-col' + (i === peak ? ' is-peak' : ''), x: x, y: y, width: bw, height: Math.max(h, it.value > 0 ? 1 : 0), rx: 2 });
      var title = document.createElementNS(svgNS, 'title'); title.textContent = it.label + ': ' + fmtInt(it.value); rect.appendChild(title);
      svg.appendChild(rect);
      if (it.short) { var tx = el('text', { x: x + bw / 2, y: H - 6, 'text-anchor': 'middle' }); tx.textContent = it.short; axis.appendChild(tx); }
      if (i === peak && it.value > 0) { var tv = el('text', { x: x + bw / 2, y: y - 4, 'text-anchor': 'middle' }); tv.textContent = fmtInt(it.value); axis.appendChild(tv); }
    });
    svg.appendChild(axis);
    container.appendChild(svg);
  }

  function prettyLabel(label, kind) {
    if (kind === 'hour') { return label + ' ' + t('hour_suffix'); }
    var m = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(label);
    if (!m) { return label; }
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, m[3] ? +m[3] : 1));
    return d.toLocaleDateString(cfg.locale || undefined, kind === 'month' ? { month: 'short', year: '2-digit', timeZone: 'UTC' } : { day: '2-digit', month: '2-digit', timeZone: 'UTC' });
  }

  function drawLine(container, labels, values, actions) {
    var W = 800, H = 220, padL = 44, padR = 12, padT = 12, padB = 28;
    var n = values.length, max = Math.max.apply(null, values) || 1;
    var niceMax = niceCeil(max);
    var divisions = niceDivisions(niceMax);
    var xs = function (i) { return padL + (n === 1 ? (W - padL - padR) / 2 : (i / (n - 1)) * (W - padL - padR)); };
    var ys = function (v) { return padT + (1 - v / niceMax) * (H - padT - padB); };
    var svgNS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('role', 'img');

    var grid = el('g', { 'class': 'mov-grid' }), axis = el('g', { 'class': 'mov-axis' });
    for (var g = 0; g <= divisions; g++) {
      var v = (niceMax / divisions) * g, y = ys(v);
      grid.appendChild(el('line', { x1: padL, x2: W - padR, y1: y, y2: y }));
      axis.appendChild(text(padL - 8, y + 4, fmtInt(v), 'end'));
    }
    var step = Math.max(1, Math.ceil(n / 8));
    for (var i = 0; i < n; i += step) {
      axis.appendChild(text(xs(i), H - 8, labels[i], n === 1 ? 'middle' : (i === 0 ? 'start' : 'middle')));
    }
    svg.appendChild(grid); svg.appendChild(axis);

    var linePts = values.map(function (v, i) { return xs(i).toFixed(1) + ',' + ys(v).toFixed(1); });
    if (n > 1) {
      svg.appendChild(el('path', { 'class': 'mov-area', d: 'M' + linePts[0] + ' L' + linePts.join(' L') + ' L' + xs(n - 1).toFixed(1) + ',' + ys(0).toFixed(1) + ' L' + xs(0).toFixed(1) + ',' + ys(0).toFixed(1) + ' Z' }));
      svg.appendChild(el('path', { 'class': 'mov-line', d: 'M' + linePts.join(' L') }));
    }
    var cross = el('line', { 'class': 'mov-crosshair', y1: padT, y2: H - padB, x1: 0, x2: 0 });
    svg.appendChild(cross);
    var dots = values.map(function (v, i) { var c = el('circle', { 'class': 'mov-dot', cx: xs(i), cy: ys(v), r: 4 }); svg.appendChild(c); return c; });
    var hit = el('rect', { 'class': 'mov-hit', x: padL, y: padT, width: W - padL - padR, height: H - padT - padB });
    svg.appendChild(hit);
    container.appendChild(svg);
    var tip = document.createElement('div');
    tip.className = 'matomo-ov-tooltip';
    container.appendChild(tip);

    var active = -1;
    function show(i) {
      if (i === active) { return; }
      if (active >= 0) { dots[active].classList.remove('is-active'); }
      active = i;
      dots[i].classList.add('is-active');
      cross.setAttribute('x1', xs(i)); cross.setAttribute('x2', xs(i)); cross.style.opacity = 1;
      tip.innerHTML = '<strong>' + esc(labels[i]) + '</strong>' + esc(t('chart_visits')) + ': ' + fmtInt(values[i]) + '<br>' + esc(t('chart_actions')) + ': ' + fmtInt(actions[i]);
      var rect = container.getBoundingClientRect();
      tip.style.left = (xs(i) / W * rect.width) + 'px';
      tip.style.top = (ys(values[i]) / H * rect.height) + 'px';
      tip.classList.add('is-visible');
    }
    function hide() {
      if (active >= 0) { dots[active].classList.remove('is-active'); }
      active = -1; cross.style.opacity = 0; tip.classList.remove('is-visible');
    }
    svg.addEventListener('mousemove', function (e) {
      var rect = svg.getBoundingClientRect();
      var x = (e.clientX - rect.left) / rect.width * W;
      var best = 0, dist = Infinity;
      for (var i = 0; i < n; i++) { var d = Math.abs(xs(i) - x); if (d < dist) { dist = d; best = i; } }
      show(best);
    });
    svg.addEventListener('mouseleave', hide);

    function el(name, attrs) {
      var node = document.createElementNS(svgNS, name);
      Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
      return node;
    }
    function text(x, y, str, anchor) {
      var node = el('text', { x: x, y: y, 'text-anchor': anchor });
      node.textContent = str;
      return node;
    }
  }

  // Ganzzahlige Achsenschritte: 10er/5er in fünf, 4er in vier, 2er in zwei Teile
  function niceDivisions(niceMax) {
    var mag = Math.pow(10, Math.floor(Math.log10(niceMax)));
    var nice = Math.round(niceMax / mag);
    return nice % 5 === 0 ? 5 : (nice === 2 ? 2 : 4);
  }

  function niceCeil(v) {
    if (v <= 4) { return 4; }
    var mag = Math.pow(10, Math.floor(Math.log10(v)));
    var norm = v / mag;
    var nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 4 ? 4 : norm <= 5 ? 5 : 10;
    return nice * mag;
  }

  if (window.jQuery) {
    jQuery(document).on('rex:ready', init);
  }
  if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', init); }
})();
