<?php

// Autoloader für FriendsOfRedaxo\Matomo Namespace
require_once __DIR__ . '/vendor/autoload.php';

// Assets für Dashboard-Integration laden  
if (rex::isBackend() && str_starts_with(rex_be_controller::getCurrentPage(), 'matomo/')) {
    // iframeResizer für bessere iframe-Integration (von Matomo empfohlen)
    if (file_exists($this->getAssetsPath('iframeResizer.min.js'))) {
        rex_view::addJsFile($this->getAssetsUrl('iframeResizer.min.js'));
    }
}
