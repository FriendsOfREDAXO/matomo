<?php

use FriendsOfRedaxo\Matomo\MatomoApi;
use rex_socket;
use rex_socket_exception;

$addon = rex_addon::get('matomo');

if (rex_request('func', 'string') === 'test_connection') {
    rex_response::cleanOutputBuffers();

    $testUrl = trim(rex_request('matomo_url', 'string', ''));
    if ('' === $testUrl) {
        rex_response::sendJson(['success' => false, 'message' => 'Keine URL angegeben']);
        exit;
    }

    if (false === filter_var($testUrl, FILTER_VALIDATE_URL)) {
        rex_response::sendJson(['success' => false, 'message' => 'Ungültige URL']);
        exit;
    }

    $jsUrl = rtrim($testUrl, '/') . '/matomo.js';

    try {
        $socket = rex_socket::factoryUrl($jsUrl);
        $socket->setTimeout(10);
        $response = $socket->doGet();

        if (!$response->isSuccessful()) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'HTTP ' . $response->getStatusCode(),
                'url' => $jsUrl,
            ]);
            exit;
        }

        $body = $response->getBody();
        $isMatomoJs = str_contains($body, 'Matomo') || str_contains($body, 'Piwik');

        rex_response::sendJson([
            'success' => $isMatomoJs,
            'message' => $isMatomoJs ? 'Verbindung erfolgreich' : 'Datei geladen, aber kein Matomo JS erkannt',
            'url' => $jsUrl,
            'size' => strlen($body),
        ]);
        exit;
    } catch (rex_socket_exception $e) {
        rex_response::sendJson([
            'success' => false,
            'message' => 'Socket-Fehler: ' . $e->getMessage(),
            'url' => $jsUrl,
        ]);
        exit;
    }
}

if (rex_request('func', 'string') === 'test_proxy') {
    rex_response::cleanOutputBuffers();

    $matomoUrl = (string) rex_config::get('matomo', 'matomo_url', '');
    if ('' === $matomoUrl) {
        rex_response::sendJson(['success' => false, 'message' => 'Matomo URL nicht konfiguriert']);
        exit;
    }

    $proxyUrl = rex_url::frontendController([
        'rex-api-call' => 'matomo_proxy',
        'file' => 'matomo.js',
        'test' => '1',
    ]);

    try {
        $server = rtrim((string) rex::getServer(), '/');
        $requestUrl = ('' !== $server ? $server : '') . $proxyUrl;
        $socket = rex_socket::factoryUrl($requestUrl);
        $socket->setTimeout(10);
        $response = $socket->doGet();

        if (!$response->isSuccessful()) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'HTTP ' . $response->getStatusCode(),
                'url' => $proxyUrl,
            ]);
            exit;
        }

        $body = $response->getBody();
        $isMatomoJs = str_contains($body, 'Matomo') || str_contains($body, 'Piwik');

        rex_response::sendJson([
            'success' => $isMatomoJs,
            'message' => $isMatomoJs ? 'Proxy funktioniert' : 'Proxy antwortet, aber kein Matomo JS erkannt',
            'url' => $proxyUrl,
            'size' => strlen($body),
        ]);
        exit;
    } catch (rex_socket_exception $e) {
        rex_response::sendJson([
            'success' => false,
            'message' => 'Socket-Fehler: ' . $e->getMessage(),
            'url' => $proxyUrl,
        ]);
        exit;
    }
}

$csrf = rex_csrf_token::factory('matomo_config');
$message = '';
$error = '';

rex_view::addJsFile($addon->getAssetsUrl('matomo-config.js'), ['defer' => true]);

