<?php

$addon = rex_addon::get('matomo');

// Prüfen ob Matomo konfiguriert ist
$matomo_url = rex_config::get('matomo', 'matomo_url', '');
$admin_token = rex_config::get('matomo', 'admin_token', '');
$user_token = rex_config::get('matomo', 'user_token', '');
$matomo_path = rex_config::get('matomo', 'matomo_path', '');

$matomo_ready = false;
if ($matomo_url && $admin_token && $matomo_path) {
    $full_path = rex_path::frontend($matomo_path . '/');
    $matomo_ready = file_exists($full_path . 'index.php');
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

// Nachrichten anzeigen
if ($message) {
    echo rex_view::success($message);
}
if ($error) {
    echo rex_view::error($error);
}

if (!$matomo_ready) {
    echo rex_view::warning($addon->i18n('matomo_not_configured', rex_url::currentBackendPage(['page' => 'matomo/settings'])));
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
                                    <th>Tracking Code</th>
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
                    <li><strong>Cookie Notice</strong> - <?= $addon->i18n('matomo_cookie_notice_desc') ?></li>
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
</script>