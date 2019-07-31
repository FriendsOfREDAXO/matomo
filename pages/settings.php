<?php 
$addon = rex_addon::get('matomo')
$form = rex_config_form::factory($addon);

$field = $form->addInputField('url', null);
$field->setLabel($addon->i18n('matomo_Url'));
$field->setNotice('Comment');

$field = $form->addInputField('token', null]);
$field->setLabel($addon->i18n('matomo_token'));
$field->setNotice('Comment');

$field = $form->addInputField('user', null]);
$field->setLabel($addon->i18n('matomo_user'));
$field->setNotice('Comment');

$field = $form->addInputField('password', null);
$field->setLabel($addon->i18n('matomo_pwasword'));
$field->setNotice('Comment');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', "Matomo Settings", false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
