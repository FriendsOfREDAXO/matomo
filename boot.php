<?php

use FriendsOfRedaxo\Matomo\MatomoInfoCenterWidget;
use FriendsOfRedaxo\Matomo\MatomoDashboardItem;
use FriendsOfRedaxo\Matomo\MatomoProxyApi;
use FriendsOfRedaxo\Matomo\MatomoEventApi;
use FriendsOfRedaxo\Matomo\Tracker;

// API-Funktionen registrieren
rex_api_function::register('matomo_proxy', MatomoProxyApi::class);
rex_api_function::register('matomo_event', MatomoEventApi::class);

if (rex::isBackend() && rex_be_controller::getCurrentPage() === 'matomo/config') {
    rex_view::addJsFile(rex_addon::get('matomo')->getAssetsUrl('matomo-config.js'));
}

// ── Server-Side Tracking (kein JS, keine Cookies) ──────────────────────────
if (!rex::isBackend() && (bool) rex_config::get('matomo', 'server_side_tracking', false)) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep): void {
        // Kein Tracking für AJAX-Requests
        if (rex_request::isXmlHttpRequest()) {
            return;
        }

        // Nur echte Artikel-Seiten (kein 404, kein Redirect-only)
        $article = rex_article::getCurrent();
        if ($article === null) {
            return;
        }

        $siteId = (int) rex_config::get('matomo', 'server_side_site_id', 0);
        if ($siteId === 0) {
            return;
        }

        $tracker = Tracker::factory($siteId);
        if ($tracker === null) {
            return;
        }

        // Bots nicht tracken
        if ($tracker->isBot()) {
            return;
        }

        // Referer weiterleiten
        $referer = rex_request::server('HTTP_REFERER', 'string', '');
        if ('' !== $referer) {
            $tracker->setReferer($referer);
        }

        // Aktuell eingeloggten YCom-User tracken (falls YCom verfügbar)
        if (rex_addon::get('ycom')->isAvailable() && class_exists('rex_ycom_auth')) {
            // @phpstan-ignore-next-line
            $ycomUser = rex_ycom_auth::getUser();
            if ($ycomUser !== null) {
                // @phpstan-ignore-next-line
                $tracker->setUserId((string) $ycomUser->getValue('login'));
            }
        }

        $tracker->trackPageView($article->getName());
    });
}

// Browser-Event-Tracking-Script wird bewusst NICHT automatisch injiziert.
// Einbindung erfolgt manuell durch den Integrator (Template/Consent-Manager).

if (rex::isBackend()) {
    // Info-Center Widget registrieren (falls Info-Center AddOn vorhanden ist)
    if (rex_addon::get('info_center')->isAvailable()) {
        rex_extension::register('PACKAGES_INCLUDED', function () {
            // Nur registrieren wenn User Berechtigung für Matomo Overview hat
            $user = rex::getUser();
            if (null !== $user && $user->hasPerm('matomo[overview]')) {
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
            if (null !== $user && $user->hasPerm('matomo[overview]')) {
                \FriendsOfRedaxo\Dashboard\Dashboard::addItem(
                    MatomoDashboardItem::factory('matomo-statistics', '📊 ' . rex_i18n::msg('matomo_widget_title'))
                        ->setColumns(2) // Normal breit (2 Spalten)
                );
            }
        }, rex_extension::LATE);
    }
}
