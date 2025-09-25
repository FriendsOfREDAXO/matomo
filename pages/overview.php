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

// Auto-Login Fix verarbeiten
if (rex_get('action') === 'fix_autologin' && $is_admin) {
    $config_file = rex_path::frontend($matomo_path . '/config/config.ini.php');
    echo rex_view::info('Debug: Versuche Datei zu bearbeiten: ' . $config_file);
    
    if (file_exists($config_file)) {
        if (is_writable($config_file)) {
            $config_content = file_get_contents($config_file);
            $original_content = $config_content;
            
            // Prüfe ob login_allow_logme bereits existiert
            if (strpos($config_content, 'login_allow_logme') !== false) {
                echo rex_view::warning('login_allow_logme ist bereits in der Konfiguration vorhanden.');
            } else {
                // Prüfe ob [General] Sektion existiert
                if (strpos($config_content, '[General]') !== false) {
                    // Füge login_allow_logme zur [General] Sektion hinzu (nach der Zeile mit [General])
                    $config_content = preg_replace(
                        '/(\[General\]\s*\n)/i',
                        '$1login_allow_logme = 1' . PHP_EOL,
                        $config_content,
                        1
                    );
                } else {
                    // Füge [General] Sektion am Anfang hinzu
                    $config_content = "[General]" . PHP_EOL . "login_allow_logme = 1" . PHP_EOL . PHP_EOL . $config_content;
                }
                
                if (file_put_contents($config_file, $config_content)) {
                    echo rex_view::success('Auto-Login wurde erfolgreich aktiviert! Die Buttons funktionieren jetzt.');
                    $auto_login_available = true;
                    $auto_login_config_error = false;
                } else {
                    echo rex_view::error('Fehler beim Schreiben der Konfigurationsdatei.');
                }
            }
        } else {
            echo rex_view::error('Konfigurationsdatei ist nicht beschreibbar. Permissions: ' . substr(sprintf('%o', fileperms($config_file)), -4));
        }
    } else {
        echo rex_view::error('Konfigurationsdatei nicht gefunden: ' . $config_file);
    }
}

// Domains und Statistiken laden
$sites = [];
$stats_today = [];
$stats_week = [];

// User-spezifische Domain-Filter
$user = rex::getUser();
$is_admin = $user && $user->isAdmin();
$show_all_domains = $is_admin;
$user_allowed_domains = [];

// Wenn nicht Admin, Domain-Filter anwenden (später implementieren)
if (!$show_all_domains) {
    // Hier könnten wir User-spezifische Domain-Berechtigungen laden
    // Für jetzt alle Domains anzeigen, aber das können wir später erweitern
    $show_all_domains = true;
}

// Auto-Login Status prüfen
$matomo_user = rex_config::get('matomo', 'matomo_user', '');
$matomo_password = rex_config::get('matomo', 'matomo_password', '');
$auto_login_available = false;
$auto_login_config_error = false;

if ($matomo_user && $matomo_password && $matomo_path) {
    // Prüfe ob Matomo config.ini.php existiert und bearbeitbar ist
    $config_file = rex_path::frontend($matomo_path . '/config/config.ini.php');
    if (file_exists($config_file)) {
        $config_content = file_get_contents($config_file);
        if (strpos($config_content, 'login_allow_logme = 1') !== false) {
            $auto_login_available = true;
        } elseif (is_writable($config_file)) {
            $auto_login_config_error = 'configurable';
        } else {
            $auto_login_config_error = 'readonly';
        }
    }
}

