<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// Konfiguration laden
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$dashboard_site = rex_config::get('matomo', 'dashboard_site', '0');

echo rex_view::title($addon->i18n('matomo_dashboard'));

// Prüfung ob konfiguriert
if (!$matomo_url || !$admin_token) {
    echo rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings'])));
    return;
}

// Token für iframe verwenden
$iframe_token = $user_token ?: $admin_token;

// Site-Informationen laden wenn spezifische Site gewählt
$selected_site = null;
if ($dashboard_site != '0') {
    try {
        $api = new MatomoApi($matomo_url, $admin_token, $user_token);
        $sites = $api->getSites();
        foreach ($sites as $site) {
            if ($site['idsite'] == $dashboard_site) {
                $selected_site = $site;
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
                        📊 <?= $addon->i18n('matomo_dashboard_all_sites') ?>
                        <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm pull-right">
                            🔗 <?= $addon->i18n('matomo_open_matomo') ?>
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
                            📈 <?= $addon->i18n('matomo_goto_overview') ?>
                        </a>
                        
                        <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/config']) ?>" class="btn btn-default">
                            ⚙️ <?= $addon->i18n('matomo_dashboard_configure') ?>
                        </a>
                    </p>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Spezifische Site Dashboard -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        📊 <?= $selected_site ? rex_escape($selected_site['name']) : $addon->i18n('matomo_dashboard') ?>
                        <div class="pull-right">
                            <a href="<?= rex_escape($matomo_url) ?>/index.php?module=CoreHome&action=index&idSite=<?= $dashboard_site ?>&period=day&date=today" 
                               target="_blank" class="btn btn-primary btn-sm">
                                🔗 <?= $addon->i18n('matomo_open_matomo') ?>
                            </a>
                            <a href="<?= rex_url::currentBackendPage(['page' => 'matomo/config']) ?>" class="btn btn-default btn-sm">
                                ⚙️ <?= $addon->i18n('matomo_dashboard_configure') ?>
                            </a>
                        </div>
                    </h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    
                    <?php if ($selected_site): ?>
                        <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                            <strong><?= $addon->i18n('matomo_dashboard_site') ?>:</strong> 
                            <?= rex_escape($selected_site['name']) ?> 
                            <small class="text-muted">(<?= rex_escape($selected_site['main_url']) ?>)</small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Matomo iframe -->
                    <div style="position: relative; overflow: hidden;">
                        <iframe id="matomo-dashboard" 
                                src="<?= rex_escape($matomo_url) ?>/index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=<?= $dashboard_site ?>&period=week&date=today&token_auth=<?= rex_escape($iframe_token) ?>" 
                                frameborder="0" 
                                width="100%" 
                                style="height: 800px; min-height: 600px;"
                                onload="adjustIframeHeight()">
                        </iframe>
                    </div>
                    
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<script>
function adjustIframeHeight() {
    var iframe = document.getElementById('matomo-dashboard');
    if (iframe) {
        // Versuche Iframe-Höhe anzupassen
        try {
            var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            var height = Math.max(
                iframeDoc.body.scrollHeight,
                iframeDoc.body.offsetHeight,
                iframeDoc.documentElement.clientHeight,
                iframeDoc.documentElement.scrollHeight,
                iframeDoc.documentElement.offsetHeight
            );
            if (height > 400) {
                iframe.style.height = height + 'px';
            }
        } catch (e) {
            // Cross-origin Fehler ignorieren
            console.log('Cannot adjust iframe height due to cross-origin restrictions');
        }
    }
}

// Iframe alle 30 Sekunden neu laden für aktuelle Daten
setInterval(function() {
    var iframe = document.getElementById('matomo-dashboard');
    if (iframe && iframe.src) {
        // Timestamp hinzufügen um Cache zu umgehen
        var src = iframe.src.split('&_t=')[0];
        iframe.src = src + '&_t=' + new Date().getTime();
    }
}, 30000);
</script>
?>
