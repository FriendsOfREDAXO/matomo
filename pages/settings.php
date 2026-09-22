<?php

use FriendsOfRedaxo\Matomo\AdminReset;
use FriendsOfRedaxo\Matomo\AutoLogin;
use FriendsOfRedaxo\Matomo\ConsentRegistration;
use FriendsOfRedaxo\Matomo\MatomoApi;
use FriendsOfRedaxo\Matomo\MatomoCli;
use FriendsOfRedaxo\Matomo\UserAccess;

$addon = rex_addon::get('matomo');
$csrf = rex_csrf_token::factory('matomo_setup');

$messages = [];
$errors = [];

$matomo_url = rtrim(trim((string) rex_config::get('matomo', 'matomo_url', '')), '/');
$matomo_path = trim((string) rex_config::get('matomo', 'matomo_path', ''));
$admin_token = trim((string) rex_config::get('matomo', 'admin_token', ''));

$api = static function () use (&$matomo_url, &$admin_token): MatomoApi {
    return new MatomoApi($matomo_url, $admin_token);
};

// ---------------------------------------------------------------------------
// Aktionen
// ---------------------------------------------------------------------------
if ('' !== rex_post('setup_action', 'string', '') && !$csrf->isValid()) {
    $errors[] = rex_i18n::msg('csrf_token_invalid');
} else {
    switch (rex_post('setup_action', 'string', '')) {
        case 'download':
            $download_path = trim(rex_post('download_path', 'string', ''), " /\\");
            $download_url = rtrim(trim(rex_post('download_url', 'string', '')), '/');
            if ('' === $download_path || '' === $download_url) {
                $errors[] = $addon->i18n('matomo_fill_all_fields');
            } elseif (str_contains($download_path, '..')) {
                $errors[] = $addon->i18n('matomo_setup_invalid_path');
            } else {
                try {
                    MatomoApi::downloadMatomo(rex_path::frontend($download_path));
                    rex_config::set('matomo', 'matomo_path', $download_path);
                    rex_config::set('matomo', 'matomo_url', $download_url);
                    $matomo_path = $download_path;
                    $matomo_url = $download_url;
                    $messages[] = $addon->i18n('matomo_download_success');
                } catch (Exception $e) {
                    $errors[] = $addon->i18n('matomo_download_failed', $e->getMessage());
                }
            }
            break;

        case 'connection':
            $new_url = rtrim(trim(rex_post('matomo_url', 'string', '')), '/');
            $new_path = trim(rex_post('matomo_path', 'string', ''), " /\\");
            $new_token = trim(rex_post('admin_token', 'string', ''));
            $login = trim(rex_post('matomo_login', 'string', ''));
            $password = rex_post('matomo_login_password', 'string', '');

            if ('' === $new_url || false === filter_var($new_url, FILTER_VALIDATE_URL)) {
                $errors[] = $addon->i18n('matomo_setup_invalid_url');
            } elseif (str_contains($new_path, '..')) {
                $errors[] = $addon->i18n('matomo_setup_invalid_path');
            } else {
                rex_config::set('matomo', 'matomo_url', $new_url);
                rex_config::set('matomo', 'matomo_path', $new_path);
                rex_config::set('matomo', 'verify_ssl', rex_post('verify_ssl', 'boolean', false));
                $matomo_url = $new_url;
                $matomo_path = $new_path;

                if ('' !== $login && '' !== $password) {
                    try {
                        $new_token = MatomoApi::createTokenWithCredentials($new_url, $login, $password, 'REDAXO ' . rex::getServerName());
                        $messages[] = $addon->i18n('matomo_setup_token_created', $login);
                    } catch (Exception $e) {
                        $errors[] = $addon->i18n('matomo_setup_token_failed', $e->getMessage());
                        $new_token = '';
                    }
                }
                if ('' !== $new_token) {
                    rex_config::set('matomo', 'admin_token', $new_token);
                    $admin_token = $new_token;
                }
                if ([] === $errors) {
                    $messages[] = $addon->i18n('matomo_config_saved');
                }
            }
            break;

        case 'consent':
            $tool = rex_post('consent_tool', 'string', '');
            $site_id = rex_post('consent_site_id', 'int', 0);
            if (!in_array($tool, ConsentRegistration::availableTools(), true) || $site_id < 1) {
                $errors[] = $addon->i18n('matomo_fill_all_fields');
            } else {
                try {
                    ConsentRegistration::register($tool, $api(), $site_id, $api()->getSites());
                    $messages[] = $addon->i18n('matomo_setup_consent_registered', $tool);
                } catch (Exception $e) {
                    $errors[] = $addon->i18n('matomo_setup_consent_failed', $e->getMessage());
                }
            }
            break;

        case 'autologin_enable':
            $err = AutoLogin::enableLocally();
            if (null === $err) {
                $messages[] = $addon->i18n('matomo_autologin_enabled');
            } else {
                $errors[] = $addon->i18n('matomo_autologin_enable_failed', $err);
            }
            break;

        case 'autologin_external':
            rex_config::set('matomo', AutoLogin::CONFIG_KEY, rex_post('autologin_confirmed', 'boolean', false));
            $messages[] = $addon->i18n('matomo_config_saved');
            break;

        case 'reset':
            try {
                $result = AdminReset::reset(rex_post('reset_login', 'string', ''), rex_post('reset_password', 'string', ''));
                $admin_token = $result['token'];
                $messages[] = rex_i18n::rawMsg('matomo_setup_reset_done', rex_escape(trim(rex_post('reset_login', 'string', ''))), rex_escape($result['password']));
            } catch (Exception $e) {
                $errors[] = $addon->i18n('matomo_setup_reset_failed', $e->getMessage());
            }
            break;

        case 'access_create':
        case 'access_create_all':
        case 'access_remove':
        case 'access_sites':
            $action = rex_post('setup_action', 'string', '');
            $user_id = rex_post('user_id', 'int', 0);
            $site_ids = array_values(array_map('intval', rex_post('site_ids', 'array', [])));
            try {
                if ('access_remove' === $action) {
                    UserAccess::remove($api(), $user_id);
                    $messages[] = $addon->i18n('matomo_setup_access_removed');
                } elseif ('access_sites' === $action) {
                    UserAccess::updateSites($api(), $user_id, $site_ids);
                    $messages[] = $addon->i18n('matomo_setup_access_sites_saved', rex_user::get($user_id)?->getLogin() ?? (string) $user_id);
                } else {
                    $targets = [];
                    $skipped = [];
                    foreach (UserAccess::redaxoUsers() as $ru) {
                        if ('access_create_all' === $action ? null === UserAccess::get($ru['id']) : $ru['id'] === $user_id) {
                            if (!$ru['has_email']) {
                                $skipped[] = $ru['login'];
                                continue;
                            }
                            $targets[] = $ru['id'];
                        }
                    }
                    if ([] !== $skipped) {
                        $errors[] = $addon->i18n('matomo_setup_access_no_email', implode(', ', $skipped));
                    }
                    foreach ($targets as $id) {
                        $rex_user = rex_user::get($id);
                        if (null === $rex_user) {
                            continue;
                        }
                        $entry = UserAccess::create($api(), $matomo_url, $rex_user, 'access_create_all' === $action ? [] : $site_ids);
                        $messages[] = $addon->i18n('matomo_setup_access_created', $rex_user->getLogin(), $entry['login']);
                    }
                }
            } catch (Exception $e) {
                $errors[] = $addon->i18n('matomo_setup_access_failed', $e->getMessage());
            }
            break;
    }
}