try {
    $api = new MatomoApi($matomo_url, $admin_token, $user_token);
    $all_sites = $api->getSites();
    
    // Domain-Filterung anwenden
    $sites = [];
    foreach ($all_sites as $site) {
        if ($show_all_domains) {
            $sites[] = $site;
        }
        // TODO: Hier später User-spezifische Domain-Filterung
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
} catch (Exception $e) {
    echo rex_view::error($addon->i18n('matomo_overview_load_error', $e->getMessage()));
    return;
}

?>

<div class="row">
    <div class="col-sm-12">
        
        <!-- Auto-Login Status Warnung (nur für Admins) -->
        <?php if ($is_admin && $matomo_user && $matomo_password && $auto_login_config_error): ?>
            <div class="alert alert-warning">
                <h4><i class="fa fa-exclamation-triangle"></i> Auto-Login nicht verfügbar</h4>
                <p><strong>Problem:</strong> Matomo Auto-Login ist nicht konfiguriert.</p>
                
                <?php if ($auto_login_config_error === 'configurable'): ?>
                    <p><strong>Lösung:</strong> 
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/overview', 'action' => 'fix_autologin']) ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fa fa-wrench"></i> Automatisch reparieren
                        </a>
                        oder manuell in <code><?= rex_escape($matomo_path) ?>/config/config.ini.php</code> hinzufügen:
                    </p>
                    <pre>[General]
login_allow_logme = 1</pre>
                <?php else: ?>
                    <p><strong>Manuelle Lösung erforderlich:</strong> Fügen Sie in <code><?= rex_escape($matomo_path) ?>/config/config.ini.php</code> hinzu:</p>
                    <pre>[General]
login_allow_logme = 1</pre>
                    <p><small class="text-muted">Die Datei ist nicht beschreibbar - bitte manuell bearbeiten.</small></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Gesamt-Statistiken -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-chart-bar"></i> Analytics Übersicht
                    <small class="text-muted">(<?= count($sites) ?> <?= count($sites) == 1 ? 'Domain' : 'Domains' ?>)</small>
                    <div class="btn-group pull-right">
                        <?php
                        $matomo_user = rex_config::get('matomo', 'matomo_user', '');
                        $matomo_password = rex_config::get('matomo', 'matomo_password', '');
                        
                        if ($matomo_user && $matomo_password): 
                            // Einfache Login-URL ohne Weiterleitung - Matomo macht das automatisch
                            $password_hash = md5($matomo_password);
                            $login_url = $matomo_url . '/index.php?module=Login&action=logme&login=' . 
                                        urlencode($matomo_user) . '&password=' . urlencode($password_hash);
                        ?>
                            <a href="<?= rex_escape($login_url) ?>" target="_blank" class="btn btn-primary btn-sm rex-pulse">
                                <i class="fa fa-sign-in-alt"></i> Automatisch anmelden
                            </a>
                            <?php if ($is_admin): ?>
                                <br><small class="text-muted">Debug: <code><?= rex_escape($login_url) ?></code></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm rex-pulse">
                                <i class="fa fa-external-link-alt"></i> Matomo öffnen
                            </a>
                            <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/settings']) ?>" class="btn btn-warning btn-sm">
                                <i class="fa fa-cog"></i> Login konfigurieren
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
                $total_visits_week = 0;
                $total_actions_week = 0;  
                $total_users_week = 0;
                $total_bounce_rate = 0;
                $total_avg_time = 0;
                $active_sites = 0;
                
                foreach ($stats_today as $stat) {
                    $total_visits_today += $stat['nb_visits'] ?? 0;
                    $total_actions_today += $stat['nb_actions'] ?? 0;
                    $total_users_today += $stat['nb_users'] ?? 0;
                    if (($stat['nb_visits'] ?? 0) > 0) {
                        $active_sites++;
                    }
                }
                
                foreach ($stats_week as $stat) {
                    $total_visits_week += $stat['nb_visits'] ?? 0;
                    $total_actions_week += $stat['nb_actions'] ?? 0;
                    $total_users_week += $stat['nb_users'] ?? 0;
                }
                
                // Durchschnittswerte berechnen
                $avg_actions_per_visit = $total_visits_today > 0 ? round($total_actions_today / $total_visits_today, 1) : 0;
                $growth_rate = $total_visits_week > 0 && $total_visits_today > 0 ? 
                    round((($total_visits_today * 7) / $total_visits_week - 1) * 100, 1) : 0;
                ?>
                
                <!-- Erweiterte Metriken -->
                <div class="row">
                    <!-- Heute -->
                    <div class="col-sm-6">
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h4><i class="fa fa-calendar-day"></i> Heute</h4>
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
                    
                    <!-- Diese Woche -->  
                    <div class="col-sm-6">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h4><i class="fa fa-calendar-week"></i> Diese Woche</h4>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-eye fa-2x text-primary"></i>
                                        <h3 class="text-primary"><?= number_format($total_visits_week) ?></h3>
                                        <small class="text-muted">Besuche</small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-mouse-pointer fa-2x text-success"></i>
                                        <h3 class="text-success"><?= number_format($total_actions_week) ?></h3>
                                        <small class="text-muted">Aktionen</small>
                                    </div>
                                    <div class="col-xs-4 text-center">
                                        <i class="fa fa-users fa-2x text-info"></i>
                                        <h3 class="text-info"><?= number_format($total_users_week) ?></h3>
                                        <small class="text-muted">Benutzer</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Zusätzliche Metriken -->
                <div class="row">
                    <div class="col-sm-3">
                        <div class="panel panel-default">
                            <div class="panel-body text-center">
                                <i class="fa fa-chart-line fa-2x text-warning"></i>
                                <h3 class="text-warning"><?= $avg_actions_per_visit ?></h3>
                                <small class="text-muted">Ø Aktionen/Besuch</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-default">
                            <div class="panel-body text-center">
                                <i class="fa fa-globe fa-2x text-primary"></i>
                                <h3 class="text-primary"><?= $active_sites ?></h3>
                                <small class="text-muted">Aktive Domains heute</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-default">
                            <div class="panel-body text-center">
                                <i class="fa fa-<?= $growth_rate >= 0 ? 'arrow-up text-success' : 'arrow-down text-danger' ?> fa-2x"></i>
                                <h3 class="<?= $growth_rate >= 0 ? 'text-success' : 'text-danger' ?>"><?= $growth_rate ?>%</h3>
                                <small class="text-muted">Trend (7 Tage)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-default">
                            <div class="panel-body text-center">
                                <i class="fa fa-calendar fa-2x text-muted"></i>
                                <h3 class="text-muted"><?= count($sites) ?></h3>
                                <small class="text-muted">Überwachte Domains</small>
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
                    <i class="fa fa-globe"></i> Domain-Statistiken
                    <?php if (!$show_all_domains): ?>
                        <small class="text-muted">(gefiltert für Ihre Berechtigungen)</small>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/domains']) ?>" class="btn btn-success btn-sm pull-right">
                            <i class="fa fa-plus"></i> Domains verwalten
                        </a>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="panel-body">
                
                <?php if (empty($sites)): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <strong>Keine Domains verfügbar:</strong> 
                        <?php if ($show_all_domains): ?>
                            Es wurden noch keine Domains in Matomo konfiguriert.
                        <?php else: ?>
                            Sie haben keine Berechtigung für Domain-Statistiken oder es wurden Ihnen keine Domains zugewiesen.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><i class="fa fa-tag"></i> Domain</th>
                                    <th><i class="fa fa-link"></i> URL</th>
                                    <th class="text-center"><i class="fa fa-calendar-day"></i> Heute</th>
                                    <th class="text-center"><i class="fa fa-calendar-week"></i> Diese Woche</th>
                                    <th class="text-center"><i class="fa fa-mouse-pointer"></i> Aktionen</th>
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
                                        <span class="label label-success" title="Aktionen heute">
                                            <i class="fa fa-mouse-pointer"></i> <?= number_format($today['nb_actions'] ?? 0) ?>
                                        </span>
                                        <br>
                                        <span class="label label-warning" title="Aktionen diese Woche">
                                            <i class="fa fa-chart-bar"></i> <?= number_format($week['nb_actions'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($matomo_user && $matomo_password): 
                                            // Site-spezifische Login-URL
                                            $password_hash = md5($matomo_password);
                                            $site_url = $matomo_url . '/index.php?module=CoreHome&action=index&idSite=' . $site_id . '&period=day&date=today';
                                            $site_login_url = $matomo_url . '/index.php?module=Login&action=logme&login=' . 
                                                            urlencode($matomo_user) . '&password=' . urlencode($password_hash) . 
                                                            '&url=' . urlencode($site_url);
                                        ?>
                                            <a href="<?= rex_escape($site_login_url) ?>" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fa fa-sign-in-alt"></i> Öffnen
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=day&date=today" 
                                               target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fa fa-external-link-alt"></i> Öffnen
                                            </a>
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

