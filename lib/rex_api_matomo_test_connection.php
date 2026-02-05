<?php

use FriendsOfRedaxo\Matomo\MatomoApi;

class rex_api_matomo_test_connection extends rex_api_function
{
    protected $published = false; // Nur für Backend-User

    public function execute()
    {
        $url = rex_request('url', 'string', '');
        
        if (!$url) {
            $this->sendResponse(false, 'Keine URL angegeben');
        }

        // Protokoll ergänzen falls fehlt
        if (!preg_match('~^https?://~', $url)) {
            $url = 'https://' . $url;
        }

        $log = [];
        $info = [];
        $response = '';
        $success = false;

        $ch = curl_init();
        
        // Optionen für den Verbindungstest
        // Wir nutzen cURL direkt für detaillierteres Debugging als rex_socket bietet
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 Sekunden Connect Timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // 10 Sekunden Gesamt Timeout
        curl_setopt($ch, CURLOPT_HEADER, true);     // Header in Output
        
        // Debugging aktivieren
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        // Proxy Support aus Config laden
        $addon = rex_addon::get('matomo');
        $proxyHost = $addon->getConfig('http_proxy_host');
        if ($proxyHost) {
            $proxyPort = (int) $addon->getConfig('http_proxy_port', 80);
            curl_setopt($ch, CURLOPT_PROXY, $proxyHost);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
            
            $proxyUser = $addon->getConfig('http_proxy_user');
            $proxyPass = $addon->getConfig('http_proxy_password');
            if ($proxyUser) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
            }
        }

        // SSL Verify Option
        if (!$addon->getConfig('ssl_verify')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $rawResponse = curl_exec($ch);
        
        if ($rawResponse === false) {
            $message = 'cURL Fehler: ' . curl_error($ch);
            $log[] = "Error Code: " . curl_errno($ch);
        } else {
            $success = true;
            $info = curl_getinfo($ch);
            $headerSize = $info['header_size'];
            $header = substr($rawResponse, 0, $headerSize);
            $body = substr($rawResponse, $headerSize);
            
            // Prüfen ob es wie Matomo aussieht
            if (strpos($body, 'Matomo') !== false || strpos($body, 'Piwik') !== false || strpos($header, 'X-Matomo') !== false) {
                $message = 'Verbindung erfolgreich. Matomo Instanz erkannt.';
            } else {
                $message = 'Verbindung erfolgreich, aber keine eindeutige Matomo-Signatur gefunden.';
                $success = false; // Soft fail
            }
            
            if ($info['http_code'] >= 400) {
                $success = false;
                $message = 'HTTP Fehler: ' . $info['http_code'];
            }
            
            $response = substr($body, 0, 500) . (strlen($body) > 500 ? '...' : '');
        }

        // Verbose Log lesen
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);

        $data = [
            'http_code' => $info['http_code'] ?? 0,
            'total_time' => $info['total_time'] ?? 0,
            'primary_ip' => $info['primary_ip'] ?? '',
            'primary_port' => $info['primary_port'] ?? '',
            'curl_log' => $verboseLog,
            'response_sample' => htmlspecialchars($response) // Escape HTML for safe display
        ];

        rex_response::cleanOutputBuffers();
        rex_response::sendJson([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    private function sendResponse($success, $message) {
        rex_response::cleanOutputBuffers();
        rex_response::sendJson([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }
}