// ---------------------------------------------------------------------------
// Status
// ---------------------------------------------------------------------------
$verify_ssl = MatomoApi::verifySsl();
$is_local = '' !== $matomo_path;
$installed = $is_local ? file_exists(rex_path::frontend($matomo_path . '/index.php')) : '' !== $matomo_url;
$config_written = $is_local && file_exists(rex_path::frontend($matomo_path . '/config/config.ini.php'));

// Werbe-Plugin ProfessionalServices bei lokaler Installation immer deaktivieren (einmal je Installation)
$unwanted_plugins_state = '';
if ($config_written && '' !== MatomoCli::matomoDir()) {
    if ((string) rex_config::get('matomo', 'unwanted_plugins_done', '') === MatomoCli::matomoDir()) {
        $unwanted_plugins_state = 'done';
    } else {
        $plugin_error = MatomoCli::isAvailable() ? MatomoCli::deactivatePlugin('ProfessionalServices') : MatomoCli::unavailableReason();
        if (null === $plugin_error) {
            rex_config::set('matomo', 'unwanted_plugins_done', MatomoCli::matomoDir());
            $unwanted_plugins_state = 'done';
            $messages[] = $addon->i18n('matomo_setup_plugins_disabled');
        } else {
            $unwanted_plugins_state = $plugin_error;
        }
    }
}