if (rex_post('save_config', 'boolean')) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } else {
        $matomoUrl = trim(rex_post('matomo_url', 'string', ''));
        if ('' !== $matomoUrl && false === filter_var($matomoUrl, FILTER_VALIDATE_URL)) {
            $error = 'Bitte eine gültige Matomo URL eingeben.';
        } else {
            $cookieLifetime = rex_post('cookie_lifetime', 'int', 2592000);
            $allowedCookieLifetimes = [1800, 3600, 86400, 604800, 2592000, 31536000];
            if (!in_array($cookieLifetime, $allowedCookieLifetimes, true)) {
                $cookieLifetime = 2592000;
            }

            $apiTimeout = rex_post('api_timeout', 'int', 30);
            $allowedApiTimeouts = [10, 30, 60, 120];
            if (!in_array($apiTimeout, $allowedApiTimeouts, true)) {
                $apiTimeout = 30;
            }

            $socketTimeout = (float) rex_post('socket_timeout', 'float', 1.0);
            if ($socketTimeout < 0.1) {
                $socketTimeout = 0.1;
            }

            rex_config::set('matomo', 'matomo_url', $matomoUrl);
            rex_config::set('matomo', 'matomo_path', trim(rex_post('matomo_path', 'string', '')));
            rex_config::set('matomo', 'admin_token', trim(rex_post('admin_token', 'string', '')));
            rex_config::set('matomo', 'user_token', trim(rex_post('user_token', 'string', '')));
            rex_config::set('matomo', 'api_timeout', $apiTimeout);
            rex_config::set('matomo', 'ssl_verify', rex_post('ssl_verify', 'boolean', false));
            rex_config::set('matomo', 'socket_timeout', $socketTimeout);
            rex_config::set('matomo', 'anonymize_ip', rex_post('anonymize_ip', 'boolean', false));
            rex_config::set('matomo', 'cookieless_tracking', rex_post('cookieless_tracking', 'boolean', false));
            rex_config::set('matomo', 'show_top_pages', rex_post('show_top_pages', 'boolean', false));
            rex_config::set('matomo', 'proxy_enabled', rex_post('proxy_enabled', 'boolean', false));
            rex_config::set('matomo', 'server_side_tracking', rex_post('server_side_tracking', 'boolean', false));
            rex_config::set('matomo', 'server_side_site_id', max(0, rex_post('server_side_site_id', 'int', 0)));
            rex_config::set('matomo', 'event_tracking_js', rex_post('event_tracking_js', 'boolean', false));
            rex_config::set('matomo', 'respect_dnt', rex_post('respect_dnt', 'boolean', false));
            rex_config::set('matomo', 'cookie_lifetime', $cookieLifetime);

            $message = $addon->i18n('matomo_config_saved');
        }
    }
}

// Status-Panel
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');
$api_timeout = (int) rex_config::get('matomo', 'api_timeout', 30);
$ssl_verify = (bool) rex_config::get('matomo', 'ssl_verify', false);
$socket_timeout = (float) rex_config::get('matomo', 'socket_timeout', 1.0);
$anonymize_ip = (bool) rex_config::get('matomo', 'anonymize_ip', false);
$cookieless_tracking = (bool) rex_config::get('matomo', 'cookieless_tracking', false);
$show_top_pages = (bool) rex_config::get('matomo', 'show_top_pages', false);
$proxy_enabled = (bool) rex_config::get('matomo', 'proxy_enabled', false);
$server_side_tracking = (bool) rex_config::get('matomo', 'server_side_tracking', false);
$server_side_site_id = (int) rex_config::get('matomo', 'server_side_site_id', 0);
$event_tracking_js = (bool) rex_config::get('matomo', 'event_tracking_js', false);
$respect_dnt = (bool) rex_config::get('matomo', 'respect_dnt', false);
$cookie_lifetime = (int) rex_config::get('matomo', 'cookie_lifetime', 2592000);

$matomo_ready = false;
$is_external_matomo = false;
$api_status = 'Nicht getestet';
$superuser_status = 'Nicht getestet';

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
    
    if ($matomo_ready) {
        try {
            $api = new MatomoApi($matomo_url, $admin_token, $user_token);
            $sites = $api->getSites();
            $api_status = '✅ Verbunden (' . count($sites) . ' Sites)';

            if ($api->hasSuperUserAccess()) {
                $superuser_status = '✅ Ja';
            } else {
                $superuser_status = '❌ Nein (Token hat keine Superuser-Rechte)';
            }
        } catch (Exception $e) {
            $api_status = '❌ Fehler: ' . $e->getMessage();
            $superuser_status = '❌ Nicht prüfbar';
        }
    }
}

?>

<?php if ('' !== $message): ?>
    <?= rex_view::success($message) ?>
<?php endif; ?>
<?php if ('' !== $error): ?>
    <?= rex_view::error($error) ?>
