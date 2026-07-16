<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

// Form-Verarbeitung
$message = '';
$error = '';

if (rex_post('save_settings', 'boolean')) {
    $matomo_path = trim(rex_post('matomo_path', 'string', ''));
    $matomo_url = trim(rex_post('matomo_url', 'string', ''));
    $admin_token = trim(rex_post('admin_token', 'string', ''));
    $matomo_user = trim(rex_post('matomo_user', 'string', ''));
    $matomo_password = rex_post('matomo_password', 'string', '');
    $verify_ssl = rex_post('verify_ssl', 'boolean', true);

    rex_config::set('matomo', 'matomo_path', $matomo_path);
    rex_config::set('matomo', 'matomo_url', $matomo_url);
    rex_config::set('matomo', 'admin_token', $admin_token);
    rex_config::set('matomo', 'matomo_user', $matomo_user);
    rex_config::set('matomo', 'matomo_password', $matomo_password);
    rex_config::set('matomo', 'verify_ssl', $verify_ssl);

    $message = $addon->i18n('matomo_config_saved');
}

if (rex_post('download_matomo', 'boolean')) {
    $download_path = rex_post('download_path', 'string', '');
    $matomo_url = rex_post('download_url', 'string', '');

    if ($download_path !== '' && $matomo_url !== '') {
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
$verify_ssl = rex_config::get('matomo', 'verify_ssl', true);

// Status prüfen
$matomo_installed = false;
$is_external_matomo = false;
$superuser_status = 'Nicht getestet';

if ($matomo_url !== '' && $admin_token !== '') {
    if ($matomo_path !== '') {
        // Lokale Matomo-Installation - prüfe ob verfügbar
        $full_path = rex_path::frontend($matomo_path . '/');
        $matomo_installed = file_exists($full_path . 'index.php');
    } else {
        // Externe Matomo-Installation - keine lokale Verfügbarkeitsprüfung möglich
        $matomo_installed = true;
        $is_external_matomo = true;
    }

    if ($matomo_installed) {
        try {
            $api = new MatomoApi($matomo_url, $admin_token);
            if ($api->hasSuperUserAccess()) {
                $superuser_status = '✅ Ja';
            } else {
                $superuser_status = '❌ Nein (Token hat keine Superuser-Rechte)';
            }
        } catch (Exception $e) {
            $superuser_status = '❌ Nicht prüfbar';
        }
    }
}

// Nachrichten anzeigen
if ($message !== '') {
    echo rex_view::success($message);
}
if ($error !== '') {
    echo rex_view::error($error);
}

// Check for cURL availability
if (!function_exists('curl_init')) {
    echo rex_view::info($addon->i18n('matomo_curl_not_available'));
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
                        <input type="url" class="form-control" id="matomo_url" name="matomo_url" 
                               value="<?= rex_escape($matomo_url) ?>" placeholder="https://ihre-domain.de/matomo">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_token">Admin Token:</label>
                        <input type="text" class="form-control" id="admin_token" name="admin_token" 
                               value="<?= rex_escape($admin_token) ?>" placeholder="">
                        <small class="text-muted">Finden Sie in Matomo unter: Administration → Platform → API → User Authentication</small>
                    </div>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="verify_ssl" value="1" <?= (bool)$verify_ssl ? 'checked' : '' ?>>
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
                
                <?php if ($matomo_path !== ''): ?>
                <p><strong>Pfad:</strong><br>
                <code><?= rex_escape($matomo_path) ?></code></p>
                <?php endif; ?>
                
                <?php if ($matomo_url !== ''): ?>
                <p><strong>URL:</strong><br>
                <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm">
                    🔗 Matomo öffnen
                </a></p>
                <?php endif; ?>
                
                <?php if ($admin_token !== ''): ?>
                <p><strong>Admin Token:</strong><br>
                <span class="text-success"><i class="fa fa-check-circle"></i> Konfiguriert</span></p>
                <p><strong>Superuser-Token:</strong><br>
                <?= rex_escape($superuser_status) ?></p>
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

