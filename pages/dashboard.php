<?php 
$addon = rex_addon::get('matomo');

if (rex::isBackend() 
    and $addon->getConfig('token')!='' 
    and $addon->getConfig('user')!='' 
    and $addon->getConfig('password')!=''
    and $addon->getConfig('url')!='' 
    and $addon->getConfig('id')!=''
    and $addon->getConfig('matomocheck') == true 
   )
{
$pass = $addon->getConfig('password');
$pass = md5($pass);
?>
<a class="pull-right btn btn-primary" target="_blank" href="<?= $addon->getConfig('url')?>index.php?module=Login&action=logme&login=<?= $addon->getConfig('user')?>&password=<?=$pass?>"><?=$addon->i18n('matomo_link')?></a>


<iframe id="matomoframe" src="<?= $addon->getConfig('url')?>index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=<?= $addon->getConfig('id')?>&period=week&date=yesterday&token_auth=<?= $addon->getConfig('token')?>
" frameborder="0" marginheight="0" marginwidth="0" width="100%" style="height: 160vh" onload="iFrameResize({ log: false }, '#matomoframe');"></iframe>

<?php } 

else
    {
    echo $addon->i18n('matomo_settings_info');
}
?>
