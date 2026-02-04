<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// Test-Connection Handler
if (rex_request('func', 'string') === 'test_connection') {
    rex_response::cleanOutputBuffers();
    
    // Gebe nur die Test-URL zurück - Test passiert im Browser
    $test_url = rex_request('matomo_url', 'string', '');
    
    if ('' === $test_url) {
        rex_response::sendJson(['success' => false, 'message' => 'Keine URL angegeben']);
        exit;
    }
    
    // Prüfe nur ob URL valide ist
    if (!filter_var($test_url, FILTER_VALIDATE_URL)) {
        rex_response::sendJson(['success' => false, 'message' => 'Ungültige URL']);
        exit;
    }
    
    // Gebe Test-URL zurück für Client-seitigen Test
    rex_response::sendJson([
        'success' => true,
        'test_url' => rtrim($test_url, '/') . '/matomo.js',
        'message' => 'Teste Verbindung...'
    ]);
    exit;
}

// Test-Proxy Handler
if (rex_request('func', 'string') === 'test_proxy') {
    rex_response::cleanOutputBuffers();
    
    // Prüfe ob Matomo-URL konfiguriert ist
    $matomo_url = rex_config::get('matomo', 'matomo_url', '');
    if ('' === $matomo_url) {
        rex_response::sendJson(['success' => false, 'message' => 'Matomo URL nicht konfiguriert']);
        exit;
    }
    
    // Debug: Prüfe ob API registriert ist
    $registered_apis = rex_api_function::getRegisteredApis();
    $is_registered = isset($registered_apis['matomo_proxy']);
    
    // Generiere Proxy-URL für Client-seitigen Test - absolute URL
    $proxy_url = rex_url::frontendController(['rex-api-call' => 'matomo_proxy', 'file' => 'matomo.js', 'test' => '1']);
    
    rex_response::sendJson([
        'success' => true,
        'proxy_url' => $proxy_url,
        'message' => 'Teste Proxy...',
        'debug' => [
            'is_registered' => $is_registered,
            'registered_apis' => array_keys($registered_apis)
        ]
    ]);
    exit;
}

// Form-Verarbeitung
$message = '';
$error = '';

if (rex_post('save_settings', 'boolean')) {
    $matomo_path = rex_post('matomo_path', 'string', '');
    $matomo_url = rex_post('matomo_url', 'string', '');
    $admin_token = rex_post('admin_token', 'string', '');
    $matomo_user = rex_post('matomo_user', 'string', '');
    $matomo_password = rex_post('matomo_password', 'string', '');
    $show_top_pages = rex_post('show_top_pages', 'boolean', false);
    $verify_ssl = rex_post('verify_ssl', 'boolean', true);
    $proxy_enabled = rex_post('proxy_enabled', 'boolean', false);

    rex_config::set('matomo', 'matomo_path', $matomo_path);
    rex_config::set('matomo', 'matomo_url', $matomo_url);
    rex_config::set('matomo', 'admin_token', $admin_token);
    rex_config::set('matomo', 'matomo_user', $matomo_user);
    rex_config::set('matomo', 'matomo_password', $matomo_password);
    rex_config::set('matomo', 'show_top_pages', $show_top_pages);
    rex_config::set('matomo', 'verify_ssl', $verify_ssl);
    rex_config::set('matomo', 'proxy_enabled', $proxy_enabled);

    $message = $addon->i18n('matomo_config_saved');
}

if (rex_post('download_matomo', 'boolean')) {
    $download_path = rex_post('download_path', 'string', '');
    $matomo_url = rex_post('download_url', 'string', '');

    if ($download_path && $matomo_url) {
        try {
            $full_path = rex_path::frontend($download_path);
            MatomoApi::downloadMatomo($full_path);

            // Einstellungen automatisch setzen
            rex_config::set('matomo', 'matomo_path', $download_path);
            rex_config::set('matomo', 'matomo_url', $matomo_url);

            $message = $addon->i18n('matomo_download_success');
        } catch (Exception $e) {
            $error = $addon->i18n('matomo_download_failed', $e->getMessage());
        }
    } else {
        $error = $addon->i18n('matomo_fill_all_fields');
    }
}

