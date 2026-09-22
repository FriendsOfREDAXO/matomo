<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

$addon = rex_addon::get('matomo');

$csrf = rex_csrf_token::factory('matomo_config');
$message = '';
$error = '';

if (rex_post('save_config', 'boolean')) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
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

        rex_config::set('matomo', 'api_timeout', $apiTimeout);
        rex_config::set('matomo', 'verify_ssl', rex_post('verify_ssl', 'boolean', false));
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

        // Optionale Matomo-Datenbankzugangsdaten für Passwort-Reset (nur wenn config.ini.php nicht reicht)
        rex_config::set('matomo', 'db_override_host', trim(rex_post('db_override_host', 'string', '')));
        rex_config::set('matomo', 'db_override_user', trim(rex_post('db_override_user', 'string', '')));
        $dbPassword = rex_post('db_override_password', 'string', '');
        if ('' !== $dbPassword || '' === trim(rex_post('db_override_user', 'string', ''))) {
            rex_config::set('matomo', 'db_override_password', $dbPassword);
        }
        rex_config::set('matomo', 'db_override_name', trim(rex_post('db_override_name', 'string', '')));
        rex_config::set('matomo', 'db_override_prefix', trim(rex_post('db_override_prefix', 'string', 'matomo_')));

        $message = $addon->i18n('matomo_config_saved');
    }
}

// Status
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$api_timeout = (int) rex_config::get('matomo', 'api_timeout', 30);
$verify_ssl = MatomoApi::verifySsl();
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

$api_status = '';
if ($matomo_url !== '' && $admin_token !== '') {
    try {
        $api = new MatomoApi($matomo_url, $admin_token);
        $api_status = '<span class="text-success"><i class="fa fa-check-circle"></i> ' . $addon->i18n('matomo_connected') . ' (' . count($api->getSites()) . ' ' . $addon->i18n('matomo_setup_websites') . ')</span>';
    } catch (Exception $e) {
        $api_status = '<span class="text-danger"><i class="fa fa-times-circle"></i> ' . rex_escape($e->getMessage()) . '</span>';
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
    <i class="fa fa-info-circle"></i>
    <?= rex_i18n::rawMsg('matomo_config_intro', rex_url::backendPage('matomo/settings')) ?>
    <?= '' !== $api_status ? '<br>' . $api_status : '' ?>
</div>

<div id="matomo-config-endpoints"
    data-test-proxy-url="<?= rex_escape(rex_url::backendController(['rex-api-call' => 'matomo_test_proxy'])) ?>"></div>

<form method="post" class="rex-form">
    <?= $csrf->getHiddenField() ?>
    <div class="row">
        <div class="col-sm-6">
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
                            <input type="checkbox" name="verify_ssl" value="1" <?= $verify_ssl ? 'checked' : '' ?>>
                            <?= $addon->i18n('matomo_verify_ssl') ?>
                        </label>
                        <p class="help-block"><?= $addon->i18n('matomo_verify_ssl_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="socket_timeout"><?= $addon->i18n('matomo_socket_timeout') ?></label>
                        <p class="help-block"><?= $addon->i18n('matomo_socket_timeout_help') ?></p>
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

    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-database"></i> <?= $addon->i18n('matomo_db_override_title') ?></h3></div>
        <div class="panel-body">
            <p class="help-block"><?= $addon->i18n('matomo_db_override_help') ?></p>
            <div class="row">
                <div class="col-sm-3"><div class="form-group"><label for="db_override_host"><?= $addon->i18n('matomo_db_override_host') ?></label><input type="text" id="db_override_host" name="db_override_host" class="form-control" value="<?= rex_escape((string) rex_config::get('matomo', 'db_override_host', '')) ?>" placeholder="localhost"></div></div>
                <div class="col-sm-3"><div class="form-group"><label for="db_override_user"><?= $addon->i18n('matomo_db_override_user') ?></label><input type="text" id="db_override_user" name="db_override_user" class="form-control" value="<?= rex_escape((string) rex_config::get('matomo', 'db_override_user', '')) ?>" autocomplete="off"></div></div>
                <div class="col-sm-3"><div class="form-group"><label for="db_override_password"><?= $addon->i18n('matomo_db_override_password') ?></label><input type="password" id="db_override_password" name="db_override_password" class="form-control" value="" placeholder="<?= '' !== (string) rex_config::get('matomo', 'db_override_password', '') ? '••••••••' : '' ?>" autocomplete="new-password"></div></div>
                <div class="col-sm-2"><div class="form-group"><label for="db_override_name"><?= $addon->i18n('matomo_db_override_name') ?></label><input type="text" id="db_override_name" name="db_override_name" class="form-control" value="<?= rex_escape((string) rex_config::get('matomo', 'db_override_name', '')) ?>"></div></div>
                <div class="col-sm-1"><div class="form-group"><label for="db_override_prefix"><?= $addon->i18n('matomo_db_override_prefix') ?></label><input type="text" id="db_override_prefix" name="db_override_prefix" class="form-control" value="<?= rex_escape((string) rex_config::get('matomo', 'db_override_prefix', 'matomo_')) ?>"></div></div>
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
