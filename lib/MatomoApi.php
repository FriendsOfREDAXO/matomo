<?php

namespace FriendsOfRedaxo\Matomo;

use rex_socket;
use rex_socket_exception;
use rex_dir;
use rex_file;
use rex_addon;
use rex_config;
use rex_url;
use rex_request;
use Exception;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Matomo API Client mit rex_socket
 * 
 * Bietet Zugriff auf die Matomo HTTP API für Site-Management, 
 * Statistiken und Download-Funktionalitäten.
 * 
 * @package FriendsOfRedaxo\Matomo
 * @author Friends Of REDAXO
 */
class MatomoApi
{
    /** @var string Basis-URL der Matomo-Installation */
    private string $matomo_url;
    
    /** @var string Admin-Token für API-Zugriff */
    private string $admin_token;
    
    /** @var string User-Token für Statistik-Zugriff */
    private string $user_token;

    /**
     * Konstruktor
     * 
     * @param string $matomo_url Basis-URL der Matomo-Installation
     * @param string $admin_token Admin-Token für API-Zugriff
     * @param string|null $user_token Optional: User-Token, falls nicht gesetzt wird Admin-Token verwendet
     */
    public function __construct(string $matomo_url, string $admin_token, ?string $user_token = null)
    {
        $this->matomo_url = rtrim($matomo_url, '/');
        $this->admin_token = $admin_token;
        $this->user_token = $user_token ?? $admin_token;
    }