$connected = false;
$superuser = false;
$sites = [];
$api_error = '';
if ('' !== $matomo_url && '' !== $admin_token) {
    try {
        $sites = $api()->getSites();
        $connected = true;
        foreach ($api()->repairPlaceholderSiteNames($sites) as $renamed) {
            $messages[] = $addon->i18n('matomo_site_name_repaired', $renamed['id'], $renamed['old'], $renamed['new']);
        }
        $superuser = $api()->hasSuperUserAccess();
    } catch (Exception $e) {
        $api_error = $e->getMessage();
    }
}

$consent_tools = ConsentRegistration::availableTools();
$access_entries = UserAccess::all();
$redaxo_users = UserAccess::redaxoUsers();
$access_missing = 0;
foreach ($redaxo_users as $ru) {
    if (!isset($access_entries[$ru['id']]) && $ru['has_email']) {
        ++$access_missing;
    }
}

foreach ($messages as $m) {
    echo rex_view::success($m);
}
foreach ($errors as $m) {
    echo rex_view::error($m);
}

$step = static function (int $number, string $title, bool $done, string $body, string $panelClass = 'default'): string {
    $badge = $done
        ? '<span class="label label-success"><i class="fa fa-check"></i></span>'
        : '<span class="label label-default">' . $number . '</span>';
    return '<div class="panel panel-' . $panelClass . '"><div class="panel-heading"><h3 class="panel-title">' . $badge . ' ' . rex_escape($title) . '</h3></div><div class="panel-body">' . $body . '</div></div>';
};

$hidden = '<input type="hidden" name="page" value="matomo/settings">' . $csrf->getHiddenField();

?>
<div id="matomo-config-endpoints"
    data-test-connection-url="<?= rex_escape(rex_url::backendController(['rex-api-call' => 'matomo_test_connection'])) ?>"
    data-test-proxy-url="<?= rex_escape(rex_url::backendController(['rex-api-call' => 'matomo_test_proxy'])) ?>"></div>

