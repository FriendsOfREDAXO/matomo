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
 * Je REDAXO-Benutzer wird ein Matomo-Benutzer mit Leserecht auf alle Websites
 * angelegt und dafür ein App-Token erzeugt. Mit diesem Token öffnet Matomo die
 * komplette Oberfläche ohne Login (token_auth in der URL). Das ersetzt den
 * alten "logme"-Auto-Login, der login_allow_logme in der config.ini.php brauchte.
 * Matomo lässt dafür nur Tokens ohne Schreib-/Superuser-Rechte zu.
 */
class UserAccess
{
    private const CONFIG_KEY = 'user_access';

    /**
     * @return array<int, array{login: string, token: string, created: string}>
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
            ];
        }
        return $out;
    }

    /**
     * @return array{login: string, token: string, created: string}|null
     */
    public static function get(int $userId): ?array
    {
        return self::all()[$userId] ?? null;
    }

    /**
     * @return array{login: string, token: string, created: string}|null
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
     * @return array{login: string, token: string, created: string}
     *
     * @throws Exception
     */
    public static function create(MatomoApi $api, string $matomoUrl, rex_user $user): array
    {
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
        try {
            $api->addUser($login, $password, self::email($user, $matomoUrl, $login), 'view');
        } catch (Exception $e) {
            // E-Mail-Adressen sind in Matomo eindeutig; dann mit generierter Adresse anlegen
            if (false === stripos($e->getMessage(), 'mail')) {
                throw $e;
            }
            $api->addUser($login, $password, self::generatedEmail($matomoUrl, $login), 'view');
        }

        $token = MatomoApi::createTokenWithCredentials($matomoUrl, $login, $password, 'REDAXO ' . rex::getServerName() . ' / ' . $user->getLogin());

        $entry = ['login' => $login, 'token' => $token, 'created' => date('Y-m-d H:i:s')];
        $all = self::all();
        $all[$user->getId()] = $entry;
        rex_config::set('matomo', self::CONFIG_KEY, $all);

        return $entry;
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
        if ($api->userExists($all[$userId]['login'])) {
            $api->deleteUser($all[$userId]['login']);
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

        $params = [
            'module' => 'CoreHome',
            'action' => 'index',
            'idSite' => $siteId > 0 ? $siteId : self::firstSiteId(),
            'period' => $period,
            'date' => $date,
            'token_auth' => $access['token'],
        ];

        return $base . '/index.php?' . http_build_query($params, '', '&');
    }

    /**
     * REDAXO-Backend-Benutzer für die Zugangsverwaltung.
     *
     * @return list<array{id: int, login: string, name: string, email: string, admin: bool}>
     */
    public static function redaxoUsers(): array
    {
        $rows = rex_sql::factory()->getArray('SELECT id, login, name, email, admin FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY login');
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'login' => (string) $row['login'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
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

    private static function email(rex_user $user, string $matomoUrl, string $login): string
    {
        $email = trim((string) $user->getValue('email'));
        if ('' !== $email && false !== filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return self::generatedEmail($matomoUrl, $login);
    }

    private static function generatedEmail(string $matomoUrl, string $login): string
    {
        $host = (string) parse_url($matomoUrl, PHP_URL_HOST);
        $local = (string) preg_replace('/[^A-Za-z0-9_.+-]/', '_', $login);
        if ('' === $host || false === filter_var($local . '@' . $host, FILTER_VALIDATE_EMAIL)) {
            $host = 'redaxo.invalid';
        }
        return $local . '@' . $host;
    }

    private static function randomPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=') . 'A1!';
    }

    private static function firstSiteId(): int
    {
        return max(1, (int) rex_config::get('matomo', 'server_side_site_id', 0));
    }
}
