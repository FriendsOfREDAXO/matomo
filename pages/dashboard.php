<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// Konfiguration laden
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$dashboard_site = rex_config::get('matomo', 'dashboard_site', '0');

// Prüfung ob konfiguriert
if (!$matomo_url || !$admin_token) {
    echo rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings'])));
    return;
}

// Token für iframe verwenden
$iframe_token = $user_token ?: $admin_token;

// Site-Informationen laden wenn spezifische Site gewählt
$selected_site = null;
$dashboard_site_id = $dashboard_site; // Numerische ID für iframe
if ($dashboard_site != '0') {
    try {
        $api = new MatomoApi($matomo_url, $admin_token, $user_token);
        $sites = $api->getSites();
        foreach ($sites as $site) {
            if ($site['idsite'] == $dashboard_site) {
                $selected_site = $site;
                $dashboard_site_id = $site['idsite']; // Stelle sicher, dass wir die numerische ID haben
                break;
            }
        }
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('matomo_dashboard_load_error', $e->getMessage()));
        return;
    }
}

?>

<div class="row">
    <div class="col-sm-12">
        
        <?php if ($dashboard_site == '0'): ?>
            <!-- Alle Sites Dashboard -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-chart-bar"></i> <?= $addon->i18n('matomo_dashboard_all_sites') ?>
                        <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm pull-right rex-pulse">
                            <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?>
                        </a>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <strong><?= $addon->i18n('matomo_dashboard_all_info') ?>:</strong> 
                        <?= $addon->i18n('matomo_dashboard_all_description') ?>
                    </div>
                    
                    <p>
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/overview']) ?>" class="btn btn-success btn-lg">
                            <i class="fa fa-chart-line"></i> <?= $addon->i18n('matomo_goto_overview') ?>
                        </a>
                        
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/config']) ?>" class="btn btn-default">
                            <i class="fa fa-cog"></i> <?= $addon->i18n('matomo_dashboard_configure') ?>
                        </a>
                    </p>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Spezifische Site Dashboard -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-tachometer-alt"></i> <?= $selected_site ? rex_escape($selected_site['name']) : $addon->i18n('matomo_dashboard') ?>
                        <div class="pull-right">
                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= rex_escape($dashboard_site_id) ?>&period=day&date=today" 
                               target="_blank" class="btn btn-primary btn-sm rex-pulse">
                                <i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?>
                            </a>
                            <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/config']) ?>" class="btn btn-default btn-sm">
                                <i class="fa fa-cog"></i> <?= $addon->i18n('matomo_dashboard_configure') ?>
                            </a>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <?php if ($selected_site): ?>
                        <div class="alert alert-info">
                            <strong><?= $addon->i18n('matomo_dashboard_site') ?>:</strong> 
                            <?= rex_escape($selected_site['name']) ?> 
                            <small class="text-muted">(<?= rex_escape($selected_site['main_url']) ?>)</small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Matomo iframe -->
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe id="matomo-dashboard" 
                                src="<?= rex_escape($matomo_url) ?>/index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=<?= rex_escape($dashboard_site_id) ?>&period=week&date=today&token_auth=<?= rex_escape($iframe_token) ?>" 
                                class="embed-responsive-item"
                                frameborder="0">
                        </iframe>
                    </div>
                    
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<script>
// iframeResizer für bessere iframe-Integration (von Matomo empfohlen)
if (typeof iFrameResize !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        var iframe = document.getElementById('matomo-dashboard');
        if (iframe) {
            // Verwende iframeResizer für automatische Höhenanpassung
            iFrameResize({
                log: false,
                enablePublicMethods: true,
                minHeight: 600,
                heightCalculationMethod: 'max',
                onResized: function(messageData) {
                    console.log('Matomo dashboard resized to: ' + messageData.height + 'px');
                }
            }, iframe);
        }
    });
} else {
    // Fallback: Setze feste Höhe für responsive iframe
    document.addEventListener('DOMContentLoaded', function() {
        var iframe = document.getElementById('matomo-dashboard');
        if (iframe) {
            iframe.style.height = '800px';
        }
    });
}

// Iframe alle 5 Minuten neu laden für aktuelle Daten
setInterval(function() {
    var iframe = document.getElementById('matomo-dashboard');
    if (iframe && iframe.src) {
        var src = iframe.src.split('&_t=')[0];
        iframe.src = src + '&_t=' + new Date().getTime();
    }
}, 300000); // 5 Minuten
</script>
