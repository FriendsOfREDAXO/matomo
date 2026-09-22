<?php

namespace FriendsOfRedaxo\Matomo;

use rex;
use rex_socket;
use rex_socket_exception;
use rex_dir;
use rex_file;
use rex_addon;
use rex_config;
use rex_url;
use rex_request;
use rex_string;
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
        $this->matomo_url = rtrim(trim($matomo_url), '/');
        $this->admin_token = trim($admin_token);
        $this->user_token = '' !== trim((string) $user_token) ? trim((string) $user_token) : trim($admin_token);
    }

    /**
     * Prüft, ob das konfigurierte Admin-Token Superuser-Rechte hat.
     *
     * @return bool true, wenn Superuser-Zugriff vorhanden ist
     * @throws Exception bei API-Fehlern
     */
    public function hasSuperUserAccess(): bool
    {
        $result = $this->apiCall('UsersManager.hasSuperUserAccess');

        if (is_array($result) && isset($result['value'])) {
            $result = $result['value'];
        }

        return self::toBool($result);
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
        return self::request($this->matomo_url, $method, $params, $use_user_token ? $this->user_token : $this->admin_token);
    }

    /**
     * Roher API-Request. Ohne Token nur für Methoden, die Matomo anonym erlaubt
     * (z.B. UsersManager.createAppSpecificTokenAuth mit Login + Passwort).
     *
     * @param array<string, mixed> $params
     * @return mixed
     * @throws Exception
     */
    private static function request(string $matomo_url, string $method, array $params, ?string $token)
    {
        $params['module'] = 'API';
        $params['method'] = $method;
        $params['format'] = 'json';
        if (null !== $token && '' !== $token) {
            $params['token_auth'] = $token;
        }

        try {
            $socket = rex_socket::factoryUrl(rtrim(trim($matomo_url), '/') . '/index.php');

            if (!self::verifySsl()) {
                $socket->setOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
            }

            $socket->setTimeout(max(1, (int) rex_config::get('matomo', 'api_timeout', 30)));

            $response = $socket->doPost($params);

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

            if (is_array($data) && isset($data['result']) && $data['result'] === 'error') {
                throw new Exception($data['message'] ?? 'Unbekannter API-Fehler');
            }

            return $data;
        } catch (rex_socket_exception $e) {
            throw new Exception('Socket-Fehler: ' . $e->getMessage());
        }
    }

    /**
     * SSL-Zertifikate prüfen? Standard: ja. Nur für selbstsignierte Zertifikate deaktivieren.
     */
    public static function verifySsl(): bool
    {
        return (bool) rex_config::get('matomo', 'verify_ssl', true);
    }

    /**
     * Erzeugt ein App-Token aus Matomo-Login und Passwort. Matomo erlaubt diesen
     * Aufruf ohne bestehendes Token, das Passwort wird nicht gespeichert.
     *
     * @throws Exception bei falschen Zugangsdaten oder API-Fehlern
     */
    public static function createTokenWithCredentials(string $matomo_url, string $login, string $password, string $description, int $expireHours = 0): string
    {
        $params = [
            'userLogin' => $login,
            'passwordConfirmation' => $password,
            'description' => $description,
        ];
        if ($expireHours > 0) {
            $params['expireHours'] = $expireHours;
        }
        $result = self::request($matomo_url, 'UsersManager.createAppSpecificTokenAuth', $params, null);

        $token = is_array($result) ? ($result['value'] ?? '') : '';
        if (!is_string($token) || '' === $token) {
            throw new Exception('Matomo hat kein Token zurückgegeben');
        }

        return $token;
    }

    /**
     * Alle Matomo-Benutzer (benötigt Superuser-Token).
     *
     * @return list<array{login: string, email: string, superuser_access: bool}>
     * @throws Exception
     */
    public function getUsers(): array
    {
        $result = $this->apiCall('UsersManager.getUsers');
        $users = [];
        foreach (is_array($result) ? $result : [] as $row) {
            if (!is_array($row) || !isset($row['login'])) {
                continue;
            }
            $users[] = [
                'login' => (string) $row['login'],
                'email' => (string) ($row['email'] ?? ''),
                'superuser_access' => !empty($row['superuser_access']),
            ];
        }
        return $users;
    }

    /**
     * @throws Exception
     */
    public function userExists(string $login): bool
    {
        $result = $this->apiCall('UsersManager.userExists', ['userLogin' => $login]);
        return self::toBool(is_array($result) ? ($result['value'] ?? false) : $result);
    }

    /**
     * Legt einen Matomo-Benutzer an und gibt ihm den gewünschten Zugriff auf alle Websites.
     * Per Token-Auth verlangt Matomo dafür keine Passwortbestätigung.
     *
     * @param string $access view|write|admin
     * @param int|string $idSites Site-ID(s), kommagetrennt, oder 'all'
     * @throws Exception
     */
    public function addUser(string $login, string $password, string $email, string $access = 'view', $idSites = 'all'): void
    {
        $this->apiCall('UsersManager.addUser', [
            'userLogin' => $login,
            'password' => $password,
            'email' => $email,
        ]);
        $this->setUserAccess($login, $access, $idSites);
    }

    /**
     * @param string $access view|write|admin|noaccess
     * @param int|string $idSites Site-ID(s) oder 'all'
     * @throws Exception
     */
    public function setUserAccess(string $login, string $access, $idSites = 'all'): void
    {
        $this->apiCall('UsersManager.setUserAccess', [
            'userLogin' => $login,
            'access' => $access,
            'idSites' => $idSites,
        ]);
    }

    /**
     * Passwort eines Benutzers ändern. Matomo verlangt dafür das aktuelle Passwort des
     * aufrufenden Benutzers; aufgerufen mit dem Token des Benutzers selbst ist das sein eigenes.
     *
     * @throws Exception
     */
    public function updateUserPassword(string $login, string $newPassword, string $currentPassword): void
    {
        $this->apiCall('UsersManager.updateUser', [
            'userLogin' => $login,
            'password' => $newPassword,
            'passwordConfirmation' => $currentPassword,
        ]);
    }

    /**
     * @throws Exception
     */
    public function deleteUser(string $login): void
    {
        $this->apiCall('UsersManager.deleteUser', ['userLogin' => $login]);
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }
        return false;
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
     * Website umbenennen
     *
     * @throws Exception bei API-Fehlern
     */
    public function renameSite(int $site_id, string $name): void
    {
        $this->apiCall('SitesManager.updateSite', [
            'idSite' => $site_id,
            'siteName' => $name,
        ]);
    }

    /**
     * Sites, deren Name noch ein YRewrite-Titelschema ist (Platzhalter wie %T),
     * bekommen ihren Host als Namen. Frühere Versionen haben so importiert.
     *
     * @param array<int, array<string, mixed>> $sites
     * @return list<array{id: int, old: string, new: string}> umbenannte Sites
     */
    public function repairPlaceholderSiteNames(array &$sites): array
    {
        $renamed = [];
        foreach ($sites as $i => $site) {
            $name = (string) ($site['name'] ?? '');
            $host = (string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST);
            if (!str_contains($name, '%') || '' === $host) {
                continue;
            }
            $this->renameSite((int) $site['idsite'], $host);
            $sites[$i]['name'] = $host;
            $renamed[] = ['id' => (int) $site['idsite'], 'old' => $name, 'new' => $host];
        }
        return $renamed;
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
        $serverSideTracking = (bool) rex_config::get('matomo', 'server_side_tracking', false);
        $eventTrackingJs = (bool) rex_config::get('matomo', 'event_tracking_js', false);
        
        // URLs bestimmen
        if ($use_proxy) {
            $tracker_url = $this->buildProxyUrl('matomo.php');
            $js_url = $this->buildProxyUrl('matomo.js');
        } else {
            // Direkte Matomo-URLs
            $tracker_url = $matomo_url . '/matomo.php';
            $js_url = $matomo_url . '/matomo.js';
        }
        
        $async = $async_tracking ? ' async defer' : '';
        $sections = [];

        if (!$serverSideTracking) {
            $sections[] = $this->buildClientTrackingSnippet($site_id, $tracker_url, $js_url, $async);
        }

        if ($eventTrackingJs) {
            $sections[] = $this->buildManualBrowserEventSnippet();
        }

        if ([] === $sections) {
            $sections[] = $this->buildNoSnippetRequiredMessage();
        }
        
        return implode("\n\n", $sections);
    }

    /**
     * Reiner Tracking-Code (ohne Erklärtext) für Consent-Tools und Templates.
     *
     * @param array<string, int> $hostSiteMap Hostname => Site-ID; wenn gesetzt, wählt der
     *                                        Code die Site-ID zur Laufzeit anhand von location.hostname
     */
    public function trackingSnippet(int $site_id, bool $use_proxy = false, array $hostSiteMap = []): string
    {
        if ($use_proxy) {
            $tracker_url = $this->buildProxyUrl('matomo.php');
            $js_url = $this->buildProxyUrl('matomo.js');
        } else {
            $tracker_url = $this->matomo_url . '/matomo.php';
            $js_url = $this->matomo_url . '/matomo.js';
        }

        return $this->buildTrackingHtml($site_id, $tracker_url, $js_url, ' async defer', $hostSiteMap);
    }

    private function buildClientTrackingSnippet(int $site_id, string $tracker_url, string $js_url, string $async): string
    {
        return "=== 1) Matomo Basis-Tracking ===\nDiesen Block in Consent-Manager oder Template einbinden.\n\n"
            . $this->buildTrackingHtml($site_id, $tracker_url, $js_url, $async);
    }

    /**
     * @param array<string, int> $hostSiteMap
     */
    private function buildTrackingHtml(int $site_id, string $tracker_url, string $js_url, string $async, array $hostSiteMap = []): string
    {
        $siteIdExpr = "'{$site_id}'";
        if ([] !== $hostSiteMap) {
            $map = json_encode(array_map('strval', $hostSiteMap), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $siteIdExpr = "({$map})[location.hostname] || '{$site_id}'";
        }

        return <<<JS
<!-- Matomo -->
<script{$async}>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
    (function() {
        _paq.push(['setTrackerUrl', '{$tracker_url}']);
    _paq.push(['setSiteId', {$siteIdExpr}]);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src='{$js_url}'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->
JS;
    }

    /**
         * Optionaler Snippet fuer manuelles Browser-Event-Tracking.
         * Wird bewusst nicht automatisch injiziert, sondern nur als Copy-Vorlage ausgegeben.
     */
    private function buildManualBrowserEventSnippet(): string
    {
        $endpoint = $this->buildFrontendApiUrl('matomo_event');
        $eventsJs = $this->buildFrontendAssetUrl('matomo-events.js');

        return <<<JS
=== 2) Browser-Event-Tracking ===
Diesen Block zusaetzlich manuell einbinden, wenn Downloads, Outbound-Links und Formular-Events erfasst werden sollen.

<script>
  window.MatomoEventsConfig = {
    endpoint: '{$endpoint}'
  };
</script>
<script defer src="{$eventsJs}"></script>
JS;
    }

        private function buildNoSnippetRequiredMessage(): string
        {
                return <<<TXT
=== Kein Frontend-Code erforderlich ===
Server-seitiges Tracking ist aktiv und Browser-Event-Tracking ist deaktiviert.
Fuer diese Konfiguration muss kein zusaetzlicher Code in Template oder Consent-Manager eingebunden werden.
TXT;
        }

    /**
     * Erzeugt eine Frontend-Asset-URL fuer Addon-Dateien mit korrektem Webroot.
     */
    private function buildFrontendAssetUrl(string $assetFile): string
    {
        $frontendIndex = $this->resolveFrontendIndexUrl();

        if (str_ends_with($frontendIndex, '/index.php')) {
            $base = substr($frontendIndex, 0, -strlen('/index.php'));
        } else {
            $base = rtrim(dirname($frontendIndex), '/');
        }

        return rtrim($base, '/') . '/assets/addons/matomo/' . ltrim($assetFile, '/');
    }

    /**
     * Erzeugt eine Frontend-API-URL mit korrektem Webroot (auch bei Unterordner-Installation).
     */
    private function buildFrontendApiUrl(string $apiCall): string
    {
        $query = rex_string::buildQuery([
            'rex-api-call' => $apiCall,
        ]);

        return $this->resolveFrontendIndexUrl() . '?' . $query;
    }

    /**
     * Loest den Frontend-Indexpfad zu einer Webroot-korrekten URL auf.
     */
    private function resolveFrontendIndexUrl(): string
    {
        $frontendIndex = rex_url::frontend('index.php');

        if (preg_match('@^https?://@i', $frontendIndex)) {
            return $frontendIndex;
        }

        if (str_starts_with($frontendIndex, './') || str_starts_with($frontendIndex, '../')) {
            $scriptName = rex_request::server('SCRIPT_NAME', 'string', '');
            if ('' !== $scriptName) {
                $backendDir = dirname($scriptName);
                $frontendBase = dirname($backendDir);
                $frontendIndex = rtrim($frontendBase, '/') . '/index.php';
            }
        }

        if (!str_starts_with($frontendIndex, '/')) {
            $frontendIndex = '/' . ltrim($frontendIndex, '/');
        }

        return $frontendIndex;
    }

    /**
     * Baut eine Proxy-URL mit korrektem Webroot und rohen Query-Parametern.
     */
    private function buildProxyUrl(string $file): string
    {
        $query = rex_string::buildQuery([
            'rex-api-call' => 'matomo_proxy',
            'file' => $file,
        ]);

        return $this->resolveFrontendIndexUrl() . '?' . $query;
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
            
            if (!self::verifySsl()) {
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
     * Mehrere Reports in einem Aufruf (API.getBulkRequest). Jeder Eintrag ist ein
     * Parameter-Array mit method, idSite, period, date usw.; die Antworten kommen in
     * derselben Reihenfolge zurück.
     *
     * @param list<array<string, mixed>> $requests
     * @return list<mixed>
     * @throws Exception bei API-Fehlern
     */
    public function bulk(array $requests): array
    {
        if ([] === $requests) {
            return [];
        }
        $urls = [];
        foreach ($requests as $request) {
            $urls[] = http_build_query($request, '', '&');
        }
        $result = $this->apiCall('API.getBulkRequest', ['urls' => $urls]);
        if (!is_array($result)) {
            throw new Exception('Unerwartete Antwort auf Bulk-Request');
        }
        return array_values($result);
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
                'title' => self::domainTitle($domain, $name),
                'host' => $domain->getHost()
            ];
        }
        
        return $domains;
    }
    
    /**
     * YRewrites "Titel" ist das Schema für Seitentitel (z.B. "%T / %SN"), kein Anzeigename.
     * Als Matomo-Site-Name dient deshalb der Host.
     */
    private static function domainTitle(\rex_yrewrite_domain $domain, string $name): string
    {
        return '' !== $domain->getHost() ? $domain->getHost() : $name;
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
        
        $yrewrite_hosts = array_map([self::class, 'normalizeHost'], array_column(self::getAvailableDomains(), 'host'));

        // Default Domain auch erlauben
        $default_domain = \rex_yrewrite::getDomainByName('default');
        if (null !== $default_domain) {
            $yrewrite_hosts[] = self::normalizeHost($default_domain->getHost());
        }

        $filtered_sites = [];
        foreach ($matomo_sites as $site) {
            $site_host = self::normalizeHost((string) parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST));
            if ('' !== $site_host && in_array($site_host, $yrewrite_hosts, true)) {
                $filtered_sites[] = $site;
            }
        }

        return $filtered_sites;
    }
    
    /**
     * Hostvergleich ohne Port, Schema und "www." (YRewrite-Domains können Ports enthalten).
     */
    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('~^https?://~', '', $host);
        $host = (string) preg_replace('~[:/].*$~', '', $host);
        return (string) preg_replace('/^www\./', '', $host);
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
        
        $host = self::normalizeHost($host);
        foreach ($yrewrite_domains as $name => $domain) {
            if (self::normalizeHost($domain->getHost()) === $host) {
                return [
                    'name' => $name,
                    'title' => self::domainTitle($domain, $name),
                ];
            }
        }
        
        return null;
    }
}