<?php endif; ?>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    <strong>Konfiguration:</strong> Alle Tracking-Optionen sind hier zentral gebündelt. Das Matomo-Setup enthält nur noch Installation und Grund-Setup.
</div>

<div id="matomo-config-endpoints"
     data-test-connection-url="<?= rex_escape(rex_url::currentBackendPage(['func' => 'test_connection'])) ?>"
     data-test-proxy-url="<?= rex_escape(rex_url::currentBackendPage(['func' => 'test_proxy'])) ?>"></div>

<form method="post" class="rex-form">
    <?= $csrf->getHiddenField() ?>
    <div class="row">
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-globe"></i> Basis & Zugang</h3></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="matomo_url"><?= $addon->i18n('matomo_url') ?></label>
                        <div class="input-group">
                            <input type="url" id="matomo_url" name="matomo_url" class="form-control" value="<?= rex_escape($matomo_url) ?>" placeholder="https://ihre-domain.de/matomo">
                            <span class="input-group-btn">
                                <button type="button" id="test-connection" class="btn btn-default" title="Verbindung testen">
                                    <i class="fas fa-plug"></i> Test
                                </button>
                            </span>
                        </div>
                        <p class="help-block"><?= $addon->i18n('matomo_url_help') ?></p>
                        <div id="test-result"></div>
                    </div>
                    <div class="form-group">
                        <label for="matomo_path"><?= $addon->i18n('matomo_path') ?></label>
                        <input type="text" id="matomo_path" name="matomo_path" class="form-control" value="<?= rex_escape($matomo_path) ?>" placeholder="matomo">
                        <p class="help-block"><?= $addon->i18n('matomo_path_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="admin_token"><?= $addon->i18n('matomo_admin_token') ?></label>
                        <input type="password" id="admin_token" name="admin_token" class="form-control" value="<?= rex_escape($admin_token) ?>">
                        <p class="help-block"><?= $addon->i18n('matomo_admin_token_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="user_token"><?= $addon->i18n('matomo_user_token') ?></label>
                        <input type="password" id="user_token" name="user_token" class="form-control" value="<?= rex_escape($user_token) ?>">
                        <p class="help-block"><?= $addon->i18n('matomo_user_token_help') ?></p>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-plug"></i> API & Verbindung</h3></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="api_timeout"><?= $addon->i18n('matomo_api_timeout') ?></label>
                        <select id="api_timeout" name="api_timeout" class="form-control">
                            <option value="10" <?= 10 === $api_timeout ? 'selected' : '' ?>><?= $addon->i18n('matomo_timeout_10s') ?></option>
                            <option value="30" <?= 30 === $api_timeout ? 'selected' : '' ?>><?= $addon->i18n('matomo_timeout_30s') ?></option>
                            <option value="60" <?= 60 === $api_timeout ? 'selected' : '' ?>><?= $addon->i18n('matomo_timeout_60s') ?></option>
                            <option value="120" <?= 120 === $api_timeout ? 'selected' : '' ?>><?= $addon->i18n('matomo_timeout_120s') ?></option>
                        </select>
                        <p class="help-block"><?= $addon->i18n('matomo_api_timeout_help') ?></p>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="ssl_verify" value="1" <?= $ssl_verify ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_ssl_verify_option') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_ssl_verify_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="socket_timeout">Socket Timeout (Sekunden)</label>
                        <input type="number" id="socket_timeout" name="socket_timeout" class="form-control" min="0.1" step="0.1" value="<?= rex_escape((string) $socket_timeout) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-chart-line"></i> Tracking-Features</h3></div>
                <div class="panel-body">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="show_top_pages" value="1" <?= $show_top_pages ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_show_top_pages') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_show_top_pages_help') ?></p>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="proxy_enabled" value="1" <?= $proxy_enabled ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_proxy_enabled') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_proxy_enabled_help') ?></p>
                        <button type="button" id="test-proxy" class="btn btn-default btn-sm">
                            <i class="fas fa-shield-alt"></i> Proxy testen
                        </button>
                        <div id="test-proxy-result" style="margin-top: 8px;"></div>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="server_side_tracking" value="1" <?= $server_side_tracking ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_server_side_tracking_enable') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_server_side_tracking_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="server_side_site_id"><?= $addon->i18n('matomo_server_side_site_id') ?></label>
                        <input type="number" id="server_side_site_id" name="server_side_site_id" class="form-control" min="0" value="<?= (int) $server_side_site_id ?>">
                        <p class="help-block"><?= $addon->i18n('matomo_server_side_site_id_help') ?></p>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="event_tracking_js" value="1" <?= $event_tracking_js ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_event_tracking_js') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_event_tracking_js_help') ?></p>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-user-shield"></i> Datenschutz</h3></div>
                <div class="panel-body">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="anonymize_ip" value="1" <?= $anonymize_ip ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_anonymize_ip_option') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_anonymize_ip_help') ?></p>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="cookieless_tracking" value="1" <?= $cookieless_tracking ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_cookieless_option') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_cookieless_help') ?></p>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="respect_dnt" value="1" <?= $respect_dnt ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_respect_dnt_option') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_respect_dnt_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="cookie_lifetime"><?= $addon->i18n('matomo_cookie_lifetime') ?></label>
                        <select id="cookie_lifetime" name="cookie_lifetime" class="form-control">
                            <option value="1800" <?= 1800 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_30min') ?></option>
                            <option value="3600" <?= 3600 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_1hour') ?></option>
                            <option value="86400" <?= 86400 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_1day') ?></option>
                            <option value="604800" <?= 604800 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_1week') ?></option>
                            <option value="2592000" <?= 2592000 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_1month') ?></option>
                            <option value="31536000" <?= 31536000 === $cookie_lifetime ? 'selected' : '' ?>><?= $addon->i18n('matomo_cookie_1year') ?></option>
                        </select>
                        <p class="help-block"><?= $addon->i18n('matomo_cookie_lifetime_help') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-success">
        <div class="panel-body text-right">
            <button type="submit" name="save_config" value="1" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> <?= $addon->i18n('matomo_save') ?>
            </button>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-<?= $matomo_ready ? 'success' : 'warning' ?>">
            <div class="panel-heading">
                <h3 class="panel-title">📊 Matomo Status</h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td><strong>Installation:</strong></td>
                        <td class="text-<?= $matomo_ready ? 'success' : 'danger' ?>">
                            <?php if ($is_external_matomo): ?>
                                🌐 Externe Installation
                            <?php else: ?>
                                <?= $matomo_ready ? '✅ Gefunden' : '❌ Nicht gefunden' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>API Status:</strong></td>
                        <td><?= rex_escape($api_status) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Superuser-Token:</strong></td>
                        <td><?= rex_escape($superuser_status) ?></td>
                    </tr>
                    <?php if ($matomo_path !== ''): ?>
                    <tr>
                        <td><strong>Pfad:</strong></td>
                        <td><code><?= rex_escape($matomo_path) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($matomo_url !== ''): ?>
                    <tr>
                        <td><strong>URL:</strong></td>
                        <td>
                            <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-xs btn-primary">
                                🔗 Öffnen
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">💡 Hilfe</h3>
            </div>
            <div class="panel-body">
                <h5><?= $addon->i18n('matomo_help_first_steps') ?>:</h5>
                <ol>
                    <li><?= $addon->i18n('matomo_help_step1', rex_url::currentBackendPage(['page' => 'matomo/settings'])) ?></li>
                    <li><?= $addon->i18n('matomo_help_step2') ?></li>
                    <li><?= $addon->i18n('matomo_help_step3') ?></li>
                    <li><?= $addon->i18n('matomo_help_step4') ?></li>
                </ol>
                
                <h5><?= $addon->i18n('matomo_help_tokens') ?>:</h5>
                <p><strong><?= $addon->i18n('matomo_help_admin_token') ?>:</strong><br>
                <?= $addon->i18n('matomo_help_admin_token_desc') ?></p>
                
                <p><strong><?= $addon->i18n('matomo_help_user_token') ?>:</strong><br>
                <?= $addon->i18n('matomo_help_user_token_desc') ?></p>
                
                <p><?= $addon->i18n('matomo_help_token_location') ?>:<br>
                <code>Administration → Platform → API → User Authentication</code></p>
            </div>
        </div>
    </div>
</div>