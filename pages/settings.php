<?php 
$form = rex_config_form::factory($this->name);

$field = $form->addInputField('url', null, ["class" => "codemirror"]);
$field->setLabel("Matomo-Url");
$field->setNotice('Comment');

$field = $form->addInputField('token', null, ["class" => "codemirror"]);
$field->setLabel("API-Token");
$field->setNotice('Comment');

$field = $form->addInputField('user', null, ["class" => "codemirror"]);
$field->setLabel("User");
$field->setNotice('Comment');

$field = $form->addInputField('password', null, ["class" => "codemirror"]);
$field->setLabel("Passwort");
$field->setNotice('Comment');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', "Matomo Settings", false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
