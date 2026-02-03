<?php

use FriendsOfRedaxo\Matomo\MatomoApi;
use FriendsOfRedaxo\Matomo\YRewriteHelper;

$addon = rex_addon::get('matomo');

// Prüfen ob Matomo konfiguriert ist
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');

$matomo_ready = false;
$is_external_matomo = false;

if ($matomo_url && $admin_token) {
    if ($matomo_path) {
        // Lokale Matomo-Installation - prüfe ob verfügbar
        $full_path = rex_path::frontend($matomo_path . '/');
        $matomo_ready = file_exists($full_path . 'index.php');
    } else {
        // Externe Matomo-Installation - keine lokale Verfügbarkeitsprüfung möglich
        $matomo_ready = true;
        $is_external_matomo = true;
    }
}

$message = '';
$error = '';

// Domain hinzufügen
if (rex_post('add_domain', 'boolean') && $matomo_ready) {
    $domain_name = rex_post('domain_name', 'string', '');
    $domain_url = rex_post('domain_url', 'string', '');

    if ($domain_name && $domain_url) {
        try {
            $api = new MatomoApi($matomo_url, $admin_token, $user_token);
            $site_id = $api->addSite($domain_name, $domain_url);
            
            if ($site_id) {
                $message = $addon->i18n('matomo_domain_added', $domain_name, $site_id);
            } else {
                $error = $addon->i18n('matomo_domain_add_failed');
            }
        } catch (Exception $e) {
            $error = $addon->i18n('matomo_domain_add_error', $e->getMessage());
        }
    } else {
        $error = $addon->i18n('matomo_fill_all_fields');
    }
}

// YRewrite Domain Import
if (rex_post('import_yrewrite', 'boolean') && $matomo_ready && !empty(rex_post('import_domains', 'array'))) {
    $import_domains = rex_post('import_domains', 'array');
    
    if (class_exists('FriendsOfRedaxo\Matomo\YRewriteHelper') && YRewriteHelper::isAvailable()) {
        $yrewrite_domains = YRewriteHelper::getAvailableDomains();
        $imported_count = 0;
        $skipped_count = 0;
        $import_errors = [];
        
        try {
            $api = new MatomoApi($matomo_url, $admin_token, $user_token);
            
            // Erst alle existierenden Matomo-Sites laden um Duplikate zu vermeiden
            $existing_sites = $api->getSites();
            $existing_urls = [];
            foreach ($existing_sites as $site) {
                $existing_urls[] = rtrim($site['main_url'], '/');
            }
            
            foreach ($import_domains as $domain_name) {
                if (isset($yrewrite_domains[$domain_name])) {
                    $domain = $yrewrite_domains[$domain_name];
                    $domain_url = rtrim($domain['url'], '/');
                    
                    // Prüfen ob Domain bereits existiert
                    if (in_array($domain_url, $existing_urls)) {
                        $skipped_count++;
                        continue;
                    }
                    
                    try {
                        $site_id = $api->addSite($domain['title'] ?: $domain['name'], $domain['url']);
                        if ($site_id) {
                            $imported_count++;
                        } else {
                            $import_errors[] = $addon->i18n('matomo_domain_import_error', $domain_name);
                        }
                    } catch (Exception $e) {
                        $import_errors[] = $addon->i18n('matomo_domain_import_error_details', $domain_name, $e->getMessage());
                    }
                }
            }
            
            // Erfolgsmeldung zusammenstellen
            $success_parts = [];
            if ($imported_count > 0) {
                $success_parts[] = $addon->i18n('matomo_domain_import_success', $imported_count);
            }
            if ($skipped_count > 0) {
                $success_parts[] = $addon->i18n('matomo_domain_import_skipped', $skipped_count);
            }
            
            if (!empty($success_parts)) {
                $message = implode(', ', $success_parts) . '.';
            }
            
            if (!empty($import_errors)) {
                $error = implode('<br>', $import_errors);
            }
            
        } catch (Exception $e) {
            $error = $addon->i18n('matomo_domain_import_general_error', $e->getMessage());
        }
    } else {
        $error = $addon->i18n('matomo_yrewrite_not_available');
    }
}

// Domain löschen
if (rex_post('delete_domain', 'boolean') && $matomo_ready) {
    $site_id = rex_post('site_id', 'int', 0);
    $site_name = rex_post('site_name', 'string', '');
    
    if ($site_id > 0) {
        try {
            $api = new MatomoApi($matomo_url, $admin_token, $user_token);
            $success = $api->deleteSite($site_id);
            
            if ($success) {
                $message = $addon->i18n('matomo_domain_delete_success', rex_escape($site_name), $site_id);
            } else {
                $error = $addon->i18n('matomo_domain_delete_error', rex_escape($site_name));
            }
        } catch (Exception $e) {
            $error = $addon->i18n('matomo_domain_delete_error_details', $e->getMessage());
        }
    } else {
        $error = $addon->i18n('matomo_domain_delete_invalid_id');
    }
}

