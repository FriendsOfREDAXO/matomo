<?php

class rex_api_matomo_test_proxy extends rex_api_function
{
    protected $published = false;

    public function execute()
    {
        rex_response::cleanOutputBuffers();

        $matomoUrl = (string) rex_config::get('matomo', 'matomo_url', '');
        if ('' === $matomoUrl) {
            $this->sendResponse(false, 'Matomo URL nicht konfiguriert');
        }

        $proxyUrl = $this->buildProxyUrl();
        $requestUrl = $this->toAbsoluteUrl($proxyUrl);

        try {
            $socket = rex_socket::factoryUrl($requestUrl);
            $socket->setTimeout(10);
            $response = $socket->doGet();

            if (!$response->isSuccessful()) {
                $this->sendResponse(false, 'HTTP ' . $response->getStatusCode(), $proxyUrl);
            }

            $body = $response->getBody();
            $isMatomoJs = str_contains($body, 'Matomo') || str_contains($body, 'Piwik');

            $this->sendResponse(
                $isMatomoJs,
                $isMatomoJs ? 'Proxy funktioniert' : 'Proxy antwortet, aber kein Matomo JS erkannt',
                $proxyUrl,
                strlen($body)
            );
        } catch (rex_socket_exception $e) {
            $this->sendResponse(false, 'Socket-Fehler: ' . $e->getMessage(), $proxyUrl);
        }
    }

    private function sendResponse(bool $success, string $message, string $url = '', int $size = 0): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::sendJson([
            'success' => $success,
            'message' => $message,
            'url' => $url,
            'size' => $size,
        ]);
        exit;
    }

    private function buildProxyUrl(): string
    {
        $query = rex_string::buildQuery([
            'rex-api-call' => 'matomo_proxy',
            'file' => 'matomo.js',
            'test' => '1',
        ]);

        return $this->resolveFrontendIndexUrl() . '?' . $query;
    }

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

    private function toAbsoluteUrl(string $url): string
    {
        if (preg_match('@^https?://@i', $url)) {
            return $url;
        }

        $server = rtrim((string) rex::getServer(), '/');
        return $server . '/' . ltrim($url, '/');
    }
}