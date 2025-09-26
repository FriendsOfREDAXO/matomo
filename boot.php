<?php

use FriendsOfRedaxo\Matomo\MatomoInfoCenterWidget;

if (rex::isBackend()) {
        
    // Info-Center Widget registrieren (falls Info-Center AddOn vorhanden ist)
    if (rex_addon::get('info_center')->isAvailable()) {
        rex_extension::register('PACKAGES_INCLUDED', function () {
            $infoCenter = \KLXM\InfoCenter\InfoCenter::getInstance();
            
            // Matomo Widget registrieren
            $widget = new MatomoInfoCenterWidget();
            $widget->setPriority(3); // Nach Article Widget, vor Upkeep Widget
            $infoCenter->registerWidget($widget);
        });
    }
}