// Nachrichten anzeigen
if ($message) {
    echo rex_view::success($message);
}
if ($error) {
    echo rex_view::error($error);
}

if (!$matomo_ready) {
    echo html_entity_decode(rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings']))));
    return;
}

// Domains laden
$sites = [];
$tracking_codes = [];

try {
    $api = new MatomoApi($matomo_url, $admin_token);
    $sites = $api->getSites();
    
    // Tracking Codes für alle Sites laden
    foreach ($sites as $site) {
        $tracking_codes[$site['idsite']] = $api->getTrackingCode($site['idsite']);
    }
} catch (Exception $e) {
    echo rex_view::error($addon->i18n('matomo_sites_load_error', $e->getMessage()));
}

?>

<div class="row">
    <div class="col-sm-8">
        
        <!-- Domain hinzufügen -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">➕ Neue Domain hinzufügen</h3>
            </div>
            <div class="panel-body">
                                <form method="post" class="rex-form">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="rex-form-group">
                                <label class="control-label" for="domain_name"><?= $addon->i18n('matomo_domain_name') ?>:</label>
                                <div class="rex-form-control">
                                    <input type="text" class="form-control" id="domain_name" name="domain_name" 
                                           placeholder="Meine Website" required>
                                    <p class="help-block small"><?= $addon->i18n('matomo_domain_name_help') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="rex-form-group">
                                <label class="control-label" for="domain_url">Domain URL:</label>
                                <div class="rex-form-control">
                                    <input type="url" class="form-control" id="domain_url" name="domain_url" 
                                           placeholder="https://meine-domain.de" required>
                                    <p class="help-block small">Vollständige URL mit http:// oder https://</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rex-form-group">
                        <div class="rex-form-control">
                            <button type="submit" name="add_domain" value="1" class="btn btn-primary">
                                <i class="rex-icon rex-icon-add"></i> Domain hinzufügen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- YRewrite Domains Import -->
        <?php if (class_exists('FriendsOfRedaxo\Matomo\YRewriteHelper') && YRewriteHelper::isAvailable()): ?>
            <?php 
            $yrewrite_domains = YRewriteHelper::getAvailableDomains();
            if (!empty($yrewrite_domains)):
                // Bereits vorhandene URLs sammeln um Duplikate zu markieren
                $existing_urls = [];
                foreach ($sites as $site) {
                    $existing_urls[] = rtrim($site['main_url'], '/');
                }
            ?>
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-download"></i> <?= $addon->i18n('matomo_yrewrite_import_title') ?>
                        <small class="text-muted">(<?= count($yrewrite_domains) ?> <?= $addon->i18n('matomo_yrewrite_domains_available') ?>)</small>
                    </h3>
                </div>
                <div class="panel-body">
                    <p class="help-block">
                        <i class="fa fa-info-circle"></i> 
                        <?= $addon->i18n('matomo_yrewrite_import_description') ?>
                    </p>
                    
                    <form method="post">
                        <div class="form-group">
                            <?php foreach ($yrewrite_domains as $domain): 
                                $domain_url = rtrim($domain['url'], '/');
                                $already_exists = in_array($domain_url, $existing_urls);
                            ?>
                                <div class="checkbox <?= $already_exists ? 'text-muted' : '' ?>">
                                    <label>
                                        <input type="checkbox" 
                                               name="import_domains[]" 
                                               value="<?= rex_escape($domain['name']) ?>"
                                               <?= $already_exists ? 'disabled' : '' ?>>
                                        <strong><?= rex_escape($domain['name']) ?></strong>
                                        <?php if ($already_exists): ?>
                                            <span class="label label-warning"><?= $addon->i18n('matomo_yrewrite_already_exists') ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fa fa-link"></i> <?= rex_escape($domain['url']) ?>
                                            <?php if ($domain['title'] && $domain['title'] !== $domain['name']): ?>
                                                <br><i class="fa fa-tag"></i> <?= rex_escape($domain['title']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" name="import_yrewrite" value="1" class="btn btn-success">
                            <i class="fa fa-download"></i> <?= $addon->i18n('matomo_yrewrite_import_button') ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Domain Liste -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">🌐 Vorhandene Domains (<?= count($sites) ?>)</h3>
            </div>
            <div class="panel-body">
                <?php if (empty($sites)): ?>
                    <p class="text-muted">Noch keine Domains konfiguriert.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>URL</th>
                                    <th>Erstellt</th>
                                    <th><?= $addon->i18n('matomo_table_tracking') ?></th>
                                    <th><?= $addon->i18n('matomo_domain_actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td><strong><?= rex_escape($site['idsite']) ?></strong></td>
                                    <td><?= rex_escape($site['name']) ?></td>
                                    <td>
                                        <a href="<?= rex_escape($site['main_url']) ?>" target="_blank">
                                            <?= rex_escape($site['main_url']) ?>
                                        </a>
                                    </td>
                                    <td><?= date('d.m.Y H:i', strtotime($site['ts_created'])) ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" onclick="showTrackingCode(<?= $site['idsite'] ?>)">
                                            📋 Code anzeigen
                                        </button>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="confirmDeleteDomain(<?= $site['idsite'] ?>, '<?= rex_escape($site['name']) ?>')">
                                            <i class="fa fa-trash"></i> <?= $addon->i18n('matomo_domain_delete_button') ?>
                                        </button>
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
    
    <div class="col-sm-4">
        
        <!-- Status -->
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">📊 Matomo Status</h3>
            </div>
            <div class="panel-body">
                <p><strong>Matomo URL:</strong><br>
                <a href="<?= rex_escape($matomo_url) ?>" target="_blank" class="btn btn-primary btn-sm">
                    🔗 Matomo öffnen
                </a></p>
                
                <p><strong>API Status:</strong><br>
                <span class="text-success">✅ Verbunden</span></p>
                
                <p><strong>Domains:</strong><br>
                <span class="badge badge-info"><?= count($sites) ?></span></p>
            </div>
        </div>
        
        <!-- Consent Manager Empfehlung -->
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title">⚠️ <?= $addon->i18n('matomo_consent_title') ?></h3>
            </div>
            <div class="panel-body">
                <p><?= $addon->i18n('matomo_consent_recommendation') ?></p>
                <p><strong><?= $addon->i18n('matomo_consent_addon_recommendation') ?>:</strong></p>
                <ul>
                    <li><strong>Consent Manager</strong> - <?= $addon->i18n('matomo_consent_manager_desc') ?></li>
                </ul>
                <div class="alert alert-info">
                    <strong><?= $addon->i18n('matomo_consent_important') ?>:</strong> <?= $addon->i18n('matomo_consent_manual_integration') ?>
                </div>
            </div>
        </div>

        <!-- Tracking Code Anzeige -->
        <div class="panel panel-info" id="tracking-panel" style="display: none;">
            <div class="panel-heading">
                <h3 class="panel-title">📋 <?= $addon->i18n('matomo_tracking_code_title') ?></h3>
            </div>
            <div class="panel-body">
                <p><strong><?= $addon->i18n('matomo_tracking_site_id') ?>:</strong> <span id="current-site-id"></span></p>
                <div class="form-group">
                    <label><?= $addon->i18n('matomo_tracking_code_label') ?>:</label>
                    <textarea id="tracking-code-text" class="form-control" rows="12" readonly onclick="this.select()"></textarea>
                </div>
                <div class="alert alert-info">
                    <small>
                        <strong><?= $addon->i18n('matomo_tracking_note') ?>:</strong> 
                        <?= $addon->i18n('matomo_tracking_privacy_note') ?>
                    </small>
                </div>
                <button class="btn btn-success btn-sm" onclick="copyTrackingCode()">
                    📋 <?= $addon->i18n('matomo_copy_code') ?>
                </button>
                <button class="btn btn-default btn-sm" onclick="hideTrackingCode()">
                    ❌ <?= $addon->i18n('matomo_close') ?>
                </button>
            </div>
        </div>
        
    </div>
</div>

<!-- Verstecktes Form für Domain-Löschung -->
<form id="delete-form" method="post" style="display: none;">
    <input type="hidden" name="delete_domain" value="1">
    <input type="hidden" name="site_id" id="delete-site-id">
    <input type="hidden" name="site_name" id="delete-site-name">
</form>

<!-- JavaScript für Tracking Code -->
<script>
var trackingCodes = <?= json_encode($tracking_codes) ?>;

function showTrackingCode(siteId) {
    document.getElementById('current-site-id').textContent = siteId;
    document.getElementById('tracking-code-text').value = trackingCodes[siteId] || '<?= $addon->i18n('matomo_tracking_code_not_found') ?>';
    document.getElementById('tracking-panel').style.display = 'block';
    
    // Scroll to panel
    document.getElementById('tracking-panel').scrollIntoView({ behavior: 'smooth' });
}

function hideTrackingCode() {
    document.getElementById('tracking-panel').style.display = 'none';
}

function copyTrackingCode() {
    var textarea = document.getElementById('tracking-code-text');
    textarea.select();
    document.execCommand('copy');
    
    // Feedback
    var btn = event.target;
    var originalText = btn.textContent;
    btn.textContent = '✅ Kopiert!';
    btn.className = btn.className.replace('btn-success', 'btn-info');
    
    setTimeout(function() {
        btn.textContent = originalText;
        btn.className = btn.className.replace('btn-info', 'btn-success');
    }, 2000);
}

function confirmDeleteDomain(siteId, siteName) {
    var message = '<?= $addon->i18n('matomo_delete_confirm_message', '', '') ?>'.replace('{0}', siteName).replace('{1}', siteId) + '\n\n';
    message += '<?= $addon->i18n('matomo_delete_confirm_warning') ?>\n';
    message += '<?= $addon->i18n('matomo_delete_confirm_data_loss') ?>';
    
    if (confirm(message)) {
        // Formular-Werte setzen und absenden
        document.getElementById('delete-site-id').value = siteId;
        document.getElementById('delete-site-name').value = siteName;
        document.getElementById('delete-form').submit();
    }
}
</script>