// Aktuelle Konfiguration laden mit rex_config
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', 'auswertung');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$matomo_user = rex_config::get('matomo', 'matomo_user', '');
$matomo_password = rex_config::get('matomo', 'matomo_password', '');
$show_top_pages = rex_config::get('matomo', 'show_top_pages', false);
$verify_ssl = rex_config::get('matomo', 'verify_ssl', true);
$proxy_enabled = rex_config::get('matomo', 'proxy_enabled', false);

// Status prüfen
$matomo_installed = false;
$is_external_matomo = false;

if ($matomo_url && $admin_token) {
    if ($matomo_path) {
        // Lokale Matomo-Installation - prüfe ob verfügbar
        $full_path = rex_path::frontend($matomo_path . '/');
        $matomo_installed = file_exists($full_path . 'index.php');
    } else {
        // Externe Matomo-Installation - keine lokale Verfügbarkeitsprüfung möglich
        $matomo_installed = true;
        $is_external_matomo = true;
    }
}

// Nachrichten anzeigen
if ($message) {
    echo rex_view::success($message);
}
if ($error) {
    echo rex_view::error($error);
}

?>

<div class="row">
    <div class="col-sm-8">
        
        <!-- Matomo Download -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">📥 <?= $addon->i18n('matomo_download_title') ?></h3>
            </div>
            <div class="panel-body">
                <p><?= $addon->i18n('matomo_download_description') ?></p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="download_path"><?= $addon->i18n('matomo_installation_path') ?>:</label>
                        <input type="text" class="form-control" id="download_path" name="download_path" 
                               value="matomo" placeholder="matomo" required>
                        <small class="text-muted"><?= $addon->i18n('matomo_installation_path_help') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="download_url"><?= $addon->i18n('matomo_installation_url') ?>:</label>
                        <input type="url" class="form-control" id="download_url" name="download_url" 
                               placeholder="https://ihre-domain.de/matomo" required>
                        <small class="text-muted">Vollständige URL unter der Matomo erreichbar sein soll</small>
                    </div>
                    
                    <button type="submit" name="download_matomo" value="1" class="btn btn-primary btn-lg">
                        🚀 Matomo herunterladen & konfigurieren
                    </button>
                </form>
            </div>
        </div>

        <!-- Manuelle Konfiguration -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">⚙️ Manuelle Konfiguration</h3>
            </div>
            <div class="panel-body">
                <form method="post">
                    <div class="form-group">
                        <label for="matomo_path">Matomo Pfad:</label>
                        <input type="text" class="form-control" id="matomo_path" name="matomo_path" 
                               value="<?= rex_escape($matomo_path) ?>" placeholder="matomo">
                        <small class="text-muted">Pfad relativ zum Web-Root (ohne führenden/abschließenden Slash)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="matomo_url">Matomo URL:</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="matomo_url" name="matomo_url" 
                                   value="<?= rex_escape($matomo_url) ?>" placeholder="https://ihre-domain.de/matomo">
                            <span class="input-group-btn">
                                <button type="button" id="test-connection" class="btn btn-default" title="Verbindung testen">
                                    <i class="fa fa-plug"></i> Test
                                </button>
                            </span>
                        </div>
                        <div id="test-result" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_token">Admin Token:</label>
                        <input type="text" class="form-control" id="admin_token" name="admin_token" 
                               value="<?= rex_escape($admin_token) ?>" placeholder="">
                        <small class="text-muted">Finden Sie in Matomo unter: Administration → Platform → API → User Authentication</small>
                    </div>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="verify_ssl" value="1" <?= $verify_ssl ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('matomo_verify_ssl') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('matomo_verify_ssl_help') ?></p>
                    </div>
                    
                    <hr>
                    <h4><i class="fa fa-sign-in-alt"></i> Automatischer Login (optional)</h4>
                    <p class="text-muted">Für den "Automatisch anmelden" Button in der Übersicht:</p>
                    
                    <div class="form-group">
                        <label for="matomo_user">Matomo Username:</label>
                        <input type="text" class="form-control" id="matomo_user" name="matomo_user" 
                               value="<?= rex_escape($matomo_user) ?>" placeholder="admin">
                        <small class="text-muted">Ihr Matomo-Benutzername für automatischen Login</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="matomo_password">Matomo Passwort:</label>
                        <input type="password" class="form-control" id="matomo_password" name="matomo_password" 
                               value="<?= rex_escape($matomo_password) ?>" placeholder="">
                        <small class="text-muted">Ihr Matomo-Passwort für automatischen Login</small>
                    </div>
                    
                    <hr>
                    <h4><i class="fa fa-chart-line"></i> Statistik-Features</h4>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="show_top_pages" value="1" <?= $show_top_pages ? 'checked' : '' ?>>
                            <strong>Top 5 Seiten anzeigen</strong>
                        </label>
                        <p class="text-muted">Zeigt die 5 meistbesuchten Seiten der aktuellen Woche in der Übersicht an</p>
                    </div>
                    
                    <hr>
                    <h4><i class="fa fa-shield"></i> Tracking-Proxy (Anti-Adblocker)</h4>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="proxy_enabled" value="1" <?= $proxy_enabled ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('matomo_proxy_enabled') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('matomo_proxy_enabled_help') ?></p>
                    </div>
                    
                    <?php if ($matomo_url): ?>
                    <div style="margin: 15px 0;">
                        <button type="button" id="test-proxy" class="btn btn-default">
                            <i class="fa fa-shield"></i> Proxy testen
                        </button>
                        <div id="test-proxy-result" style="margin-top: 10px;"></div>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="save_settings" value="1" class="btn btn-success">
                        <i class="fa fa-save"></i> Einstellungen speichern
                    </button>
                </form>
            </div>
        </div>
        
    </div>
    
    <div class="col-sm-4">
        
        <!-- Status -->
        <div class="panel panel-<?= $matomo_installed ? 'success' : 'warning' ?>">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-chart-bar"></i> Status</h3>
            </div>
            <div class="panel-body">
                <p><strong>Matomo Installation:</strong></p>
                <p class="text-<?= $matomo_installed ? 'success' : 'warning' ?>">
                    <?php if ($is_external_matomo): ?>
                        <i class="fa fa-globe text-info"></i> Externe Installation
                    <?php else: ?>
                        <?= $matomo_installed ? '<i class="fa fa-check-circle text-success"></i> Installiert' : '<i class="fa fa-times-circle text-danger"></i> Nicht gefunden' ?>
                    <?php endif; ?>
                </p>
                
                <?php if ($matomo_path): ?>
                <p><strong>Pfad:</strong><br>
                <code><?= rex_escape($matomo_path) ?></code></p>
                <?php endif; ?>
                
                <?php if ($matomo_url): ?>
                <p><strong>URL:</strong><br>
                <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm">
                    🔗 Matomo öffnen
                </a></p>
                <?php endif; ?>
                
                <?php if ($admin_token): ?>
                <p><strong>Admin Token:</strong><br>
                <span class="text-success"><i class="fa fa-check-circle"></i> Konfiguriert</span></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Anleitungen -->
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">💡 Hilfe</h3>
            </div>
            <div class="panel-body">
                <h5>Nach dem Download:</h5>
                <ol>
                    <li>Klicken Sie auf "Matomo öffnen"</li>
                    <li>Folgen Sie dem Installationsassistenten</li>
                    <li>Erstellen Sie Datenbank und Admin-Account</li>
                    <li>Kopieren Sie den Admin Token aus den Einstellungen</li>
                    <li>Fügen Sie den Token hier ein</li>
                </ol>
                
                <h5>Admin Token finden:</h5>
                <p>In Matomo: <br>
                <strong>Administration → Platform → API → User Authentication</strong></p>
            </div>
        </div>
        
    </div>
