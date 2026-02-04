<?php

use FriendsOfRedaxo\Matomo\MatomoApi;
use FriendsOfRedaxo\Matomo\YRewriteHelper;

$addon = rex_addon::get('matomo');

// Prüfen ob Matomo konfiguriert ist
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');
$show_top_pages = rex_config::get('matomo', 'show_top_pages', false);

?>
<style>
    .matomo-stats-grid { display: flex; flex-wrap: wrap; margin: 0 -10px 20px; }
    .matomo-stat-col { padding: 0 10px; width: 25%; box-sizing: border-box; }
    @media (max-width: 991px) { .matomo-stat-col { width: 50%; margin-bottom: 20px; } }
    @media (max-width: 480px) { .matomo-stat-col { width: 100%; } }

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
    .matomo-stat-card.green { border-left-color: #5cb85c; }
    .matomo-stat-card.purple { border-left-color: #9b59b6; }
    .matomo-stat-card.orange { border-left-color: #f0ad4e; }

    .stat-icon { position: absolute; top: 15px; right: 15px; font-size: 24px; opacity: 0.15; color: #333; }
    .stat-number { font-size: 28px; font-weight: 700; color: #333; white-space: nowrap; line-height: 1.2; margin-bottom: 5px; }
    .stat-label { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; font-weight: 600; }
    .stat-trend { margin-top: 10px; font-size: 12px; font-weight: 500; display: flex; align-items: center; }
    .stat-trend i { margin-right: 4px; }
    
    .matomo-anim-delay-1 { animation-delay: 0.1s; }
    .matomo-anim-delay-2 { animation-delay: 0.2s; }
    .matomo-anim-delay-3 { animation-delay: 0.3s; }
    .matomo-anim-delay-4 { animation-delay: 0.4s; }

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
    echo html_entity_decode(rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings']))));
    return;
}

// User und Admin-Status früh definieren
$user = rex::getUser();
$is_admin = $user instanceof rex_user && $user->isAdmin();

// Auto-Login Fix verarbeiten (nur für lokale Installationen)
if (rex_get('action', 'string') === 'fix_autologin' && $is_admin === true && $is_external_matomo === false && $matomo_path !== '') {
    // Host aus Matomo URL extrahieren
    $parsed_url = parse_url($matomo_url);
    if (!is_array($parsed_url) || !isset($parsed_url['host'])) {
        echo rex_view::error('Ungültige Matomo URL');
        return;
    }
    $host = $parsed_url['host'];
    
    // Verschiedene mögliche Pfade testen
    $possible_config_files = [
        // Standard REDAXO frontend Pfad
        rex_path::frontend($matomo_path . '/config/config.ini.php'),
        // Relative Pfade
        rex_path::frontend('../' . $matomo_path . '/config/config.ini.php'),
        // Absoluter vhosts Pfad (wahrscheinlichster für dein Setup)
        '/var/www/vhosts/' . $host . '/httpdocs/' . $matomo_path . '/config/config.ini.php',
        // Alternative vhosts Struktur
        '/var/www/vhosts/' . $host . '/' . $matomo_path . '/config/config.ini.php',
        // REDAXO Base-Pfad
        rex_path::base($matomo_path . '/config/config.ini.php'),
        // Ein Level höher vom Base-Pfad
        dirname(rex_path::base()) . '/' . $matomo_path . '/config/config.ini.php'
    ];
    
    $config_file = '';
    foreach ($possible_config_files as $test_file) {
        if (file_exists($test_file)) {
            $config_file = $test_file;
            break;
        }
    }
    
    if ($config_file === '') {
        echo rex_view::error('Keine Matomo config.ini.php gefunden. Getestete Pfade: ' . implode(', ', $possible_config_files));
        return;
    }
    

    
    if (file_exists($config_file)) {
        if (is_writable($config_file)) {
            $config_content = (string) file_get_contents($config_file);
            $original_content = $config_content;
            
            // Prüfe ob login_allow_logme bereits existiert
            if (strpos($config_content, 'login_allow_logme') !== false) {
                echo rex_view::warning('login_allow_logme ist bereits in der Konfiguration vorhanden.');
            } else {
                // Prüfe ob [General] Sektion existiert
                if (strpos($config_content, '[General]') !== false) {
                    // Füge login_allow_logme zur [General] Sektion hinzu (nach der Zeile mit [General])
                    $config_content = (string) preg_replace(
                        '/(\[General\]\s*\n)/i',
                        '$1login_allow_logme = 1' . PHP_EOL,
                        $config_content,
                        1
                    );
                } else {
                    // Füge [General] Sektion am Anfang hinzu
                    $config_content = "[General]" . PHP_EOL . "login_allow_logme = 1" . PHP_EOL . PHP_EOL . $config_content;
                }
                
                if (file_put_contents($config_file, $config_content) !== false) {
                    echo rex_view::success('Auto-Login wurde erfolgreich aktiviert! Die Buttons funktionieren jetzt.');
                    $auto_login_available = true;
                    $auto_login_config_error = false;
                } else {
                    echo rex_view::error('Fehler beim Schreiben der Konfigurationsdatei.');
                }
            }
        } else {
            echo rex_view::error('Konfigurationsdatei ist nicht beschreibbar. Permissions: ' . substr(sprintf('%o', (int) fileperms($config_file)), -4));
        }
    } else {
        echo rex_view::error('Konfigurationsdatei nicht gefunden: ' . $config_file);
    }
}

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

// Auto-Login Status prüfen
$matomo_user = rex_config::get('matomo', 'matomo_user', '');
$matomo_password = rex_config::get('matomo', 'matomo_password', '');
$auto_login_available = false;
$auto_login_config_error = false;
$debug_info = '';

if ($matomo_user !== '' && $matomo_password !== '') {
    if ($is_external_matomo || $matomo_path === '') {
        // Externe Matomo-Installation - Auto-Login verfügbar, aber keine Konfigurationsprüfung möglich
        $auto_login_available = true;
        $debug_info = "Externe Matomo-Installation - Auto-Login ohne lokale Konfigurationsprüfung";
    } elseif ($matomo_path !== '') {
    // Host aus Matomo URL extrahieren für Status-Prüfung
    $parsed_url = parse_url($matomo_url);
    if (!is_array($parsed_url) || !isset($parsed_url['host'])) {
        // Skip check if url invalid
        $host = 'unknown';
    } else {
        $host = $parsed_url['host'];
    }
    
    // Dieselben Pfade wie bei der Reparatur testen
    $possible_config_files = [
        rex_path::frontend($matomo_path . '/config/config.ini.php'),
        rex_path::frontend('../' . $matomo_path . '/config/config.ini.php'),
        '/var/www/vhosts/' . $host . '/httpdocs/' . $matomo_path . '/config/config.ini.php',
        '/var/www/vhosts/' . $host . '/' . $matomo_path . '/config/config.ini.php',
        rex_path::base($matomo_path . '/config/config.ini.php'),
        dirname(rex_path::base()) . '/' . $matomo_path . '/config/config.ini.php'
    ];
    
    $config_file = '';
    foreach ($possible_config_files as $test_file) {
        if (file_exists($test_file)) {
            $config_file = $test_file;
            break;
        }
    }
    
    if ($config_file !== '') {
        $debug_info = "Config-Datei: $config_file | ";
        $debug_info .= "Existiert: Ja | ";
        $debug_info .= "Berechtigung: " . substr(sprintf('%o', (int) fileperms($config_file)), -4) . " | ";
        $debug_info .= "Beschreibbar: " . (is_writable($config_file) ? 'Ja' : 'Nein') . " | ";
        
        $config_content = (string) file_get_contents($config_file);
        // Prüfe ob login_allow_logme existiert (egal welcher Wert)
        if (strpos($config_content, 'login_allow_logme') !== false) {
            // Prüfe ob es auf 1 gesetzt ist
            if (preg_match('/login_allow_logme\s*=\s*1/i', $config_content) === 1) {
                $auto_login_available = true;
                $debug_info .= "Status: Bereits konfiguriert (login_allow_logme = 1)";
            } else {
                $auto_login_config_error = 'configurable';
                $debug_info .= "Status: login_allow_logme existiert aber ist nicht auf 1 gesetzt";
            }
        } elseif (is_writable($config_file)) {
            $auto_login_config_error = 'configurable';
            $debug_info .= "Status: Kann repariert werden";
        } else {
            $auto_login_config_error = 'readonly';
            $debug_info .= "Status: Readonly - manuelle Bearbeitung nötig";
        }
    } else {
        $debug_info = "Config-Datei: Nicht gefunden in: " . implode(', ', $possible_config_files);
        $auto_login_config_error = 'configurable'; // Trotzdem reparierbar, da die Reparatur-Funktion möglicherweise einen Pfad findet
    }
    }
}

try {
    $api = new MatomoApi($matomo_url, $admin_token, $user_token);
    $all_sites = $api->getSites();
    
    // YRewrite-Filter anwenden (zeigt nur YRewrite-Domains + Default)
    if (class_exists('FriendsOfRedaxo\Matomo\YRewriteHelper') && YRewriteHelper::isAvailable()) {
        $filtered_sites = YRewriteHelper::filterMatomoSitesByYRewrite($all_sites);
    } else {
        $filtered_sites = $all_sites;
    }
    
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
        
        <!-- Auto-Login Status Warnung (nur für Admins und lokale Installationen) -->
        <?php if ($is_admin && $matomo_user !== '' && $matomo_password !== '' && !$auto_login_available && $auto_login_config_error !== false && !$is_external_matomo): ?>
            <div class="alert alert-warning">
                <h4><i class="fa fa-exclamation-triangle"></i> <?= $addon->i18n('matomo_auto_login_not_available') ?></h4>
                <p><strong><?= $addon->i18n('matomo_problem') ?>:</strong> <?= $addon->i18n('matomo_auto_login_not_configured') ?></p>
                
                <!-- Debug-Info für Entwicklung -->
                <?php if ($debug_info !== ''): ?>
                    <div class="alert alert-info" style="margin: 10px 0;">
                        <small><strong><?= $addon->i18n('matomo_debug') ?>:</strong> <?= rex_escape($debug_info) ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ($auto_login_config_error === 'configurable'): ?>
                    <p><strong><?= $addon->i18n('matomo_solution') ?>:</strong> 
                        <?php if ($matomo_path !== ''): ?>
                            <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/overview', 'action' => 'fix_autologin']) ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fa fa-wrench"></i> <?= $addon->i18n('matomo_auto_repair') ?>
                            </a>
                            <?= $addon->i18n('matomo_or_manually_add_to') ?> <code><?= rex_escape($matomo_path) ?>/config/config.ini.php</code>:
                        <?php else: ?>
                            <?= $addon->i18n('matomo_manual_config_required') ?>
                        <?php endif; ?>
                    </p>
                    <pre>[General]
login_allow_logme = 1</pre>
                <?php else: ?>
                    <p><strong><?= $addon->i18n('matomo_manual_solution_required') ?>:</strong> 
                        <?php if ($matomo_path !== ''): ?>
                            <?= $addon->i18n('matomo_add_to') ?> <code><?= rex_escape($matomo_path) ?>/config/config.ini.php</code>:
                        <?php else: ?>
                            <?= $addon->i18n('matomo_add_to_your_matomo') ?> <code>config/config.ini.php</code>:
                        <?php endif; ?>
                    </p>
                    <pre>[General]
login_allow_logme = 1</pre>
                    <p><small class="text-muted">
                        <?php if ($matomo_path !== ''): ?>
                            <?= $addon->i18n('matomo_file_not_writable') ?>
                        <?php else: ?>
                            <?= $addon->i18n('matomo_external_installation_edit_on_server') ?>
                        <?php endif; ?>
                    </small></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Werbebanner -->
        <div class="rex-matomo-banner">
            <i class="fa fa-rocket"></i> 
            <?= $addon->i18n('matomo_pro_tip') ?>: 
            <?= $addon->i18n('matomo_check_out') ?> <a href="https://github.com/FriendsOfREDAXO/matomo/wiki" target="_blank" rel="noopener">FriendsOfREDAXO / Matomo Wiki</a> 
            <?= $addon->i18n('matomo_for_more_info') ?>
        </div>
        
        <!-- Gesamt-Statistiken -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-chart-bar"></i> <?= $addon->i18n('matomo_analytics_overview') ?>
                    <small class="text-muted">(<?= count($sites) ?> <?= count($sites) === 1 ? $addon->i18n('matomo_domain') : $addon->i18n('matomo_domains') ?>)</small>
                    <div class="btn-group pull-right">
                        <?php
                        $matomo_user = rex_config::get('matomo', 'matomo_user', '');
                        $matomo_password = rex_config::get('matomo', 'matomo_password', '');
                        
                        if ($matomo_user !== '' && $matomo_password !== ''): 
                            // Einfache Login-URL ohne Weiterleitung - Matomo macht das automatisch
                            $password_hash = md5($matomo_password);
                            $login_url = $matomo_url . '/index.php?module=Login&action=logme&login=' . 
                                        urlencode($matomo_user) . '&password=' . urlencode($password_hash);
                        ?>
                            <a href="<?= rex_escape($login_url) ?>" target="_blank" class="btn btn-primary btn-sm rex-pulse">
                                <i class="fa fa-sign-in-alt"></i> <?= $addon->i18n('matomo_auto_login') ?>
                            </a>

                        <?php else: ?>
                            <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm rex-pulse">
                                <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?>
                            </a>
                            <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/settings']) ?>" class="btn btn-warning btn-sm">
                                <i class="fa fa-cog"></i> <?= $addon->i18n('matomo_configure_login') ?>
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
                
                $total_visits_week = 0;
                $active_sites = 0;
                
                foreach ($stats_today as $stat) {
                    $visits = isset($stat['nb_visits']) ? (int) $stat['nb_visits'] : 0;
                    $total_visits_today += $visits;
                    $total_actions_today += isset($stat['nb_actions']) ? (int) $stat['nb_actions'] : 0;
                    $total_users_today += isset($stat['nb_users']) ? (int) $stat['nb_users'] : 0;
                    $bounce_count_today += isset($stat['bounce_count']) ? (int) $stat['bounce_count'] : 0;
                    $sum_visit_length_today += isset($stat['sum_visit_length']) ? (int) $stat['sum_visit_length'] : 0;
                    
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
                    
                    <!-- Actions -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card green matomo-anim-delay-2">
                            <div class="stat-icon"><i class="fa fa-mouse-pointer"></i></div>
                            <div class="stat-number" data-count="<?= $total_actions_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_actions') ?> (<?= $addon->i18n('matomo_today') ?>)</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-list-ol"></i> 
                                <?= $total_visits_today > 0 ? round($total_actions_today / $total_visits_today, 1) : 0 ?> Actions/Visit
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card purple matomo-anim-delay-3">
                            <div class="stat-icon"><i class="fa fa-users"></i></div>
                            <div class="stat-number" data-count="<?= $total_users_today ?>">0</div>
                            <div class="stat-label"><?= $addon->i18n('matomo_users') ?> (<?= $addon->i18n('matomo_today') ?>)</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-globe"></i> 
                                <?= $active_sites ?> <?= $addon->i18n('matomo_active_domains_today') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Engagement -->
                    <div class="matomo-stat-col">
                        <div class="matomo-stat-card orange matomo-anim-delay-4">
                            <div class="stat-icon"><i class="fa fa-clock"></i></div>
                            <div class="stat-number"><?= gmdate("i:s", (int)$avg_time_on_site) ?></div>
                            <div class="stat-label">Avg Duration</div>
                            <div class="stat-trend text-muted">
                                <i class="fa fa-sign-out-alt"></i> 
                                <?= $bounce_rate ?>% Bounce Rate
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
                                        <span class="label label-success" title="<?= $addon->i18n('matomo_actions_today') ?>">
                                            <i class="fa fa-mouse-pointer"></i> <?= number_format($today['nb_actions'] ?? 0) ?>
                                        </span>
                                        <br>
                                        <span class="label label-warning" title="<?= $addon->i18n('matomo_actions_this_week') ?>">
                                            <i class="fa fa-chart-bar"></i> <?= number_format($week['nb_actions'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($matomo_user !== '' && $matomo_password !== ''): 
                                            // Site-spezifische Login-URL
                                            $password_hash = md5($matomo_password);
                                            $site_url = $matomo_url . '/index.php?module=CoreHome&action=index&idSite=' . $site_id . '&period=day&date=today';
                                            $site_login_url = $matomo_url . '/index.php?module=Login&action=logme&login=' . 
                                                            urlencode($matomo_user) . '&password=' . urlencode($password_hash) . 
                                                            '&url=' . urlencode($site_url);
                                        ?>
                                            <a href="<?= rex_escape($site_login_url) ?>" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fa fa-sign-in-alt"></i> <?= $addon->i18n('matomo_open') ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $site_id ?>&period=day&date=today" 
                                               target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open') ?>
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

<!-- Auto-Refresh Script -->
document.addEventListener('DOMContentLoaded', function() {
    var now = new Date();
    var timeString = now.toLocaleTimeString('de-DE');
    var infoPanel = document.querySelector('.panel-primary .panel-heading h3');
    if (infoPanel) {
        infoPanel.innerHTML += ' <small class="text-muted">(<?= $addon->i18n('matomo_last_update') ?>: ' + timeString + ')</small>';
    }
});
</script>