    /**
     * API-Aufruf mit rex_socket
     * 
     * @param string $method API-Methode (z.B. 'SitesManager.getSites')
     * @param array<string, mixed> $params zusätzliche Parameter für den API-Aufruf
     * @param bool $use_user_token true = User-Token verwenden, false = Admin-Token verwenden
     * @return mixed API-Antwort als Array oder skalarer Wert
     * @throws Exception bei HTTP-Fehlern, JSON-Parsing-Fehlern oder API-Fehlern
     */
    private function apiCall(string $method, array $params = [], bool $use_user_token = false)
    {
        $params['module'] = 'API';
        $params['method'] = $method;
        $params['format'] = 'json';
        $params['token_auth'] = $use_user_token ? $this->user_token : $this->admin_token;

        try {
            // Socket-Verbindung erstellen
            $socket = rex_socket::factoryUrl($this->matomo_url . '/index.php');
            
            // SSL-Verifizierung aus Config auslesen (Standard: true für Sicherheit)
            $verify_ssl = (bool) rex_config::get('matomo', 'verify_ssl', true);
            
            // SSL-Optionen setzen
            if (false === $verify_ssl) {
                // Nur bei deaktivierter Verifizierung (z.B. selbstsignierte Zertifikate)
                $socket->setOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
            }
            
            // Timeout setzen
            $socket->setTimeout(10);
            
            // POST-Request senden
            $response = $socket->doPost($params);
            
            // Antwort prüfen
            if (!$response->isSuccessful()) {
                throw new Exception('HTTP Fehler: ' . $response->getStatusCode() . ' ' . $response->getStatusMessage());
            }
            
            $body = $response->getBody();
            
            if ('' === $body) {
                throw new Exception('Keine Antwort vom Matomo Server');
            }
            
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Ungültige JSON-Antwort: ' . substr($body, 0, 200));
            }
            
            if (isset($data['result']) && $data['result'] === 'error') {
                throw new Exception($data['message'] ?? 'Unbekannter API-Fehler');
            }
            
            return $data;
            
        } catch (rex_socket_exception $e) {
            throw new Exception('Socket-Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Alle Websites abrufen
     * 
     * @return array<int, array<string, mixed>> Liste aller Websites mit Admin-Zugriff
     * @throws Exception bei API-Fehlern
     */
    public function getSites(): array
    {
        $result = $this->apiCall('SitesManager.getSitesWithAdminAccess');
        return is_array($result) ? $result : [];
    }

    /**
     * Website hinzufügen
     * 
     * @param string $name Name der Website
     * @param string $url URL der Website
     * @return int|null Site-ID der neu erstellten Website oder null bei Fehler
     * @throws Exception bei API-Fehlern
     */
    public function addSite(string $name, string $url): ?int
    {
        $result = $this->apiCall('SitesManager.addSite', [
            'siteName' => $name,
            'urls' => $url
        ]);

        if (is_array($result) && isset($result['value'])) {
            return is_numeric($result['value']) ? (int) $result['value'] : null;
        }
        
        return null;
    }

    /**
     * Website löschen
     * 
     * @param int $site_id Site-ID der zu löschenden Website
     * @return bool true bei erfolgreichem Löschen
     * @throws Exception bei API-Fehlern
     */
    public function deleteSite(int $site_id): bool
    {
        $result = $this->apiCall('SitesManager.deleteSite', [
            'idSite' => $site_id
        ]);

        return is_array($result) && isset($result['result']) && $result['result'] === 'success';
    }

    /**
     * Tracking Code für eine Website abrufen
     * 
     * @param int $site_id Site-ID der Website
     * @return string JavaScript Tracking-Code
     * @throws Exception bei API-Fehlern
     */
    public function getTrackingCode(int $site_id): string
    {
        $result = $this->apiCall('SitesManager.getJavascriptTag', [
            'idSite' => $site_id
        ]);

        if (is_array($result) && isset($result['value']) && is_string($result['value'])) {
            return $result['value'];
        }
        
        return '';
    }

    /**
     * Generiert optimierten Tracking-Code mit optionalem Proxy
     * 
     * @param int $site_id Site-ID der Website
     * @param bool $use_proxy Proxy verwenden (nutzt REDAXO API)
     * @param bool $async_tracking Asynchrones Tracking aktivieren
     * @return string JavaScript Tracking-Code
     */
    public function generateTrackingCode(int $site_id, bool $use_proxy = false, bool $async_tracking = true): string
    {
        $matomo_url = rtrim($this->matomo_url, '/');
        
        // URLs bestimmen
        if ($use_proxy) {
            // Proxy über REDAXO API
            $server = rex::getServer();
            if ($server !== '') {
                $base_url = rtrim($server, '/');
            } else {
                $base_url = '';
            }
            
            $tracker_url = $base_url . '/index.php?rex-api-call=matomo_proxy';
            $js_url = $base_url . '/index.php?rex-api-call=matomo_proxy&file=matomo.js';
        } else {
            // Direkte Matomo-URLs
            $tracker_url = $matomo_url . '/';
            $js_url = $matomo_url . '/matomo.js';
        }
        
        $async = $async_tracking ? ' async defer' : '';
        
        $code = <<<JS
<!-- Matomo -->
<script{$async}>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="{$tracker_url}";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '{$site_id}']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src='{$js_url}'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->
JS;
        
        return $code;
    }

    /**
     * Matomo herunterladen und entpacken mit rex_socket
     * 
     * @param string $target_path Zielpfad für die Matomo-Installation
     * @return bool true bei erfolgreichem Download und Installation
     * @throws Exception bei Download-Fehlern, ZIP-Fehlern oder Dateisystem-Fehlern
     */
    public static function downloadMatomo(string $target_path): bool
    {
        if (!is_dir($target_path)) {
            rex_dir::create($target_path);
        }

        $zip_file = $target_path . '/matomo-latest.zip';
        $download_url = 'https://builds.matomo.org/matomo-latest.zip';

        try {
            // Socket-Verbindung für Download
            $socket = rex_socket::factoryUrl($download_url);
            
            // SSL-Verifizierung aus Config auslesen (Standard: true)
            $verify_ssl = (bool) rex_config::get('matomo', 'verify_ssl', true);
            
            // SSL-Optionen setzen
            if (false === $verify_ssl) {
                $socket->setOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
            }
            
            // Längeren Timeout für Download
            $socket->setTimeout(120);
            
            // Redirects folgen (max 1 Redirect)
            $socket->followRedirects(1);
            
            // GET-Request senden
            $response = $socket->doGet();
            
            if (!$response->isSuccessful()) {
                throw new Exception('Download fehlgeschlagen: HTTP ' . $response->getStatusCode());
            }
            
            $content = $response->getBody();
            
            if (strlen($content) < 1000000) {
                throw new Exception('Download zu klein - wahrscheinlich fehlgeschlagen');
            }
            
            // Datei speichern mit rex_file
            if (!rex_file::put($zip_file, $content)) {
                throw new Exception('Konnte ZIP-Datei nicht speichern');
            }

        } catch (rex_socket_exception $e) {
            throw new Exception('Socket-Fehler beim Download: ' . $e->getMessage());
        }

        // ZIP entpacken
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive nicht verfügbar');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== TRUE) {
            throw new Exception('ZIP-Datei konnte nicht geöffnet werden');
        }

        $zip->extractTo($target_path);
        $zip->close();

        // Dateien aus matomo/ Unterordner nach oben verschieben
        $matomo_dir = $target_path . '/matomo';
        if (is_dir($matomo_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($matomo_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $target = $target_path . '/' . substr($file->getPathname(), strlen($matomo_dir) + 1);
                if ($file->isDir()) {
                    if (!is_dir($target)) {
                        rex_dir::create($target);
                    }
                } else {
                    $target_dir = dirname($target);
                    if (!is_dir($target_dir)) {
                        rex_dir::create($target_dir);
                    }
                    rename($file->getPathname(), $target);
                }
            }

            // Leeren matomo/ Ordner entfernen
            rex_dir::delete($matomo_dir);
        }

        // ZIP-Datei löschen
        rex_file::delete($zip_file);

        return true;
    }

    /**
     * Dashboard-Statistiken abrufen (verwendet User Token)
     * 
     * @param int $site_id Site-ID der Website
     * @param string $period Zeitraum ('day', 'week', 'month', 'year')
     * @param string $date Datum ('today', 'yesterday', 'YYYY-MM-DD')
     * @return array<int, mixed> Bulk-Request Ergebnisse für verschiedene Metriken
     * @throws Exception bei API-Fehlern
     */
    public function getDashboardData(int $site_id, string $period = 'day', string $date = 'today'): array
    {
        $params = [
            'idSite' => $site_id,
            'period' => $period,
            'date' => $date
        ];
        
        // Mehrere Metriken in einem Aufruf
        $result = $this->apiCall('API.getBulkRequest', [
            'urls' => [
                'VisitsSummary.get?' . http_build_query($params),
                'Actions.getPageUrls?' . http_build_query($params + ['flat' => 1, 'expanded' => 1]),
                'Referrers.getWebsites?' . http_build_query($params),
                'UserCountry.getCountry?' . http_build_query($params)
            ]
        ], true); // User Token verwenden
        
        return is_array($result) ? $result : [];
    }

    /**
     * Einfache Besucher-Statistiken abrufen
     * 
     * @param int $site_id Site-ID der Website
     * @param string $period Zeitraum ('day', 'week', 'month', 'year')
     * @param string $date Datum ('today', 'yesterday', 'YYYY-MM-DD')
     * @return array<string, mixed> Besucher-Statistiken (nb_visits, nb_actions, nb_users, etc.)
     * @throws Exception bei API-Fehlern
     */
    public function getVisitorStats(int $site_id, string $period = 'day', string $date = 'today'): array
    {
        $params = [
            'idSite' => $site_id,
            'period' => $period,
            'date' => $date
        ];
        
        $result = $this->apiCall('VisitsSummary.get', $params, true);
        return is_array($result) ? $result : [];
    }

    /**
     * Heutige Besucher abrufen (Convenience-Methode)
     * 
     * @param int $site_id Site-ID der Website
     * @return int Anzahl der heutigen Besucher
     * @throws Exception bei API-Fehlern
     */
    public function getVisitorsToday(int $site_id): int
    {
        $stats = $this->getVisitorStats($site_id, 'day', 'today');
        return (int) ($stats['nb_visits'] ?? 0);
    }

    /**
     * Top Seiten abrufen
     * 
     * @param int $site_id Site-ID der Website
     * @param string $period Zeitraum ('day', 'week', 'month', 'year')
     * @param string $date Datum ('today', 'yesterday', 'YYYY-MM-DD')
     * @param int $limit Maximale Anzahl Ergebnisse
     * @return array<int, array<string, mixed>> Liste der Top-Seiten mit URLs und Statistiken
     * @throws Exception bei API-Fehlern
     */
    public function getTopPages(int $site_id, string $period = 'week', string $date = 'today', int $limit = 5): array
    {
        $params = [
            'idSite' => $site_id,
            'period' => $period,
            'date' => $date,
            'filter_limit' => $limit,
            'flat' => 1,
            'expanded' => 1
        ];
        
        $result = $this->apiCall('Actions.getPageUrls', $params, true);
        return is_array($result) ? $result : [];
    }
}

/**
 * YRewrite Integration Helper
 * 
 * Hilfsfunktionen für die Integration mit dem YRewrite AddOn
 */
class YRewriteHelper
{
    /**
     * Prüft ob YRewrite AddOn verfügbar ist
     * 
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable();
    }
    
    /**
     * Holt alle YRewrite Domains (außer Default)
     * 
     * @return array<string, array{name: string, url: string, title: string, host: string}>
     */
    public static function getAvailableDomains(): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        
        $domains = [];
        $yrewrite_domains = \rex_yrewrite::getDomains();
        
