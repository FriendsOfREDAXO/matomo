<?php
$addon = rex_addon::get('matomo');
$form = rex_config_form::factory($addon->name);
$field = $form->addInputField('text', 'url', null, ["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_url'));
$field = $form->addInputField('text', 'id', null, ["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_id'));
$field = $form->addInputField('text', 'token', null, ["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_token'));
$field = $form->addInputField('text', 'user', null, ["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_user'));
$field = $form->addInputField('password', 'password', null, ["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_password'));

// Tracking code settings 
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
$fragment->setVar('title', "Matomo Settings", false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
$tracking_code_extra = array_filter(explode("|", $addon->getConfig('tracking_setup')), 'strlen');
$url_extra = '';
if (count($tracking_code_extra) > 0) {
    foreach ($tracking_code_extra as $value) {
        $url_extra.= $value;
    }
}
if (rex::isBackend() and $addon->getConfig('token') != '' and $addon->getConfig('user') != '' and $addon->getConfig('password') != '' and $addon->getConfig('url') != '' and $addon->getConfig('id') != '') {
    $url = rex_escape($addon->getConfig('url')) . 'index.php?module=API&method=SitesManager.getJavascriptTag&idSite=' . rex_escape($addon->getConfig('id')) . $url_extra . '&format=JSON&token_auth=' . $addon->getConfig('token');
    //URL of targeted site
    $ch = curl_init();
    // set URL and other appropriate options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // close curl resource, and free up system resources
    $output = json_decode(curl_exec($ch), true);
    if (array_key_exists("value", $output)) {
        $check = strpos($output['value'], 'Matomo');
    } else {
        $check = false;
    }
    if ($check !== false) {
        $content = '<p>' . $addon->i18n('matomo_trackingcode') . '</p><div class="rex-form-group form-group"><textarea class="form-control codemirror" height="80">' . $output['value'] . '</textarea></div>';
        $addon->setConfig('matomocheck', true);
        $addon->setConfig('matomojs', $output['value']);
        $fragment = new rex_fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $addon->i18n('matomo_trackingcode_headline'), false);
        $fragment->setVar('body', $content, false);
        echo $fragment->parse('core/page/section.php');
    } else {
        $content = '<div class="alert alert-danger">';
        $content.= $addon->i18n('matomo_notfound');
        $content.= '</div>';
        echo $content;
        $addon->setConfig('matomocheck', false);
    }
}
