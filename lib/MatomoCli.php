<?php

namespace FriendsOfRedaxo\Matomo;

use rex_config;
use rex_path;

/**
 * Ruft bei lokaler Installation Matomos Konsole bzw. das mitgelieferte
 * bin/matomo-user-password.php per PHP-CLI auf.
 */
class MatomoCli
{
    /**
     * Lokales Matomo-Verzeichnis (leer, wenn extern oder nicht gefunden).
     */
    public static function matomoDir(): string
    {
        $path = trim((string) rex_config::get('matomo', 'matomo_path', ''), " /\\");
        if ('' === $path) {
            return '';
        }
        $dir = rtrim(rex_path::frontend($path), '/');
        return is_file($dir . '/core/bootstrap.php') && is_file($dir . '/config/config.ini.php') ? $dir : '';
    }

    public static function isAvailable(): bool
    {
        return '' !== self::matomoDir() && function_exists('proc_open') && null !== self::phpBinary();
    }

    /**
     * Warum die CLI nicht nutzbar ist (leer = nutzbar).
     */
    public static function unavailableReason(): string
    {
        if ('' === self::matomoDir()) {
            return 'kein lokales Matomo-Verzeichnis';
        }
        if (!function_exists('proc_open')) {
            return 'proc_open ist deaktiviert';
        }
        if (null === self::phpBinary()) {
            return 'kein PHP-CLI-Binary gefunden';
        }
        return '';
    }

    /**
     * PHP-CLI: gleiche Version wie der Webserver, u.a. Plesk-Pfade.
     */
    public static function phpBinary(): ?string
    {
        $candidates = [];
        if (!str_contains(PHP_BINARY, 'fpm') && !str_contains(PHP_BINARY, 'cgi')) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = PHP_BINDIR . '/php';
        $candidates[] = '/opt/plesk/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/bin/php';
        $candidates[] = '/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Führt ein PHP-Script mit Argumenten aus.
     *
     * @param list<string> $args Script und Argumente (ohne PHP-Binary)
     * @param array<string, string> $env zusätzliche Umgebungsvariablen
     * @return array{code: int, stdout: string, stderr: string}
     */
    public static function run(array $args, array $env = []): array
    {
        $reason = self::unavailableReason();
        if ('' !== $reason) {
            return ['code' => -1, 'stdout' => '', 'stderr' => $reason];
        }
        $cmd = array_merge([(string) self::phpBinary(), '-d', 'display_errors=stderr'], $args);
        $env += ['PATH' => (string) getenv('PATH'), 'HOME' => sys_get_temp_dir()];

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes, self::matomoDir(), $env);
        if (!is_resource($process)) {
            return ['code' => -1, 'stdout' => '', 'stderr' => 'Prozess konnte nicht gestartet werden (' . self::phpBinary() . ')'];
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 120);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * Matomo-Konsole (`console <command>`), z.B. plugin:deactivate.
     *
     * @param list<string> $args
     * @return array{code: int, stdout: string, stderr: string}
     */
    public static function console(array $args): array
    {
        return self::run(array_merge([self::matomoDir() . '/console'], $args, ['--no-interaction']));
    }

    /**
     * Deaktiviert ein Matomo-Plugin (idempotent).
     *
     * @return string|null Fehlertext, null bei Erfolg
     */
    public static function deactivatePlugin(string $plugin): ?string
    {
        $result = self::console(['plugin:deactivate', $plugin]);
        if (0 === $result['code']) {
            return null;
        }
        $message = trim($result['stdout'] . "\n" . $result['stderr']);
        return '' !== $message ? substr($message, 0, 500) : 'Exit-Code ' . $result['code'];
    }
}