<div class="row">
<div class="col-md-8">
<?php
// ---------------------------------------------------------------------------
// Schritt 1: Matomo bereitstellen
// ---------------------------------------------------------------------------
ob_start();
if ($installed) {
    if ($is_local) {
        echo '<p class="text-success"><i class="fa fa-check-circle"></i> ' . $addon->i18n('matomo_setup_installed_local', rex_escape($matomo_path)) . '</p>';
        if (!$config_written) {
            echo '<div class="alert alert-warning">' . $addon->i18n('matomo_setup_wizard_pending') . ' <a class="btn btn-primary btn-sm" target="_blank" href="' . rex_escape($matomo_url) . '/"><i class="fa fa-external-link-alt"></i> ' . $addon->i18n('matomo_setup_open_wizard') . '</a></div>';
        } elseif ('done' === $unwanted_plugins_state) {
            echo '<p class="text-success"><i class="fa fa-check-circle"></i> ' . $addon->i18n('matomo_setup_plugins_disabled_state') . '</p>';
        } elseif ('' !== $unwanted_plugins_state) {
            echo '<p class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' . $addon->i18n('matomo_setup_plugins_disable_failed', $unwanted_plugins_state) . '</p>';
        }
    } else {
        echo '<p class="text-success"><i class="fa fa-globe"></i> ' . $addon->i18n('matomo_setup_installed_external', rex_escape($matomo_url)) . '</p>';
    }
} else {
    ?>
    <p><?= $addon->i18n('matomo_setup_install_intro') ?></p>
    <form method="post" class="rex-form">
        <?= $hidden ?>
        <input type="hidden" name="setup_action" value="download">
        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    <label for="download_path"><?= $addon->i18n('matomo_installation_path') ?></label>
                    <input type="text" class="form-control" id="download_path" name="download_path" value="<?= rex_escape($matomo_path ?: 'matomo') ?>" required>
                    <p class="help-block"><?= $addon->i18n('matomo_installation_path_help') ?></p>
                </div>
            </div>
            <div class="col-sm-8">
                <div class="form-group">
                    <label for="download_url"><?= $addon->i18n('matomo_installation_url') ?></label>
                    <input type="url" class="form-control" id="download_url" name="download_url" value="<?= rex_escape($matomo_url ?: rex::getServer() . 'matomo') ?>" required>
                    <p class="help-block"><?= $addon->i18n('matomo_installation_url_help') ?></p>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-download"></i> <?= $addon->i18n('matomo_download_button') ?></button>
        <p class="help-block" style="margin-top:10px"><?= $addon->i18n('matomo_setup_external_hint') ?></p>
    </form>
    <?php
}
echo $step(1, $addon->i18n('matomo_setup_step_install'), $installed && (!$is_local || $config_written), (string) ob_get_clean(), $installed ? 'success' : 'primary');

// ---------------------------------------------------------------------------
// Schritt 2: Verbindung
// ---------------------------------------------------------------------------
ob_start();
?>
<form method="post" class="rex-form" autocomplete="off">
    <?= $hidden ?>
    <input type="hidden" name="setup_action" value="connection">
    <div class="row">
        <div class="col-sm-8">
            <div class="form-group">
                <label for="matomo_url"><?= $addon->i18n('matomo_url') ?></label>
                <div class="input-group">
                    <input type="url" id="matomo_url" name="matomo_url" class="form-control" value="<?= rex_escape($matomo_url) ?>" placeholder="https://ihre-domain.de/matomo" required>
                    <span class="input-group-btn">
                        <button type="button" id="test-connection" class="btn btn-default"><i class="fa fa-plug"></i> <?= $addon->i18n('matomo_setup_test') ?></button>
                    </span>
                </div>
                <div id="test-result"></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                <label for="matomo_path"><?= $addon->i18n('matomo_path') ?></label>
                <input type="text" id="matomo_path" name="matomo_path" class="form-control" value="<?= rex_escape($matomo_path) ?>" placeholder="matomo">
                <p class="help-block"><?= $addon->i18n('matomo_setup_path_help') ?></p>
            </div>
        </div>
    </div>
    <div class="checkbox">
        <label><input type="checkbox" name="verify_ssl" value="1" <?= $verify_ssl ? 'checked' : '' ?>> <?= $addon->i18n('matomo_verify_ssl') ?></label>
        <p class="help-block"><?= $addon->i18n('matomo_verify_ssl_help') ?></p>
    </div>

    <h4><?= $addon->i18n('matomo_setup_token_headline') ?></h4>
    <p class="help-block"><?= $addon->i18n('matomo_setup_token_intro') ?></p>
    <div class="row">
        <div class="col-sm-6">
            <div class="well well-sm">
                <strong><?= $addon->i18n('matomo_setup_token_auto') ?></strong>
                <div class="form-group">
                    <label for="matomo_login"><?= $addon->i18n('matomo_setup_login') ?></label>
                    <input type="text" id="matomo_login" name="matomo_login" class="form-control" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="matomo_login_password"><?= $addon->i18n('matomo_setup_password') ?></label>
                    <input type="password" id="matomo_login_password" name="matomo_login_password" class="form-control" autocomplete="new-password">
                    <p class="help-block"><?= $addon->i18n('matomo_setup_password_help') ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="well well-sm">
                <strong><?= $addon->i18n('matomo_setup_token_manual') ?></strong>
                <div class="form-group">
                    <label for="admin_token"><?= $addon->i18n('matomo_setup_token') ?></label>
                    <input type="password" id="admin_token" name="admin_token" class="form-control" value="" placeholder="<?= '' !== $admin_token ? $addon->i18n('matomo_setup_token_keep') : '' ?>" autocomplete="new-password">
                    <p class="help-block"><?= $addon->i18n('matomo_setup_token_manual_help') ?></p>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= $addon->i18n('matomo_setup_save_connection') ?></button>
