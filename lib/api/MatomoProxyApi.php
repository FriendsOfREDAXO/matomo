<?php

namespace FriendsOfRedaxo\Matomo;

use rex_api_function;
use rex_api_result;
use rex_config;
use rex_request;
use rex_response;
use rex_socket;
use rex_socket_exception;
use rex_string;
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
        
        // Erlaube Test-Modus über Parameter (für Backend-Test)
        $is_test = rex_request::request('test', 'boolean', false);
        
        // Prüfen ob Proxy aktiviert ist (außer im Test-Modus)
        if (!$is_test && !$this->isProxyEnabled()) {
            $this->sendError('Proxy ist nicht aktiviert', 403);
        }
        
        // Dateinamen abrufen
        $file = rex_request::request('file', 'string', 'matomo.js');
        
        // Validieren
        if (!isset(self::ALLOWED_FILES[$file])) {
            $this->sendError('Ungültige Datei', 400);
        }
        
        try {
            [$content, $contentType] = $this->fetchFile($file);
            $this->sendResponse($content, $contentType, $file);
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
    private function fetchFile(string $file): array
    {
        $matomo_url = (string) rex_config::get('matomo', 'matomo_url', '');
        
        if ('' === $matomo_url) {
            throw new Exception('Matomo URL nicht konfiguriert');
        }
        
        $url = rtrim($matomo_url, '/') . '/' . $file;

        $query = $this->getForwardedQueryString();
        if ('' !== $query) {
            $url .= '?' . $query;
        }
        
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

            $this->forwardClientHeaders($socket);
            
            $socket->setTimeout(10);

            $requestMethod = strtoupper(rex_request::server('REQUEST_METHOD', 'string', 'GET'));
            if ('POST' === $requestMethod) {
                $response = $socket->doPost($this->getRawRequestBody());
            } else {
                $response = $socket->doGet();
            }
            
            if (!$response->isSuccessful()) {
                throw new Exception('HTTP ' . $response->getStatusCode());
            }

            $contentType = (string) $response->getHeader('Content-Type', self::ALLOWED_FILES[$file]);

            return [$response->getBody(), $contentType];
            
        } catch (rex_socket_exception $e) {
            throw new Exception('Socket-Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Leitet alle Tracking-Query-Parameter an Matomo weiter.
     */
    private function getForwardedQueryString(): string
    {
        $queryString = rex_request::server('QUERY_STRING', 'string', '');
        if ('' === $queryString) {
            return '';
        }

        parse_str($queryString, $params);
        unset($params['rex-api-call'], $params['file'], $params['test']);

        if ([] === $params) {
            return '';
        }

        return rex_string::buildQuery($params);
    }

    /**
     * Reicht relevante Client-Header an Matomo durch.
     */
    private function forwardClientHeaders(rex_socket $socket): void
    {
        $userAgent = rex_request::server('HTTP_USER_AGENT', 'string', '');
        if ('' !== $userAgent) {
            $socket->addHeader('User-Agent', $userAgent);
        }

        $acceptLanguage = rex_request::server('HTTP_ACCEPT_LANGUAGE', 'string', '');
        if ('' !== $acceptLanguage) {
            $socket->addHeader('Accept-Language', $acceptLanguage);
        }

        $remoteAddr = rex_request::server('REMOTE_ADDR', 'string', '');
        if ('' !== $remoteAddr) {
            $existingForwarded = rex_request::server('HTTP_X_FORWARDED_FOR', 'string', '');
            $forwardedFor = '' !== $existingForwarded ? $existingForwarded . ', ' . $remoteAddr : $remoteAddr;
            $socket->addHeader('X-Forwarded-For', $forwardedFor);
            $socket->addHeader('X-Real-IP', $remoteAddr);
        }
    }

    private function getRawRequestBody(): string
    {
        $body = file_get_contents('php://input');
        if (false === $body) {
            return '';
        }

        return $body;
    }

    /**
     * Sendet die Response
     */
    private function sendResponse(string $content, string $contentType, string $file): never
    {
        header('Content-Type: ' . $contentType);

        if (in_array($file, ['matomo.php', 'piwik.php'], true)) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            header('Cache-Control: public, max-age=' . self::CACHE_DURATION);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::CACHE_DURATION) . ' GMT');
        }

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
