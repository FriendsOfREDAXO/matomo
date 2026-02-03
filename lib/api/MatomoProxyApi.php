<?php

namespace FriendsOfRedaxo\Matomo;

use rex_api_function;
use rex_api_result;
use rex_config;
use rex_request;
use rex_response;
use rex_socket;
use rex_socket_exception;
use Exception;

/**
 * API Function für Matomo JS Proxy.
 * Proxied matomo.js/piwik.js über REDAXO um Tracker-Blocking zu umgehen.
 * 
 * Aufruf: index.php?rex-api-call=matomo_proxy&file=matomo.js
 * 
 * @package FriendsOfRedaxo\Matomo
 */
class MatomoProxyApi extends rex_api_function
{
    /** @var array<string, string> Erlaubte Dateien */
    private const ALLOWED_FILES = [
        'matomo.js' => 'application/javascript',
        'piwik.js' => 'application/javascript',
        'matomo.php' => 'image/gif',
        'piwik.php' => 'image/gif'
    ];
    
    /** @var int Cache-Dauer in Sekunden (24 Stunden) */
    private const CACHE_DURATION = 86400;

    /** @var bool API ist öffentlich zugänglich (keine Authentifizierung nötig) */
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        
        // Prüfen ob Proxy aktiviert ist
        if (!$this->isProxyEnabled()) {
            $this->sendError('Proxy ist nicht aktiviert', 403);
        }
        
        // Dateinamen abrufen
        $file = rex_request::request('file', 'string', 'matomo.js');
        
        // Validieren
        if (!isset(self::ALLOWED_FILES[$file])) {
            $this->sendError('Ungültige Datei', 400);
        }
        
        try {
            $content = $this->fetchFile($file);
            $this->sendResponse($content, self::ALLOWED_FILES[$file]);
        } catch (Exception $e) {
            $this->sendError('Fehler beim Laden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Prüft ob Proxy aktiviert ist
     */
    private function isProxyEnabled(): bool
    {
        return (bool) rex_config::get('matomo', 'proxy_enabled', false);
    }

    /**
     * Lädt die Datei von Matomo
     */
    private function fetchFile(string $file): string
    {
        $matomo_url = (string) rex_config::get('matomo', 'matomo_url', '');
        
        if ('' === $matomo_url) {
            throw new Exception('Matomo URL nicht konfiguriert');
        }
        
        $url = rtrim($matomo_url, '/') . '/' . $file;
        
        try {
            $socket = rex_socket::factoryUrl($url);
            
            // SSL-Verifizierung aus Config
            $verify_ssl = (bool) rex_config::get('matomo', 'verify_ssl', true);
            if (false === $verify_ssl) {
                $socket->setOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
            }
            
            $socket->setTimeout(10);
            $response = $socket->doGet();
            
            if (!$response->isSuccessful()) {
                throw new Exception('HTTP ' . $response->getStatusCode());
            }
            
            return $response->getBody();
            
        } catch (rex_socket_exception $e) {
            throw new Exception('Socket-Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Sendet die Response
     */
    private function sendResponse(string $content, string $contentType): never
    {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=' . self::CACHE_DURATION);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::CACHE_DURATION) . ' GMT');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Accept-Encoding');
        
        echo $content;
        exit;
    }

    /**
     * Sendet Fehlermeldung
     */
    private function sendError(string $message, int $code): never
    {
        http_response_code($code);
        header('Content-Type: text/plain');
        echo $message;
        exit;
    }
}
