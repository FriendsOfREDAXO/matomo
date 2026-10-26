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

    /**
     * Parameter, die Matomo nur mit Token annimmt. Der Proxy setzt selbst ein Token, deshalb werden
     * sie aus Besucher-Anfragen entfernt – sonst liessen sich IP, Zeitpunkt und Ort faelschen.
     */
    private const PROTECTED_PARAMS = ['cip', 'token_auth', 'cdt', 'cdo', 'country', 'region', 'city', 'lat', 'long'];

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
        
        // Validieren; der Test aus dem Backend laedt nur das Script, Tracking nur bei aktivem Proxy
        if (!isset(self::ALLOWED_FILES[$file]) || ($is_test && !$this->isProxyEnabled() && !str_ends_with($file, '.js'))) {
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

        $params = $this->getForwardedParams();
        $requestMethod = strtoupper(rex_request::server('REQUEST_METHOD', 'string', 'GET'));
        $body = 'POST' === $requestMethod ? $this->getRawRequestBody() : '';
        if (str_ends_with($file, '.php')) {
            [$params, $body] = $this->withVisitorIp($params, $body);
        }
        if ([] !== $params) {
            $url .= '?' . rex_string::buildQuery($params);
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

            if ('POST' === $requestMethod) {
                // Ohne Content-Type liest Matomo lange Einzelanfragen (form-urlencoded) nicht aus
                $contentType = rex_request::server('CONTENT_TYPE', 'string', '');
                if ('' !== $contentType) {
                    $socket->addHeader('Content-Type', $contentType);
                }
                $response = $socket->doPost($body);
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
     * Query-Parameter der Anfrage ohne die eigenen (rex-api-call, file, test).
     *
     * @return array<mixed>
     */
    private function getForwardedParams(): array
    {
        $queryString = rex_request::server('QUERY_STRING', 'string', '');
        if ('' === $queryString) {
            return [];
        }

        parse_str($queryString, $params);
        unset($params['rex-api-call'], $params['file'], $params['test']);

        return $params;
    }

    /**
     * Echte Besucher-IP an Matomo: als `cip` mit dem Token aus der Einrichtung, wie der offizielle
     * Matomo Tracker Proxy. Ohne Token nimmt Matomo `cip` nicht an und zaehlt die IP dieses Servers.
     * Bei Sammelanfragen (JSON mit "requests") bekommt jeder Eintrag die IP, das Token steht oben im JSON.
     *
     * @param array<mixed> $params
     * @return array{0: array<mixed>, 1: string}
     */
    private function withVisitorIp(array $params, string $body): array
    {
        $params = $this->withoutProtectedParams($params);
        $ip = rex_request::server('REMOTE_ADDR', 'string', '');
        $token = (string) rex_config::get('matomo', 'admin_token', '');
        $ip = false !== filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';

        $bulk = '' !== $body ? json_decode(str_replace(["\n", "\r"], '', trim($body)), true) : null;
        if (is_array($bulk) && isset($bulk['requests']) && is_array($bulk['requests'])) {
            foreach ($bulk['requests'] as $index => $request) {
                if (is_string($request)) {
                    parse_str((string) parse_url($request, PHP_URL_QUERY), $entry);
                    $entry = $this->withoutProtectedParams($entry);
                    if ('' !== $ip && '' !== $token) {
                        $entry['cip'] = $ip;
                    }
                    $bulk['requests'][$index] = '?' . rex_string::buildQuery($entry);
                } elseif (is_array($request)) {
                    $request = $this->withoutProtectedParams($request);
                    if ('' !== $ip && '' !== $token) {
                        $request['cip'] = $ip;
                    }
                    $bulk['requests'][$index] = $request;
                }
            }
            unset($bulk['token_auth']);
            if ('' !== $ip && '' !== $token) {
                $bulk['token_auth'] = $token;
            }
            $encoded = json_encode($bulk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return [$params, false !== $encoded ? $encoded : ''];
        }

        if ('' !== $body) {
            parse_str($body, $post);
            $body = rex_string::buildQuery($this->withoutProtectedParams($post));
        }
        if ('' !== $ip && '' !== $token) {
            $params['cip'] = $ip;
            $params['token_auth'] = $token;
        }
        return [$params, $body];
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private function withoutProtectedParams(array $params): array
    {
        return array_diff_key($params, array_flip(self::PROTECTED_PARAMS));
    }

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