</form>

<hr>
<details>
    <summary style="cursor:pointer"><strong><i class="fa fa-life-ring"></i> <?= $addon->i18n('matomo_setup_reset_headline') ?></strong></summary>
    <p class="help-block" style="margin-top:10px"><?= $addon->i18n('matomo_setup_reset_intro') ?></p>
    <?php if (AdminReset::isAvailable()): ?>
    <form method="post" class="rex-form" autocomplete="off" onsubmit="return confirm('<?= rex_escape($addon->i18n('matomo_setup_reset_confirm'), 'js') ?>')">
        <?= $hidden ?>
        <input type="hidden" name="setup_action" value="reset">
        <div class="row">
            <div class="col-sm-5">
                <div class="form-group">
                    <label for="reset_login"><?= $addon->i18n('matomo_setup_reset_login') ?></label>
                    <input type="text" id="reset_login" name="reset_login" class="form-control" value="admin" required autocomplete="off">
                </div>
            </div>
            <div class="col-sm-7">
                <div class="form-group">
                    <label for="reset_password"><?= $addon->i18n('matomo_setup_reset_password') ?></label>
                    <input type="password" id="reset_password" name="reset_password" class="form-control" autocomplete="new-password">
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-key"></i> <?= $addon->i18n('matomo_setup_reset_button') ?></button>
    </form>
    <?php else: ?>
        <p class="text-muted"><?= $addon->i18n('matomo_setup_reset_unavailable') ?></p>
    <?php endif; ?>
</details>
<?php
echo $step(2, $addon->i18n('matomo_setup_step_connection'), $connected && $superuser, (string) ob_get_clean(), $connected ? ($superuser ? 'success' : 'warning') : 'default');

// ---------------------------------------------------------------------------
// Schritt 3: Websites
// ---------------------------------------------------------------------------
ob_start();
if (!$connected) {
    echo '<p class="text-muted">' . $addon->i18n('matomo_setup_needs_connection') . '</p>';
} else {
    echo '<p>' . $addon->i18n('matomo_setup_sites_count', count($sites)) . '</p>';
    if ([] !== $sites) {
        echo '<ul class="list-inline">';
        foreach ($sites as $site) {
            echo '<li><span class="label label-default">' . (int) $site['idsite'] . '</span> ' . rex_escape((string) $site['name']) . ' <small class="text-muted">' . rex_escape((string) ($site['main_url'] ?? '')) . '</small></li>';
        }
        echo '</ul>';
    }
    echo '<a class="btn btn-default btn-sm" href="' . rex_url::backendPage('matomo/domains') . '"><i class="fa fa-sitemap"></i> ' . $addon->i18n('matomo_setup_manage_sites') . '</a>';
}
echo $step(3, $addon->i18n('matomo_setup_step_sites'), $connected && [] !== $sites, (string) ob_get_clean());

