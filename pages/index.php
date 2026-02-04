<?php

$addon = rex_addon::get('matomo');
echo rex_view::title($addon->i18n('matomo_Matomo'));
$subpage = rex_be_controller::getCurrentPagePart(2);
rex_be_controller::includeCurrentPageSubPath();
