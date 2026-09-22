<?php

use FriendsOfRedaxo\Matomo\MatomoApi;
use FriendsOfRedaxo\Matomo\MatomoStatsApi;
use FriendsOfRedaxo\Matomo\UserAccess;

$addon = rex_addon::get('matomo');

$matomo_url = (string) rex_config::get('matomo', 'matomo_url', '');
$admin_token = (string) rex_config::get('matomo', 'admin_token', '');
$matomo_path = (string) rex_config::get('matomo', 'matomo_path', '');

$matomo_ready = false;
if ('' !== $matomo_url && '' !== $admin_token) {
    $matomo_ready = '' === $matomo_path || file_exists(rex_path::frontend($matomo_path . '/index.php'));
}
if (!$matomo_ready) {
    echo rex_view::warning(rex_i18n::rawMsg('matomo_not_configured', rex_url::backendPage('matomo/settings')));
    return;
}

$user = rex::getUser();
$is_admin = $user instanceof rex_user && $user->isAdmin();

// Nur die Website-Liste wird synchron geladen (ein API-Aufruf), alle Kennzahlen kommen per Ajax.
try {
    $sites = MatomoStatsApi::allowedSites(new MatomoApi($matomo_url, $admin_token));
} catch (Exception $e) {
    echo rex_view::error($addon->i18n('matomo_overview_load_error', $e->getMessage()));
    return;
}

if ([] === $sites) {
    echo rex_view::info('<strong>' . $addon->i18n('matomo_no_domains_available') . ':</strong> ' . $addon->i18n('matomo_no_domains_configured'));
    if ($is_admin) {
        echo '<a class="btn btn-primary" href="' . rex_url::backendPage('matomo/domains') . '"><i class="fa fa-plus"></i> ' . $addon->i18n('matomo_add_domain') . '</a>';
    }
    return;
}

$ranges = [
    'today' => $addon->i18n('matomo_range_today'),
    'yesterday' => $addon->i18n('matomo_range_yesterday'),
    'last7' => $addon->i18n('matomo_range_last7'),
    'last30' => $addon->i18n('matomo_range_last30'),
    'month' => $addon->i18n('matomo_range_month'),
    'year' => $addon->i18n('matomo_range_year'),
];

$i18n = [];
foreach ([
    'visits', 'unique_visitors', 'actions', 'bounce_rate', 'avg_duration', 'actions_per_visit', 'conversions', 'conversion_rate',
    'vs_previous', 'no_data', 'loading', 'error_prefix', 'hits', 'page', 'referrer_direct', 'domain', 'open', 'updated',
    'chart_visits', 'chart_actions', 'hour_suffix', 'table_view', 'chart_view',
] as $key) {
    $i18n[$key] = $addon->i18n('matomo_ov_' . $key);
}

$config = [
    'endpoint' => rex_url::backendController(['rex-api-call' => 'matomo_stats']),
    'sites' => array_map(static fn (array $s): array => [
        'id' => (int) $s['idsite'],
        'name' => (string) $s['name'],
        'host' => (string) parse_url((string) ($s['main_url'] ?? ''), PHP_URL_HOST),
    ], $sites),
    'openUrlAll' => UserAccess::openUrl(),
    'showTopPages' => (bool) rex_config::get('matomo', 'show_top_pages', true),
    'locale' => str_replace('_', '-', rex_i18n::getLocale()),
    'i18n' => $i18n,
];

$card = static function (string $id, string $title, string $icon, string $extra = ''): string {
    return '<div class="panel panel-default matomo-ov-card" data-matomo-part="' . $id . '">'
        . '<div class="panel-heading"><h3 class="panel-title"><i class="fa ' . $icon . '"></i> ' . rex_escape($title) . $extra . '</h3></div>'
        . '<div class="panel-body matomo-ov-body"><div class="matomo-ov-skeleton"></div></div></div>';
};
?>
<div class="matomo-ov" id="matomo-overview" data-config="<?= rex_escape(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">

    <div class="matomo-ov-toolbar">
        <div class="matomo-ov-filters">
            <label for="matomo-ov-site" class="sr-only"><?= $addon->i18n('matomo_ov_domain') ?></label>
            <select id="matomo-ov-site" class="form-control input-sm" data-matomo-filter="site">
                <option value="0"><?= $addon->i18n('matomo_ov_all_domains') ?> (<?= count($sites) ?>)</option>
                <?php foreach ($config['sites'] as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= rex_escape('' !== $s['host'] ? $s['host'] : $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="matomo-ov-range" class="sr-only"><?= $addon->i18n('matomo_ov_range') ?></label>
            <select id="matomo-ov-range" class="form-control input-sm" data-matomo-filter="range">
                <?php foreach ($ranges as $key => $label): ?>
                    <option value="<?= $key ?>"<?= 'last7' === $key ? ' selected' : '' ?>><?= rex_escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-default btn-sm" data-matomo-refresh title="<?= rex_escape($addon->i18n('matomo_ov_refresh')) ?>"><i class="fa fa-sync"></i></button>
            <span class="matomo-ov-updated text-muted" data-matomo-updated></span>
        </div>
        <div class="matomo-ov-actions">
            <a href="<?= rex_escape($config['openUrlAll']) ?>" target="_blank" class="btn btn-primary btn-sm" data-matomo-open><i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?></a>
            <?php if ($is_admin): ?>
                <a href="<?= rex_url::backendPage('matomo/domains') ?>" class="btn btn-default btn-sm"><i class="fa fa-sitemap"></i> <?= $addon->i18n('matomo_manage_domains') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="matomo-ov-kpis" data-matomo-part="summary">
        <?php for ($i = 0; $i < 8; ++$i): ?>
            <div class="matomo-ov-kpi"><div class="matomo-ov-skeleton"></div></div>
        <?php endfor; ?>
    </div>

    <?= $card('chart', $addon->i18n('matomo_ov_trend'), 'fa-chart-line', '<span class="matomo-ov-card-tools"><button type="button" class="btn btn-link btn-xs" data-matomo-toggle-table>' . rex_escape($addon->i18n('matomo_ov_table_view')) . '</button></span>') ?>

    <div class="matomo-ov-grid">
        <?php if ($config['showTopPages']): ?>
            <div class="matomo-ov-grid-wide"><?= $card('pages', $addon->i18n('matomo_ov_top_pages'), 'fa-file-alt') ?></div>
        <?php endif; ?>
        <?= $card('referrers', $addon->i18n('matomo_ov_referrers'), 'fa-share-alt') ?>
        <?= $card('devices', $addon->i18n('matomo_ov_devices'), 'fa-mobile-alt') ?>
        <?= $card('countries', $addon->i18n('matomo_ov_countries'), 'fa-globe') ?>
    </div>

    <?= $card('sites', $addon->i18n('matomo_domain_statistics'), 'fa-sitemap') ?>
</div>
