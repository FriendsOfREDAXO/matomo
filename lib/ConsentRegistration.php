<?php

namespace FriendsOfRedaxo\Matomo;

use Exception;
use rex;
use rex_addon;
use rex_clang;
use rex_config;
use rex_sql;

/**
 * Registriert Matomo als Dienst im Consent-Tool (consent_kit oder consent_manager),
 * sofern eines installiert ist. Der Tracking-Code wird aus den Addon-Einstellungen
 * erzeugt (inkl. Proxy-Option), Cookie-Angaben stammen aus Matomos Standard-Cookies.
 */
class ConsentRegistration
{
    public const SERVICE_KEY = 'matomo';

    /** @var list<array{name: string, duration: string, purpose_de: string, purpose_en: string, unit: string, value: int}> */
    private const COOKIES = [
        ['name' => '_pk_id*', 'duration' => '13 Monate', 'unit' => 'months', 'value' => 13, 'purpose_de' => 'Speichert eine eindeutige Besucher-ID.', 'purpose_en' => 'Stores a unique visitor ID.'],
        ['name' => '_pk_ses*', 'duration' => '30 Minuten', 'unit' => 'minutes', 'value' => 30, 'purpose_de' => 'Speichert vorübergehend Daten zum aktuellen Besuch.', 'purpose_en' => 'Temporarily stores data for the visit.'],
        ['name' => '_pk_ref*', 'duration' => '6 Monate', 'unit' => 'months', 'value' => 6, 'purpose_de' => 'Speichert die Herkunft (Referrer) des Besuchers.', 'purpose_en' => 'Stores the referrer that brought the visitor.'],
    ];

    /**
     * @return list<string> Schlüssel der verfügbaren Consent-Tools
     */
    public static function availableTools(): array
    {
        $tools = [];
        foreach (['consent_kit', 'consent_manager'] as $tool) {
            if (rex_addon::exists($tool) && rex_addon::get($tool)->isAvailable()) {
                $tools[] = $tool;
            }
        }
        return $tools;
    }

