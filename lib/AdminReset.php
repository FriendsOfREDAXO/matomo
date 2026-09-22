<?php

namespace FriendsOfRedaxo\Matomo;

use Exception;
use PDO;
use rex;
use rex_config;
use rex_path;

/**
 * Notfall-Reset für den Matomo-Superuser bei lokaler Installation.
 *
 * Die Matomo-API verlangt für jede Passwortänderung das aktuelle Passwort, hilft
 * also nicht, wenn es verloren ist. Bei lokaler Installation liest das Addon die
 * Datenbankzugangsdaten aus Matomos config.ini.php und setzt das Passwort direkt
 * in der Benutzertabelle (so wie es Matomos FAQ zum vergessenen Passwort beschreibt).
 * Anschließend werden alte Tokens des Benutzers verworfen und ein neues Token erzeugt.
 */
class AdminReset
{
    public static function configFile(): string
    {
        $path = trim((string) rex_config::get('matomo', 'matomo_path', ''), " /\\");
        return '' === $path ? '' : rex_path::frontend($path . '/config/config.ini.php');
    }

    public static function isAvailable(): bool
    {
        $file = self::configFile();
        return '' !== $file && is_readable($file) && class_exists(PDO::class);
    }

    /**
     * @return array{password: string, token: string}
     * @throws Exception
     */
    public static function reset(string $login, string $newPassword = ''): array
    {
        if (!self::isAvailable()) {
            throw new Exception('Reset ist nur bei lokaler Matomo-Installation mit lesbarer config/config.ini.php möglich');
        }
        $login = trim($login);
        if ('' === $login) {
            throw new Exception('Kein Matomo-Benutzername angegeben');
        }
        if ('' === $newPassword) {
            $newPassword = self::randomPassword();
        }
        if (strlen($newPassword) < 6) {
            throw new Exception('Das Passwort muss mindestens 6 Zeichen haben');
        }

        $config = self::readConfig();
        $pdo = self::connect($config);
        $prefix = $config['tables_prefix'];

        $stmt = $pdo->prepare('SELECT superuser_access FROM `' . $prefix . 'user` WHERE login = ?');
        $stmt->execute([$login]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $row) {
            throw new Exception('Matomo-Benutzer "' . $login . '" nicht gefunden');
        }
        if (1 !== (int) $row['superuser_access']) {
            throw new Exception('"' . $login . '" ist kein Matomo-Superuser; nur Superuser können hier zurückgesetzt werden');
        }

        // Matomo: password_hash(md5($password)) – siehe UsersManager::getPasswordHash() + Auth\Password::hash()
        $hash = password_hash(md5($newPassword), self::algorithm($config['password_hash_algorithm']));

        $pdo->prepare('UPDATE `' . $prefix . 'user` SET password = ?, ts_password_modified = NOW() WHERE login = ?')
            ->execute([$hash, $login]);
        $pdo->prepare('DELETE FROM `' . $prefix . 'user_token_auth` WHERE login = ?')->execute([$login]);

        $matomoUrl = (string) rex_config::get('matomo', 'matomo_url', '');
        $token = MatomoApi::createTokenWithCredentials($matomoUrl, $login, $newPassword, 'REDAXO ' . rex::getServerName());
        rex_config::set('matomo', 'admin_token', $token);

        return ['password' => $newPassword, 'token' => $token];
    }

    /**
     * @return array{host: string, port: int, username: string, password: string, dbname: string, tables_prefix: string, charset: string, password_hash_algorithm: string}
     * @throws Exception
     */
    private static function readConfig(): array
    {
        $raw = (string) file_get_contents(self::configFile());
        // erste Zeile ist der PHP-Exit-Schutz
        $raw = (string) preg_replace('/^;.*\R/', '', $raw, 1);
        $ini = parse_ini_string($raw, true, INI_SCANNER_RAW);
        if (false === $ini || !isset($ini['database']) || !is_array($ini['database'])) {
            throw new Exception('config.ini.php enthält keinen [database]-Abschnitt');
        }
        $db = $ini['database'];
        $general = isset($ini['General']) && is_array($ini['General']) ? $ini['General'] : [];

        return [
            'host' => trim((string) ($db['host'] ?? 'localhost'), '"'),
            'port' => (int) trim((string) ($db['port'] ?? 3306), '"'),
            'username' => trim((string) ($db['username'] ?? ''), '"'),
            'password' => trim((string) ($db['password'] ?? ''), '"'),
            'dbname' => trim((string) ($db['dbname'] ?? ''), '"'),
            'tables_prefix' => trim((string) ($db['tables_prefix'] ?? 'matomo_'), '"'),
            'charset' => trim((string) ($db['charset'] ?? 'utf8mb4'), '"'),
            'password_hash_algorithm' => trim((string) ($general['password_hash_algorithm'] ?? 'default'), '"'),
        ];
    }

    /**
     * @param array{host: string, port: int, username: string, password: string, dbname: string, tables_prefix: string, charset: string, password_hash_algorithm: string} $config
     * @throws Exception
     */
    private static function connect(array $config): PDO
    {
        $host = $config['host'];
        $dsn = 'mysql:dbname=' . $config['dbname'] . ';charset=' . $config['charset'];
        if (str_starts_with($host, '/')) {
            $dsn .= ';unix_socket=' . $host;
        } else {
            $dsn .= ';host=' . $host . ';port=' . ($config['port'] > 0 ? $config['port'] : 3306);
        }
        try {
            return new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $e) {
            throw new Exception('Verbindung zur Matomo-Datenbank fehlgeschlagen: ' . $e->getMessage());
        }
    }

    private static function algorithm(string $configured): string
    {
        return match (strtolower($configured)) {
            'argon2id' => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
            'argon2i' => defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_BCRYPT,
            default => PASSWORD_BCRYPT,
        };
    }

    public static function randomPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=') . '!7a';
    }
}
