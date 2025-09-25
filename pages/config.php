<?php
use FriendsOfRedaxo\Matomo\MatomoApi;
/**
 * Erweiterte Konfigurationsseite mit rex_config_form
 */

$addon = rex_addon::get('matomo');

// Konfigurationsformular erstellen
$form = rex_config_form::factory('matomo');

// Matomo Installation Sektion
$form->addFieldset($addon->i18n('matomo_installation_section'));

$field = $form->addTextField('matomo_url');
$field->setLabel($addon->i18n('matomo_url'));
$field->setNotice($addon->i18n('matomo_url_help'));
$field->getValidator()->add('url', 'Bitte eine gültige URL eingeben');

$field = $form->addTextField('matomo_path');
$field->setLabel($addon->i18n('matomo_path'));
$field->setNotice($addon->i18n('matomo_path_help'));

$field = $form->addTextField('admin_token');
$field->setLabel($addon->i18n('matomo_admin_token'));
$field->setNotice($addon->i18n('matomo_admin_token_help'));
$field->setAttribute('type', 'password');

$field = $form->addTextField('user_token');
$field->setLabel($addon->i18n('matomo_user_token'));
$field->setNotice($addon->i18n('matomo_user_token_help'));
$field->setAttribute('type', 'password');

// API Einstellungen Sektion
$form->addFieldset($addon->i18n('matomo_api_section'));

$field = $form->addSelectField('api_timeout');
$field->setLabel($addon->i18n('matomo_api_timeout'));
$field->setNotice($addon->i18n('matomo_api_timeout_help'));
$select = $field->getSelect();
$select->addOptions([
    '10' => $addon->i18n('matomo_timeout_10s'),
    '30' => $addon->i18n('matomo_timeout_30s'), 
    '60' => $addon->i18n('matomo_timeout_60s'),
    '120' => $addon->i18n('matomo_timeout_120s')
], true);

$field = $form->addCheckboxField('ssl_verify');
$field->setLabel($addon->i18n('matomo_ssl_verify'));
$field->addOption($addon->i18n('matomo_ssl_verify_option'), '1');
$field->setNotice($addon->i18n('matomo_ssl_verify_help'));

// Dashboard Einstellungen
$form->addFieldset($addon->i18n('matomo_dashboard_section'));

// Dashboard Domain dynamisch laden
$dashboard_options = ['0' => $addon->i18n('matomo_dashboard_all_sites')];
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');

if ($matomo_url && $admin_token) {
    try {
        $api = new MatomoApi($matomo_url, $admin_token, $user_token);
        $sites = $api->getSites();
        foreach ($sites as $site) {
            $dashboard_options[$site['idsite']] = $site['name'] . ' (' . $site['main_url'] . ')';
        }
    } catch (Exception $e) {
        // Fehler ignorieren, zeige nur Standardoption
    }
}

$field = $form->addSelectField('dashboard_site');
$field->setLabel($addon->i18n('matomo_dashboard_site'));
$field->setNotice($addon->i18n('matomo_dashboard_site_help'));
$select = $field->getSelect();
$select->addOptions($dashboard_options, true);

// Tracking Optionen Sektion
$form->addFieldset($addon->i18n('matomo_tracking_options'));

$field = $form->addCheckboxField('anonymize_ip');
$field->setLabel($addon->i18n('matomo_anonymize_ip'));
$field->addOption($addon->i18n('matomo_anonymize_ip_option'), '1');
$field->setNotice($addon->i18n('matomo_anonymize_ip_help'));

$field = $form->addCheckboxField('cookieless_tracking');
$field->setLabel($addon->i18n('matomo_cookieless'));
$field->addOption($addon->i18n('matomo_cookieless_option'), '1');
$field->setNotice($addon->i18n('matomo_cookieless_help'));

// Datenschutz Sektion
$form->addFieldset($addon->i18n('matomo_privacy_section'));

$field = $form->addCheckboxField('respect_dnt');
$field->setLabel($addon->i18n('matomo_respect_dnt'));
$field->addOption($addon->i18n('matomo_respect_dnt_option'), '1');
$field->setNotice($addon->i18n('matomo_respect_dnt_help'));

$field = $form->addSelectField('cookie_lifetime');
$field->setLabel($addon->i18n('matomo_cookie_lifetime'));
$field->setNotice($addon->i18n('matomo_cookie_lifetime_help'));
$select = $field->getSelect();
$select->addOptions([
    '1800' => $addon->i18n('matomo_cookie_30min'),
    '3600' => $addon->i18n('matomo_cookie_1hour'),
    '86400' => $addon->i18n('matomo_cookie_1day'),
    '604800' => $addon->i18n('matomo_cookie_1week'),
    '2592000' => $addon->i18n('matomo_cookie_1month'),
    '31536000' => $addon->i18n('matomo_cookie_1year')
], true);

// Formular anzeigen
$content = $form->get();

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', 'Matomo Konfiguration');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Status-Panel
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');

$matomo_ready = false;
$api_status = 'Nicht getestet';

if ($matomo_url && $admin_token && $matomo_path) {
    $full_path = rex_path::frontend($matomo_path . '/');
    $matomo_ready = file_exists($full_path . 'index.php');
    
    if ($matomo_ready) {
        try {
            $api = new MatomoApi($matomo_url, $admin_token, $user_token);
            $sites = $api->getSites();
            $api_status = '✅ Verbunden (' . count($sites) . ' Sites)';
        } catch (Exception $e) {
            $api_status = '❌ Fehler: ' . $e->getMessage();
        }
    }
}

?>

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
                            <?= $matomo_ready ? '✅ Gefunden' : '❌ Nicht gefunden' ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>API Status:</strong></td>
                        <td><?= $api_status ?></td>
                    </tr>
                    <?php if ($matomo_path): ?>
                    <tr>
                        <td><strong>Pfad:</strong></td>
                        <td><code><?= rex_escape($matomo_path) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($matomo_url): ?>
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