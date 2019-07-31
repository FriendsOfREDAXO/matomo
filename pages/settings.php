<?php 
$addon = rex_addon::get('matomo');
$form = rex_config_form::factory($addon->name);

$field = $form->addInputField('text', 'url',null,["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_url'));

$field = $form->addInputField('text', 'id',null,["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_id'));


$field = $form->addInputField('text', 'token',null,["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_token'));

$field = $form->addInputField('text', 'user',null,["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_user'));

$field = $form->addInputField('password', 'password',null,["class" => "form-control"]);
$field->setLabel($addon->i18n('matomo_password'));


$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', "Matomo Settings", false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');