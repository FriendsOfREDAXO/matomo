<?php
/**
 * Setzt das Passwort eines Matomo-Benutzers über Matomos eigenen Bootstrap und dessen
 * UsersManager-API – mit Matomos eigener Datenbankverbindung, ohne die Zugangsdaten
 * selbst zu parsen. Wird vom REDAXO-Addon per PHP-CLI aufgerufen.
 *
 * Aufruf: php matomo-user-password.php <matomo-verzeichnis> <login> [--revoke-tokens] [--require-superuser]
 * Passwort über Umgebungsvariable MATOMO_NEW_PASSWORD (nicht in der Prozessliste sichtbar).
 * Exit-Code 0 = ok, sonst Fehlertext auf STDERR.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$matomoDir = rtrim((string) ($argv[1] ?? ''), '/');
if ('' === $matomoDir || !is_file($matomoDir . '/core/bootstrap.php')) {
    fwrite(STDERR, "Matomo-Verzeichnis nicht gefunden\n");
    exit(2);
}

define('PIWIK_DOCUMENT_ROOT', $matomoDir);
define('PIWIK_INCLUDE_PATH', $matomoDir);
define('PIWIK_USER_PATH', $matomoDir);
define('PIWIK_ENABLE_ERROR_HANDLER', false);
define('PIWIK_ENABLE_SESSION_START', false);

if (is_file($matomoDir . '/bootstrap.php')) {
    require_once $matomoDir . '/bootstrap.php';
}
require_once $matomoDir . '/core/bootstrap.php';

final class RedaxoSetPasswordCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('redaxo:set-password')
            ->addArgument('login', InputArgument::REQUIRED)
            ->addOption('revoke-tokens', null, InputOption::VALUE_NONE)
            ->addOption('require-superuser', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $login = (string) $input->getArgument('login');
        $password = (string) getenv('MATOMO_NEW_PASSWORD');
        if ('' === $password) {
            $output->writeln('Kein Passwort übergeben (MATOMO_NEW_PASSWORD)');
            return 3;
        }

        try {
            \Piwik\Access::doAsSuperUser(static function () use ($login, $password, $input): void {
                $api = \Piwik\Plugins\UsersManager\API::getInstance();
                if (!$api->userExists($login)) {
                    throw new \RuntimeException('Matomo-Benutzer "' . $login . '" nicht gefunden');
                }
                if ($input->getOption('require-superuser')) {
                    $user = $api->getUser($login);
                    if (empty($user['superuser_access'])) {
                        throw new \RuntimeException('"' . $login . '" ist kein Matomo-Superuser');
                    }
                }
                \Piwik\Plugins\UsersManager\API::$UPDATE_USER_REQUIRE_PASSWORD_CONFIRMATION = false;
                $api->updateUser($login, $password);
                if ($input->getOption('revoke-tokens')) {
                    \Piwik\Container\StaticContainer::get(\Piwik\Plugins\UsersManager\Model::class)->deleteAllTokensForUser($login);
                }
            });
        } catch (\Throwable $e) {
            $output->writeln($e->getMessage());
            return 4;
        }

        $output->writeln('OK');
        return 0;
    }
}

// Matomos Console-Anwendung bootstrappt Konfiguration, Datenbank und Plugins wie das
// Original-Script "console"; unser Kommando wird darüber ausgeführt.
$script = array_shift($argv);
array_shift($argv); // Matomo-Verzeichnis
$_SERVER['argv'] = array_merge([$script, 'redaxo:set-password'], $argv);
$_SERVER['argc'] = count($_SERVER['argv']);

\Piwik\FrontController::setUpSafeMode();
$console = new \Piwik\Console();
$console->setAutoExit(false);
$console->add(new RedaxoSetPasswordCommand());
exit($console->run());