// ---------------------------------------------------------------------------
// Schritt 4: Consent-Tool
// ---------------------------------------------------------------------------
ob_start();
$consent_done = false;
if ([] === $consent_tools) {
    echo '<p class="text-muted">' . $addon->i18n('matomo_setup_consent_none') . '</p>';
} elseif (!$connected || [] === $sites) {
    echo '<p class="text-muted">' . $addon->i18n('matomo_setup_needs_sites') . '</p>';
} else {
    echo '<p>' . $addon->i18n('matomo_setup_consent_intro') . '</p>';
    foreach ($consent_tools as $tool) {
        $registered = ConsentRegistration::isRegistered($tool);
        $consent_done = $consent_done || $registered;
        ?>
        <form method="post" class="form-inline" style="margin-bottom:10px">
            <?= $hidden ?>
            <input type="hidden" name="setup_action" value="consent">
            <input type="hidden" name="consent_tool" value="<?= rex_escape($tool) ?>">
            <strong style="display:inline-block;min-width:150px"><?= rex_escape($tool) ?></strong>
            <?= $registered ? '<span class="label label-success">' . $addon->i18n('matomo_setup_consent_registered_label') . '</span>' : '<span class="label label-default">' . $addon->i18n('matomo_setup_consent_missing_label') . '</span>' ?>
            <select name="consent_site_id" class="form-control input-sm">
                <?php foreach ($sites as $site): ?>
                    <option value="<?= (int) $site['idsite'] ?>"><?= rex_escape((string) $site['name']) ?> (ID <?= (int) $site['idsite'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-<?= $registered ? 'default' : 'primary' ?> btn-sm"><i class="fa fa-<?= $registered ? 'sync' : 'plus' ?>"></i> <?= $addon->i18n($registered ? 'matomo_setup_consent_update' : 'matomo_setup_consent_register') ?></button>
        </form>
        <?php
    }
    echo '<p class="help-block">' . $addon->i18n('matomo_setup_consent_help') . '</p>';
}
echo $step(4, $addon->i18n('matomo_setup_step_consent'), $consent_done, (string) ob_get_clean());

// ---------------------------------------------------------------------------
// Schritt 5: Persönliche Zugänge
// ---------------------------------------------------------------------------
ob_start();
if (!$connected) {
    echo '<p class="text-muted">' . $addon->i18n('matomo_setup_needs_connection') . '</p>';
} elseif (!$superuser) {
    echo '<p class="text-warning">' . $addon->i18n('matomo_setup_needs_superuser') . '</p>';
} else {
    echo '<p>' . $addon->i18n('matomo_setup_access_intro') . '</p>';
    ?>
    <?php
    $siteSelect = static function (array $selected) use ($sites, $addon): string {
        $html = '<select name="site_ids[]" class="selectpicker" multiple data-width="240px" data-size="8" data-actions-box="true" data-live-search="' . (count($sites) > 8 ? 'true' : 'false') . '" data-selected-text-format="count > 2" data-count-selected-text="{0} ' . rex_escape($addon->i18n('matomo_setup_websites')) . '" data-select-all-text="' . rex_escape($addon->i18n('matomo_setup_access_sites_select_all')) . '" data-deselect-all-text="' . rex_escape($addon->i18n('matomo_setup_access_sites_deselect_all')) . '" data-none-selected-text="' . rex_escape($addon->i18n('matomo_setup_access_sites_all')) . '" title="' . rex_escape($addon->i18n('matomo_setup_access_sites_all')) . '">';
        foreach ($sites as $site) {
            $id = (int) $site['idsite'];
            $host = (string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST);
            $html .= '<option value="' . $id . '"' . (in_array($id, $selected, true) ? ' selected' : '') . '>' . rex_escape('' !== $host ? $host : (string) $site['name']) . ' (ID ' . $id . ')</option>';
        }
        return $html . '</select>';
    };
    ?>
    <table class="table table-condensed table-hover">
        <thead><tr>
            <th><?= $addon->i18n('matomo_setup_access_redaxo_user') ?></th>
            <th><?= $addon->i18n('matomo_setup_access_matomo_user') ?></th>
            <th class="text-right"><?= $addon->i18n('matomo_setup_access_sites') ?>
                <?php if ($access_missing > 0): ?>
                <form method="post" style="display:inline"><?= $hidden ?><input type="hidden" name="setup_action" value="access_create_all"><button type="submit" class="btn btn-primary btn-xs"><i class="fa fa-users"></i> <?= $addon->i18n('matomo_setup_access_create_all', $access_missing) ?></button></form>
                <?php endif; ?>
            </th>
        </tr></thead>
        <tbody>
        <?php foreach ($redaxo_users as $ru): $entry = $access_entries[$ru['id']] ?? null; ?>
            <tr>
                <td><strong><?= rex_escape($ru['login']) ?></strong> <small class="text-muted"><?= rex_escape($ru['name']) ?></small><?= $ru['admin'] ? ' <span class="label label-default">Admin</span>' : '' ?><?= $ru['has_email'] ? '<br><small class="text-muted">' . rex_escape($ru['email']) . '</small>' : '<br><small class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' . rex_escape($addon->i18n('matomo_setup_access_email_missing')) . '</small>' ?></td>
                <td><?= null !== $entry ? '<i class="fa fa-check text-success"></i> ' . rex_escape($entry['login']) . ' <small class="text-muted">' . rex_escape($entry['created']) . '</small>' : '<span class="text-muted">–</span>' ?></td>
                <td class="text-right">
                    <form method="post" class="form-inline">
                    <?= $hidden ?>
                    <input type="hidden" name="user_id" value="<?= $ru['id'] ?>">
                    <?= $siteSelect(null !== $entry ? $entry['sites'] : []) ?>
                    <?php if (null !== $entry): ?>
                        <button type="submit" name="setup_action" value="access_sites" class="btn btn-default btn-xs"><i class="fa fa-save"></i> <?= $addon->i18n('matomo_setup_access_sites_save') ?></button>
                        <button type="submit" name="setup_action" value="access_remove" class="btn btn-default btn-xs" onclick="return confirm('<?= rex_escape($addon->i18n('matomo_setup_access_remove_confirm', $entry['login']), 'js') ?>')"><i class="fa fa-times"></i> <?= $addon->i18n('matomo_setup_access_remove') ?></button>
                    <?php elseif ($ru['has_email']): ?>
                        <button type="submit" name="setup_action" value="access_create" class="btn btn-primary btn-xs"><i class="fa fa-user-plus"></i> <?= $addon->i18n('matomo_setup_access_create') ?></button>
                    <?php else: ?>
                        <button type="button" class="btn btn-default btn-xs" disabled title="<?= rex_escape($addon->i18n('matomo_setup_access_email_missing')) ?>"><i class="fa fa-user-plus"></i> <?= $addon->i18n('matomo_setup_access_create') ?></button>
                    <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="help-block"><?= $addon->i18n('matomo_setup_access_sites_help') ?></p>
    <p class="help-block"><?= $addon->i18n('matomo_setup_access_help') ?></p>

    <h4><i class="fa fa-sign-in-alt"></i> <?= $addon->i18n('matomo_autologin_headline') ?></h4>
    <p class="help-block"><?= $addon->i18n('matomo_autologin_intro') ?></p>
    <?php if (AutoLogin::isEnabled()): ?>
        <p class="text-success"><i class="fa fa-check-circle"></i> <?= $addon->i18n('matomo_autologin_state_on') ?></p>
    <?php else: ?>
        <p class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= $addon->i18n('matomo_autologin_state_off') ?></p>
    <?php endif; ?>
    <?php if ($is_local && $config_written): ?>
        <?php if (!AutoLogin::isEnabled()): ?>
        <form method="post" style="display:inline"><?= $hidden ?><input type="hidden" name="setup_action" value="autologin_enable"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-magic"></i> <?= $addon->i18n('matomo_autologin_enable') ?></button></form>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" class="form-inline"><?= $hidden ?><input type="hidden" name="setup_action" value="autologin_external">
            <div class="checkbox"><label><input type="checkbox" name="autologin_confirmed" value="1" <?= AutoLogin::isEnabled() ? 'checked' : '' ?>> <?= $addon->i18n('matomo_autologin_external_confirm') ?></label></div>
            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-save"></i> <?= $addon->i18n('matomo_save') ?></button>
        </form>
        <pre style="margin-top:8px">[General]
login_allow_logme = 1</pre>
    <?php endif; ?>
    <?php
}
echo $step(5, $addon->i18n('matomo_setup_step_access'), [] !== $access_entries, (string) ob_get_clean());
?>
</div>

<div class="col-md-4">
    <div class="panel panel-<?= $connected ? 'success' : 'warning' ?>">
        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-chart-bar"></i> <?= $addon->i18n('matomo_status_title') ?></h3></div>
        <div class="panel-body">
            <table class="table table-condensed" style="margin-bottom:0">
                <tr><td><?= $addon->i18n('matomo_installation_status') ?></td><td><?= $installed ? ($is_local ? '<code>' . rex_escape($matomo_path) . '</code>' : $addon->i18n('matomo_setup_external')) : '<span class="text-danger">' . $addon->i18n('matomo_not_found') . '</span>' ?></td></tr>
                <tr><td><?= $addon->i18n('matomo_api_status') ?></td><td><?= $connected ? '<span class="text-success">' . $addon->i18n('matomo_connected') . '</span>' : ('' !== $api_error ? '<span class="text-danger">' . rex_escape($api_error) . '</span>' : $addon->i18n('matomo_not_tested')) ?></td></tr>
                <tr><td><?= $addon->i18n('matomo_setup_superuser') ?></td><td><?= $connected ? ($superuser ? '<span class="text-success">' . $addon->i18n('matomo_yes') . '</span>' : '<span class="text-warning">' . $addon->i18n('matomo_setup_superuser_no') . '</span>') : '–' ?></td></tr>
                <tr><td><?= $addon->i18n('matomo_setup_websites') ?></td><td><?= $connected ? count($sites) : '–' ?></td></tr>
                <tr><td><?= $addon->i18n('matomo_setup_step_access') ?></td><td><?= count($access_entries) ?> / <?= count($redaxo_users) ?></td></tr>
            </table>
            <?php if ('' !== $matomo_url): ?>
                <p style="margin-top:10px"><a href="<?= rex_escape(UserAccess::openUrl()) ?>" target="_blank" class="btn btn-primary btn-sm"><i class="fa fa-external-link-alt"></i> <?= $addon->i18n('matomo_open_matomo') ?></a></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel panel-info">
        <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-info-circle"></i> <?= $addon->i18n('matomo_help_first_steps') ?></h3></div>
        <div class="panel-body">
            <ol style="padding-left:18px">
                <li><?= $addon->i18n('matomo_setup_help_1') ?></li>
                <li><?= $addon->i18n('matomo_setup_help_2') ?></li>
                <li><?= $addon->i18n('matomo_setup_help_3') ?></li>
                <li><?= $addon->i18n('matomo_setup_help_4') ?></li>
                <li><?= $addon->i18n('matomo_setup_help_5') ?></li>
            </ol>
            <p class="help-block"><?= rex_i18n::rawMsg('matomo_setup_help_config', rex_url::backendPage('matomo/config')) ?></p>
        </div>
    </div>
</div>
</div>