</div>

<script nonce="<?= rex_response::getNonce() ?>">
jQuery(function($) {
    $('#test-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-result');
        var url = $('#matomo_url').val();
        
        if (!url) {
            $result.html('<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Bitte eine URL eingeben</div>');
            return;
        }
        
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Teste...');
        $result.html('');
        
        // Hole Test-URL vom Backend
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                func: 'test_connection',
                matomo_url: url
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.test_url) {
                // Teste direkt per JavaScript
                var testUrl = response.test_url;
                var startTime = new Date().getTime();
                
                $.ajax({
                    url: testUrl,
                    method: 'GET',
                    dataType: 'text',
                    timeout: 10000,
                    cache: false
                }).done(function(data) {
                    var loadTime = new Date().getTime() - startTime;
                    var size = data.length;
                    
                    if (data.indexOf('Matomo') > -1 || data.indexOf('Piwik') > -1) {
                        $result.html('<div class="alert alert-success">' +
                            '<i class="fa fa-check-circle"></i> Verbindung erfolgreich!<br>' +
                            '<small>Größe: ' + (size / 1024).toFixed(1) + ' KB | ' +
                            'Ladezeit: ' + loadTime + ' ms</small></div>');
                    } else {
                        $result.html('<div class="alert alert-warning">' +
                            '<i class="fa fa-exclamation-triangle"></i> Datei geladen, aber kein Matomo JavaScript erkannt</div>');
                    }
                }).fail(function(xhr, status, error) {
                    var msg = 'Verbindung fehlgeschlagen';
                    if (xhr.status > 0) {
                        msg += ' (HTTP ' + xhr.status + ')';
                    } else if (status === 'timeout') {
                        msg += ' (Timeout)';
                    } else if (error) {
                        msg += ' (' + error + ')';
                    }
                    $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + msg + '</div>');
                });
            } else {
                $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + (response.message || 'Fehler') + '</div>');
            }
        }).fail(function(xhr) {
            var msg = 'Backend-Anfrage fehlgeschlagen';
            if (xhr.responseText) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        msg = response.message;
                    }
                } catch(e) {}
            }
            $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + msg + '</div>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test');
        });
    });
    
    $('#test-proxy').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-proxy-result');
        
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Teste...');
        $result.html('');
        
        // Hole Proxy-URL vom Backend
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                func: 'test_proxy'
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.proxy_url) {
                var proxyUrl = response.proxy_url;
                var startTime = new Date().getTime();
                
                // Debug-Info anzeigen wenn vorhanden
                if (response.debug) {
                    console.log('Proxy Debug:', response.debug);
                    console.log('Proxy URL:', proxyUrl);
                }
                
                // Teste Proxy direkt per JavaScript
                $.ajax({
                    url: proxyUrl,
                    method: 'GET',
                    dataType: 'text',
                    timeout: 10000,
                    cache: false
                }).done(function(data) {
                    var loadTime = new Date().getTime() - startTime;
                    var size = data.length;
                    
                    if (data.indexOf('Matomo') > -1 || data.indexOf('Piwik') > -1) {
                        $result.html('<div class="alert alert-success">' +
                            '<i class="fa fa-check-circle"></i> Proxy funktioniert!<br>' +
                            '<small>Größe: ' + (size / 1024).toFixed(1) + ' KB | ' +
                            'Ladezeit: ' + loadTime + ' ms<br>' +
                            'URL: <code>' + proxyUrl + '</code></small></div>');
                    } else {
                        $result.html('<div class="alert alert-warning">' +
                            '<i class="fa fa-exclamation-triangle"></i> Proxy antwortet, aber kein Matomo JavaScript erkannt</div>');
                    }
                }).fail(function(xhr, status, error) {
                    var msg = 'Proxy-Aufruf fehlgeschlagen';
                    if (xhr.status > 0) {
                        msg += ' (HTTP ' + xhr.status + ')';
                    } else if (status === 'timeout') {
                        msg += ' (Timeout)';
                    } else if (error) {
                        msg += ' (' + error + ')';
                    }
                    msg += '<br><small>URL: <code>' + proxyUrl + '</code></small>';
                    $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + msg + '</div>');
                });
            } else {
                $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + (response.message || 'Fehler') + '</div>');
            }
        }).fail(function(xhr) {
            var msg = 'Backend-Anfrage fehlgeschlagen';
            if (xhr.responseText) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        msg = response.message;
                    }
                } catch(e) {}
            }
            $result.html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + msg + '</div>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-shield"></i> Proxy testen');
        });
    });
});
</script>
