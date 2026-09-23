<?php

namespace FriendsOfRedaxo\Matomo;

use Exception;
use rex;
use rex_config;
use rex_sql;
use rex_user;

/**
 * Persönliche Matomo-Zugänge für REDAXO-Benutzer.
 *
 * Je REDAXO-Benutzer wird ein Matomo-Benutzer mit Leserecht auf alle oder
 * ausgewählte Websites angelegt, dazu ein Passwort und ein App-Token. Das Passwort
 * wird gespeichert, damit der Benutzer es auf der Übersicht sehen und über die API
 * (mit seinem eigenen Token) ändern kann – auch bei externem Matomo. Mit diesem Token öffnet Matomo die
 * komplette Oberfläche ohne Login (token_auth in der URL). Das ersetzt den
 * alten "logme"-Auto-Login, der login_allow_logme in der config.ini.php brauchte.
 * Matomo lässt dafür nur Tokens ohne Schreib-/Superuser-Rechte zu.
 */
class UserAccess
{
    private const CONFIG_KEY = 'user_access';

    /**
     * @return array<int, array{login: string, token: string, created: string, sites: list<int>, password: string}> sites leer = alle Websites, password leer = unbekannt (Zugang aus älterer Version)
     */
    public static function all(): array
    {
        $stored = rex_config::get('matomo', self::CONFIG_KEY, []);
        if (!is_array($stored)) {
            return [];
        }
        $out = [];
        foreach ($stored as $userId => $entry) {
            if (!is_array($entry) || !isset($entry['login'], $entry['token'])) {
                continue;
            }
            $out[(int) $userId] = [
                'login' => (string) $entry['login'],
                'token' => (string) $entry['token'],
                'created' => (string) ($entry['created'] ?? ''),
                'sites' => self::normalizeSites((array) ($entry['sites'] ?? [])),
                'password' => (string) ($entry['password'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return array{login: string, token: string, created: string, sites: list<int>, password: string}|null
     */
    public static function get(int $userId): ?array
    {
        return self::all()[$userId] ?? null;
    }

    /**
     * @return array{login: string, token: string, created: string, sites: list<int>, password: string}|null
     */
    public static function forCurrentUser(): ?array
    {
        $user = rex::getUser();
        return $user ? self::get($user->getId()) : null;
    }

    /**
     * Legt Matomo-Benutzer + Token für einen REDAXO-Benutzer an. Ist der Login in
     * Matomo schon vergeben (fremdes Konto), bekommt der neue Benutzer einen Suffix;
     * fremde Passwörter werden nie angefasst.
     *
     * @param list<int> $siteIds leer = alle Websites
     * @return array{login: string, token: string, created: string, sites: list<int>, password: string}
     *
     * @throws Exception
     */
    public static function create(MatomoApi $api, string $matomoUrl, rex_user $user, array $siteIds = []): array
    {
        $siteIds = self::normalizeSites($siteIds);
        $existing = self::get($user->getId());
        if (null !== $existing && $api->userExists($existing['login'])) {
            $api->deleteUser($existing['login']);
        }

        $base = self::matomoLogin($user);
        $login = '';
        foreach ([$base, $base . '.redaxo', $base . '.redaxo' . $user->getId()] as $candidate) {
            if (!$api->userExists($candidate)) {
                $login = $candidate;
                break;
            }
        }
        if ('' === $login) {
            throw new Exception('Kein freier Matomo-Login für ' . $user->getLogin());
        }

        $password = self::randomPassword();
        $access = [] === $siteIds ? 'all' : implode(',', $siteIds);
        $api->addUser($login, $password, self::email($user), 'view', $access);

        $token = MatomoApi::createTokenWithCredentials($matomoUrl, $login, $password, 'REDAXO ' . rex::getServerName() . ' / ' . $user->getLogin());

        $entry = ['login' => $login, 'token' => $token, 'created' => date('Y-m-d H:i:s'), 'sites' => $siteIds, 'password' => $password];
        $all = self::all();
        $all[$user->getId()] = $entry;
        rex_config::set('matomo', self::CONFIG_KEY, $all);

        return $entry;
    }

    /**
     * Setzt ein neues Passwort (leer = erzeugen). Mit bekanntem aktuellem Passwort über die
     * API als der Benutzer selbst; ohne (Zugang aus älterer Version) wird der Matomo-Benutzer
     * mit gleichem Login neu angelegt.
     *
     * @return string das neue Passwort
     * @throws Exception
     */
    public static function setPassword(MatomoApi $adminApi, string $matomoUrl, int $userId, string $newPassword = ''): string
    {
        $all = self::all();
        if (!isset($all[$userId])) {
            throw new Exception('Kein Matomo-Zugang für Benutzer ' . $userId);
        }
        if ('' === $newPassword) {
            $newPassword = self::randomPassword();
        }
        if (strlen($newPassword) < 8 || strlen(count_chars($newPassword, 3)) < 2) {
            throw new Exception('Das Passwort muss mindestens 8 Zeichen haben');
        }
        $entry = $all[$userId];

        if ('' === $entry['password']) {
            $user = rex_user::get($userId);
            if (null === $user) {
                throw new Exception('REDAXO-Benutzer ' . $userId . ' nicht gefunden');
            }
            $entry = self::create($adminApi, $matomoUrl, $user, $entry['sites']);
            $all = self::all();
        }

        self::changePassword($matomoUrl, $entry['token'], $entry['login'], $entry['password'], $newPassword);

        $entry['password'] = $newPassword;
        $all[$userId] = $entry;
        rex_config::set('matomo', self::CONFIG_KEY, $all);

        return $newPassword;
    }

    /**
     * Passwortänderung über die API als der Benutzer selbst. Matomo ändert das Passwort
     * und versucht danach eine Benachrichtigungsmail; schlägt die fehl, meldet die API
     * einen Fehler, obwohl das Passwort bereits gesetzt ist. Deshalb wird bei einem
     * Fehler geprüft, ob das neue Passwort schon gilt (Token mit einer Stunde Laufzeit).
     *
     * @throws Exception
     */
    private static function changePassword(string $matomoUrl, string $token, string $login, string $currentPassword, string $newPassword): void
    {
        try {
            (new MatomoApi($matomoUrl, $token))->updateUserPassword($login, $newPassword, $currentPassword);
        } catch (Exception $e) {
            try {
                MatomoApi::createTokenWithCredentials($matomoUrl, $login, $newPassword, 'REDAXO Passwortprüfung', 1);
            } catch (Exception) {
                throw $e;
            }
        }
    }

    /**
     * Ändert, welche Websites ein Benutzer sehen darf (leer = alle).
     *
     * @param list<int> $siteIds
     * @throws Exception
     */
    public static function updateSites(MatomoApi $api, int $userId, array $siteIds): void
    {
        $all = self::all();
        if (!isset($all[$userId])) {
            throw new Exception('Kein Matomo-Zugang für Benutzer ' . $userId);
        }
        $siteIds = self::normalizeSites($siteIds);
        $login = $all[$userId]['login'];
        $api->setUserAccess($login, 'noaccess', 'all');
        $api->setUserAccess($login, 'view', [] === $siteIds ? 'all' : implode(',', $siteIds));
        $all[$userId]['sites'] = $siteIds;
        rex_config::set('matomo', self::CONFIG_KEY, $all);
    }

    /**
     * Matomo kennt kein "alle künftigen Websites": Leserecht auf "alle" ist ein Schnappschuss
     * beim Speichern. Deshalb bekommen Zugänge ohne Einschränkung hier erneut Leserecht auf
     * alle aktuell vorhandenen Websites – nach dem Anlegen von Websites und beim Aufruf der
     * Einrichtung, sobald sich die Website-Liste geändert hat.
     *
     * @param array<int> $siteIds aktuelle Matomo-Site-IDs (für den Änderungsmarker)
     * @return array{synced: int, missing: list<string>} abgeglichene Zugänge und Logins, die es in Matomo nicht mehr gibt
     */
    public static function syncAllSites(MatomoApi $api, array $siteIds, bool $force = false): array
    {
        sort($siteIds);
        $marker = md5(implode(',', $siteIds));
        $result = ['synced' => 0, 'missing' => []];
        if (!$force && (string) rex_config::get('matomo', 'access_sync_marker', '') === $marker) {
            return $result;
        }
        foreach (self::all() as $entry) {
            if ([] !== $entry['sites']) {
                continue;
            }
            try {
                $api->setUserAccess($entry['login'], 'view', 'all');
                ++$result['synced'];
            } catch (Exception $e) {
                // Matomo-Benutzer direkt in Matomo gelöscht: Eintrag bleibt, wird in der Einrichtung markiert
                $result['missing'][] = $entry['login'];
            }
        }
        rex_config::set('matomo', 'access_sync_marker', $marker);
        return $result;
    }

    /**
     * Logins, die in Matomo fehlen, obwohl ein Zugang gespeichert ist (z.B. dort gelöscht).
     *
     * @return list<string>
     */
    public static function missingInMatomo(MatomoApi $api): array
    {
        try {
            $logins = array_column($api->getUsers(), 'login');
        } catch (Exception) {
            return [];
        }
        $missing = [];
        foreach (self::all() as $entry) {
            if (!in_array($entry['login'], $logins, true)) {
                $missing[] = $entry['login'];
            }
        }
        return $missing;
    }

    /**
     * Websites, die der aktuelle Benutzer im REDAXO-Backend sehen darf.
     * null = keine Einschränkung (kein persönlicher Zugang oder "alle").
     *
     * @return list<int>|null
     */
    public static function allowedSiteIds(): ?array
    {
        $access = self::forCurrentUser();
        if (null === $access || [] === $access['sites']) {
            return null;
        }
        return $access['sites'];
    }

    /**
     * @param array<int, array<string, mixed>> $sites Matomo-Sites
     * @return array<int, array<string, mixed>>
     */
    public static function filterSites(array $sites): array
    {
        $allowed = self::allowedSiteIds();
        if (null === $allowed) {
            return $sites;
        }
        return array_values(array_filter($sites, static fn (array $site): bool => in_array((int) ($site['idsite'] ?? 0), $allowed, true)));
    }

    /**
     * @param array<mixed> $siteIds
     * @return list<int>
     */
    private static function normalizeSites(array $siteIds): array
    {
        $out = array_values(array_unique(array_filter(array_map('intval', $siteIds), static fn (int $id): bool => $id > 0)));
        sort($out);
        return $out;
    }

    /**
     * Entfernt Zugang und Matomo-Benutzer.
     *
     * @throws Exception
     */
    public static function remove(MatomoApi $api, int $userId): void
    {
        $all = self::all();
        if (!isset($all[$userId])) {
            return;
        }
        try {
            if ($api->userExists($all[$userId]['login'])) {
                $api->deleteUser($all[$userId]['login']);
            }
        } catch (Exception) {
            // Benutzer in Matomo bereits weg: Eintrag trotzdem entfernen
        }
        unset($all[$userId]);
        rex_config::set('matomo', self::CONFIG_KEY, $all);
    }

    /**
     * Matomo-URL für den aktuellen Benutzer: mit persönlichem Token direkt in der
     * Oberfläche, sonst die Login-Seite.
     */
    public static function openUrl(int $siteId = 0, string $period = 'day', string $date = 'today'): string
    {
        $base = rtrim((string) rex_config::get('matomo', 'matomo_url', ''), '/');
        $access = self::forCurrentUser();
        if (null === $access) {
            return $siteId > 0
                ? $base . '/index.php?module=CoreHome&action=index&idSite=' . $siteId . '&period=' . $period . '&date=' . $date
                : $base . '/';
        }
        if ($siteId <= 0 && [] === $access['sites']) {
            // Token-Zugang braucht eine Site-ID; ohne bekannte Website Matomo selbst wählen lassen
            return $base . '/index.php?token_auth=' . rawurlencode($access['token']);
        }

        $params = [
            'module' => 'CoreHome',
            'action' => 'index',
            'idSite' => $siteId > 0 ? $siteId : $access['sites'][0],
            'period' => $period,
            'date' => $date,
            'token_auth' => $access['token'],
        ];

        return $base . '/index.php?' . http_build_query($params, '', '&');
    }

    /**
     * REDAXO-Backend-Benutzer für die Zugangsverwaltung.
     *
     * @return list<array{id: int, login: string, name: string, email: string, has_email: bool, admin: bool}>
     */
    public static function redaxoUsers(): array
    {
        $rows = rex_sql::factory()->getArray('SELECT id, login, name, email, admin FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY login');
        $out = [];
        foreach ($rows as $row) {
            $email = trim((string) $row['email']);
            $out[] = [
                'id' => (int) $row['id'],
                'login' => (string) $row['login'],
                'name' => (string) $row['name'],
                'email' => $email,
                'has_email' => '' !== $email && false !== filter_var($email, FILTER_VALIDATE_EMAIL),
                'admin' => 1 === (int) $row['admin'],
            ];
        }
        return $out;
    }

    /**
     * Matomo erlaubt nur [A-Za-zÄäÖöÜüß0-9_.@+-], 2–100 Zeichen.
     */
    public static function matomoLogin(rex_user $user): string
    {
        $login = (string) preg_replace('/[^A-Za-zÄäÖöÜüß0-9_.@+-]/u', '_', $user->getLogin());
        if (strlen($login) < 2) {
            $login = 'redaxo_' . $user->getId();
        }
        return substr($login, 0, 100);
    }

    /**
     * Matomo verlangt je Benutzer eine eindeutige E-Mail-Adresse; sie muss im REDAXO-Benutzer stehen.
     */
    public static function email(rex_user $user): string
    {
        $email = trim((string) $user->getValue('email'));
        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('REDAXO-Benutzer "' . $user->getLogin() . '" hat keine gültige E-Mail-Adresse; Matomo benötigt je Benutzer eine');
        }
        return $email;
    }

    public static function hasEmail(rex_user $user): bool
    {
        try {
            self::email($user);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    public static function randomPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=') . 'A1!';
    }
}
