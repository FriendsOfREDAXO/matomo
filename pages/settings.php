<?php
$addon = rex_addon::get('matomo');

// Handle domain operations
$action = rex_request('domain_action', 'string', '');
$domain_key = rex_request('domain_key', 'string', '');

if ($action === 'add_domain' && rex_request_method() === 'POST') {
    $csrf_token = rex_csrf_token::factory('domain_form');
    if (!$csrf_token->isValid()) {
        echo '<div class="alert alert-danger">CSRF token mismatch!</div>';
    } else {
        $domain_name = rex_request('domain_name', 'string', '');
        $domain_url = rex_request('domain_url', 'string', '');
        $domain_id = rex_request('domain_id', 'string', '');
        $domain_token = rex_request('domain_token', 'string', '');
        $domain_user = rex_request('domain_user', 'string', '');
        $domain_password = rex_request('domain_password', 'string', '');
        
        if ($domain_name && $domain_url && $domain_id && $domain_token && $domain_user && $domain_password) {
            $domains = $addon->getConfig('domains', []);
            $domains[$domain_name] = [
                'url' => $domain_url,
                'id' => $domain_id,
                'token' => $domain_token,
                'user' => $domain_user,
                'password' => $domain_password
            ];
            $addon->setConfig('domains', $domains);
            echo '<div class="alert alert-success">Domain "' . rex_escape($domain_name) . '" hinzugefügt.</div>';
        } else {
            echo '<div class="alert alert-danger">Alle Felder sind erforderlich.</div>';
        }
    }
}

if ($action === 'delete_domain' && $domain_key) {
    $domains = $addon->getConfig('domains', []);
    if (isset($domains[$domain_key])) {
        unset($domains[$domain_key]);
        $addon->setConfig('domains', $domains);
        echo '<div class="alert alert-success">Domain "' . rex_escape($domain_key) . '" gelöscht.</div>';
    }
}