        foreach ($yrewrite_domains as $name => $domain) {
            // Skip default domain
            if ($name === 'default') {
                continue;
            }
            
            $domains[$name] = [
                'name' => $name,
                'url' => $domain->getUrl(),
                'title' => $domain->getTitle() !== '' ? $domain->getTitle() : $name,
                'host' => $domain->getHost()
            ];
        }
        
        return $domains;
    }
    
    /**
     * Filtert Matomo Sites nach YRewrite Domains
     * 
     * @param array<int, array<string, mixed>> $matomo_sites
     * @return array<int, array<string, mixed>> gefilterte Sites
     */
    public static function filterMatomoSitesByYRewrite(array $matomo_sites): array
    {
        if (!self::isAvailable()) {
            return $matomo_sites;
        }
        
        $yrewrite_domains = self::getAvailableDomains();
        $yrewrite_hosts = array_column($yrewrite_domains, 'host');
        
        // Default Domain auch erlauben
        $default_domain = \rex_yrewrite::getDomainByName('default');
        if (null !== $default_domain) {
            $yrewrite_hosts[] = $default_domain->getHost();
        }
        
        $filtered_sites = [];
        
        foreach ($matomo_sites as $site) {
            $site_url = $site['main_url'] ?? '';
            $site_host = parse_url($site_url, PHP_URL_HOST);
            
            // Prüfe ob Site-Host in YRewrite Domains enthalten ist
            if (in_array($site_host, $yrewrite_hosts, true)) {
                $filtered_sites[] = $site;
            }
        }
        
        return $filtered_sites;
    }
    
    /**
     * Holt YRewrite Domain Info für einen Host
     * 
     * @param string $host
     * @return array{name: string, title: string}|null
     */
    public static function getDomainInfoByHost(string $host): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }
        
        $yrewrite_domains = \rex_yrewrite::getDomains();
        
        foreach ($yrewrite_domains as $name => $domain) {
            if ($domain->getHost() === $host) {
                return [
                    'name' => $name,
                    'title' => $domain->getTitle() !== '' ? $domain->getTitle() : $name
                ];
            }
        }
        
        return null;
    }
}