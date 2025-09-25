<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// Prüfen ob Matomo konfiguriert ist
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');

$matomo_ready = false;
if ($matomo_url && $admin_token && $matomo_path) {
    $full_path = rex_path::frontend($matomo_path . '/');
    $matomo_ready = file_exists($full_path . 'index.php');
}

if (!$matomo_ready) {
    echo rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings'])));
    return;
}

// Domains und Statistiken laden
$sites = [];
$stats_today = [];
$stats_week = [];

try {
    $api = new MatomoApi($matomo_url, $admin_token, $user_token);
    $sites = $api->getSites();
    
    // Statistiken für heute und diese Woche laden
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
} catch (Exception $e) {
    echo rex_view::error($addon->i18n('matomo_overview_load_error', $e->getMessage()));
    return;
}

?>

<div class="row">
    <div class="col-sm-12">
        
        <!-- Gesamt-Statistiken -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-chart-bar"></i> <?= $addon->i18n('matomo_overview_summary') ?>
                    <div class="btn-group pull-right">
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/dashboard']) ?>" class="btn btn-primary btn-sm">
                            <i class="fa fa-tachometer-alt"></i> <?= $addon->i18n('matomo_goto_dashboard') ?>
                        </a>
                        <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-default btn-sm">
                            <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?>
                        </a>
                    </div>
                </h3>
            </div>
            <div class="panel-body">
                <?php
                $total_visits_today = 0;
                $total_actions_today = 0;
                $total_users_today = 0;
                $total_visits_week = 0;
                $total_actions_week = 0;  
                $total_users_week = 0;
                
                foreach ($stats_today as $stat) {
                    $total_visits_today += $stat['nb_visits'] ?? 0;
                    $total_actions_today += $stat['nb_actions'] ?? 0;
                    $total_users_today += $stat['nb_users'] ?? 0;
                }
                
                foreach ($stats_week as $stat) {
                    $total_visits_week += $stat['nb_visits'] ?? 0;
                    $total_actions_week += $stat['nb_actions'] ?? 0;
                    $total_users_week += $stat['nb_users'] ?? 0;
                }
                ?>
                
                <div class="row">
                    <div class="col-sm-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4><?= $addon->i18n('matomo_overview_today') ?></h4>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-eye fa-2x text-primary"></i>
                                        <h3 class="text-primary"><?= number_format($total_visits_today) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_visits') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-mouse-pointer fa-2x text-success"></i>
                                        <h3 class="text-success"><?= number_format($total_actions_today) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_actions') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-users fa-2x text-info"></i>
                                        <h3 class="text-info"><?= number_format($total_users_today) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_users') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-sm-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4><?= $addon->i18n('matomo_overview_week') ?></h4>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-eye fa-2x text-primary"></i>
                                        <h3 class="text-primary"><?= number_format($total_visits_week) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_visits') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-mouse-pointer fa-2x text-success"></i>
                                        <h3 class="text-success"><?= number_format($total_actions_week) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_actions') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-users fa-2x text-info"></i>
                                        <h3 class="text-info"><?= number_format($total_users_week) ?></h3>
                                        <small class="text-muted"><?= $addon->i18n('matomo_users') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain-spezifische Statistiken -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-globe"></i> <?= $addon->i18n('matomo_overview_by_domain') ?> (<?= count($sites) ?>)
                    <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/domains']) ?>" class="btn btn-success btn-sm pull-right">
                        <i class="fa fa-plus"></i> <?= $addon->i18n('matomo_add_domain') ?>
                    </a>
                </h3>
            </div>
            <div class="panel-body">
                
                <?php if (empty($sites)): ?>
                    <p class="text-muted"><?= $addon->i18n('matomo_no_domains_yet') ?></p>
                <?php else: ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?= $addon->i18n('matomo_table_name') ?></th>
                                    <th><?= $addon->i18n('matomo_table_url') ?></th>
                                    <th class="text-center"><?= $addon->i18n('matomo_overview_today') ?></th>
                                    <th class="text-center"><?= $addon->i18n('matomo_overview_week') ?></th>
                                    <th class="text-center"><?= $addon->i18n('matomo_actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): 
                                    $site_id = $site['idsite'];
                                    $today = $stats_today[$site_id] ?? ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
                                    $week = $stats_week[$site_id] ?? ['nb_visits' => 0, 'nb_actions' => 0, 'nb_users' => 0];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= rex_escape($site['name']) ?></strong>
                                        <br><small class="text-muted">ID: <?= $site_id ?></small>
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
                                        <div class="btn-group-vertical" role="group">
                                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=day&date=today" 
                                               target="_blank" class="btn btn-primary btn-xs">
                                                <i class="fa fa-chart-line"></i> <?= $addon->i18n('matomo_daily_stats') ?>
                                            </a>
                                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=week&date=today" 
                                               target="_blank" class="btn btn-info btn-xs">
                                                <i class="fa fa-calendar-week"></i> <?= $addon->i18n('matomo_weekly_stats') ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- Auto-Refresh Script -->
<script>
// Seite alle 5 Minuten automatisch aktualisieren
setTimeout(function() {
    location.reload();
}, 300000); // 5 Minuten

// Letzte Aktualisierung anzeigen
document.addEventListener('DOMContentLoaded', function() {
    var now = new Date();
    var timeString = now.toLocaleTimeString('de-DE');
    var infoPanel = document.querySelector('.panel-primary .panel-heading h3');
    if (infoPanel) {
        infoPanel.innerHTML += ' <small class="text-muted">(<?= $addon->i18n('matomo_last_update') ?>: ' + timeString + ')</small>';
    }
});
</script>

