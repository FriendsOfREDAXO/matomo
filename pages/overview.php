<?php

use FriendsOfRedaxo\Matomo\MatomoApi;
use FriendsOfRedaxo\Matomo\UserAccess;
use FriendsOfRedaxo\Matomo\YRewriteHelper;

$addon = rex_addon::get('matomo');

// Prüfen ob Matomo konfiguriert ist
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');
$show_top_pages = rex_config::get('matomo', 'show_top_pages', false);

?>
<style>
    .matomo-stats-grid { display: flex; flex-wrap: wrap; margin: 0 -10px 20px; }
    .matomo-stat-col { padding: 0 10px; width: 33.333%; box-sizing: border-box; margin-bottom: 20px; }
    @media (max-width: 1200px) { .matomo-stat-col { width: 50%; } }
    @media (max-width: 600px) { .matomo-stat-col { width: 100%; } }

    .matomo-stat-card {
        background: #fff;
        border-radius: 4px;
        border-left: 4px solid #dfe3e9;
        padding: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        height: 100%;
        position: relative;
        transition: all 0.3s ease;
        opacity: 0; 
        animation: matomoSlideUp 0.6s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
    }
    .matomo-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .matomo-stat-card.blue { border-left-color: #3bb5f1; }
    .matomo-stat-card.green { border-left-color: #2ecc71; }
    .matomo-stat-card.purple { border-left-color: #9b59b6; }
    .matomo-stat-card.orange { border-left-color: #f39c12; }
    .matomo-stat-card.red { border-left-color: #e74c3c; }
    .matomo-stat-card.teal { border-left-color: #1abc9c; }

    .stat-icon { position: absolute; top: 15px; right: 15px; font-size: 24px; opacity: 0.15; color: #333; }
    .stat-number { font-size: 28px; font-weight: 700; color: #333; white-space: nowrap; line-height: 1.2; margin-bottom: 5px; }
    .stat-label { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; font-weight: 600; }
    .stat-trend { margin-top: 10px; font-size: 12px; font-weight: 500; display: flex; align-items: center; }
    .stat-trend i { margin-right: 4px; }
    
    .matomo-anim-delay-1 { animation-delay: 0.05s; }
    .matomo-anim-delay-2 { animation-delay: 0.1s; }
    .matomo-anim-delay-3 { animation-delay: 0.15s; }
    .matomo-anim-delay-4 { animation-delay: 0.2s; }
    .matomo-anim-delay-5 { animation-delay: 0.25s; }
    .matomo-anim-delay-6 { animation-delay: 0.3s; }

    @keyframes matomoSlideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<?php

$matomo_ready = false;
$is_external_matomo = false;

if ($matomo_url !== '' && $admin_token !== '') {
    if ($matomo_path !== '') {
        // Lokale Matomo-Installation - prüfe ob verfügbar
        $full_path = rex_path::frontend($matomo_path . '/');
        $matomo_ready = file_exists($full_path . 'index.php');
    } else {
        // Externe Matomo-Installation - keine lokale Verfügbarkeitsprüfung möglich
        $matomo_ready = true;
        $is_external_matomo = true;
    }
}

if (!$matomo_ready) {
    echo rex_view::warning(rex_i18n::rawMsg('matomo_not_configured', rex_url::backendPage('matomo/settings')));
    return;
}

// User und Admin-Status früh definieren
$user = rex::getUser();
$is_admin = $user instanceof rex_user && $user->isAdmin();

// Domains und Statistiken laden
$sites = [];
$stats_today = [];
$stats_week = [];

// User-spezifische Domain-Filter (bereits oben definiert)
// $show_all_domains = true; // TODO: Später echte berechtigungsprüfung. Aktuell alles anzeigen.
$user_allowed_domains = [];

// Wenn nicht Admin, Domain-Filter anwenden (später implementieren)
/** 
 * Todo: User-Rechte implementieren
if (!$show_all_domains) {
    // Hier könnten wir User-spezifische Domain-Berechtigungen laden
    // Für jetzt alle Domains anzeigen, aber das können wir später erweitern
    $show_all_domains = true;
}
*/

try {
    $api = new MatomoApi($matomo_url, $admin_token);
    $all_sites = $api->getSites();
    
    // YRewrite-Filter anwenden (zeigt nur YRewrite-Domains + Default)
    if (class_exists('FriendsOfRedaxo\Matomo\YRewriteHelper') && YRewriteHelper::isAvailable()) {
        $filtered_sites = YRewriteHelper::filterMatomoSitesByYRewrite($all_sites);
    } else {
        $filtered_sites = $all_sites;
    }
    // Persönlicher Zugang kann auf einzelne Websites beschränkt sein
    $filtered_sites = UserAccess::filterSites($filtered_sites);
    
    // Domain-Filterung anwenden
    $sites = [];
    foreach ($filtered_sites as $site) {
        // TODO: Hier später User-spezifische Domain-Filterung
        // Für jetzt zeigen wir alles an da $show_all_domains true ist
        $sites[] = $site;
    }
    
    // Erweiterte Statistiken laden
    foreach ($sites as $site) {
        $site_id = $site['idsite'];
        
        // Heute
        try {
            $today = $api->getVisitorStats($site_id, 'day', 'today');
            $stats_today[$site_id] = $today;
        } catch (Exception $e) {
            $stats_today[$site_id] = ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
        }
        
        // Diese Woche  
        try {
            $week = $api->getVisitorStats($site_id, 'week', 'today');
            $stats_week[$site_id] = $week;
        } catch (Exception $e) {
            $stats_week[$site_id] = ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
        }
    }

    // Top Pages laden (wenn aktiviert)
    $top_pages_data = [];
    $show_top_pages_bool = (bool) $show_top_pages;
    if ($show_top_pages_bool) {
        foreach ($sites as $site) {
            $site_id = (int) $site['idsite'];
            try {
                $top_pages = $api->getTopPages($site_id, 'week', 'today', 5);
                $top_pages_data[$site_id] = $top_pages;
            } catch (Exception $e) {
                $top_pages_data[$site_id] = [];
            }
        }
    }
} catch (Exception $e) {
    echo rex_view::error($addon->i18n('matomo_overview_load_error', $e->getMessage()));
    return;
}

?>

<div class="row">
    <div class="col-sm-12">
        
        <!-- Gesamt-Statistiken -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-chart-bar"></i> <?= $addon->i18n('matomo_analytics_overview') ?>
                    <small class="text-muted">(<?= count($sites) ?> <?= count($sites) === 1 ? $addon->i18n('matomo_domain') : $addon->i18n('matomo_domains') ?>)</small>
                    <div class="btn-group pull-right">
                        <a href="<?= rex_escape(UserAccess::openUrl()) ?>" target="_blank" class="btn btn-primary btn-sm rex-pulse">
                            <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?>
                        </a>
                        <?php if ($is_admin && null === UserAccess::forCurrentUser()): ?>
                            <a href="<?= rex_url::backendPage('matomo/settings') ?>" class="btn btn-default btn-sm" title="<?= rex_escape($addon->i18n('matomo_setup_access_hint')) ?>">
                                <i class="fa fa-user-plus"></i> <?= $addon->i18n('matomo_setup_step_access') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </h3>
            </div>
            <div class="panel-body">
                <?php
                // Gesamtstatistiken berechnen
                $total_visits_today = 0;
                $total_actions_today = 0;
                $total_users_today = 0;
                $bounce_count_today = 0;
                $sum_visit_length_today = 0;
                $total_conversions_today = 0;
                $max_actions_today = 0;
                
                $total_visits_week = 0;
                $active_sites = 0;
                
                foreach ($stats_today as $stat) {
                    $visits = isset($stat['nb_visits']) ? (int) $stat['nb_visits'] : 0;
                    $total_visits_today += $visits;
                    $total_actions_today += isset($stat['nb_actions']) ? (int) $stat['nb_actions'] : 0;
                    $total_users_today += isset($stat['nb_users']) ? (int) $stat['nb_users'] : 0;
                    $bounce_count_today += isset($stat['bounce_count']) ? (int) $stat['bounce_count'] : 0;
                    $sum_visit_length_today += isset($stat['sum_visit_length']) ? (int) $stat['sum_visit_length'] : 0;
                    $total_conversions_today += isset($stat['nb_visits_converted']) ? (int) $stat['nb_visits_converted'] : 0;
                    
                    $current_max_actions = isset($stat['max_actions']) ? (int) $stat['max_actions'] : 0;
                    if ($current_max_actions > $max_actions_today) {
                        $max_actions_today = $current_max_actions;
                    }
                    
                    if ($visits > 0) {
                        $active_sites++;
                    }
                }
                
                foreach ($stats_week as $stat) {
                    $total_visits_week += isset($stat['nb_visits']) ? (int) $stat['nb_visits'] : 0;
                }
                
                // Durchschnittswerte berechnen
                $avg_time_on_site = $total_visits_today > 0 ? round($sum_visit_length_today / $total_visits_today) : 0;
                $bounce_rate = $total_visits_today > 0 ? round(($bounce_count_today / $total_visits_today) * 100, 1) : 0;
                
                $growth_rate = $total_visits_week > 0 && $total_visits_today > 0 ? 
                    round((($total_visits_today * 7) / $total_visits_week - 1) * 100, 1) : 0;
                ?>
                
                <!-- Metrics Grid -->
                <div class="matomo-stats-grid">
                    <!-- Visits -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card blue matomo-anim-delay-1">
                            <div class="stat-icon"><i class="fa fa-eye"></i></div>
                            <div class="stat-number" data-count="<?= $total_visits_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_visits') ?> (<?= $addon->i18n('matomo_today') ?>)</div>
                            <div class="stat-trend <?= $growth_rate >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="fa fa-<?= $growth_rate >= 0 ? 'chart-line' : 'arrow-down' ?>"></i> 
                                <?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>% <?= $addon->i18n('matomo_trend_7_days') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card purple matomo-anim-delay-2">
                            <div class="stat-icon"><i class="fa fa-users"></i></div>
                            <div class="stat-number" data-count="<?= $total_users_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_users') ?> (<?= $addon->i18n('matomo_today') ?>)</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-globe"></i> 
                                <?= $active_sites ?> <?= $addon->i18n('matomo_active_domains_today') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Engagement (Duration) -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card orange matomo-anim-delay-3">
                            <div class="stat-icon"><i class="fa fa-clock"></i></div>
                            <div class="stat-number"><?= gmdate("i:s", (int)$avg_time_on_site) ?></div>
                            <div class="stat-label"><?= $addon->i18n('matomo_avg_duration') ?></div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-sign-out-alt"></i> 
                                <?= $bounce_rate ?>% <?= $addon->i18n('matomo_bounce_rate') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card green matomo-anim-delay-4">
                            <div class="stat-icon"><i class="fa fa-mouse-pointer"></i></div>
                            <div class="stat-number" data-count="<?= $total_actions_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_actions') ?> (<?= $addon->i18n('matomo_today') ?>)</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-list-ol"></i> 
                                <?= $total_visits_today > 0 ? round($total_actions_today / $total_visits_today, 1) : 0 ?> <?= $addon->i18n('matomo_actions_per_visit') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversions (Goals) -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card red matomo-anim-delay-5">
                            <div class="stat-icon"><i class="fa fa-flag-checkered"></i></div>
                            <div class="stat-number" data-count="<?= $total_conversions_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_conversions') ?> (<?= $addon->i18n('matomo_goals') ?>)</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-trophy"></i> 
                                <?= $total_visits_today > 0 ? round(($total_conversions_today / $total_visits_today) * 100, 1) : 0 ?>% <?= $addon->i18n('matomo_conversion_rate') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Peak Activity -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card teal matomo-anim-delay-6">
                            <div class="stat-icon"><i class="fa fa-bolt"></i></div>
                            <div class="stat-number" data-count="<?= $max_actions_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_max_actions') ?></div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-level-up-alt"></i> 
                                <?= $addon->i18n('matomo_highest_engagement') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Number Animation Script -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var counters = document.querySelectorAll('.stat-number[data-count]');
                    counters.forEach(function(counter) {
                        var target = parseInt(counter.getAttribute('data-count'));
                        var duration = 1500; // ms
                        var start = null;
                        
                        function step(timestamp) {
                            if (!start) start = timestamp;
                            var progress = timestamp - start;
                            var percent = Math.min(progress / duration, 1);
                            
                            // Ease out calc
                            var easeOut = 1 - Math.pow(1 - percent, 3);
                            
                            counter.innerText = Math.floor(easeOut * target).toLocaleString('de-DE');
                            
                            if (progress < duration) {
                                window.requestAnimationFrame(step);
                            } else {
                                counter.innerText = target.toLocaleString('de-DE');
                            }
                        }
                        
                        window.requestAnimationFrame(step);
                    });
                });
                </script>
            </div>
        </div>

        <!-- Top 5 Seiten (diese Woche) -->
        <?php if ($show_top_pages_bool && count($sites) > 0): ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-chart-line"></i> <?= $addon->i18n('matomo_top_5_pages_this_week') ?>
                    <small class="text-muted">(<?= $addon->i18n('matomo_most_visited_pages') ?>)</small>
                </h3>
            </div>
            <div class="panel-body">
                <?php 
                // Alle Top Pages kombinieren und nach Visits sortieren
                $all_top_pages = [];
                foreach ($top_pages_data as $site_id => $pages) {
                        $site_name = '';
                        foreach ($sites as $site) {
                            if ($site['idsite'] === $site_id) {
                                $site_name = $site['name'];
                                break;
                            }
                        }
                        
                        foreach ($pages as $page) {
                            if (isset($page['label']) && isset($page['nb_visits'])) {
                                $all_top_pages[] = [
                                    'site_name' => $site_name,
                                    'site_id' => $site_id,
                                    'url' => $page['label'],
                                    'visits' => (int) $page['nb_visits'],
                                    'actions' => (int) ($page['nb_hits'] ?? 0),
                                    'avg_time' => isset($page['avg_time_on_page']) ? round($page['avg_time_on_page']) : 0
                                ];
                            }
                        }
                    }
                
                // Nach Visits sortieren
                usort($all_top_pages, function($a, $b) {
                    return $b['visits'] - $a['visits'];
                });
                
                // Nur Top 5 anzeigen
                $all_top_pages = array_slice($all_top_pages, 0, 5);
                ?>
                
                <?php if (count($all_top_pages) === 0): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <strong><?= $addon->i18n('matomo_no_data_available') ?>:</strong> <?= $addon->i18n('matomo_no_page_views_recorded') ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><i class="fa fa-trophy"></i> <?= $addon->i18n('matomo_rank') ?></th>
                                    <th><i class="fa fa-file-alt"></i> <?= $addon->i18n('matomo_page') ?></th>
                                    <th><i class="fa fa-globe"></i> <?= $addon->i18n('matomo_domain') ?></th>
                                    <th class="text-center"><i class="fa fa-eye"></i> <?= $addon->i18n('matomo_visits') ?></th>
                                    <th class="text-center"><i class="fa fa-mouse-pointer"></i> <?= $addon->i18n('matomo_actions') ?></th>
                                    <th class="text-center"><i class="fa fa-clock"></i> <?= $addon->i18n('matomo_avg_time') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_top_pages as $index => $page): ?>
                                <tr>
                                    <td>
                                        <span class="label <?= $index < 3 ? 'label-warning' : 'label-default' ?>">
                                            #<?= $index + 1 ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code title="<?= rex_escape($page['url']) ?>">
                                            <?= rex_escape(strlen($page['url']) > 50 ? substr($page['url'], 0, 47) . '...' : $page['url']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= rex_escape($page['site_name']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-primary">
                                            <i class="fa fa-eye"></i> <?= number_format($page['visits']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-success">
                                            <i class="fa fa-mouse-pointer"></i> <?= number_format($page['actions']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($page['avg_time'] > 0): ?>
                                            <span class="label label-info">
                                                <i class="fa fa-clock"></i> <?= gmdate('i:s', (int) $page['avg_time']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Domain-spezifische Statistiken -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-globe"></i> <?= $addon->i18n('matomo_domain_statistics') ?>
                    <?php /* if (!$show_all_domains): ?>
                        <small class="text-muted">(<?= $addon->i18n('matomo_filtered_permissions') ?>)</small>
                    <?php endif; */ ?>
                    <?php if ($is_admin): ?>
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/domains']) ?>" class="btn btn-success btn-sm pull-right">
                            <i class="fa fa-plus"></i> <?= $addon->i18n('matomo_manage_domains') ?>
                        </a>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="panel-body">
                
                <?php if (count($sites) === 0): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <strong><?= $addon->i18n('matomo_no_domains_available') ?>:</strong> 
                        <?= $addon->i18n('matomo_no_domains_configured') ?>
                    </div>
                <?php else: ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><i class="fa fa-tag"></i> <?= $addon->i18n('matomo_domain') ?></th>
                                    <th><i class="fa fa-link"></i> URL</th>
                                    <th class="text-center"><i class="fa fa-calendar-day"></i> <?= $addon->i18n('matomo_today') ?></th>
                                    <th class="text-center"><i class="fa fa-calendar-week"></i> <?= $addon->i18n('matomo_this_week') ?></th>
                                    <th class="text-center"><i class="fa fa-mouse-pointer"></i> <?= $addon->i18n('matomo_actions') ?></th>
                                    <th class="text-center"><i class="fa fa-external-link-alt"></i> Matomo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): 
                                    $site_id = $site['idsite'];
                                    $today = $stats_today[$site_id] ?? ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
                                    $week = $stats_week[$site_id] ?? ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
                                ?>
                                <tr>
                                    <?php
                                    $site_host = (string) parse_url((string) $site['main_url'], PHP_URL_HOST);
                                    $site_label = '' !== $site_host ? $site_host : (string) $site['name'];
                                    $site_sub = ('' !== $site_host && $site['name'] !== $site_host && !str_contains((string) $site['name'], '%')) ? $site['name'] . ' · ' : '';
                                    ?>
                                    <td>
                                        <strong><?= rex_escape($site_label) ?></strong>
                                        <br><small class="text-muted"><?= rex_escape($site_sub) ?>ID: <?= $site_id ?></small>
                                    </td>
                                    <td>
                                        <a href="<?= rex_escape($site['main_url']) ?>" target="_blank" class="btn btn-link btn-sm">
                                            <i class="fa fa-external-link-alt"></i> <?= rex_escape(parse_url($site['main_url'], PHP_URL_HOST)) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-primary" title="<?= $addon->i18n('matomo_visits') ?>">
                                            <i class="fa fa-eye"></i> <?= number_format($today['nb_visits'] ?? 0) ?>
                                        </span>
                                        <br>
                                        <span class="label label-info" title="<?= $addon->i18n('matomo_users') ?>">
                                            <i class="fa fa-users"></i> <?= number_format($today['nb_users'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-primary" title="<?= $addon->i18n('matomo_visits') ?>">
                                            <i class="fa fa-eye"></i> <?= number_format($week['nb_visits'] ?? 0) ?>
                                        </span>
                                        <br>
                                        <span class="label label-info" title="<?= $addon->i18n('matomo_users') ?>">
                                            <i class="fa fa-users"></i> <?= number_format($week['nb_users'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-success" title="<?= $addon->i18n('matomo_actions_today') ?>">
                                            <i class="fa fa-mouse-pointer"></i> <?= number_format($today['nb_actions'] ?? 0) ?>
                                        </span>
                                        <br>
                                        <span class="label label-warning" title="<?= $addon->i18n('matomo_actions_this_week') ?>">
                                            <i class="fa fa-chart-bar"></i> <?= number_format($week['nb_actions'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= rex_escape(UserAccess::openUrl((int) $site_id)) ?>" target="_blank" class="btn btn-primary btn-sm">
                                            <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open') ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php endif; ?>
    </div>
</div>

<!-- Auto-Refresh Script -->
<script>
// Seite alle 5 Minuten automatisch aktualisieren
setTimeout(function() {
    location.reload();
}, 300000); // 5 Minuten
</script>