// Tracking code settings form
$form = rex_config_form::factory($addon->name);
$field = $form->addSelectField('tracking_setup', null, ['class' => 'form-control selectpicker']); // die Klasse selectpicker aktiviert den Selectpicker von Bootstrap
$field->setAttribute('multiple', 'multiple');
$field->setLabel($addon->i18n('matomo_track_setup'));
$select = $field->getSelect();
$select->setSize(3);
$select->addOption($addon->i18n('matomo_track_mergeSubdomains'), '&mergeSubdomains=true');
$select->addOption($addon->i18n('matomo_track_groupPageTitlesByDomain'), '&groupPageTitlesByDomain=true');
$select->addOption($addon->i18n('matomo_track_DonotTrack'), '&doNotTrack=true');
$select->addOption($addon->i18n('matomo_track_disableCookies'), '&disableCookies=true');
$select->addOption($addon->i18n('matomo_track_mergeAliasUrls'), '&mergeAliasUrls=true');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('matomo_track_setup'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');

// Multidomain management section
$domains = $addon->getConfig('domains', []);

$domain_content = '<h3>' . $addon->i18n('matomo_domains') . '</h3>';

// Domain list
if (!empty($domains)) {
    $domain_content .= '<div class="table-responsive"><table class="table table-striped">';
    $domain_content .= '<thead><tr><th>' . $addon->i18n('matomo_domain_name') . '</th><th>URL</th><th>Site-ID</th><th>Actions</th></tr></thead><tbody>';
    
    foreach ($domains as $domain_name => $domain_config) {
        $domain_content .= '<tr>';
        $domain_content .= '<td>' . rex_escape($domain_name) . '</td>';
        $domain_content .= '<td>' . rex_escape($domain_config['url']) . '</td>';
        $domain_content .= '<td>' . rex_escape($domain_config['id']) . '</td>';
        $domain_content .= '<td><a href="' . rex_url::currentBackendPage(['domain_action' => 'delete_domain', 'domain_key' => $domain_name]) . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Domain löschen?\');">' . $addon->i18n('matomo_delete_domain') . '</a></td>';
        $domain_content .= '</tr>';
    }
    $domain_content .= '</tbody></table></div>';
} else {
    $domain_content .= '<p>' . $addon->i18n('matomo_no_domains_configured') . '</p>';
}

// Add domain form
$domain_content .= '<h4>' . $addon->i18n('matomo_add_domain') . '</h4>';
$domain_content .= '<form method="post" action="' . rex_url::currentBackendPage() . '">';
$domain_content .= '<input type="hidden" name="domain_action" value="add_domain">';
$domain_content .= rex_csrf_token::factory('domain_form')->getHiddenField();
$domain_content .= '<div class="row">';
$domain_content .= '<div class="col-md-6">';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_domain_name') . '</label>';
$domain_content .= '<input type="text" name="domain_name" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_url') . '</label>';
$domain_content .= '<input type="text" name="domain_url" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_id') . '</label>';
$domain_content .= '<input type="text" name="domain_id" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '</div>';
$domain_content .= '<div class="col-md-6">';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_token') . '</label>';
$domain_content .= '<input type="text" name="domain_token" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_user') . '</label>';
$domain_content .= '<input type="text" name="domain_user" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '<div class="form-group">';
$domain_content .= '<label>' . $addon->i18n('matomo_password') . '</label>';
$domain_content .= '<input type="password" name="domain_password" class="form-control" required>';
$domain_content .= '</div>';
$domain_content .= '</div>';
$domain_content .= '</div>';
$domain_content .= '<div class="form-group">';
$domain_content .= '<button type="submit" class="btn btn-primary">' . $addon->i18n('matomo_add_domain') . '</button>';
$domain_content .= '</div>';
$domain_content .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('matomo_domains'), false);
$fragment->setVar('body', $domain_content, false);
echo $fragment->parse('core/page/section.php');
// Tracking code generation for multidomain configurations
$tracking_code_extra = array_filter(explode("|", $addon->getConfig('tracking_setup')), 'strlen');
$url_extra = '';
if (count($tracking_code_extra) > 0) {
    foreach ($tracking_code_extra as $value) {
        $url_extra.= $value;
    }
}
$domains = $addon->getConfig('domains', []);
if (!empty($domains)) {
    $tracking_codes = [];
    
    foreach ($domains as $domain_name => $domain_config) {
        if (!empty($domain_config['url']) && !empty($domain_config['id']) && !empty($domain_config['token'])) {
            $url = rex_escape($domain_config['url']) . 'index.php?module=API&method=SitesManager.getJavascriptTag&idSite=' . rex_escape($domain_config['id']) . $url_extra . '&format=JSON&token_auth=' . $domain_config['token'];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = json_decode(curl_exec($ch), true);
            
            if (array_key_exists("value", $output) && strpos($output['value'], 'Matomo') !== false) {
                $tracking_codes[$domain_name] = $output['value'];
            }
        }
    }
    
    if (!empty($tracking_codes)) {
        $domain_tracking_content = '<h4>' . $addon->i18n('matomo_trackingcode_headline') . ' - ' . $addon->i18n('matomo_domains') . '</h4>';
        
        foreach ($tracking_codes as $domain_name => $tracking_code) {
            $domain_tracking_content .= '<h5>' . rex_escape($domain_name) . '</h5>';
            $domain_tracking_content .= '<div class="rex-form-group form-group">';
            $domain_tracking_content .= '<textarea class="form-control codemirror" height="80">' . $tracking_code . '</textarea>';
            $domain_tracking_content .= '</div>';
        }
        
        // Store all tracking codes for programmatic access
        $addon->setConfig('domain_tracking_codes', $tracking_codes);
        
        $fragment = new rex_fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $addon->i18n('matomo_trackingcode_headline') . ' - ' . $addon->i18n('matomo_domains'), false);
        $fragment->setVar('body', $domain_tracking_content, false);
        echo $fragment->parse('core/page/section.php');
    }
}
