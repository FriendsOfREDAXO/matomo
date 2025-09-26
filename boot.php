<?php

use FriendsOfRedaxo\Matomo\MatomoInfoCenterWidget;
use FriendsOfRedaxo\Matomo\MatomoDashboardItem;

if (rex::isBackend()) {
    // Info-Center Widget registrieren (falls Info-Center AddOn vorhanden ist)
    if (rex_addon::get('info_center')->isAvailable()) {
        rex_extension::register('PACKAGES_INCLUDED', function () {
            // Nur registrieren wenn User Berechtigung für Matomo Overview hat
            $user = rex::getUser();
            if ($user && $user->hasPerm('matomo[overview]')) {
                $infoCenter = \KLXM\InfoCenter\InfoCenter::getInstance();
                
                // Matomo Widget registrieren
                $widget = new MatomoInfoCenterWidget();
                $widget->setPriority(3); // Nach Article Widget, vor Upkeep Widget
                $infoCenter->registerWidget($widget);
            }
        });
    }
    
    // Dashboard Widget registrieren (falls Dashboard AddOn vorhanden ist)
    if (rex_addon::get('dashboard')->isAvailable()) {
        rex_extension::register('PACKAGES_INCLUDED', function () {
            // Nur registrieren wenn User Berechtigung für Matomo Overview hat
            $user = rex::getUser();
            if ($user && $user->hasPerm('matomo[overview]')) {
                rex_dashboard::addItem(
                    MatomoDashboardItem::factory('matomo-statistics', '📊 ' . rex_i18n::msg('matomo_widget_title'))
                        ->setColumns(2) // Normal breit (2 Spalten)
                );
            }
        }, rex_extension::LATE);
    }
}
