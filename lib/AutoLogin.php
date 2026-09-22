<?php

namespace FriendsOfRedaxo\Matomo;

use rex_config;
use rex_escape;
use rex_file;
use rex_i18n;

/**
 * Auto-Login in Matomo über dessen "logme"-Funktion: Matomo meldet den Benutzer mit
 * Login und md5-Passwort an und legt eine reguläre Session an. Voraussetzung ist
 * login_allow_logme = 1 in Matomos config.ini.php; bei lokaler Installation setzt das
 * Addon das selbst, bei externem Matomo bestätigt der Admin die Einstellung.
 * Genutzt werden die je Benutzer gespeicherten Zugangsdaten (kein geteiltes Konto);
 * Matomo lässt logme nur für Benutzer ohne Superuser-Rechte zu. Die Anmeldung wird
 * per POST-Formular abgeschickt, damit der Passwort-Hash nicht in URLs landet.
 */
class AutoLogin
{
    public const CONFIG_KEY = 'autologin_enabled';

    /**
     * Ist logme in Matomo aktiv? Lokal wird die config.ini.php gelesen, sonst die
     * vom Admin bestätigte Einstellung.
     */
    public static function isEnabled(): bool
    {
        $file = AdminReset::configFile();
        if ('' !== $file && is_readable($file)) {
            return self::configHasLogme((string) rex_file::get($file));
        }
        return (bool) rex_config::get('matomo', self::CONFIG_KEY, false);
    }

    public static function configHasLogme(string $ini): bool
    {
        return 1 === preg_match('/^\s*login_allow_logme\s*=\s*"?1"?\s*$/m', $ini);
    }

    /**
     * Aktiviert logme bei lokaler Installation: bevorzugt über Matomos Konsole
     * (config:set), sonst direkt in der config.ini.php.
     *
     * @return string|null Fehlertext, null bei Erfolg
     */
    public static function enableLocally(): ?string
    {
        $file = AdminReset::configFile();
        if ('' === $file || !is_file($file)) {
            return 'config/config.ini.php nicht gefunden';
        }

        if (MatomoCli::isAvailable()) {
            $result = MatomoCli::console(['config:set', '--section=General', '--key=login_allow_logme', '--value=1']);
            if (0 === $result['code'] && self::isEnabled()) {
                rex_config::set('matomo', self::CONFIG_KEY, true);
                return null;
            }
        }

        // Fallback: Datei direkt ändern
        if (!is_writable($file)) {
            return 'config/config.ini.php ist nicht beschreibbar';
        }
        $ini = (string) rex_file::get($file);
        if (self::configHasLogme($ini)) {
            rex_config::set('matomo', self::CONFIG_KEY, true);
            return null;
        }
        if (1 === preg_match('/^\s*login_allow_logme\s*=.*$/m', $ini)) {
            $ini = (string) preg_replace('/^\s*login_allow_logme\s*=.*$/m', 'login_allow_logme = 1', $ini, 1);
        } elseif (1 === preg_match('/^\[General\]\s*$/m', $ini)) {
            $ini = (string) preg_replace('/^(\[General\]\s*\R)/m', "$1login_allow_logme = 1\n", $ini, 1);
        } else {
            $ini = rtrim($ini) . "\n\n[General]\nlogin_allow_logme = 1\n";
        }
        if (!rex_file::put($file, $ini)) {
            return 'config/config.ini.php konnte nicht geschrieben werden';
        }
        rex_config::set('matomo', self::CONFIG_KEY, true);
        return null;
    }

    /**
     * Anmeldedaten für den aktuellen Benutzer, sofern Auto-Login möglich ist.
     *
     * @return array{action: string, login: string, hash: string}|null
     */
    public static function credentialsForCurrentUser(): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }
        $access = UserAccess::forCurrentUser();
        if (null === $access || '' === $access['password']) {
            return null;
        }
        return [
            'action' => rtrim((string) rex_config::get('matomo', 'matomo_url', ''), '/') . '/index.php',
            'login' => $access['login'],
            'hash' => md5($access['password']),
        ];
    }

    /**
     * Ziel-URL innerhalb von Matomo nach der Anmeldung.
     */
    public static function targetUrl(int $siteId = 0): string
    {
        if ($siteId <= 0) {
            $access = UserAccess::forCurrentUser();
            $siteId = null !== $access && [] !== $access['sites'] ? $access['sites'][0] : max(1, (int) rex_config::get('matomo', 'server_side_site_id', 0));
        }
        return 'index.php?module=CoreHome&action=index&idSite=' . $siteId . '&period=day&date=today';
    }

    /**
     * POST-Formular "Matomo öffnen" mit automatischer Anmeldung.
     */
    public static function form(int $siteId, string $label, string $class = 'btn btn-primary btn-sm', string $icon = 'fa-sign-in-alt'): string
    {
        $c = self::credentialsForCurrentUser();
        if (null === $c) {
            return '';
        }
        return '<form method="post" action="' . rex_escape($c['action']) . '" target="_blank" class="matomo-autologin" style="display:inline">'
            . '<input type="hidden" name="module" value="Login"><input type="hidden" name="action" value="logme">'
            . '<input type="hidden" name="login" value="' . rex_escape($c['login']) . '">'
            . '<input type="hidden" name="password" value="' . rex_escape($c['hash']) . '">'
            . '<input type="hidden" name="url" value="' . rex_escape(self::targetUrl($siteId)) . '">'
            . '<button type="submit" class="' . rex_escape($class) . '" title="' . rex_escape(rex_i18n::msg('matomo_autologin_title')) . '"><i class="fa ' . rex_escape($icon) . '"></i> ' . rex_escape($label) . '</button></form>';
    }
}
