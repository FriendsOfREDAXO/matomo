<?php
if (rex::isBackend() && rex_be_controller::getCurrentPage() == 'matomo/dashboard') { 
	
    rex_view::addJsFile($this->getAssetsUrl('iframeResizer.min.js'));
}
?>
