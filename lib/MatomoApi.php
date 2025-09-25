<?php

/**
 * Einfache Matomo API Klasse mit rex_socket
 */
class MatomoApi
{
    private $matomo_url;
    private $admin_token;
    private $user_token;

    public function __construct($matomo_url, $admin_token, $user_token = null)
    {
        $this->matomo_url = rtrim($matomo_url, '/');
        $this->admin_token = $admin_token;
        $this->user_token = $user_token ?: $admin_token;
    }

    /**
     * API-Aufruf mit rex_socket
     */
    private function apiCall($method, $params = [], $use_user_token = false)
    {
        $params['module'] = 'API';
        $params['method'] = $method;
        $params['format'] = 'json';
        $params['token_auth'] = $use_user_token ? $this->user_token : $this->admin_token;

        try {
            // Socket-Verbindung erstellen
            $socket = rex_socket::factoryUrl($this->matomo_url . '/index.php');
            
            // SSL-Optionen setzen für selbstsignierte Zertifikate
            $socket->setOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            // Timeout setzen
            $socket->setTimeout(10);
            
            // POST-Request senden
            $response = $socket->doPost($params);
            
            // Antwort prüfen
            if (!$response->isSuccessful()) {
                throw new Exception('HTTP Fehler: ' . $response->getStatusCode() . ' ' . $response->getStatusMessage());
            }
            
            $body = $response->getBody();
            
            if (empty($body)) {
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
     */
    public function getSites()
    {
        return $this->apiCall('SitesManager.getSitesWithAdminAccess');
    }

    /**
     * Website hinzufügen
     */
    public function addSite($name, $url)
    {
        $result = $this->apiCall('SitesManager.addSite', [
            'siteName' => $name,
            'urls' => $url
        ]);

        return $result['value'] ?? null;
    }

    /**
     * Tracking Code für eine Website abrufen
     */
    public function getTrackingCode($site_id)
    {
        $result = $this->apiCall('SitesManager.getJavascriptTag', [
            'idSite' => $site_id
        ]);

        return $result['value'] ?? '';
    }

    /**
     * Matomo herunterladen und entpacken mit rex_socket
     */
    public static function downloadMatomo($target_path)
    {
        if (!is_dir($target_path)) {
            rex_dir::create($target_path);
        }

        $zip_file = $target_path . '/matomo-latest.zip';
        $download_url = 'https://builds.matomo.org/matomo-latest.zip';

        try {
            // Socket-Verbindung für Download
            $socket = rex_socket::factoryUrl($download_url);
            
            // SSL-Optionen setzen
            $socket->setOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            // Längeren Timeout für Download
            $socket->setTimeout(120);
            
            // Redirects folgen
            $socket->followRedirects(true);
            
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
     * Dashboard-Statistiken abrufen (benötigt User Token)
     */
    public function getDashboardData($site_id, $period = 'day', $date = 'today')
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
        
        return $result;
    }

    /**
     * Einfache Besucher-Statistiken
     */
    public function getVisitorStats($site_id, $period = 'day', $date = 'today')
    {
        $params = [
            'idSite' => $site_id,
            'period' => $period,
            'date' => $date
        ];
        
        return $this->apiCall('VisitsSummary.get', $params, true);
    }
}