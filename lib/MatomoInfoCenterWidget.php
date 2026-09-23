<?php

namespace FriendsOfRedaxo\Matomo;

use Exception;
use KLXM\InfoCenter\AbstractWidget;
use rex_config;
use rex_escape;
use rex_i18n;
use rex_url;

/**
 * Info-Center-Widget: Domain-Statistiken wie auf der Übersicht, kompakt.
 * Je Website Besuche der letzten 7 Tage mit Sparkline und Vergleich zur Vorwoche,
 * dazu "Öffnen" mit Auto-Login bzw. Token des aktuellen Benutzers. Die Zahlen werden
 * nach dem Rendern über die Stats-API nachgeladen (Server-Cache), damit das Info-Center
 * nicht auf Matomo wartet.
 */
class MatomoInfoCenterWidget extends AbstractWidget
{
    protected bool $supportsLazyLoading = false;

    public function __construct()
    {
        parent::__construct();
        $this->title = rex_i18n::msg('matomo_widget_title');
        $this->priority = 3;
    }

    public function render(): string
    {
        $matomoUrl = (string) rex_config::get('matomo', 'matomo_url', '');
        $adminToken = (string) rex_config::get('matomo', 'admin_token', '');
        if ('' === $matomoUrl || '' === $adminToken) {
            return $this->wrapContent($this->notice('info', rex_i18n::msg('matomo_widget_not_configured'), rex_i18n::msg('matomo_widget_configure_help'), 'matomo/settings', rex_i18n::msg('matomo_configure')));
        }

        try {
            $sites = MatomoStatsApi::allowedSites(new MatomoApi($matomoUrl, $adminToken));
        } catch (Exception $e) {
            return $this->wrapContent($this->notice('danger', rex_i18n::msg('matomo_widget_error'), $e->getMessage(), 'matomo/settings', rex_i18n::msg('matomo_check_settings')));
        }
        if ([] === $sites) {
            return $this->wrapContent($this->notice('warning', rex_i18n::msg('matomo_widget_no_sites'), rex_i18n::msg('matomo_widget_add_sites_help'), 'matomo/domains', rex_i18n::msg('matomo_add_domain')));
        }

        $rows = '';
        foreach ($sites as $site) {
            $id = (int) $site['idsite'];
            $host = (string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST);
            $open = AutoLogin::form($id, rex_i18n::msg('matomo_ov_open'), 'btn btn-default btn-xs', 'fa-sign-in-alt');
            if ('' === $open) {
                $open = '<a class="btn btn-default btn-xs" target="_blank" href="' . rex_escape(UserAccess::openUrl($id)) . '"><i class="fa fa-external-link-alt"></i> ' . rex_escape(rex_i18n::msg('matomo_ov_open')) . '</a>';
            }
            $rows .= '<tr data-site="' . $id . '">'
                . '<td class="mw-host"><strong>' . rex_escape('' !== $host ? $host : (string) $site['name']) . '</strong></td>'
                . '<td class="mw-spark"><span class="mw-skeleton"></span></td>'
                . '<td class="mw-num"><span class="mw-visits">–</span><small class="mw-delta"></small></td>'
                . '<td class="mw-open">' . $open . '</td></tr>';
        }

        $config = [
            'endpoint' => rex_url::backendController(['rex-api-call' => 'matomo_stats']),
            'locale' => str_replace('_', '-', rex_i18n::getLocale()),
            'i18n' => ['visits' => rex_i18n::msg('matomo_ov_visits'), 'vs' => rex_i18n::msg('matomo_widget_vs_prev_week'), 'error' => rex_i18n::msg('matomo_widget_error')],
        ];

        $content = '<div class="matomo-widget" data-config="' . rex_escape((string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">'
            . '<table class="matomo-widget-table"><thead><tr><th>' . rex_escape(rex_i18n::msg('matomo_ov_domain')) . '</th><th>' . rex_escape(rex_i18n::msg('matomo_widget_last7')) . '</th><th class="mw-num">' . rex_escape(rex_i18n::msg('matomo_ov_visits')) . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<div class="matomo-widget-footer"><a href="' . rex_url::backendPage('matomo/overview') . '"><i class="fa fa-chart-bar"></i> ' . rex_escape(rex_i18n::msg('matomo_full_stats')) . '</a><span class="mw-updated text-muted"></span></div>'
            . '</div>' . self::assets();

        return $this->wrapContent($content);
    }

    private function notice(string $type, string $title, string $text, string $page, string $button): string
    {
        return '<div class="alert alert-' . $type . '" style="margin-bottom:0"><strong>' . rex_escape($title) . '</strong><br>' . rex_escape($text)
            . '<br><a href="' . rex_url::backendPage($page) . '" class="btn btn-primary btn-xs" style="margin-top:6px">' . rex_escape($button) . '</a></div>';
    }

    /**
     * Stil und Nachlade-Script einmal je Seite.
     */
    private static function assets(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        return <<<'HTML'
<style>
.matomo-widget { --mw-accent: #2f7fcf; --mw-soft: rgba(47,127,207,.14); --mw-grid: rgba(128,128,128,.22); --mw-muted: #7a8391; --mw-good: #2e8b57; --mw-bad: #c8463e; }
.rex-theme-dark .matomo-widget { --mw-accent: #6fb1f0; --mw-soft: rgba(111,177,240,.18); --mw-muted: #9aa4b1; --mw-good: #5fc37f; --mw-bad: #ef7b73; }
@media (prefers-color-scheme: dark) { body:not(.rex-theme-light) .matomo-widget { --mw-accent: #6fb1f0; --mw-soft: rgba(111,177,240,.18); --mw-muted: #9aa4b1; --mw-good: #5fc37f; --mw-bad: #ef7b73; } }
.matomo-widget-table { width: 100%; font-size: 12px; border-collapse: collapse; }
.matomo-widget-table th { font-weight: 600; color: var(--mw-muted); font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding: 0 6px 4px 0; text-align: left; border-bottom: 1px solid var(--mw-grid); }
.matomo-widget-table td { padding: 6px 6px 6px 0; border-bottom: 1px solid var(--mw-grid); vertical-align: middle; }
.matomo-widget-table tr:last-child td { border-bottom: 0; }
.matomo-widget-table .mw-host { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.matomo-widget-table .mw-spark { width: 96px; }
.matomo-widget-table .mw-spark svg { width: 90px; height: 24px; display: block; }
.matomo-widget-table .mw-spark .mw-line { fill: none; stroke: var(--mw-accent); stroke-width: 1.5; stroke-linejoin: round; }
.matomo-widget-table .mw-spark .mw-area { fill: var(--mw-soft); }
.matomo-widget-table .mw-spark .mw-dot { fill: var(--mw-accent); }
.matomo-widget-table .mw-num, .matomo-widget-table th.mw-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.matomo-widget-table .mw-visits { font-weight: 700; font-size: 14px; }
.matomo-widget-table .mw-delta { display: block; color: var(--mw-muted); font-size: 11px; }
.matomo-widget-table .mw-delta.is-up { color: var(--mw-good); }
.matomo-widget-table .mw-delta.is-down { color: var(--mw-bad); }
.matomo-widget-table .mw-open { text-align: right; white-space: nowrap; }
.matomo-widget-table .mw-open form { display: inline; }
.mw-skeleton { display: block; width: 90px; height: 18px; border-radius: 3px; background: linear-gradient(90deg, var(--mw-grid) 25%, transparent 50%, var(--mw-grid) 75%); background-size: 200% 100%; animation: mw-shimmer 1.4s infinite; }
@keyframes mw-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
.matomo-widget-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 12px; }
.matomo-widget-error { color: var(--mw-bad); font-size: 12px; margin-top: 6px; }
</style>
<script>
(function () {
  function init() {
    document.querySelectorAll('.matomo-widget:not([data-mw-init])').forEach(function (root) {
      root.setAttribute('data-mw-init', '1');
      var cfg = JSON.parse(root.getAttribute('data-config') || '{}');
      var fmt = function (n) { return new Intl.NumberFormat(cfg.locale || undefined).format(Math.round(n || 0)); };
      var fmtDec = function (n) { return new Intl.NumberFormat(cfg.locale || undefined, { maximumFractionDigits: 1 }).format(n || 0); };
      var get = function (part) { return fetch(cfg.endpoint + '&part=' + part + '&site=0&range=last7', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }); };
      get('summary').then(function (json) {
        if (!json.success) { throw new Error(json.message || 'error'); }
        (json.data.sites || []).forEach(function (site) {
          var tr = root.querySelector('tr[data-site="' + site.idsite + '"]');
          if (!tr) { return; }
          tr.querySelector('.mw-visits').textContent = fmt(site.metrics.visits);
          var cur = site.metrics.visits, prev = site.previous.visits, d = tr.querySelector('.mw-delta');
          if (prev > 0) { var pct = ((cur - prev) / prev) * 100; d.textContent = (pct > 0 ? '+' : '') + fmtDec(pct) + ' % ' + cfg.i18n.vs; d.className = 'mw-delta ' + (pct > 0 ? 'is-up' : (pct < 0 ? 'is-down' : '')); }
        });
        var upd = root.querySelector('.mw-updated');
        if (upd) { upd.textContent = new Date(Date.now() - (json.age || 0) * 1000).toLocaleTimeString(cfg.locale || undefined, { hour: '2-digit', minute: '2-digit' }); }
        return get('series');
      }).then(function (json) {
        if (!json || !json.success) { return; }
        var series = json.data.series || {};
        root.querySelectorAll('tr[data-site]').forEach(function (tr) {
          var values = series[tr.getAttribute('data-site')] || [];
          var cell = tr.querySelector('.mw-spark');
          cell.innerHTML = spark(values);
        });
      }).catch(function (e) {
        root.querySelectorAll('.mw-skeleton').forEach(function (s) { s.remove(); });
        var err = document.createElement('div'); err.className = 'matomo-widget-error'; err.textContent = cfg.i18n.error + ': ' + e.message; root.appendChild(err);
      });
    });
  }
  function spark(values) {
    var W = 90, H = 24, n = values.length;
    if (n < 2) { return '<svg viewBox="0 0 90 24"></svg>'; }
    var max = Math.max.apply(null, values) || 1;
    var pts = values.map(function (v, i) { return [(i / (n - 1)) * (W - 4) + 2, H - 3 - (v / max) * (H - 8)]; });
    var line = pts.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var last = pts[n - 1];
    return '<svg viewBox="0 0 90 24"><polygon class="mw-area" points="' + pts[0][0].toFixed(1) + ',' + (H - 3) + ' ' + line + ' ' + last[0].toFixed(1) + ',' + (H - 3) + '"/><polyline class="mw-line" points="' + line + '"/><circle class="mw-dot" cx="' + last[0].toFixed(1) + '" cy="' + last[1].toFixed(1) + '" r="2"/></svg>';
  }
  if (window.jQuery) { jQuery(document).on('rex:ready', init); }
  if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', init); }
})();
</script>
HTML;
    }
}