    /**
     * Ist Matomo im Tool bereits registriert?
     */
    public static function isRegistered(string $tool): bool
    {
        if ('consent_kit' === $tool) {
            return \KLXM\ConsentKit\Repository::serviceKeyExists(self::SERVICE_KEY);
        }
        if ('consent_manager' === $tool) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT pid FROM ' . rex::getTable('consent_manager_cookie') . ' WHERE uid = ? LIMIT 1', [self::SERVICE_KEY]);
            return $sql->getRows() > 0;
        }
        return false;
    }

    /**
     * Legt den Dienst an bzw. aktualisiert ihn.
     *
     * @param array<int, array<string, mixed>> $sites Matomo-Sites (idsite, name, main_url)
     * @throws Exception
     */
    public static function register(string $tool, MatomoApi $api, int $siteId, array $sites): void
    {
        $useProxy = (bool) rex_config::get('matomo', 'proxy_enabled', false);
        $snippet = $api->trackingSnippet($siteId, $useProxy);

        if ('consent_kit' === $tool) {
            self::registerConsentKit($api, $siteId, $sites, $useProxy, $snippet);
            return;
        }
        if ('consent_manager' === $tool) {
            self::registerConsentManager($snippet);
            return;
        }
        throw new Exception('Unbekanntes Consent-Tool: ' . $tool);
    }

    /**
     * consent_kit: Dienst aus dem mitgelieferten Matomo-Preset, Parameter aus den
     * Addon-Einstellungen. Je consent_kit-Domain, die einer Matomo-Site entspricht,
     * entsteht eine Variante mit der passenden Site-ID.
     *
     * @param array<int, array<string, mixed>> $sites
     * @throws Exception
     */
    private static function registerConsentKit(MatomoApi $api, int $siteId, array $sites, bool $useProxy, string $snippet): void
    {
        $matomoUrl = rtrim((string) rex_config::get('matomo', 'matomo_url', ''), '/') . '/';

        $preset = \KLXM\ConsentKit\PresetRepository::toService(self::SERVICE_KEY);
        if (null === $preset) {
            throw new Exception('consent_kit: Matomo-Preset nicht gefunden');
        }
        [$data, $items] = $preset;

        $existingId = 0;
        foreach (\KLXM\ConsentKit\Repository::services() as $service) {
            if ($service['key'] === self::SERVICE_KEY) {
                $existingId = $service['id'];
                // Vom Redakteur gepflegte Felder behalten
                foreach (['group_id', 'name', 'provider', 'privacy_url', 'description', 'domain_ids', 'events', 'gcm_signals'] as $field) {
                    $data[$field] = $service[$field];
                }
                if ($service['status']) {
                    $data['status'] = 1;
                }
            }
        }

        if ('' === (string) $data['provider']) {
            $data['provider'] = rex::getServerName();
        }
        $data['params'] = ['matomo_url' => $matomoUrl, 'site_id' => (string) $siteId];

        // Über den REDAXO-Proxy geladenes matomo.js passt nicht zur Preset-Vorlage,
        // dann wird der fertige Code des Addons hinterlegt.
        if ($useProxy) {
            $data['html_head'] = $snippet;
        }

        $variants = [];
        if (!$useProxy) {
            $siteByHost = [];
            foreach ($sites as $site) {
                $host = \KLXM\ConsentKit\Repository::normalizeHost((string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST));
                if ('' !== $host) {
                    $siteByHost[$host] = (int) $site['idsite'];
                }
            }
            foreach (\KLXM\ConsentKit\Repository::domains() as $domain) {
                $host = \KLXM\ConsentKit\Repository::normalizeHost($domain['host']);
                if (isset($siteByHost[$host]) && $siteByHost[$host] !== $siteId) {
                    $variants[] = ['domain_id' => $domain['id'], 'clang' => '', 'params' => ['site_id' => (string) $siteByHost[$host]]];
                }
            }
        }

        \KLXM\ConsentKit\Repository::saveService($existingId, $data, $items, $variants);
    }

    /**
     * consent_manager: ein Cookie-Datensatz je Sprache (uid "matomo") in der Gruppe
     * "statistics"; fehlt die Gruppe, wird sie angelegt.
     */
    private static function registerConsentManager(string $snippet): void
    {
        $cookieTable = rex::getTable('consent_manager_cookie');
        $groupTable = rex::getTable('consent_manager_cookiegroup');
        $now = date('Y-m-d H:i:s');
        $user = rex::getUser()?->getLogin() ?? 'matomo';

        $definition = '';
        foreach (self::COOKIES as $cookie) {
            $definition .= "-\n name: " . $cookie['name'] . "\n time: \"" . $cookie['duration'] . "\"\n desc: \"" . $cookie['purpose_de'] . "\"\n";
        }

        $cookieId = self::datasetId($cookieTable, self::SERVICE_KEY);
        $groupId = self::datasetId($groupTable, 'statistics');

        foreach (rex_clang::getAllIds() as $clangId) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT pid FROM ' . $cookieTable . ' WHERE uid = ? AND clang_id = ?', [self::SERVICE_KEY, $clangId]);
            $exists = $sql->getRows() > 0;

            $sql = rex_sql::factory()->setTable($cookieTable);
            $sql->setValue('script', $snippet);
            $sql->setValue('updateuser', $user);
            $sql->setValue('updatedate', $now);
            if ($exists) {
                $sql->setWhere(['uid' => self::SERVICE_KEY, 'clang_id' => $clangId])->update();
            } else {
                $sql->setValue('id', $cookieId);
                $sql->setValue('clang_id', $clangId);
                $sql->setValue('uid', self::SERVICE_KEY);
                $sql->setValue('service_name', 'Matomo');
                $sql->setValue('provider', rex::getServerName());
                $sql->setValue('provider_link_privacy', '');
                $sql->setValue('definition', rtrim($definition));
                $sql->setValue('script_unselect', '');
                $sql->setValue('placeholder_text', '');
                $sql->setValue('placeholder_image', '');
                $sql->setValue('createuser', $user);
                $sql->setValue('createdate', $now);
                $sql->insert();
            }

            $sql = rex_sql::factory();
            $sql->setQuery('SELECT pid, cookie FROM ' . $groupTable . ' WHERE uid = ? AND clang_id = ?', ['statistics', $clangId]);
            if ($sql->getRows() > 0) {
                $list = (string) $sql->getValue('cookie');
                if (!str_contains($list, '|' . self::SERVICE_KEY . '|')) {
                    $list = '|' . trim($list, '|') . '|' . self::SERVICE_KEY . '|';
                    $list = str_replace('||', '|', $list);
                    rex_sql::factory()->setTable($groupTable)
                        ->setValue('cookie', $list)
                        ->setValue('updateuser', $user)
                        ->setValue('updatedate', $now)
                        ->setWhere(['pid' => (int) $sql->getValue('pid')])
                        ->update();
                }
            } else {
                rex_sql::factory()->setTable($groupTable)
                    ->setValue('id', $groupId)
                    ->setValue('clang_id', $clangId)
                    ->setValue('domain', '')
                    ->setValue('uid', 'statistics')
                    ->setValue('prio', 2)
                    ->setValue('required', null)
                    ->setValue('name', 'Statistik')
                    ->setValue('description', 'Statistik-Cookies helfen zu verstehen, wie Besucher mit der Website interagieren.')
                    ->setValue('cookie', '|' . self::SERVICE_KEY . '|')
                    ->setValue('script', '')
                    ->setValue('createuser', $user)
                    ->setValue('updateuser', $user)
                    ->setValue('createdate', $now)
                    ->setValue('updatedate', $now)
                    ->insert();
            }
        }

        if (class_exists(\FriendsOfRedaxo\ConsentManager\Cache::class)) {
            \FriendsOfRedaxo\ConsentManager\Cache::forceWrite();
        }
    }

    /**
     * consent_manager nutzt eine sprachübergreifende Datensatz-ID neben dem Primärschlüssel.
     */
    private static function datasetId(string $table, string $uid): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id FROM ' . $table . ' WHERE uid = ? ORDER BY id LIMIT 1', [$uid]);
        if ($sql->getRows() > 0) {
            return (int) $sql->getValue('id');
        }
        $sql->setQuery('SELECT COALESCE(MAX(id), 0) + 1 AS next FROM ' . $table);
        return (int) $sql->getValue('next');
    }
}
