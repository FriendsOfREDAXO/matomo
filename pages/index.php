<?php
echo rex_view::title($this->i18n('Matomo'));
$subpage = rex_be_controller::getCurrentPagePart(2);
rex_be_controller::includeCurrentPageSubPath();
