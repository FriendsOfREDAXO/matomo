<?php

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

echo rex_view::title($addon->i18n('matomo_overview_title'));

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
                <h3 class="panel-title">📊 <?= $addon->i18n('matomo_overview_summary') ?></h3>
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
                                        <h3 class="text-primary"><?= number_format($total_visits_today) ?></h3>
                                        <small><?= $addon->i18n('matomo_visits') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <h3 class="text-success"><?= number_format($total_actions_today) ?></h3>
                                        <small><?= $addon->i18n('matomo_actions') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <h3 class="text-info"><?= number_format($total_users_today) ?></h3>
                                        <small><?= $addon->i18n('matomo_users') ?></small>
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
                                        <h3 class="text-primary"><?= number_format($total_visits_week) ?></h3>
                                        <small><?= $addon->i18n('matomo_visits') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <h3 class="text-success"><?= number_format($total_actions_week) ?></h3>
                                        <small><?= $addon->i18n('matomo_actions') ?></small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <h3 class="text-info"><?= number_format($total_users_week) ?></h3>
                                        <small><?= $addon->i18n('matomo_users') ?></small>
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
                <h3 class="panel-title">🌐 <?= $addon->i18n('matomo_overview_by_domain') ?> (<?= count($sites) ?>)</h3>
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
                                        <a href="<?= rex_escape($site['main_url']) ?>" target="_blank" class="btn btn-xs btn-default">
                                            🔗 <?= rex_escape(parse_url($site['main_url'], PHP_URL_HOST)) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="stats-today">
                                            <strong class="text-primary"><?= number_format($today['nb_visits'] ?? 0) ?></strong>
                                            <small class="text-muted"><?= $addon->i18n('matomo_visits_short') ?></small>
                                            <br>
                                            <span class="text-info"><?= number_format($today['nb_users'] ?? 0) ?></span>
                                            <small class="text-muted"><?= $addon->i18n('matomo_users_short') ?></small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="stats-week">
                                            <strong class="text-primary"><?= number_format($week['nb_visits'] ?? 0) ?></strong>
                                            <small class="text-muted"><?= $addon->i18n('matomo_visits_short') ?></small>
                                            <br>
                                            <span class="text-info"><?= number_format($week['nb_users'] ?? 0) ?></span>
                                            <small class="text-muted"><?= $addon->i18n('matomo_users_short') ?></small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=day&date=today" 
                                           target="_blank" class="btn btn-primary btn-sm">
                                            📊 <?= $addon->i18n('matomo_open_dashboard') ?>
                                        </a>
                                        <br><br>
                                        <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=week&date=today" 
                                           target="_blank" class="btn btn-info btn-xs">
                                            📅 <?= $addon->i18n('matomo_week_view') ?>
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

<style>
.stats-today, .stats-week {
    min-height: 40px;
}

.table > tbody > tr > td {
    vertical-align: middle;
}

.text-center .btn {
    margin-bottom: 5px;
}
</style>