<?php 
$addon = rex_addon::get('matomo');

// Get selected domain from request or use first available
$selected_domain = rex_request('domain', 'string', '');
$domains = $addon->getConfig('domains', []);

// Check if we have multidomain configuration
$has_domains = !empty($domains);
$has_legacy_config = ($addon->getConfig('token') != '' 
    && $addon->getConfig('user') != '' 
    && $addon->getConfig('password') != ''
    && $addon->getConfig('url') != '' 
    && $addon->getConfig('id') != ''
    && $addon->getConfig('matomocheck') == true);

if ($has_domains) {
    // Use multidomain configuration
    if (!$selected_domain || !isset($domains[$selected_domain])) {
        $selected_domain = array_keys($domains)[0]; // Use first domain as default
    }
    
    $current_config = $domains[$selected_domain];
    
    // Domain selector
    if (count($domains) > 1) {
        echo '<div class="alert alert-info">' . $addon->i18n('matomo_multidomain_info') . '</div>';
        echo '<div class="form-group">';
        echo '<label>' . $addon->i18n('matomo_select_domain') . ':</label>';
        echo '<select class="form-control" onchange="window.location.href=\'' . rex_url::currentBackendPage() . '&domain=\' + this.value;">';
        foreach ($domains as $domain_name => $domain_config) {
            $selected = ($domain_name === $selected_domain) ? 'selected' : '';
            echo '<option value="' . rex_escape($domain_name) . '" ' . $selected . '>' . rex_escape($domain_name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    
    $pass = md5($current_config['password']);
    ?>
    <a class="pull-right btn btn-primary" target="_blank" href="<?= $current_config['url']?>index.php?module=Login&action=logme&login=<?= $current_config['user']?>&password=<?=$pass?>"><?=$addon->i18n('matomo_link')?> (<?= rex_escape($selected_domain) ?>)</a>

    <iframe id="matomoframe" src="<?= $current_config['url']?>index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=<?= $current_config['id']?>&period=week&date=yesterday&token_auth=<?= $current_config['token']?>
    " frameborder="0" marginheight="0" marginwidth="0" width="100%" style="height: 160vh" onload="iFrameResize({ log: false }, '#matomoframe');"></iframe>
    
    <?php
} elseif ($has_legacy_config) {
    // Use legacy single domain configuration
    $pass = $addon->getConfig('password');
    $pass = md5($pass);
    ?>
    <a class="pull-right btn btn-primary" target="_blank" href="<?= $addon->getConfig('url')?>index.php?module=Login&action=logme&login=<?= $addon->getConfig('user')?>&password=<?=$pass?>"><?=$addon->i18n('matomo_link')?></a>

    <iframe id="matomoframe" src="<?= $addon->getConfig('url')?>index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=<?= $addon->getConfig('id')?>&period=week&date=yesterday&token_auth=<?= $addon->getConfig('token')?>
    " frameborder="0" marginheight="0" marginwidth="0" width="100%" style="height: 160vh" onload="iFrameResize({ log: false }, '#matomoframe');"></iframe>

    <?php 
} else {
    echo $addon->i18n('matomo_settings_info');
}
?>
