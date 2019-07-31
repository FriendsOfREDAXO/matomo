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



if (rex::isBackend() 
    and $addon->getConfig('token')!='' 
    and $addon->getConfig('user')!='' 
    and $addon->getConfig('password')!=''
    and $addon->getConfig('url')!='' 
    and $addon->getConfig('id')!=''
   )
{


$url = 'https://klxm.de/piwik//index.php?module=API&method=SitesManager.getJavascriptTag&idSite=7&piwikUrl=&format=JSON&token_auth='.$addon->getConfig('token');

?><?php  
//URL of targeted site  
$ch = curl_init();  

// set URL and other appropriate options  
curl_setopt($ch, CURLOPT_URL, $url);  
curl_setopt($ch, CURLOPT_HEADER, 0);  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  

// grab URL and pass it to the browser  

#$output = curl_exec($ch);  

// close curl resource, and free up system resources  
$output = json_decode(curl_exec($ch),true);



#echo json_decode($output);
echo '<div class="rex-form-group form-group"><textarea class="form-control" height="80">'.$output['value'].'</textarea></div>';
}
?>  


