<?php

namespace FriendsOfRedaxo\Matomo;

use Exception;
use PDO;
use rex;
use rex_addon;
use rex_config;
use rex_path;

/**
 * Passwörter von Matomo-Benutzern bei lokaler Installation setzen.
 *
 * Die Matomo-API verlangt für jede Passwortänderung das aktuelle Passwort, hilft
 * also nicht, wenn es verloren ist. Bevorzugter Weg: bin/matomo-user-password.php
 * per PHP-CLI, das Matomo selbst bootstrappt und dessen UsersManager-API nutzt –
 * mit Matomos eigener Datenbankverbindung. Fällt die CLI aus (kein proc_open, kein
 * php-Binary), liest das Addon die Datenbankzugangsdaten aus config.ini.php (oder
 * manuell hinterlegte) und setzt das Passwort direkt in der Benutzertabelle.
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
        if ('' !== self::matomoDir()) {
            return true;
        }
        return class_exists(PDO::class) && '' !== (string) rex_config::get('matomo', 'db_override_user', '');
    }

    public static function matomoDir(): string
    {
        return MatomoCli::matomoDir();
    }

    /**
     * Manuell hinterlegte Zugangsdaten zur Matomo-Datenbank (Konfiguration), falls die
     * automatische Verbindung über config.ini.php scheitert.
     *
     * @return array{host: string, port: int, unix_socket: string, username: string, password: string, dbname: string, tables_prefix: string, charset: string, password_hash_algorithm: string}|null
     */
    private static function overrideConfig(): ?array
    {
        $user = (string) rex_config::get('matomo', 'db_override_user', '');
        if ('' === $user) {
            return null;
        }
        $host = trim((string) rex_config::get('matomo', 'db_override_host', 'localhost'));
        $port = 3306;
        if (preg_match('/^(.*):(\d+)$/', $host, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        }
        return [
            'host' => '' !== $host ? $host : 'localhost',
            'port' => $port,
            'unix_socket' => '',
            'username' => $user,
            'password' => (string) rex_config::get('matomo', 'db_override_password', ''),
            'dbname' => (string) rex_config::get('matomo', 'db_override_name', ''),
            'tables_prefix' => (string) rex_config::get('matomo', 'db_override_prefix', 'matomo_'),
            'charset' => 'utf8mb4',
            'password_hash_algorithm' => 'default',
        ];
    }

    /**
     * Superuser-Reset: neues Passwort, alle Tokens verworfen, neues Addon-Token.
     *
     * @return array{password: string, token: string}
     * @throws Exception
     */
    public static function reset(string $login, string $newPassword = ''): array
    {
        $login = trim($login);
        if ('' === $newPassword) {
            $newPassword = self::randomPassword();
        }
        self::setPassword($login, $newPassword, true, true);

        $matomoUrl = (string) rex_config::get('matomo', 'matomo_url', '');
        $token = MatomoApi::createTokenWithCredentials($matomoUrl, $login, $newPassword, 'REDAXO ' . rex::getServerName());
        rex_config::set('matomo', 'admin_token', $token);

        return ['password' => $newPassword, 'token' => $token];
    }

    /**
     * Setzt das Passwort eines Matomo-Benutzers direkt in der Datenbank.
     *
     * @param bool $requireSuperuser nur Superuser zulassen (Admin-Reset)
     * @param bool $revokeTokens alle Tokens des Benutzers verwerfen
     * @throws Exception
     */
    public static function setPassword(string $login, string $newPassword, bool $requireSuperuser = false, bool $revokeTokens = false): void
    {
        if (!self::isAvailable()) {
            throw new Exception('Nur bei lokaler Matomo-Installation mit lesbarer config/config.ini.php möglich');
        }
        $login = trim($login);
        if ('' === $login) {
            throw new Exception('Kein Matomo-Benutzername angegeben');
        }
        if (strlen($newPassword) < 8) {
            throw new Exception('Das Passwort muss mindestens 8 Zeichen haben');
        }
        if (strlen(count_chars($newPassword, 3)) < 2) {
            throw new Exception('Das Passwort ist zu schwach');
        }

        $cliError = self::setPasswordViaCli($login, $newPassword, $requireSuperuser, $revokeTokens);
        if (null === $cliError) {
            return;
        }
        if (!class_exists(PDO::class)) {
            throw new Exception($cliError);
        }

        try {
            self::setPasswordViaDatabase($login, $newPassword, $requireSuperuser, $revokeTokens);
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . ' – Matomo-CLI: ' . $cliError);
        }
    }

    /**
     * Passwort über Matomos eigenen Bootstrap setzen (bin/matomo-user-password.php).
     *
     * @return string|null Fehlertext, null bei Erfolg
     */
    private static function setPasswordViaCli(string $login, string $newPassword, bool $requireSuperuser, bool $revokeTokens): ?string
    {
        $reason = MatomoCli::unavailableReason();
        if ('' !== $reason) {
            return $reason;
        }
        $args = [rex_addon::get('matomo')->getPath('bin/matomo-user-password.php'), MatomoCli::matomoDir(), $login];
        if ($requireSuperuser) {
            $args[] = '--require-superuser';
        }
        if ($revokeTokens) {
            $args[] = '--revoke-tokens';
        }
        $result = MatomoCli::run($args, ['MATOMO_NEW_PASSWORD' => $newPassword]);
        if (0 === $result['code'] && str_contains($result['stdout'], 'OK')) {
            return null;
        }
        $message = trim($result['stdout'] . "\n" . $result['stderr']);
        return '' !== $message ? substr($message, 0, 500) : 'Exit-Code ' . $result['code'];
    }

    /**
     * Passwort direkt in der Matomo-Datenbank setzen (Fallback ohne CLI).
     *
     * @throws Exception
     */
    private static function setPasswordViaDatabase(string $login, string $newPassword, bool $requireSuperuser, bool $revokeTokens): void
    {
        $config = self::overrideConfig() ?? self::readConfig();
        $pdo = self::connect($config);
        $prefix = $config['tables_prefix'];

        $stmt = $pdo->prepare('SELECT superuser_access FROM `' . $prefix . 'user` WHERE login = ?');
        $stmt->execute([$login]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $row) {
            throw new Exception('Matomo-Benutzer "' . $login . '" nicht gefunden');
        }
        if ($requireSuperuser && 1 !== (int) $row['superuser_access']) {
            throw new Exception('"' . $login . '" ist kein Matomo-Superuser; nur Superuser können hier zurückgesetzt werden');
        }

        // Matomo: password_hash(md5($password)) – siehe UsersManager::getPasswordHash() + Auth\Password::hash()
        $hash = password_hash(md5($newPassword), self::algorithm($config['password_hash_algorithm']));

        $pdo->prepare('UPDATE `' . $prefix . 'user` SET password = ?, ts_password_modified = NOW() WHERE login = ?')
            ->execute([$hash, $login]);
        if ($revokeTokens) {
            $pdo->prepare('DELETE FROM `' . $prefix . 'user_token_auth` WHERE login = ?')->execute([$login]);
        }
    }

    /**
     * Liest config.ini.php so wie Matomos IniReader: parse_ini_string im Normalmodus
     * (löst die von Matomo mit addcslashes maskierten Anführungszeichen und Backslashes
     * auf), bei Parserfehlern zeilenweise mit manueller Demaskierung.
     *
     * @return array{host: string, port: int, unix_socket: string, username: string, password: string, dbname: string, tables_prefix: string, charset: string, password_hash_algorithm: string}
     * @throws Exception
     */
    private static function readConfig(): array
    {
        $raw = (string) file_get_contents(self::configFile());
        // erste Zeile ist der PHP-Exit-Schutz
        $raw = (string) preg_replace('/^;.*\R/', '', $raw, 1);

        $ini = @parse_ini_string($raw, true);
        if (false === $ini) {
            $ini = self::parseIniFallback($raw);
        }
        if (!isset($ini['database']) || !is_array($ini['database'])) {
            throw new Exception('config.ini.php enthält keinen [database]-Abschnitt');
        }
        $db = $ini['database'];
        $general = isset($ini['General']) && is_array($ini['General']) ? $ini['General'] : [];

        return [
            'host' => (string) ($db['host'] ?? 'localhost'),
            'port' => (int) ($db['port'] ?? 3306),
            'unix_socket' => (string) ($db['unix_socket'] ?? ''),
            'username' => (string) ($db['username'] ?? ''),
            'password' => (string) ($db['password'] ?? ''),
            'dbname' => (string) ($db['dbname'] ?? ''),
            'tables_prefix' => (string) ($db['tables_prefix'] ?? 'matomo_'),
            'charset' => (string) ($db['charset'] ?? 'utf8mb4'),
            'password_hash_algorithm' => (string) ($general['password_hash_algorithm'] ?? 'default'),
        ];
    }

    /**
     * Zeilenweiser Ersatzparser: key = "wert" mit \" und \\ als Maskierung (Matomo IniWriter).
     *
     * @return array<string, array<string, string>>
     */
    private static function parseIniFallback(string $raw): array
    {
        $result = [];
        $section = '';
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line || ';' === $line[0]) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $section = $m[1];
                continue;
            }
            if (!preg_match('/^([A-Za-z0-9_.]+)(?:\[\])?\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (strlen($value) >= 2 && '"' === $value[0] && '"' === substr($value, -1)) {
                $value = stripcslashes(substr($value, 1, -1));
            }
            $result[$section][$m[1]] = $value;
        }
        return $result;
    }

    /**
     * Verbindet wie Matomos PDO-Adapter (unix_socket vor host + port). Scheitert das,
     * wird der jeweils andere Weg probiert: "localhost" bedeutet für MySQL den Socket,
     * 127.0.0.1 TCP – je nach Rechtevergabe klappt nur einer davon.
     *
     * @param array{host: string, port: int, unix_socket: string, username: string, password: string, dbname: string, tables_prefix: string, charset: string, password_hash_algorithm: string} $config
     * @throws Exception
     */
    private static function connect(array $config): PDO
    {
        $port = $config['port'] > 0 ? $config['port'] : 3306;
        $base = 'mysql:dbname=' . $config['dbname'] . ';charset=' . $config['charset'];
        $attempts = [];
        if ('' !== $config['unix_socket']) {
            $attempts['socket ' . $config['unix_socket']] = $base . ';unix_socket=' . $config['unix_socket'];
        } elseif (str_starts_with($config['host'], '/')) {
            $attempts['socket ' . $config['host']] = $base . ';unix_socket=' . $config['host'];
        } else {
            $attempts[$config['host'] . ':' . $port] = $base . ';host=' . $config['host'] . ';port=' . $port;
        }
        if (in_array($config['host'], ['localhost', ''], true) && '' === $config['unix_socket']) {
            $attempts['127.0.0.1:' . $port] = $base . ';host=127.0.0.1;port=' . $port;
        } elseif ('127.0.0.1' === $config['host']) {
            $attempts['localhost (Socket)'] = $base . ';host=localhost';
        }

        $errors = [];
        foreach ($attempts as $label => $dsn) {
            try {
                return new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            } catch (\PDOException $e) {
                $errors[] = $label . ': ' . $e->getMessage();
            }
        }

        $source = null !== self::overrideConfig() ? 'manuelle Zugangsdaten aus der Konfiguration' : 'Zugangsdaten aus ' . self::configFile();
        throw new Exception('Verbindung zur Matomo-Datenbank fehlgeschlagen (' . $source . ', Benutzer ' . $config['username'] . ', Datenbank ' . $config['dbname'] . ', Passwortlänge ' . strlen($config['password']) . '). Versucht: ' . implode(' | ', $errors));
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
